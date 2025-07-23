<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources;

use App\Enums\PermissionCategory;
use App\Filament\SuperAdmin\Clusters\PermissionManager;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionsResource\Pages;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionsResource\RelationManagers;
use App\Models\Permission as ModelsPermission;
use App\Models\Permissions;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Permission;

class PermissionsResource extends Resource
{
    protected static ?string $model = ModelsPermission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $cluster = PermissionManager::class;

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Permission Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Permission Name')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->placeholder('e.g., create_user, edit_post')
                                    ->helperText('Use snake_case format (e.g., create_user, view_reports)')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $set('display_name', ucwords(str_replace(['_', '-'], ' ', $state)));
                                    }),

                                TextInput::make('display_name')
                                    ->label('Display Name')
                                    ->maxLength(255)
                                    ->placeholder('e.g., Create User, Edit Post')
                                    ->helperText('Human-readable name for this permission'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('guard_name')
                                    ->label('Guard Name')
                                    ->default('web')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('web')
                                    ->helperText('The guard name for this permission'),

                                Select::make('category')
                                    ->label('Category')
                                    ->options(PermissionCategory::class)
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Categorize this permission for better organization'),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->placeholder('Describe what this permission allows users to do')
                            ->helperText('Optional description of what this permission grants')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Permission Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('display_name')
                    ->label('Display Name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->sortable(),

                TextColumn::make('roles_count')
                    ->label('Assigned Roles')
                    ->counts('roles')
                    ->badge(),

                // TextColumn::make('users_count')
                //     ->label('Direct Users')
                //     ->counts('users')
                //     ->sortable()
                //     ->badge()
                //     ->color('success')
                //     ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options(PermissionCategory::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Permission $record) {
                        // Detach all roles and users before deletion
                        $record->roles()->detach();
                        $record->users()->detach();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                $record->roles()->detach();
                                $record->users()->detach();
                            }
                        }),
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
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermissions::route('/create'),
            'view' => Pages\ViewPermission::route('/{record}'),
            'edit' => Pages\EditPermissions::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles', 'users']);
    }

    public static function canAccess(): bool
    {
        // Check if user has gym_owner role for this specific gym
        return auth()->user()->hasRole('super_admin');
    }
}
