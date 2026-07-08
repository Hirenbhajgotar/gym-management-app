<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Events\BiometricConsentRevoked;
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
use App\Rules\ValidEmailDomain;
use Closure;
use Filament\Facades\Filament;
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
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Carbon;
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
                    ->visible(fn ($record) => $record?->is_frozen ?? true)
                    ->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\TextColumn::make('payments_sum_amount')
                //     ->label('Total Paid')
                //     ->money('INR')
                //     ->summarize(Tables\Columns\Summarizers\Sum::make())
                //     ->badge()
                //     ->color('success'),
            ])
            ->filters([
                SelectFilter::make('gym_membership_plan_id')
                    ->relationship('membershipPlan', 'name', function (EloquentBuilder $query) {
                        if (filament()->getId() === 'admin') {
                            if (auth()->user()->hasRole('gym_owner')) {
                                return $query->whereIn('gym_id', auth()->user()?->gyms()->get()->pluck('id'));
                            }
                            if (auth()->user()->hasRole('super_admin')) {
                                return $query;
                            }
                        } elseif (filament()->getId() === 'staff') {
                            return $query->whereIn('gym_id', auth()->user()?->gyms()->get()->pluck('id'));
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
                                    if (! Hash::check($value, auth()->user()->password)) {
                                        $fail('The password is incorrect.');
                                    }
                                },
                            ]),
                    ]),

                ActionGroup::make([
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
                        ->label(fn (GymMember $record) => $record->is_frozen ? 'Unfreeze' : 'Freeze')
                        ->icon(fn (GymMember $record) => $record->is_frozen ? 'heroicon-o-play' : 'phosphor-prohibit')
                        ->color(fn (GymMember $record) => $record->is_frozen ? 'success' : 'danger')
                        ->visible(fn (GymMember $record) => $record->is_frozen || $record->membership_end?->isFuture())
                        ->tooltip(fn (GymMember $record) => $record->is_frozen ? 'Unfreeze membership' : 'Freeze membership')
                        ->form([
                            Textarea::make('reason')
                                ->label('Reason for freezing (optional)')
                                ->placeholder('Enter reason for freezing membership'),
                            TextInput::make('days')
                                ->label('Freeze for days (optional)')
                                ->placeholder('Leave empty for manual unfreeze')
                                ->numeric()
                                ->minValue(1)
                                ->helperText('Enter number of days or leave empty for manual unfreeze'),
                        ])
                        ->action(function (GymMember $record, array $data) {
                            if ($record->is_frozen) {
                                $record->unfreeze();
                                Notification::make()
                                    ->title('Member Unfrozen')
                                    ->success()
                                    ->send();
                            } else {
                                $days = isset($data['days']) && $data['days'] > 0 ? (int) $data['days'] : null;
                                $record->freeze(
                                    reason: $data['reason'] ?? null,
                                    days: $days
                                );
                                Notification::make()
                                    ->title('Member Frozen')
                                    ->success()
                                    ->send();
                            }
                        })
                        ->modalHeading(fn (GymMember $record) => $record->is_frozen ? 'Unfreeze Member' : 'Freeze Member')
                        ->modalSubmitActionLabel(fn (GymMember $record) => $record->is_frozen ? 'Unfreeze' : 'Freeze'),

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
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make()
                //         ->requiresConfirmation()
                //         ->modalHeading('Delete Selected Members')
                //         ->modalDescription('Are you sure you want to delete these members? This action requires password confirmation.')
                //         ->modalSubmitActionLabel('Confirm Delete')
                //         ->form([
                //             Forms\Components\TextInput::make('password')
                //                 ->label('Your Password')
                //                 ->revealable()
                //                 ->password()
                //                 ->required()
                //                 ->rules([
                //                     'required',
                //                     fn($get) => function ($attribute, $value, $fail) {
                //                         if (! Hash::check($value, auth()->user()->password)) {
                //                             $fail('The password is incorrect.');
                //                         }
                //                     },
                //                 ]),
                //         ]),
                // ]),
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

    public static function getDetailsFormSchema(): array
    {
        $tenant = Filament::getTenant();

        return [
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
                        ->prepend('None', null)  // Allow "No Trainer" option
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
    }
}
