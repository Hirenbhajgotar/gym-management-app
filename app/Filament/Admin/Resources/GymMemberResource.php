<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Enums\PaymentMethod;
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
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Rules\ValidEmailDomain;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
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

                    Section::make()
                        ->schema(static::getMembershipFormSchema())
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
                    ->icon(function ($state) {
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
                    ->color(function ($state) {
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
            // ->headerActions([
            //     Tables\Actions\Action::make('export_members')
            //         ->label('Export Members')
            //         ->icon('heroicon-o-arrow-down-tray')
            //         ->color('success')
            //         ->form(function () {
            //             $user = auth()->user();
            //             $gymCount = 0;
            //             if (filament()->getId() === 'admin') {
            //                 $gymCount = $user->hasRole('gym_owner') ? $user->gyms()->count() : 0;
            //             } else if (filament()->getId() === 'staff') {
            //                 $gymCount = $user->gyms()->count();
            //             }

            //             if ($gymCount > 1) {
            //                 return [
            //                     Forms\Components\Select::make('gym_id')
            //                         ->label('Select Gym')
            //                         ->options(function () {
            //                             return auth()->user()->gyms()->get()->pluck('name', 'id');
            //                         })
            //                         ->required()
            //                         ->placeholder('Choose a gym to export members'),
            //                 ];
            //             }

            //             return [];
            //         })
            //         ->action(function (array $data) {
            //             $user = auth()->user();
            //             $gymId = null;

            //             if (filament()->getId() === 'admin') {
            //                 if ($user->hasRole('gym_owner')) {
            //                     $gymCount = $user->gyms()->count();
            //                     if ($gymCount > 1) {
            //                         $gymId = $data['gym_id'];
            //                     } else {
            //                         $gymId = $user->gyms()->first()?->id;
            //                     }
            //                 }
            //             } else if (filament()->getId() === 'staff') {
            //                 $gymCount = $user->gyms()->count();
            //                 if ($gymCount > 1) {
            //                     $gymId = $data['gym_id'];
            //                 } else {
            //                     $gymId = $user->gyms()->first()?->id;
            //                 }
            //             }

            //             return Excel::download(new MembersExport($gymId), 'members-' . now()->format('Y-m-d-H-i-s') . '.csv');
            //         })
            //         ->requiresConfirmation(),
            //     Tables\Actions\ActionGroup::make([ // Group the download actions
            //         Tables\Actions\Action::make('download_csv_template')
            //             ->label('Download CSV Template')
            //             ->icon('heroicon-o-document-arrow-down')
            //             ->action(fn() => response()->download(resource_path('views/templates/gym_members_import_template.csv')))
            //             ->color('info'),
            //         Tables\Actions\Action::make('download_xlsx_template')
            //             ->label('Download Excel Template')
            //             ->icon('heroicon-o-document-arrow-down')
            //             ->action(function () {
            //                 $path = resource_path('views/templates/gym_members_import_xlsx_template.xlsx');
            //                 if (! file_exists($path)) {
            //                     // Optionally create a basic one on the fly if it doesn't exist
            //                     // Or just throw an error/notification

            //                     Notification::make()
            //                         ->title('Template Not Found')
            //                         ->body('The Excel template file could not be found.')
            //                         ->danger()
            //                         ->sendToDatabase(auth()->user())
            //                         ->send();

            //                     return null; // Stop execution
            //                 }

            //                 return response()->download($path);
            //             })
            //             ->color('success'),

            //     ])
            //         ->label('Download Templates')
            //         ->icon('heroicon-o-document-arrow-down')
            //         ->button() // Display as a button with a dropdown
            //         ->color('warning'), // Color for the main group button

            //     Tables\Actions\Action::make('import')
            //         ->label('Import Members')
            //         ->icon('heroicon-o-document-plus')
            //         ->form([
            //             Forms\Components\Select::make('gym_id')
            //                 ->relationship('gym', 'name', function ($query) {
            //                     if (filament()->getId() === 'admin') {
            //                         if (auth()->user()->hasRole('gym_owner')) {
            //                             return $query->whereIn('id', auth()->user()->gyms()->get()->pluck('id'));
            //                         }
            //                     } else if (filament()->getId() === 'staff') {
            //                         // Staff can see all gyms
            //                         return $query->whereIn('id', auth()->user()->gyms()->get()->pluck('id'));
            //                     }

            //                     return $query;
            //                 })
            //                 ->required(),
            //             Forms\Components\FileUpload::make('file')
            //                 ->label('Upload CSV/XLS')
            //                 ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            //                 ->required()
            //                 ->storeFiles(false) // Prevent automatic storage
            //                 ->getUploadedFileNameForStorageUsing(
            //                     fn($file) => 'imports/' . $file->getClientOriginalName()
            //                 ),
            //         ])
            //         ->action(function (array $data) {
            //             try {
            //                 $file = $data['file']; // TemporaryUploadedFile object
            //                 $filePath = $file->getRealPath(); // Get the actual file path (e.g., C:\Users\SID\AppData\Local\Temp\phpEB47.tmp)

            //                 if (! file_exists($filePath)) {
            //                     throw new \Exception('Uploaded file not found.');
            //                 }
            //                 Excel::import(new GymMembersImport($data['gym_id']), $filePath);
            //             } catch (\Exception $e) {
            //                 Log::error('Import failed:', ['error' => $e->getMessage()]);
            //                 $recipient = auth()->user();
            //                 Notification::make()
            //                     ->title('Import failed')
            //                     ->body($e->getMessage())
            //                     ->danger()
            //                     ->sendToDatabase($recipient)
            //                     ->send();

            //                 event(new DatabaseNotificationsSent($recipient));
            //             }
            //         })
            //         ->requiresConfirmation()
            //         ->visible(function () {
            //             $user = auth()->user();
            //             if (filament()->getId() === 'admin') {
            //                 return $user->hasRole('gym_owner') || $user->hasRole('super_admin');
            //             } else if (filament()->getId() === 'staff') {
            //                 return true; // Staff can import members
            //             }
            //             return false;
            //         }),
            // ])
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
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $dob = Date::parse($state);
                        $age = floor($dob->diffInYears(now())); // Calculate age based on current date
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
        ];
    }

    public static function getMembershipFormSchema(): array
    {
        return [
            Hidden::make('user_id')
                ->default(fn () => auth()->id()),

            Section::make('Membership Details')
                ->icon('heroicon-o-ticket')
                ->description('Configure membership settings')
                ->schema([
                    Hidden::make('gym_id')
                        ->default(function () {
                            /** @var Gym|null $tenant */
                            $tenant = Filament::getTenant();

                            return $tenant?->id;
                        }),

                    Select::make('trainer_id')
                        ->label('Assign Trainer')
                        ->options(function (callable $get) {
                            // $gymId = $get('gym_id');
                            // if (!$gymId) {
                            //     return [];
                            // }
                            /** @var Gym|null $tenant */
                            $tenant = Filament::getTenant();
                            $gymId = $tenant?->id;

                            return Trainer::query()->where('gym_id', $gymId)
                                ->available()
                                ->pluck('name', 'id')
                                ->prepend('None', null); // Allow "No Trainer" option
                        })
                        ->reactive()
                        ->nullable()
                        ->afterStateHydrated(function ($state, callable $set, $record) {
                            if (! $state && $record) {
                                $set('trainer_id', null);
                            }
                        })
                        ->helperText('Leave as "None" if the member does not want a personal trainer.'),
                ])
                ->columns(2),
        ];
    }

    public static function getMembershipPlanFormSchema(Closure|int|string|null $gymId = null): array
    {
        return [
            Select::make('membership_plan_id')
                ->label('Membership Plan')
                ->placeholder('Select a plan')
                ->reactive()
                ->required()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if ($state) {
                        /** @var MembershipPlan|null $plan */
                        $plan = MembershipPlan::query()->find($state);
                        if ($plan && $plan->duration) {
                            $start = $get('membership_start') ?? now();
                            $set('membership_end', Date::parse($start)->addMonths($plan->duration)->toDateString());
                            $set('amount', $plan->price);
                            $set('discount_amount', 0);
                            $set('amount_paid', $plan->price);
                        }
                    }
                })
                ->options(function () use ($gymId) {
                    $gymId = $gymId instanceof Closure ? $gymId() : $gymId;
                    if (! $gymId) {
                        /** @var Gym|null $tenant */
                        $tenant = Filament::getTenant();
                        $gymId = $tenant?->id;
                    }

                    return MembershipPlan::query()
                        ->where('gym_id', $gymId)
                        ->where('is_active', true)
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->dehydrated(true)
                ->createOptionForm([
                    Section::make('Plan Details')
                        ->icon('heroicon-o-ticket')
                        ->description('Create a membership plan for your gym')
                        ->schema(MembershipPlanResource::planFormSchema())
                        ->columns(2),
                ])
                ->createOptionUsing(function (array $data) {
                    // Create the new state and return its ID
                    $state = MembershipPlan::query()->create($data);

                    return $state->id;
                }),

            DatePicker::make('membership_start')
                ->placeholder('Select start date')
                ->native(false)
                ->closeOnDateSelection(true)
                ->live()
                ->required()
                ->default(now())
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    $planId = $get('membership_plan_id');
                    if ($planId && $state) {
                        /** @var MembershipPlan|null $plan */
                        $plan = MembershipPlan::query()->find($planId);
                        if ($plan && $plan->duration) {
                            $set('membership_end', Date::parse($state)->addMonths($plan->duration)->toDateString());
                        }
                    }
                }),
            DatePicker::make('membership_end')
                ->placeholder('Auto-calculated based on plan')
                ->native(false)
                ->closeOnDateSelection(true)
                ->required()
                ->default(now()),
        ];
    }

    public static function getPaymentFormSchema(): array
    {
        return [
            Section::make('Payment Details')
                ->icon('heroicon-o-credit-card')
                ->description('Record the member\'s payment')
                ->schema([
                    TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->prefix(function (callable $get): string {
                            $currency = Currency::tryFrom(is_string($get('currency')) ? $get('currency') : Currency::INR->value);

                            return $currency?->getSymbol() ?? Currency::INR->getSymbol();
                        })
                        ->minValue(0)
                        ->step(0.01)
                        ->disabled()
                        ->dehydrated()
                        ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

                    TextInput::make('discount_amount')
                        ->label('Discount')
                        ->numeric()
                        ->prefix('-')
                        ->minValue(0)
                        ->maxValue(function (callable $get) {
                            $planId = $get('membership_plan_id');
                            if (! $planId) {
                                return null;
                            }

                            /** @var MembershipPlan|null $plan */
                            $plan = MembershipPlan::query()->find($planId);

                            return $get('amount') ?? $plan->price ?? 0;
                        })
                        ->step(0.01)
                        ->default(0)
                        ->reactive()
                        ->live(debounce: 400)
                        ->afterStateUpdated(self::getDiscountCallback())
                        ->helperText('Optional: enter any discount to subtract from the plan price.'),

                    TextInput::make('amount_paid')
                        ->label('Amount Paid Now')
                        ->numeric()
                        ->required()
                        ->prefix(function (callable $get): string {
                            $currency = Currency::tryFrom(is_string($get('currency')) ? $get('currency') : Currency::INR->value);

                            return $currency?->getSymbol() ?? Currency::INR->getSymbol();
                        })
                        ->minValue(0)
                        ->maxValue(fn (callable $get) => $get('amount') ?? 0)
                        ->step(0.01)
                        ->helperText('Enter the amount the member is paying now. If less than the plan price, remaining balance will be tracked.')
                        ->default(fn (callable $get) => $get('amount') ?? 0)
                        ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

                    Select::make('currency')
                        ->options(collect(Currency::cases())->mapWithKeys(
                            fn ($method) => [$method->value => $method->getLabel()]
                        ))
                        ->required()
                        ->default(Currency::INR->value)
                        ->reactive(),

                    Select::make('payment_method')
                        ->options(collect(PaymentMethod::cases())->mapWithKeys(
                            fn ($method) => [$method->value => $method->getLabel()]
                        ))
                        ->required()
                        ->default(PaymentMethod::CASH->value),

                    ToggleButtons::make('status')
                        ->inline()
                        ->options(collect(PaymentStatus::cases())
                            ->filter(fn ($status) => $status !== PaymentStatus::EXPIRED)
                            ->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])
                            ->all()
                        )
                        ->required(),

                    DatePicker::make('payment_date')
                        ->native(false)
                        ->closeOnDateSelection(true)
                        ->required()
                        ->default(now()),

                    Textarea::make('notes')
                        ->maxLength(65535)
                        ->placeholder('e.g., Paid via UPI ID: user@upi')
                        ->extraAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500'])
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ];
    }

    public static function getDiscountCallback(): Closure
    {
        return function ($state, callable $set, callable $get): void {
            $planId = $get('membership_plan_id');
            if (! $planId) {
                return;
            }

            /** @var MembershipPlan|null $plan */
            $plan = MembershipPlan::query()->find($planId);
            if (! $plan) {
                return;
            }

            $discount = (float) (is_numeric($state) ? $state : 0);
            $finalAmount = max(0, ((float) $plan->price) - $discount);

            $set('amount', $finalAmount);
            $set('amount_paid', $finalAmount);
        };
    }
}
