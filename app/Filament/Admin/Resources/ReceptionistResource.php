<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReceptionistResource\Pages;
use App\Filament\Admin\Resources\ReceptionistResource\RelationManagers;
use App\Models\Receptionist;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ReceptionistResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Users';

    protected static ?string $tenantOwnershipRelationshipName = 'gyms';

    public static function getLabel(): string
    {
        return 'Receptionist';
    }

    public static function getPluralLabel(): string
    {
        return 'Receptionists';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['gym_owner', 'receptionist']);
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Receptionist Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Jane Doe'),
                        TextInput::make('email')
                            ->required()
                            ->email()
                            ->unique(table: 'users', ignoreRecord: true)
                            ->placeholder('e.g., jane@example.com'),
                        TextInput::make('password')
                            ->revealable()
                            ->password()
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->rules([
                                fn($state) => filled($state) ? 'regex:/[A-Z]/' : '',
                                fn($state) => filled($state) ? 'regex:/[a-z]/' : '',
                                fn($state) => filled($state) ? 'regex:/[0-9]/' : '',
                                fn($state) => filled($state) ? 'regex:/[!@#$%^&*(),.?":{}|<>]/' : '',
                            ])
                            ->validationMessages([
                                'regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
                            ])
                            ->hint('Need a strong password?')
                            ->hintAction(
                                Action::make('generatePassword')
                                    ->label('Generate')
                                    ->icon('heroicon-o-key')
                                    ->action(function (Set $set) {
                                        $password = Str::password(12);
                                        $set('password', $password);
                                    })
                            ),
                        Select::make('gyms')
                            ->multiple()
                            ->relationship('gyms', 'name')
                            ->options(function () {
                                $user = auth()->user();
                                // return $user->gyms()->wherePivot('role', 'gym_owner')->first()?->gyms()->get()->pluck('name', 'id') ?? [];
                                return $user->gyms()->get()->pluck('name', 'id') ?? [];
                            })
                            ->required()
                            ->saveRelationshipsUsing(function (Select $component, Model $record, ?array $state) {
                                // This ensures the gym data is properly handled
                                if (!empty($state)) {
                                    $pivotData = [];
                                    foreach ($state as $gymId) {
                                        // Check if this gym was already assigned to preserve its active status
                                        $existingPivot = $record->gyms()->where('gym_id', $gymId)->first()?->pivot;

                                        $pivotData[$gymId] = [
                                            'role' => 'receptionist',
                                            'active' => $existingPivot ? $existingPivot->active : true,
                                        ];
                                    }
                                    $record->gyms()->sync($pivotData);
                                } else {
                                    $record->gyms()->detach();
                                }
                            })
                            ->helperText('Select the gyms this receptionist can access.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('gyms.name')
                    ->label('Assigned Gyms')
                    ->listWithLineBreaks()
                    ->badge()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Tables\Actions\Action::make('managePermissions')
                //     ->label('Manage Permissions')
                //     ->icon('heroicon-o-shield-check')
                //     ->color('warning')
                //     ->form([
                //         Toggle::make('active')
                //             ->label('Active Status')
                //             ->helperText('Enable/disable this receptionist across all gyms'),

                //         Section::make('Resource Permissions')
                //             ->schema([
                //                 // GymMember Resource
                //                 Toggle::make('toggle_GymMember')
                //                     ->label('Manage Gym Members')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'view_any::GymMember',
                //                             'view::GymMember',
                //                             'create::GymMember',
                //                             'update::GymMember',
                //                             'delete::GymMember',
                //                             'member_check_in',
                //                             'member_check_out',
                //                             'freeze_membership',
                //                             'unfreeze_membership',
                //                             'bulk_member_actions',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('GymMember_permissions')
                //                     ->label('Gym Member Permissions')
                //                     ->options([
                //                         'view_any::GymMember' => 'View Any Members',
                //                         'view::GymMember' => 'View Member',
                //                         'create::GymMember' => 'Create Member',
                //                         'update::GymMember' => 'Update Member',
                //                         'delete::GymMember' => 'Delete Member',
                //                         'member_check_in' => 'Check In Member',
                //                         'member_check_out' => 'Check Out Member',
                //                         'freeze_membership' => 'Freeze Membership',
                //                         'unfreeze_membership' => 'Unfreeze Membership',
                //                         'bulk_member_actions' => 'Bulk Member Actions',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'view_any::GymMember',
                //                         'view::GymMember',
                //                         'create::GymMember',
                //                         'update::GymMember',
                //                         'delete::GymMember',
                //                         'member_check_in',
                //                         'member_check_out',
                //                         'freeze_membership',
                //                         'unfreeze_membership',
                //                         'bulk_member_actions',
                //                     ], $record->hasPermissionTo('view_any::GymMember'))),

                //                 // Subscription Resource
                //                 Toggle::make('toggle_Subscription')
                //                     ->label('Manage Subscriptions')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'view_any::Subscription',
                //                             'view::Subscription',
                //                             'create::Subscription',
                //                             'update::Subscription',
                //                             'transfer_membership',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('Subscription_permissions')
                //                     ->label('Subscription Permissions')
                //                     ->options([
                //                         'view_any::Subscription' => 'View Any Subscriptions',
                //                         'view::Subscription' => 'View Subscription',
                //                         'create::Subscription' => 'Create Subscription',
                //                         'update::Subscription' => 'Update Subscription',
                //                         'transfer_membership' => 'Transfer Membership',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'view_any::Subscription',
                //                         'view::Subscription',
                //                         'create::Subscription',
                //                         'update::Subscription',
                //                         'transfer_membership',
                //                     ], $record->hasPermissionTo('view_any::Subscription'))),

                //                 // MembershipPlan Resource
                //                 Toggle::make('toggle_MembershipPlan')
                //                     ->label('Manage Membership Plans')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'view_any::MembershipPlan',
                //                             'view::MembershipPlan',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('MembershipPlan_permissions')
                //                     ->label('Membership Plan Permissions')
                //                     ->options([
                //                         'view_any::MembershipPlan' => 'View Any Plans',
                //                         'view::MembershipPlan' => 'View Plan',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'view_any::MembershipPlan',
                //                         'view::MembershipPlan',
                //                     ], $record->hasPermissionTo('view_any::MembershipPlan'))),

                //                 // Payment Resource
                //                 Toggle::make('toggle_Payment')
                //                     ->label('Manage Payments')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'view_any::Payment',
                //                             'view::Payment',
                //                             'create::Payment',
                //                             'update::Payment',
                //                             'process_payments',
                //                             'refund_payments',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('Payment_permissions')
                //                     ->label('Payment Permissions')
                //                     ->options([
                //                         'view_any::Payment' => 'View Any Payments',
                //                         'view::Payment' => 'View Payment',
                //                         'create::Payment' => 'Create Payment',
                //                         'update::Payment' => 'Update Payment',
                //                         'process_payments' => 'Process Payments',
                //                         'refund_payments' => 'Refund Payments',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'view_any::Payment',
                //                         'view::Payment',
                //                         'create::Payment',
                //                         'update::Payment',
                //                         'process_payments',
                //                         'refund_payments',
                //                     ], $record->hasPermissionTo('view_any::Payment'))),

                //                 // Class and Booking Resources
                //                 Toggle::make('toggle_ClassBooking')
                //                     ->label('Manage Classes & Bookings')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'view_any::Class',
                //                             'view::Class',
                //                             'view_any::Booking',
                //                             'view::Booking',
                //                             'create::Booking',
                //                             'update::Booking',
                //                             'delete::Booking',
                //                             'book_classes_for_members',
                //                             'cancel_bookings',
                //                             'manage_waiting_lists',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('ClassBooking_permissions')
                //                     ->label('Class & Booking Permissions')
                //                     ->options([
                //                         'view_any::Class' => 'View Any Classes',
                //                         'view::Class' => 'View Class',
                //                         'view_any::Booking' => 'View Any Bookings',
                //                         'view::Booking' => 'View Booking',
                //                         'create::Booking' => 'Create Booking',
                //                         'update::Booking' => 'Update Booking',
                //                         'delete::Booking' => 'Delete Booking',
                //                         'book_classes_for_members' => 'Book Classes',
                //                         'cancel_bookings' => 'Cancel Bookings',
                //                         'manage_waiting_lists' => 'Manage Waiting Lists',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'view_any::Class',
                //                         'view::Class',
                //                         'view_any::Booking',
                //                         'view::Booking',
                //                         'create::Booking',
                //                         'update::Booking',
                //                         'delete::Booking',
                //                         'book_classes_for_members',
                //                         'cancel_bookings',
                //                         'manage_waiting_lists',
                //                     ], $record->hasPermissionTo('view_any::Class'))),

                //                 // Attendance Resource
                //                 Toggle::make('toggle_Attendance')
                //                     ->label('Manage Attendance')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'view_any::Attendance',
                //                             'view::Attendance',
                //                             'create::Attendance',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('Attendance_permissions')
                //                     ->label('Attendance Permissions')
                //                     ->options([
                //                         'view_any::Attendance' => 'View Any Attendance',
                //                         'view::Attendance' => 'View Attendance',
                //                         'create::Attendance' => 'Create Attendance',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'view_any::Attendance',
                //                         'view::Attendance',
                //                         'create::Attendance',
                //                     ], $record->hasPermissionTo('view_any::Attendance'))),

                //                 // Trainer and Equipment Resources (view-only)
                //                 Toggle::make('toggle_TrainerEquipment')
                //                     ->label('View Trainers & Equipment')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'view_any::Trainer',
                //                             'view::Trainer',
                //                             'view_any::Equipment',
                //                             'view::Equipment',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('TrainerEquipment_permissions')
                //                     ->label('Trainer & Equipment Permissions')
                //                     ->options([
                //                         'view_any::Trainer' => 'View Any Trainers',
                //                         'view::Trainer' => 'View Trainer',
                //                         'view_any::Equipment' => 'View Any Equipment',
                //                         'view::Equipment' => 'View Equipment',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'view_any::Trainer',
                //                         'view::Trainer',
                //                         'view_any::Equipment',
                //                         'view::Equipment',
                //                     ], $record->hasPermissionTo('view_any::Trainer'))),

                //                 // Receptionist Profile
                //                 Toggle::make('toggle_Receptionist')
                //                     ->label('Manage Profile')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'view::Receptionist',
                //                             'update::Receptionist',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('Receptionist_permissions')
                //                     ->label('Profile Permissions')
                //                     ->options([
                //                         'view::Receptionist' => 'View Profile',
                //                         'update::Receptionist' => 'Update Profile',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'view::Receptionist',
                //                         'update::Receptionist',
                //                     ], $record->hasPermissionTo('view::Receptionist'))),

                //                 // Page Permissions
                //                 Toggle::make('toggle_Pages')
                //                     ->label('Access Pages')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'page_Dashboard',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('Pages_permissions')
                //                     ->label('Page Access')
                //                     ->options([
                //                         'page_Dashboard' => 'Access Dashboard',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'page_Dashboard',
                //                     ], $record->hasPermissionTo('page_Dashboard'))),

                //                 // Widget Permissions
                //                 Toggle::make('toggle_Widgets')
                //                     ->label('Access Widgets')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'widget_GymStatsOverview',
                //                             'widget_RecentActivity',
                //                             'widget_TodaysBirthdays',
                //                             'widget_ClassBookings',
                //                             'widget_PaymentSummary',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('Widgets_permissions')
                //                     ->label('Widget Access')
                //                     ->options([
                //                         'widget_GymStatsOverview' => 'Gym Stats Overview',
                //                         'widget_RecentActivity' => 'Recent Activity',
                //                         'widget_TodaysBirthdays' => 'Today\'s Birthdays',
                //                         'widget_ClassBookings' => 'Class Bookings',
                //                         'widget_PaymentSummary' => 'Payment Summary',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'widget_GymStatsOverview',
                //                         'widget_RecentActivity',
                //                         'widget_TodaysBirthdays',
                //                         'widget_ClassBookings',
                //                         'widget_PaymentSummary',
                //                     ], $record->hasPermissionTo('widget_GymStatsOverview'))),

                //                 // Custom Permissions
                //                 Toggle::make('toggle_Communication')
                //                     ->label('Manage Communication')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'send_notifications',
                //                             'send_bulk_emails',
                //                             'send_sms',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('Communication_permissions')
                //                     ->label('Communication Permissions')
                //                     ->options([
                //                         'send_notifications' => 'Send Notifications',
                //                         'send_bulk_emails' => 'Send Bulk Emails',
                //                         'send_sms' => 'Send SMS',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'send_notifications',
                //                         'send_bulk_emails',
                //                         'send_sms',
                //                     ], $record->hasPermissionTo('send_notifications'))),

                //                 Toggle::make('toggle_Reports')
                //                     ->label('Manage Reports')
                //                     ->reactive()
                //                     ->afterStateUpdated(function ($state, callable $set) {
                //                         $permissions = [
                //                             'view_attendance_reports',
                //                             'export_member_data',
                //                         ];
                //                         foreach ($permissions as $permission) {
                //                             $set($permission, $state);
                //                         }
                //                     }),
                //                 CheckboxList::make('Reports_permissions')
                //                     ->label('Report Permissions')
                //                     ->options([
                //                         'view_attendance_reports' => 'View Attendance Reports',
                //                         'export_member_data' => 'Export Member Data',
                //                     ])
                //                     ->columns(2)
                //                     ->default(fn($record) => array_fill_keys([
                //                         'view_attendance_reports',
                //                         'export_member_data',
                //                     ], $record->hasPermissionTo('view_attendance_reports'))),
                //             ]),
                //     ])
                //     ->action(function (array $data, Model $record): void {
                //         $permissions = array_filter($data, fn($key) => !in_array($key, ['active']), ARRAY_FILTER_USE_KEY);

                //         $record->syncPermissions(array_keys(array_filter($permissions)));

                //         $record->gyms()->sync($record->gyms()->pluck('id')->mapWithKeys(function ($gymId) use ($data) {
                //             return [$gymId => ['active' => $data['active'] ?? true]];
                //         }));

                //         Notification::make()
                //             ->title('Permissions Updated')
                //             ->body('Receptionist permissions have been successfully updated.')
                //             ->success()
                //             ->send();
                //     })
                //     ->modalHeading('Manage Receptionist Permissions')
                //     ->modalDescription('Configure resource, page, widget, and custom permissions.')
                //     ->modalSubmitActionLabel('Update Permissions')
                //     ->visible(function (Model $record): bool {
                //         $user = auth()->user();
                //         $userGymIds = $user->gyms()->pluck('gyms.id');

                //         return $record->gyms()
                //             ->whereIn('gyms.id', $userGymIds)
                //             ->exists();
                //     }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceptionists::route('/'),
            'create' => Pages\CreateReceptionist::route('/create'),
            'edit' => Pages\EditReceptionist::route('/{record}/edit'),
        ];
    }
}
