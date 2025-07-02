<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReceptionistResource\Pages;
use App\Filament\Admin\Resources\ReceptionistResource\RelationManagers;
use App\Models\Receptionist;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
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
        return auth()->check() && auth()->user()->hasRole('gym_owner');
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
                Tables\Actions\Action::make('managePermissions')
                    ->label('Manage Permissions')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->form([
                        Select::make('gym_id')
                            ->label('Select Gym')
                            ->options(function (Model $record) {
                                $user = auth()->user();
                                $userGymIds = $user->gyms()->pluck('gyms.id');

                                return $record->gyms()
                                    ->whereIn('gyms.id', $userGymIds)
                                    ->get()
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, Model $record) {
                                if ($state) {
                                    $pivot = $record->gyms()->where('gyms.id', $state)->first()?->pivot;
                                    $permissions = $pivot ? json_decode($pivot->permissions, true) : [];

                                    $set('manage_members', $permissions['manage_members'] ?? false);
                                    $set('manage_subscriptions', $permissions['manage_subscriptions'] ?? false);
                                    $set('manage_payments', $permissions['manage_payments'] ?? false);
                                    $set('manage_classes', $permissions['manage_classes'] ?? false);
                                    $set('view_reports', $permissions['view_reports'] ?? false);
                                    $set('manage_equipment', $permissions['manage_equipment'] ?? false);
                                    $set('active', $pivot ? $pivot->active : true);
                                }
                            }),

                        Section::make('Permissions')
                            ->schema([
                                Toggle::make('active')
                                    ->label('Active Status')
                                    ->helperText('Enable/disable this receptionist for the selected gym'),

                                Grid::make(2)
                                    ->schema([
                                        Toggle::make('manage_members')
                                            ->label('Manage Members')
                                            ->helperText('Add, edit, and view member profiles'),

                                        Toggle::make('manage_subscriptions')
                                            ->label('Manage Subscriptions')
                                            ->helperText('Create and modify member subscriptions'),

                                        Toggle::make('manage_payments')
                                            ->label('Manage Payments')
                                            ->helperText('Process payments and view payment history'),

                                        Toggle::make('manage_classes')
                                            ->label('Manage Classes')
                                            ->helperText('Schedule classes and manage bookings'),

                                        Toggle::make('view_reports')
                                            ->label('View Reports')
                                            ->helperText('Access gym reports and analytics'),

                                        Toggle::make('manage_equipment')
                                            ->label('Manage Equipment')
                                            ->helperText('Track and maintain gym equipment'),
                                    ]),
                            ])
                            ->visible(fn(callable $get) => $get('gym_id')),
                    ])
                    ->action(function (array $data, Model $record): void {
                        $gymId = $data['gym_id'];

                        $permissions = [
                            'manage_members' => $data['manage_members'] ?? false,
                            'manage_subscriptions' => $data['manage_subscriptions'] ?? false,
                            'manage_payments' => $data['manage_payments'] ?? false,
                            'manage_classes' => $data['manage_classes'] ?? false,
                            'view_reports' => $data['view_reports'] ?? false,
                            'manage_equipment' => $data['manage_equipment'] ?? false,
                        ];

                        $record->gyms()->updateExistingPivot($gymId, [
                            'permissions' => json_encode($permissions),
                            'active' => $data['active'] ?? true,
                        ]);

                        Notification::make()
                            ->title('Permissions Updated')
                            ->body('Receptionist permissions have been successfully updated.')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Manage Receptionist Permissions')
                    ->modalDescription('Configure what this receptionist can do in your gyms.')
                    ->modalSubmitActionLabel('Update Permissions')
                    ->visible(function (Model $record): bool {
                        $user = auth()->user();
                        $userGymIds = $user->gyms()->pluck('gyms.id');

                        return $record->gyms()
                            ->whereIn('gyms.id', $userGymIds)
                            ->exists();
                    }),

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
