<?php

namespace App\Filament\Admin\Resources;

use App\Exports\MembersExport;
use App\Filament\Admin\Resources\GymMemberResource\Pages;
use App\Filament\Admin\Resources\GymMemberResource\RelationManagers\PaymentsRelationManager;
use App\Forms\Components\AddressForm;
use App\Imports\GymMembersImport;
use App\Models\GymMember;
use App\Models\Trainer;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use libphonenumber\PhoneNumberType;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class GymMemberResource extends Resource
{
    protected static ?string $model = GymMember::class;

    protected static ?string $navigationLabel = 'Gym Members';

    protected static ?string $navigationGroup = 'Users';

    public static function getNavigationBadge(): ?string
    {
        $modelClass = static::$model;
        $query = $modelClass::query();
        return $query->count();
    }

    // Restrict to admins or gym owners with gyms
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        if (filament()->getId() === 'admin') {
            return $user->hasRole('super_admin') || ($user->hasRole('gym_owner'));
        } else if (filament()->getId() === 'staff') {
            return true;
        }
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema(static::getDetailsFormSchema())
                            ->columns(2),

                        Forms\Components\Section::make()
                            ->schema(static::getMembershipFormSchema())
                            ->columns(2),
                    ])
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('profile_photo')
                    ->circular()
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->label(''),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied!')
                    ->icon('heroicon-o-envelope')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->icon('heroicon-o-phone')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('gym.name')
                    ->label('Gym')
                    ->badge()
                    ->color('primary'),
                // Tables\Columns\TextColumn::make('membershipPlan.name')
                //     ->label('Plan')
                //     ->badge()
                //     ->color('success'),
                Tables\Columns\TextColumn::make('membership_start')
                    ->date()
                    ->badge()
                    ->icon('heroicon-o-calendar')
                    ->color('info'),
                Tables\Columns\TextColumn::make('membership_end')
                    ->formatStateUsing(function ($state) {
                        if (!$state) {
                            return 'Not Set';
                        }

                        $now = now(); // June 1, 2025
                        $membershipEnd = Carbon::parse(date('Y-m-d', strtotime($state)));

                        // Check if membership has already expired
                        if ($now->greaterThan($membershipEnd)) {
                            return 'Expired';
                        }

                        // Check if membership ends within 2 months (on or before July 31, 2025)
                        $diffInMonths = $now->diffInMonths($membershipEnd, false);
                        if ($diffInMonths <= 2 && $diffInMonths >= 0) {
                            $diffInDays = round($now->diffInDays($membershipEnd, false)); // Round to nearest whole number
                            return "Expire in {$diffInDays} " . Str::plural('day', $diffInDays);
                        }

                        // Otherwise, show as a regular date
                        return $membershipEnd->toFormattedDateString(); // e.g., "Aug 15, 2025"
                    })
                    ->badge()
                    ->icon(function ($state) {
                        if (!$state) {
                            return 'heroicon-o-calendar'; // Default icon for "Not Set"
                        }

                        $now = now(); // June 1, 2025, 12:43 AM IST
                        $membershipEnd = Carbon::parse($state);

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
                        if (!$state) {
                            return 'gray'; // Default color for "Not Set"
                        }

                        $now = now(); // June 1, 2025
                        $membershipEnd = Carbon::parse($state);

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
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment Status')
                    ->badge(),

                // Tables\Columns\TextColumn::make('payments_sum_amount')
                //     ->label('Total Paid')
                //     ->money('INR')
                //     ->summarize(Tables\Columns\Summarizers\Sum::make())
                //     ->badge()
                //     ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gym_id')
                    ->relationship('gym', 'name', function ($query) {
                        if (filament()->getId() === 'admin') {
                            if (auth()->user()->hasRole('gym_owner')) {
                                return $query->whereIn('id', auth()->user()?->gyms()->get()->pluck('id'));
                            }
                            if (auth()->user()->hasRole('super_admin')) {
                                return $query;
                            }
                        } else if (filament()->getId() === 'staff') {
                            return $query->whereIn('id', auth()->user()?->gyms()->get()->pluck('id'));
                        }
                    })
                    ->label('Gym'),
                Tables\Filters\SelectFilter::make('gym_membership_plan_id')
                    ->relationship('membershipPlan', 'name', function ($query) {
                        if (filament()->getId() === 'admin') {
                            if (auth()->user()->hasRole('gym_owner')) {
                                return $query->whereIn('gym_id', auth()->user()?->gyms()->get()->pluck('id'));
                            }
                            if (auth()->user()->hasRole('super_admin')) {
                                return $query;
                            }
                        } else if (filament()->getId() === 'staff') {
                            return $query->whereIn('gym_id', auth()->user()?->gyms()->get()->pluck('id'));
                        }
                    })
                    ->label('Plan'),
                SelectFilter::make('payment_status')
                    ->options([
                        'paid' => 'Paid',
                        'unpaid' => 'Unpaid',
                    ])
                    ->query(function ($query, array $state) {
                        if (! empty($state['value'])) {
                            $value = $state['value'];
                            if ($value === 'paid') {
                                $query->whereHas('payments', function ($q) {
                                    $q->where('status', 'completed');
                                });
                            } elseif ($value === 'unpaid') {
                                $query->whereDoesntHave('payments', function ($q) {
                                    $q->where('status', 'completed');
                                });
                            }
                        }
                    })
                    ->label('Payment Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary')
                    ->visible(fn() => auth()->user()->hasPermissionTo('update_gym::member')),
                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn() => auth()->user()->hasPermissionTo('delete_gym::member'))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Member')

                    ->modalDescription('Are you sure you want to delete this member? This action requires password confirmation.')
                    ->modalSubmitActionLabel('Confirm Delete')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('Your Password')
                            ->revealable()
                            ->password()
                            ->required()
                            ->rules([
                                'required',
                                fn($get) => function ($attribute, $value, $fail) {
                                    if (! Hash::check($value, auth()->user()->password)) {
                                        $fail('The password is incorrect.');
                                    }
                                },
                            ]),
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
            ->headerActions([
                Tables\Actions\Action::make('export_members')
                    ->label('Export Members')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form(function () {
                        $user = auth()->user();
                        $gymCount = 0;
                        if (filament()->getId() === 'admin') {
                            $gymCount = $user->hasRole('gym_owner') ? $user->gyms()->count() : 0;
                        } else if (filament()->getId() === 'staff') {
                            $gymCount = $user->gyms()->count();
                        }

                        if ($gymCount > 1) {
                            return [
                                Forms\Components\Select::make('gym_id')
                                    ->label('Select Gym')
                                    ->options(function () {
                                        return auth()->user()->gyms()->get()->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->placeholder('Choose a gym to export members'),
                            ];
                        }

                        return [];
                    })
                    ->action(function (array $data) {
                        $user = auth()->user();
                        $gymId = null;

                        if (filament()->getId() === 'admin') {
                            if ($user->hasRole('gym_owner')) {
                                $gymCount = $user->gyms()->count();
                                if ($gymCount > 1) {
                                    $gymId = $data['gym_id'];
                                } else {
                                    $gymId = $user->gyms()->first()?->id;
                                }
                            }
                        } else if (filament()->getId() === 'staff') {
                            $gymCount = $user->gyms()->count();
                            if ($gymCount > 1) {
                                $gymId = $data['gym_id'];
                            } else {
                                $gymId = $user->gyms()->first()?->id;
                            }
                        }

                        return Excel::download(new MembersExport($gymId), 'members-' . now()->format('Y-m-d-H-i-s') . '.csv');
                    })
                    ->requiresConfirmation(),
                Tables\Actions\ActionGroup::make([ // Group the download actions
                    Tables\Actions\Action::make('download_csv_template')
                        ->label('Download CSV Template')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(fn() => response()->download(resource_path('views/templates/gym_members_import_template.csv')))
                        ->color('info'),
                    Tables\Actions\Action::make('download_xlsx_template')
                        ->label('Download Excel Template')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function () {
                            $path = resource_path('views/templates/gym_members_import_xlsx_template.xlsx');
                            if (! file_exists($path)) {
                                // Optionally create a basic one on the fly if it doesn't exist
                                // Or just throw an error/notification

                                Notification::make()
                                    ->title('Template Not Found')
                                    ->body('The Excel template file could not be found.')
                                    ->danger()
                                    ->sendToDatabase(auth()->user())
                                    ->send();

                                return null; // Stop execution
                            }

                            return response()->download($path);
                        })
                        ->color('success'),

                ])
                    ->label('Download Templates')
                    ->icon('heroicon-o-document-arrow-down')
                    ->button() // Display as a button with a dropdown
                    ->color('warning'), // Color for the main group button

                Tables\Actions\Action::make('import')
                    ->label('Import Members')
                    ->icon('heroicon-o-document-plus')
                    ->form([
                        Forms\Components\Select::make('gym_id')
                            ->relationship('gym', 'name', function ($query) {
                                if (filament()->getId() === 'admin') {
                                    if (auth()->user()->hasRole('gym_owner')) {
                                        return $query->whereIn('id', auth()->user()->gyms()->get()->pluck('id'));
                                    }
                                } else if (filament()->getId() === 'staff') {
                                    // Staff can see all gyms
                                    return $query->whereIn('id', auth()->user()->gyms()->get()->pluck('id'));
                                }

                                return $query;
                            })
                            ->required(),
                        Forms\Components\FileUpload::make('file')
                            ->label('Upload CSV/XLS')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->required()
                            ->storeFiles(false) // Prevent automatic storage
                            ->getUploadedFileNameForStorageUsing(
                                fn($file) => 'imports/' . $file->getClientOriginalName()
                            ),
                    ])
                    ->action(function (array $data) {
                        try {
                            $file = $data['file']; // TemporaryUploadedFile object
                            $filePath = $file->getRealPath(); // Get the actual file path (e.g., C:\Users\SID\AppData\Local\Temp\phpEB47.tmp)

                            if (! file_exists($filePath)) {
                                throw new \Exception('Uploaded file not found.');
                            }
                            Excel::import(new GymMembersImport($data['gym_id']), $filePath);
                        } catch (\Exception $e) {
                            Log::error('Import failed:', ['error' => $e->getMessage()]);
                            $recipient = auth()->user();
                            Notification::make()
                                ->title('Import failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->sendToDatabase($recipient)
                                ->send();

                            event(new DatabaseNotificationsSent($recipient));
                        }
                    })
                    ->requiresConfirmation()
                    ->visible(function () {
                        $user = auth()->user();
                        if (filament()->getId() === 'admin') {
                            return $user->hasRole('gym_owner') || $user->hasRole('super_admin');
                        } else if (filament()->getId() === 'staff') {
                            return true; // Staff can import members
                        }
                        return false;
                    }),
            ])
            // ->modifyQueryUsing(function ($query) {
            //     $user = auth()->user();
            //     if ($user->hasRole('gym_owner')) {
            //         $query->whereIn('gym_id', $user->gyms()->pluck('id'));
            //     }
            //     return $query;
            //     // $optimizedQuery = $query
            //     //     ->select('gym_members.*')
            //     //     ->leftJoin('gyms', 'gym_members.gym_id', '=', 'gyms.id')
            //     //     ->leftJoin('membership_plans', 'gym_members.gym_membership_plan_id', '=', 'membership_plans.id')
            //     //     ->leftJoin('member_payments', function ($join) {
            //     //         $join->on('gym_members.id', '=', 'member_payments.member_id')
            //     //             ->where('member_payments.status', 'completed');
            //     //     })
            //     //     ->groupBy('gym_members.id')
            //     //     ->with(['gym', 'membershipPlan'])
            //     //     ->addSelect([
            //     //         'has_completed_payment' => DB::raw('IF(COUNT(member_payments.id) > 0, 1, 0)')
            //     //     ]);

            //     // Log::info('GymMemberResource Query', ['query' => $optimizedQuery->toSql(), 'bindings' => $optimizedQuery->getBindings()]);

            //     // return $optimizedQuery;
            // })
            ->defaultSort('name')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGymMembers::route('/'),
            'create' => Pages\CreateGymMember::route('/create'),
            'edit' => Pages\EditGymMember::route('/{record}/edit'),
        ];
    }

    public static function getDetailsFormSchema(): array
    {
        return [
            Forms\Components\FileUpload::make('profile_photo')
                ->image()
                ->avatar()
                ->imageEditor()
                ->circleCropper()
                ->directory('member-photos')
                ->maxSize(2048)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g., John Doe')
                ->autofocus()
                ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(GymMember::class, 'email', ignoreRecord: true)
                ->rules(['email:rfc'])
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
                ->validateFor(
                    country: ['IN', 'PK', 'BD', 'US'], // default: 'AUTO'
                    type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE], // default: null
                    lenient: true, // default: false
                )
                ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

            AddressForm::make('address')
                ->columnSpan('full'),

            Forms\Components\TextInput::make('weight')
                ->numeric()
                ->suffix('kg')
                ->step(0.01)
                ->minValue(0)
                ->maxValue(500)
                ->placeholder('e.g., 75.5'),
            Forms\Components\TextInput::make('height')
                ->numeric()
                ->suffix('cm')
                ->step(0.01)
                ->minValue(0)
                ->maxValue(300)
                ->placeholder('e.g., 175'),

            Forms\Components\DatePicker::make('dob')
                ->label('Date of Birth')
                ->placeholder('Select date of birth')
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $dob = Carbon::parse($state);
                        $age = floor($dob->diffInYears(now())); // Calculate age based on current date
                        $set('age', $age);
                    } else {
                        $set('age', null); // Clear age if dob is empty
                    }
                }),

            Forms\Components\TextInput::make('age')
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
                ->default(fn() => auth()->id()),

            Forms\Components\Section::make('Membership Details')
                ->icon('heroicon-o-ticket')
                ->description('Configure membership settings')
                ->schema([
                    Forms\Components\Select::make('gym_id')
                        ->relationship('gym', 'name', function ($query) {
                            if (filament()->getId() === 'admin') {
                                if (auth()->user()->hasRole('gym_owner')) {
                                    return $query->whereIn('id', auth()->user()->gyms()->get()->pluck('id'));
                                }
                            } else if (filament()->getId() === 'staff') {
                                return $query->whereIn('id', auth()->user()->gyms()->get()->pluck('id'));
                            }

                            return $query;
                        })
                        ->required()
                        ->placeholder('Select a gym')
                        ->live()
                        ->rules([
                            'required_if:visible,true',
                            fn($get) => function ($attribute, $value, $fail) {
                                if (filament()->getId() === 'admin') {
                                    if (auth()->user()->hasRole('gym_owner') && ! $value) {
                                        $fail('You must create a gym before adding members.');
                                    }
                                } else if (filament()->getId() === 'staff') {
                                    if (! $value) {
                                        $fail('You must select a gym to assign this member.');
                                    }
                                }
                            },
                        ]),

                    Forms\Components\Select::make('trainer_id')
                        ->label('Assign Trainer')
                        ->options(function (callable $get) {
                            $gymId = $get('gym_id');
                            if (!$gymId) {
                                return [];
                            }
                            return Trainer::where('gym_id', $gymId)
                                ->available()
                                ->pluck('name', 'id')
                                ->prepend('None', null); // Allow "No Trainer" option
                        })
                        ->reactive()
                        ->nullable()
                        ->afterStateHydrated(function ($state, callable $set, $record) {
                            if (!$state && $record) {
                                $set('trainer_id', null);
                            }
                        })
                        ->helperText('Leave as "None" if the member does not want a personal trainer.'),
                ])
                ->columns(2),
        ];
    }
}
