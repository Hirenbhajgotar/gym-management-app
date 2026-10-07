<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Events\BiometricConsentRevoked;
use App\Filament\Admin\Actions\EnrollBiometricAction;
use App\Filament\Admin\Resources\GymMemberResource\Pages\CreateGymMember;
use App\Filament\Admin\Resources\GymMemberResource\Pages\EditGymMember;
use App\Filament\Admin\Resources\GymMemberResource\Pages\ListGymMembers;
use App\Filament\Admin\Resources\GymMemberResource\RelationManagers\BiometricTemplatesRelationManager;
use App\Filament\Admin\Resources\GymMemberResource\RelationManagers\MeasurementsRelationManager;
use App\Filament\Admin\Resources\GymMemberResource\RelationManagers\PaymentsRelationManager;
use App\Filament\BaseResources\BaseResource;
use App\Forms\Components\AddressForm;
use App\Jobs\SendBiometricConsentEmail;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Trainer;
use App\Models\User;
use App\Rules\ValidEmailDomain;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component as ComponentsComponent;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use libphonenumber\PhoneNumberType;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class GymMemberResource extends BaseResource
{
    protected static ?string $model = GymMember::class;

    protected static ?string $navigationLabel = 'Gym Members';

    protected static ?string $navigationGroup = 'Users';

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        /** @var string $tenantKey */
        $tenantKey = $tenant->getKey();

        return Cache::flexible(GymMember::NAVIGATION_BADGE_CACHE_KEY . $tenantKey, [21600, 604800], function () {
            return (string) GymMember::query()->notFrozen()->membershipActive()->count();
        }) ?: null;
    }

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
                'create',
                'delete',
                'delete_any',
                'update',
                'restore',
                'replicate',
                'reorder',
                'export',
                'import',
            ],

            DefaultRole::RECEPTIONIST->value => ['view', 'view_any', 'create', 'update', 'replicate', 'reorder', 'export', 'import'],
            DefaultRole::TRAINER->value => ['view', 'view_any', 'create', 'update', 'replicate', 'reorder', 'export', 'import'],
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(self::getFormSchema())
            ->columns(1);
    }

    /**
     * @return array<int, ComponentsComponent>
     */
    public static function getFormSchema(): array
    {
        return [
            Group::make()
                ->schema([
                    Section::make()
                        ->schema(static::getDetailsFormSchema())
                        ->columns(2),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (EloquentBuilder $query) {
                // Eager-load gyms on auth user to avoid per-row N+1 in policy
                $user = auth()->user();
                if ($user instanceof User && ! $user->relationLoaded('gyms')) {
                    $user->load('gyms');
                }

                // Eager-load biometric consent existence to avoid N+1 queries
                $query->withExists(['biometricConsents' => function (EloquentBuilder $query) {
                    $query->whereColumn('biometric_consents.gym_id', 'gym_members.gym_id')
                        ->whereNull('revoked_at');
                }]);
            })
            ->columns([
                ImageColumn::make('profile_photo')
                    ->circular()
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->label(''),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Phone copied!')
                    ->icon('heroicon-o-phone')
                    ->color('gray'),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied!')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('membershipPlan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('success'),

                TextColumn::make('membership_start')
                    ->date()
                    ->badge()
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('membership_end')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return 'Not Set';
                        }

                        $now = now();
                        $membershipEnd = $state;

                        // Check if membership has already expired
                        if ($now->greaterThan($membershipEnd)) {
                            return 'Expired';
                        }

                        // Check if membership ends within 2 months (on or before July 31, 2025)
                        $diffInMonths = $now->diffInMonths($membershipEnd, false);
                        if ($diffInMonths <= 2 && $diffInMonths >= 0) {
                            $diffInDays = (int) round($now->diffInDays($membershipEnd, false)); // Round to nearest whole number

                            return "Expire in {$diffInDays} " . Str::plural('day', $diffInDays);
                        }

                        // Otherwise, show as a regular date
                        return $membershipEnd->toFormattedDateString(); // e.g., "Aug 15, 2025"
                    })
                    ->badge()
                    ->icon(function (?string $state): string {
                        if (! $state) {
                            return 'heroicon-o-calendar'; // Default icon for "Not Set"
                        }

                        $now = now(); // June 1, 2025, 12:43 AM IST
                        $membershipEnd = Date::parse($state);

                        // Check if membership has already expired
                        if ($now->greaterThan($membershipEnd)) {
                            return 'heroicon-m-x-circle'; // Icon for expired
                        }

                        // Check if membership ends within 2 months (on or before July 31, 2025)
                        $diffInMonths = $now->diffInMonths($membershipEnd, false);
                        if ($diffInMonths <= 2 && $diffInMonths >= 0) {
                            return 'heroicon-o-exclamation-circle'; // Icon for nearing expiration
                        }

                        // Default icon for more than 2 months
                        return 'heroicon-o-calendar';
                    })
                    ->color(function (?string $state): string {
                        if (! $state) {
                            return 'gray'; // Default color for "Not Set"
                        }

                        $now = now(); // June 1, 2025
                        $membershipEnd = Date::parse($state);

                        $diffInDays = $now->diffInDays($membershipEnd, false);
                        if ($diffInDays <= 60 && $diffInDays >= 14) {
                            return 'warning';
                        }

                        // Check if membership has expired or is within 2 weeks
                        if ($diffInDays <= 14 && $diffInDays >= 0 || $now->greaterThan($membershipEnd)) {
                            return 'danger';
                        }

                        // Check if membership ends within 1 month (on or before July 1, 2025)
                        $diffInMonths = $now->diffInMonths($membershipEnd, false);
                        if ($diffInMonths <= 2 && $diffInDays > 14) {
                            return 'warning';
                        }

                        // More than 2 month
                        return 'info';
                    }),

                TextColumn::make('payment_status')
                    ->label('Payment Status')
                    ->badge(),

                TextColumn::make('biometric_consent_status')
                    ->label('Biometric Consent')
                    ->getStateUsing(fn (GymMember $record) => $record->hasActiveBiometricConsent() ? 'Consented' : 'Pending')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Consented' => 'success',
                        'Pending' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_frozen')
                    ->label('Frozen')
                    ->boolean()
                    ->getStateUsing(fn (GymMember $record): bool => $record->isFrozen())
                    ->trueIcon('phosphor-prohibit')
                    ->falseIcon('heroicon-o-play')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('frozen_until')
                    ->label('Frozen Until')
                    ->date('M j, Y')
                    ->placeholder('-')
                    ->visible(fn (?GymMember $record): bool => $record !== null ? $record->isFrozen() : true)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('allow_whatsapp_marketing')
                    ->label('WhatsApp Mktg')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('is_archived')
                    ->label('Archived')
                    ->getStateUsing(fn (GymMember $record) => $record->is_archived === true ? 'Archived' : 'Active')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Archived' => 'warning',
                        'Active' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gym_membership_plan_id')
                    ->relationship('membershipPlan', 'name', function (EloquentBuilder $query) {
                        if (filament()->getId() === 'admin') {
                            if (Auth::user()->hasRole('gym_owner')) {
                                return $query->whereIn('gym_id', Auth::user()?->gyms()->get()->pluck('id'));
                            }
                            if (Auth::user()->hasRole('super_admin')) {
                                return $query;
                            }
                        } elseif (filament()->getId() === 'staff') {
                            return $query->whereIn('gym_id', Auth::user()?->gyms()->get()->pluck('id'));
                        }
                    })
                    ->label('Plan'),

                SelectFilter::make('payment_status')
                    ->options([
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'unpaid' => 'Unpaid',
                        'expire' => 'Expired',
                    ])
                    ->query(function (EloquentBuilder $query, array $state) {
                        $value = $state['value'];
                        if (! $value) {
                            return;
                        }

                        $query->where(function (EloquentBuilder $query) use ($value) {
                            if ($value === 'paid') {
                                $query->where('payment_status', PaymentStatus::COMPLETED);
                            } elseif ($value === 'partial') {
                                $query->where('payment_status', PaymentStatus::PARTIAL);
                            } elseif ($value === 'unpaid') {
                                $query->whereIn('payment_status', [
                                    PaymentStatus::PENDING,
                                    PaymentStatus::FAILED,
                                ]);
                            } elseif ($value === 'expire') {
                                $query->where('payment_status', PaymentStatus::EXPIRED);
                            }
                        });
                    })
                    ->label('Payment Status'),

                SelectFilter::make('is_frozen')
                    ->label('Freeze Status')
                    ->options([
                        'frozen' => 'Frozen',
                        'not_frozen' => 'Not Frozen',
                    ])
                    ->query(function (EloquentBuilder $query, array $state) {

                        $value = $state['value'];
                        if (! $value) {
                            return;
                        }
                        if ($value === 'frozen') {
                            $query->where(function (Builder $q) {
                                $q->where('is_frozen', true)
                                    ->where(function (Builder $q2) {
                                        $q2->whereNull('frozen_until')
                                            ->orWhere('frozen_until', '>=', now());
                                    });
                            });
                        } elseif ($value === 'not_frozen') {
                            $query->where(function (Builder $q) {
                                $q->where('is_frozen', false)
                                    ->orWhere(function (Builder $q2) {
                                        $q2->where('is_frozen', true)
                                            ->whereNotNull('frozen_until')
                                            ->where('frozen_until', '<', now());
                                    });
                            });
                        }
                    }),

                TernaryFilter::make('allow_whatsapp_marketing')
                    ->label('WhatsApp Marketing'),

                TernaryFilter::make('is_archived')
                    ->label('Archived Status'),
            ])
            ->actions([
                EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),

                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Member')
                    ->modalDescription('Are you sure you want to delete this member? This action requires password confirmation.')
                    ->modalSubmitActionLabel('Confirm Delete')
                    ->form([
                        TextInput::make('password')
                            ->label('Your Password')
                            ->revealable()
                            ->password()
                            ->required()
                            ->rules([
                                'required',
                                fn (callable $get) => function ($attribute, $value, Closure $fail) {
                                    if (! Hash::check($value, Auth::user()->password)) {
                                        $fail('The password is incorrect.');
                                    }
                                },
                            ]),
                    ]),

                ActionGroup::make([
                    EnrollBiometricAction::make(),

                    Action::make('quick_measure')
                        ->label('Measure')
                        ->icon('heroicon-o-scale')
                        ->tooltip('Add new measurement')
                        ->form([
                            Section::make('Create member measurement')
                                ->schema(MeasurementsRelationManager::getMeasurementsFormSchema())
                                ->columns(2),
                        ])
                        ->action(function (GymMember $record, array $data) {
                            $record->measurements()->create([
                                'weight' => $data['weight'],
                                'height' => $data['height'],
                                'measured_at' => $data['measured_at'],
                                'notes' => $data['notes'],
                            ]);

                            Notification::make()
                                ->title('Measurement Added')
                                ->success()
                                ->send();
                        }),

                    Action::make('toggle_freeze')
                        ->label(fn (GymMember $record) => $record->isFrozen() ? 'Unfreeze' : 'Freeze')
                        ->icon(fn (GymMember $record) => $record->isFrozen() ? 'heroicon-o-play' : 'phosphor-prohibit')
                        ->color(fn (GymMember $record) => $record->isFrozen() ? 'success' : 'danger')
                        ->visible(fn (GymMember $record) => $record->isFrozen() || $record->membership_end?->isFuture())
                        ->tooltip(fn (GymMember $record) => $record->isFrozen() ? 'Unfreeze membership' : 'Freeze membership')
                        ->authorize(fn (GymMember $record) => auth()->user()?->hasAnyRole([
                            DefaultRole::SUPER_ADMIN->value,
                            DefaultRole::ADMIN->value,
                            DefaultRole::GYM_OWNER->value,
                            DefaultRole::MANAGER->value,
                            DefaultRole::RECEPTIONIST->value,
                        ]) ?? false)
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('reason')
                                ->label('Reason for freezing (optional)')
                                ->placeholder('Enter reason for freezing membership'),
                            TextInput::make('days')
                                ->label('Freeze for days (optional)')
                                ->placeholder('Leave empty for manual unfreeze')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(365)
                                ->helperText('Enter number of days (1-365) or leave empty for manual unfreeze'),
                            Checkbox::make('extend_membership')
                                ->label('Extend Membership Plan Expiration')
                                ->default(true)
                                ->hidden(fn (GymMember $record) => $record->isFrozen())
                                ->helperText(function (GymMember $record) {
                                    $minSetting = $record->gym?->getSetting('min_freeze_days', GymMember::DEFAULT_MIN_FREEZE_DAYS);
                                    $min = is_numeric($minSetting) ? (int) $minSetting : GymMember::DEFAULT_MIN_FREEZE_DAYS;
                                    $maxSetting = $record->gym?->getSetting('max_freeze_days', GymMember::DEFAULT_MAX_FREEZE_DAYS);
                                    $max = is_numeric($maxSetting) ? (int) $maxSetting : GymMember::DEFAULT_MAX_FREEZE_DAYS;

                                    return "If checked, membership end date will be extended for freeze durations between {$min} and {$max} days.";
                                }),
                        ])
                        ->action(function (GymMember $record, array $data) {
                            $userId = Auth::id() ? (int) Auth::id() : null;
                            if ($record->isFrozen()) {
                                $oldEnd = $record->membership_end?->toDateString();
                                $unfrozen = $record->unfreeze(unfrozenById: $userId);
                                if (! $unfrozen) {
                                    Notification::make()
                                        ->title('Unable to Unfreeze')
                                        ->body('Member is not currently frozen.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $newEnd = $record->membership_end?->toDateString();
                                $endStr = $record->membership_end ? $record->membership_end->format('M j, Y') : 'N/A';

                                $body = ($oldEnd !== $newEnd)
                                    ? "Member membership unfrozen. Updated plan end date: {$endStr}"
                                    : 'Member membership unfrozen.';

                                Notification::make()
                                    ->title('Member Unfrozen')
                                    ->body($body)
                                    ->success()
                                    ->send();
                            } else {
                                $days = isset($data['days']) && is_numeric($data['days']) && (int) $data['days'] > 0 ? (int) $data['days'] : null;
                                $extend = (bool) ($data['extend_membership'] ?? false);
                                $oldEnd = $record->membership_end?->toDateString();

                                $frozen = $record->freeze(
                                    reason: $data['reason'] ?? null,
                                    days: $days,
                                    frozenById: $userId,
                                    extendMembership: $extend,
                                );

                                if (! $frozen) {
                                    Notification::make()
                                        ->title('Unable to Freeze')
                                        ->body('Member is already actively frozen.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $newEnd = $record->membership_end?->toDateString();
                                $endStr = $record->membership_end ? $record->membership_end->format('M j, Y') : 'N/A';

                                $body = ($extend && $oldEnd !== $newEnd)
                                    ? "Member membership frozen. Updated plan end date: {$endStr}"
                                    : 'Member membership frozen.';

                                Notification::make()
                                    ->title('Member Frozen')
                                    ->body($body)
                                    ->success()
                                    ->send();
                            }
                        })
                        ->modalHeading(fn (GymMember $record) => $record->isFrozen() ? 'Unfreeze Member' : 'Freeze Member')
                        ->modalSubmitActionLabel(fn (GymMember $record) => $record->isFrozen() ? 'Unfreeze' : 'Freeze'),

                    Action::make('resend_consent_email')
                        ->label('Resend Consent Email')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->visible(fn (GymMember $record) => ! $record->hasActiveBiometricConsent()
                            && filled($record->email)
                        )
                        ->action(function (GymMember $record) {
                            // Eager-load gym relation to prevent N+1 when Mailable accesses $gymMember->gym->name
                            dispatch(new SendBiometricConsentEmail($record->load('gym')));

                            Notification::make()
                                ->title('Consent email sent to ' . $record->email)
                                ->success()
                                ->send();
                        }),

                    Action::make('copy_consent_link')
                        ->label('Copy Consent Link')
                        ->icon('heroicon-o-link')
                        ->color('gray')
                        ->visible(fn (GymMember $record) => ! $record->hasActiveBiometricConsent())
                        ->action(function (GymMember $record, $livewire) {
                            $url = URL::temporarySignedRoute(
                                'biometric.consent.show',
                                now()->addDays(7),
                                [
                                    'gym_member_id' => $record->id,
                                    'gym_id' => $record->gym_id,
                                ]
                            );

                            $escapedUrl = json_encode($url);
                            $livewire->js("
                                navigator.clipboard.writeText({$escapedUrl});
                            ");

                            Notification::make()
                                ->title('Link copied')
                                ->body('Share this link via WhatsApp, SMS, or any channel.')
                                ->success()
                                ->send();
                        }),

                    // Action::make('open_consent_page')
                    //     ->label('Open on Tablet (Walk-in)')
                    //     ->icon('heroicon-o-device-tablet')
                    //     ->color('gray')
                    //     ->visible(fn (GymMember $record) => ! $record->hasActiveBiometricConsent())
                    //     ->url(fn (GymMember $record) => URL::temporarySignedRoute(
                    //         'biometric.consent.show',
                    //         now()->addHours(1),
                    //         [
                    //             'gym_member_id' => $record->id,
                    //             'gym_id' => $record->gym_id,
                    //         ]
                    //     ))
                    //     ->openUrlInNewTab(),

                    Action::make('revoke_consent')
                        ->label('Revoke Biometric Consent')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (GymMember $record) => $record->hasActiveBiometricConsent())
                        ->requiresConfirmation()
                        ->modalHeading('Revoke Biometric Consent')
                        ->modalDescription(
                            'This will revoke consent and permanently delete all biometric templates for this member. This action cannot be undone.'
                        )
                        ->modalSubmitActionLabel('Yes, Revoke and Delete')
                        ->action(function (GymMember $record) {
                            $record->activeBiometricConsent()?->update([
                                'revoked_at' => now(),
                            ]);

                            event(new BiometricConsentRevoked($record));

                            Notification::make()
                                ->title('Consent revoked')
                                ->body('All biometric data for ' . $record->name . ' has been deleted.')
                                ->warning()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('enableWhatsappMarketing')
                        ->label('Enable WhatsApp Marketing')
                        ->tooltip('Enable WhatsApp Marketing for selected members')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (EloquentCollection $records): void {
                            /** @var EloquentCollection<int, GymMember> $records */
                            $records->each(fn (GymMember $member) => $member->toggleWhatsappMarketing(true));
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('disableWhatsappMarketing')
                        ->label('Disable WhatsApp Marketing')
                        ->tooltip('Disable WhatsApp Marketing for selected members')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (EloquentCollection $records): void {
                            /** @var EloquentCollection<int, GymMember> $records */
                            $records->each(fn (GymMember $member) => $member->toggleWhatsappMarketing(false));
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('archiveMembers')
                        ->label('Archive Members')
                        ->tooltip('Archive selected members')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (EloquentCollection $records): void {
                            /** @var EloquentCollection<int, GymMember> $records */
                            $records->each(fn (GymMember $member) => $member->archive());
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unarchiveMembers')
                        ->label('Restore Archived Members')
                        ->tooltip('Restore selected archived members')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->action(function (EloquentCollection $records): void {
                            /** @var EloquentCollection<int, GymMember> $records */
                            $records->each(fn (GymMember $member) => $member->unarchive());
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
            MeasurementsRelationManager::class,
            BiometricTemplatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGymMembers::route('/'),
            'create' => CreateGymMember::route('/create'),
            'edit' => EditGymMember::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, ComponentsComponent>
     */
    public static function getDetailsFormSchema(): array
    {
        $tenant = Filament::getTenant();

        /** @var array<int, ComponentsComponent> $schema */
        $schema = [
            FileUpload::make('profile_photo')
                ->image()
                ->avatar()
                ->imageEditor()
                ->circleCropper()
                ->directory('member-photos')
                ->maxSize(2048)
                ->columnSpanFull(),

            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g., John Doe')
                ->autofocus()
                ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

            TextInput::make('email')
                ->email()
                ->nullable()
                ->maxLength(255)
                ->unique(GymMember::class, 'email', ignoreRecord: true)
                ->rules(['email:rfc', new ValidEmailDomain()])
                ->validationMessages([
                    'email' => 'Please enter a valid email address.',
                    'email.rfc' => 'The email format is invalid.',
                    'email.dns' => 'The email domain does not exist.',
                ])
                ->placeholder('e.g., john.doe@example.com')
                ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

            PhoneInput::make('phone')
                ->defaultCountry('IN')
                ->required()
                ->unique(GymMember::class, 'phone', ignoreRecord: true, modifyRuleUsing: function (Unique $rule) use ($tenant) {
                    /**
                     * @var Gym $tenant
                     */
                    return $rule->where('gym_id', $tenant->id);
                })
                ->validateFor(
                    country: ['IN', 'PK', 'BD', 'US'],
                    type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE],
                    lenient: true,
                )
                ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

            AddressForm::make('address')
                ->columnSpan('full'),

            TextInput::make('weight')
                ->numeric()
                ->suffix('kg')
                ->step(0.01)
                ->minValue(0)
                ->maxValue(500)
                ->placeholder('e.g., 75.5')
                ->visible(fn ($livewire) => $livewire instanceof CreateGymMember),

            TextInput::make('height')
                ->numeric()
                ->suffix('cm')
                ->step(0.01)
                ->minValue(50)
                ->maxValue(250)
                ->placeholder('e.g., 175')
                ->helperText('Enter height in centimeters (e.g., 175 for 1.75m)')
                ->visible(fn ($livewire) => $livewire instanceof CreateGymMember),

            DatePicker::make('dob')
                ->label('Date of Birth')
                ->placeholder('Select date of birth')
                ->native(false)
                ->closeOnDateSelection(true)
                ->reactive()
                ->afterStateUpdated(function (?string $state, callable $set): void {
                    if ($state) {
                        /** @var Carbon $dob */
                        $dob = Date::parse($state);
                        $age = (int) floor($dob->diffInYears(now())); // Calculate age based on current date
                        $set('age', $age);
                    } else {
                        $set('age', null); // Clear age if dob is empty
                    }
                }),

            TextInput::make('age')
                ->numeric()
                ->minValue(0)
                ->maxValue(150)
                ->placeholder('e.g., 25'),

            Select::make('trainer_id')
                ->label('Assign Trainer')
                ->options(function (callable $get): array {
                    /** @var array<int|string, string|null> $options */
                    $options = Trainer::query()
                        ->available()
                        ->pluck('name', 'id')
                        ->all();

                    return $options;
                })
                ->reactive()
                ->nullable()
                ->afterStateHydrated(function ($state, callable $set, $record): void {
                    if (! $state && $record) {
                        $set('trainer_id', null);
                    }
                })
                ->helperText('Leave as "None" if the member does not want a personal trainer.')
                ->hidden(fn (string $operation): bool => $operation === 'create'),
        ];

        return $schema;
    }
}
