<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources;

use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Clusters\PermissionManager;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\RoleResource\Pages\CreateRole;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\RoleResource\Pages\EditRole;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\RoleResource\Pages\ListRoles;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\RoleResource\Pages\ViewRole;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class RoleResource extends BaseResource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $cluster = PermissionManager::class;

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Role Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Role Name')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->placeholder('Enter role name')
                                    ->helperText('The name of the role (e.g., Admin, Editor, User)'),

                                TextInput::make('guard_name')
                                    ->label('Guard Name')
                                    ->default('web')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('web')
                                    ->helperText('The guard name for this role'),
                            ]),
                    ]),

                Section::make('Permissions')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('Assign Permissions')
                            ->relationship(titleAttribute: 'name')
                            // ->options(function () {
                            //     return Permission::all()->groupBy(function ($permission) {
                            //         return ucfirst(explode('.', $permission->name)[0] ?? 'General');
                            //     })->map(function ($group) {
                            //         return $group->pluck('name', 'name');
                            //     });
                            // })
                            ->columns(3)
                            ->gridDirection('row')
                            ->helperText('Select permissions to assign to this role')
                            ->searchable()
                            ->bulkToggleable(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Role Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->sortable(),

                // TagsColumn::make('permissions.name')
                //     ->label('Permissions')
                //     ->limit(3)
                //     ->limitedRemainingText(
                //         isSeparate: true,
                //     ),

                // TextColumn::make('users_count')
                //     ->label('Users Count')
                //     ->counts('users')
                //     ->sortable()
                //     ->badge()
                //     ->color('success'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Role $record) {
                        // Detach all permissions and users before deletion
                        $record->permissions()->detach();
                        $record->users()->detach();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                $record->permissions()->detach();
                                $record->users()->detach();
                            }
                        }),
                ]),
            ])->defaultSort('name');
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
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['permissions']);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }
}
