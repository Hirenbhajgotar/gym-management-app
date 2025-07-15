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
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
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
use Spatie\Permission\Models\Permission;

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
        return auth()->check() && auth()->user()->hasAnyRole(['gym_owner']);
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
                //             ->helperText('Enable/disable this receptionist across all gyms')
                //             ->default(function (Model $record): bool {
                //                 return $record->gyms()->wherePivot('active', true)->exists();
                //             }),

                //         Tabs::make('permission_tabs')
                //             ->tabs([
                //                 Tab::make('Resource Permissions')
                //                     ->icon('heroicon-o-cube')
                //                     ->schema([
                //                         Section::make('Member Management')
                //                             ->schema([
                //                                 Toggle::make('toggle_gym_member')
                //                                     ->label('Enable All Member Permissions')
                //                                     ->reactive()
                //                                     ->default(function (Model $record): bool {
                //                                         return $record->hasPermissionTo('view_any_gym_member');
                //                                     })
                //                                     ->afterStateUpdated(function ($state, callable $set) {
                //                                         $permissions = [
                //                                             'view_any_gym_member',
                //                                             'view_gym_member',
                //                                             'create_gym_member',
                //                                             'update_gym_member',
                //                                             'delete_gym_member',
                //                                         ];
                //                                         foreach ($permissions as $permission) {
                //                                             $set($permission, $state);
                //                                         }
                //                                     }),

                //                                 CheckboxList::make('gym_member_permissions')
                //                                     ->label('Member Permissions')
                //                                     ->options([
                //                                         'view_any_gym_member' => 'View Any Members',
                //                                         'view_gym_member' => 'View Member Details',
                //                                         'create_gym_member' => 'Create New Member',
                //                                         'update_gym_member' => 'Update Member Info',
                //                                         'delete_gym_member' => 'Delete Member',
                //                                     ])
                //                                     ->columns(2)
                //                                     ->default(function (Model $record): array {
                //                                         $permissions = [
                //                                             'view_any_gym_member',
                //                                             'view_gym_member',
                //                                             'create_gym_member',
                //                                             'update_gym_member',
                //                                             'delete_gym_member',
                //                                         ];
                //                                         return array_filter($permissions, fn($perm) => $record->hasPermissionTo($perm));
                //                                     }),
                //                             ])
                //                             ->collapsible(),

                //                         // Section::make('Subscription Management')
                //                         //     ->schema([
                //                         //         Toggle::make('toggle_subscription')
                //                         //             ->label('Enable All Subscription Permissions')
                //                         //             ->reactive()
                //                         //             ->default(function (Model $record): bool {
                //                         //                 return $record->hasPermissionTo('view_any::Subscription');
                //                         //             })
                //                         //             ->afterStateUpdated(function ($state, callable $set) {
                //                         //                 $permissions = [
                //                         //                     'view_any_subscription',
                //                         //                     'view_subscription',
                //                         //                     'create_subscription',
                //                         //                     'update_subscription',
                //                         //                     'delete_subscription',
                //                         //                     'transfer_membership',
                //                         //                 ];
                //                         //                 foreach ($permissions as $permission) {
                //                         //                     $set($permission, $state);
                //                         //                 }
                //                         //             }),

                //                         //         CheckboxList::make('subscription_permissions')
                //                         //             ->label('Subscription Permissions')
                //                         //             ->options([
                //                         //                 'view_any::Subscription' => 'View Any Subscriptions',
                //                         //                 'view::Subscription' => 'View Subscription Details',
                //                         //                 'create::Subscription' => 'Create New Subscription',
                //                         //                 'update::Subscription' => 'Update Subscription',
                //                         //                 'delete::Subscription' => 'Delete Subscription',
                //                         //                 'transfer_membership' => 'Transfer Membership',
                //                         //             ])
                //                         //             ->columns(2)
                //                         //             ->default(function (Model $record): array {
                //                         //                 $permissions = [
                //                         //                     'view_any::Subscription',
                //                         //                     'view::Subscription',
                //                         //                     'create::Subscription',
                //                         //                     'update::Subscription',
                //                         //                     'delete::Subscription',
                //                         //                     'transfer_membership',
                //                         //                 ];
                //                         //                 return array_filter($permissions, fn($perm) => $record->hasPermissionTo($perm));
                //                         //             }),
                //                         //     ])
                //                         //     ->collapsible(),

                //                         // Section::make('Payment Management')
                //                         //     ->schema([
                //                         //         Toggle::make('toggle_payment')
                //                         //             ->label('Enable All Payment Permissions')
                //                         //             ->reactive()
                //                         //             ->default(function (Model $record): bool {
                //                         //                 return $record->hasPermissionTo('view_any::Payment');
                //                         //             })
                //                         //             ->afterStateUpdated(function ($state, callable $set) {
                //                         //                 $permissions = [
                //                         //                     'view_any_payment',
                //                         //                     'view_payment',
                //                         //                     'create_payment',
                //                         //                     'update_payment',
                //                         //                     'process_payments',
                //                         //                     'refund_payments',
                //                         //                 ];
                //                         //                 foreach ($permissions as $permission) {
                //                         //                     $set($permission, $state);
                //                         //                 }
                //                         //             }),

                //                         //         CheckboxList::make('payment_permissions')
                //                         //             ->label('Payment Permissions')
                //                         //             ->options([
                //                         //                 'view_any::Payment' => 'View Any Payments',
                //                         //                 'view::Payment' => 'View Payment Details',
                //                         //                 'create::Payment' => 'Create Payment',
                //                         //                 'update::Payment' => 'Update Payment',
                //                         //                 'process_payments' => 'Process Payments',
                //                         //                 'refund_payments' => 'Refund Payments',
                //                         //             ])
                //                         //             ->columns(2)
                //                         //             ->default(function (Model $record): array {
                //                         //                 $permissions = [
                //                         //                     'view_any::Payment',
                //                         //                     'view::Payment',
                //                         //                     'create::Payment',
                //                         //                     'update::Payment',
                //                         //                     'process_payments',
                //                         //                     'refund_payments',
                //                         //                 ];
                //                         //                 return array_filter($permissions, fn($perm) => $record->hasPermissionTo($perm));
                //                         //             }),
                //                         //     ])
                //                         //     ->collapsible(),

                //                         Section::make('Other Resources')
                //                             ->schema([
                //                                 Toggle::make('toggle_other_resources')
                //                                     ->label('Enable View Access to Other Resources')
                //                                     ->reactive()
                //                                     ->default(function (Model $record): bool {
                //                                         return $record->hasPermissionTo('view_any_trainer');
                //                                     })
                //                                     ->afterStateUpdated(function ($state, callable $set) {
                //                                         $permissions = [
                //                                             'view_any_trainer',
                //                                             'view_trainer',
                //                                             'view_any_membership_plan',
                //                                             'view_membership_plan',
                //                                         ];
                //                                         foreach ($permissions as $permission) {
                //                                             $set($permission, $state);
                //                                         }
                //                                     }),

                //                                 CheckboxList::make('other_resource_permissions')
                //                                     ->label('Other Resource Permissions')
                //                                     ->options([
                //                                         'view_any_trainer' => 'View Any Trainers',
                //                                         'view_trainer' => 'View Trainer Details',
                //                                         'view_any_membership_plan' => 'View Any Membership Plans',
                //                                         'view_membership_plan' => 'View Membership Plan Details',
                //                                     ])
                //                                     ->columns(2)
                //                                     ->default(function (Model $record): array {
                //                                         $permissions = [
                //                                             'view_any_trainer',
                //                                             'view_trainer',
                //                                             // 'view_any_equipment',
                //                                             // 'view_equipment',
                //                                             'view_any_membership_plan',
                //                                             'view_membership_plan',
                //                                             // 'view_any_class',
                //                                             // 'view_class',
                //                                         ];
                //                                         return array_filter($permissions, fn($perm) => $record->hasPermissionTo($perm));
                //                                     }),
                //                             ])
                //                             ->collapsible(),
                //                     ]),

                //                 Tab::make('Page Permissions')
                //                     ->icon('heroicon-o-document-text')
                //                     ->schema([
                //                         Section::make('Page Access')
                //                             ->schema([
                //                                 Toggle::make('toggle_all_pages')
                //                                     ->label('Enable All Page Access')
                //                                     ->reactive()
                //                                     ->default(function (Model $record): bool {
                //                                         return $record->hasPermissionTo('page_dashboard');
                //                                     })
                //                                     ->afterStateUpdated(function ($state, callable $set) {
                //                                         $permissions = [
                //                                             'page_dashboard',
                //                                             'page_reports',
                //                                             'page_settings',
                //                                             'page_analytics',
                //                                             // 'page_gym_profile',
                //                                             // 'access_admin_panel',
                //                                         ];
                //                                         foreach ($permissions as $permission) {
                //                                             $set($permission, $state);
                //                                         }
                //                                     }),

                //                                 CheckboxList::make('page_permissions')
                //                                     ->label('Page Access Permissions')
                //                                     ->options([
                //                                         'page_dashboard' => 'Dashboard Page',
                //                                         'page_reports' => 'Reports Page',
                //                                         'page_settings' => 'Settings Page',
                //                                         'page_analytics' => 'Analytics Page',
                //                                         // 'page_gym_profile' => 'Gym Profile Page',
                //                                         // 'page_import_export' => 'Import/Export Page',
                //                                     ])
                //                                     ->columns(2)
                //                                     ->default(function (Model $record): array {
                //                                         $permissions = [
                //                                             'page_dashboard',
                //                                             'page_reports',
                //                                             'page_settings',
                //                                             'page_analytics',
                //                                             // 'page_gym_profile',
                //                                             // 'page_import_export',
                //                                         ];
                //                                         return array_filter($permissions, fn($perm) => $record->hasPermissionTo($perm));
                //                                     }),
                //                             ]),
                //                     ]),

                //                 Tab::make('Widget Permissions')
                //                     ->icon('heroicon-o-squares-2x2')
                //                     ->schema([
                //                         Section::make('Dashboard Widgets')
                //                             ->schema([
                //                                 Toggle::make('toggle_all_widgets')
                //                                     ->label('Enable All Widget Access')
                //                                     ->reactive()
                //                                     ->default(function (Model $record): bool {
                //                                         return $record->hasPermissionTo('widget_gym_stats_overview');
                //                                     })
                //                                     ->afterStateUpdated(function ($state, callable $set) {
                //                                         $permissions = [
                //                                             'widget_gym_stats_overview',
                //                                             'widget_recent_activity',
                //                                             'widget_todays_birthdays',
                //                                             'widget_gym_revenue_chart',
                //                                             'widget_membership_expiry_alerts_chart',
                //                                             // 'widget_payment_summary',
                //                                             // 'widget_class_bookings',
                //                                             // 'widget_attendance_chart',
                //                                         ];
                //                                         foreach ($permissions as $permission) {
                //                                             $set($permission, $state);
                //                                         }
                //                                     }),

                //                                 CheckboxList::make('widget_permissions')
                //                                     ->label('Widget Access Permissions')
                //                                     ->options([
                //                                         'widget_gym_stats_overview' => 'Gym Stats Overview',
                //                                         'widget_recent_activity' => 'Recent Activity',
                //                                         'widget_todays_birthdays' => 'Today\'s Birthdays',
                //                                         'widget_gym_revenue_chart' => 'Revenue Chart',
                //                                         'widget_membership_expiry_alerts_chart' => 'Membership Expiry Alerts',
                //                                         // 'widget_payment_summary' => 'Payment Summary',
                //                                         // 'widget_class_bookings' => 'Class Bookings',
                //                                         // 'widget_attendance_chart' => 'Attendance Chart',
                //                                     ])
                //                                     ->columns(2)
                //                                     ->default(function (Model $record): array {
                //                                         $permissions = [
                //                                             'widget_gym_stats_overview',
                //                                             'widget_recent_activity',
                //                                             'widget_todays_birthdays',
                //                                             'widget_gym_revenue_chart',
                //                                             'widget_membership_expiry_alerts_chart',
                //                                             // 'widget_payment_summary',
                //                                             // 'widget_class_bookings',
                //                                             // 'widget_attendance_chart',
                //                                         ];
                //                                         return array_filter($permissions, fn($perm) => $record->hasPermissionTo($perm));
                //                                     }),
                //                             ]),
                //                     ]),
                //             ])
                //             ->activeTab(1)
                //             ->persistTabInQueryString(),
                //     ])
                //     ->action(function (array $data, Model $record): void {
                //         // Collect all permissions from all tabs
                //         $allPermissions = [];

                //         // Resource permissions
                //         if (isset($data['gym_member_permissions']) && is_array($data['gym_member_permissions'])) {
                //             $allPermissions = array_merge($allPermissions, $data['gym_member_permissions']);
                //         }

                //         if (isset($data['subscription_permissions']) && is_array($data['subscription_permissions'])) {
                //             $allPermissions = array_merge($allPermissions, $data['subscription_permissions']);
                //         }

                //         if (isset($data['payment_permissions']) && is_array($data['payment_permissions'])) {
                //             $allPermissions = array_merge($allPermissions, $data['payment_permissions']);
                //         }

                //         if (isset($data['other_resource_permissions']) && is_array($data['other_resource_permissions'])) {
                //             $allPermissions = array_merge($allPermissions, $data['other_resource_permissions']);
                //         }

                //         // Page permissions
                //         if (isset($data['page_permissions']) && is_array($data['page_permissions'])) {
                //             $allPermissions = array_merge($allPermissions, $data['page_permissions']);
                //         }

                //         // Widget permissions
                //         if (isset($data['widget_permissions']) && is_array($data['widget_permissions'])) {
                //             $allPermissions = array_merge($allPermissions, $data['widget_permissions']);
                //         }

                //         // Remove duplicates and filter valid permissions
                //         $allPermissions = array_unique($allPermissions);
                //         $validPermissions = array_filter($allPermissions, function ($permission) {
                //             return !empty($permission) && Permission::where('name', $permission)->exists();
                //         });

                //         // Sync permissions to the specific receptionist
                //         $record->syncPermissions($validPermissions);

                //         // Update active status in pivot table
                //         $record->gyms()->update(['active' => $data['active'] ?? true]);

                //         Notification::make()
                //             ->title('Permissions Updated Successfully')
                //             ->body("Updated permissions for {$record->name}. Total permissions: " . count($validPermissions))
                //             ->success()
                //             ->send();
                //     })
                //     ->modalHeading(fn(Model $record): string => "Manage Permissions for {$record->name}")
                //     ->modalDescription('Configure resource access, page access, and widget permissions using the tabs below.')
                //     ->modalSubmitActionLabel('Update Permissions')
                //     ->modalWidth('7xl')
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
