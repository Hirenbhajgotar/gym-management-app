<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Fieldset;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\Pages\ListPermissionSettings;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\Pages\CreatePermissionSetting;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\Pages\EditPermissionSetting;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\Pages\ViewPermissionSetting;
use App\Enums\DefaultRole;
use App\Enums\PermissionCategory;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Clusters\PermissionManager;
use App\Jobs\ImportDefaultPermissionsJob;
use App\Models\Permission;
use App\Models\PermissionSetting;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PermissionSettingResource extends BaseResource
{
    protected static ?string $model = PermissionSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $cluster = PermissionManager::class;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('role')
                    ->options(DefaultRole::class)
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Enter a unique role name (e.g., manager, trainer).'),

                Select::make('default_permissions')
                    ->multiple()
                    ->options(fn () => Permission::query()->whereNotNull('category')
                        ->where('category', '!=', '')
                        ->orderBy('category')
                        ->orderBy('name')
                        ->get()
                        ->groupBy('category')
                        ->mapWithKeys(function ($permissions, $category) {
                            return [$category => $permissions->pluck('name', 'name')->toArray()];
                        })->all())
                    ->searchable()
                    ->optionsLimit(300)
                    ->searchingMessage('Searching permissions...')
                    ->noSearchResultsMessage('No permissions found.')
                    ->helperText('Select default permissions to assign to this role, grouped by category.'),

                Fieldset::make('Default Resources')
                    ->schema([
                        Select::make('default_resources.resources')
                            ->label('Resources')
                            ->multiple()
                            ->options(PermissionCategory::class)
                            ->default([])
                            ->helperText('Select resource categories for this role.'),
                        Select::make('default_resources.widgets')
                            ->label('Widgets')
                            ->multiple()
                            ->options(PermissionCategory::class)
                            ->default([])
                            ->helperText('Select widget categories for this role.'),
                        Select::make('default_resources.reports')
                            ->label('Reports')
                            ->multiple()
                            ->options(PermissionCategory::class)
                            ->default([])
                            ->helperText('Select report categories for this role.'),
                        Select::make('default_resources.other')
                            ->label('Other')
                            ->multiple()
                            ->options(PermissionCategory::class)
                            ->default([])
                            ->helperText('Select other categories for this role.'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('role')
                    ->searchable()
                    ->sortable()
                    ->label('Role Name'),

                TextColumn::make('default_resources.resources')
                    ->label('Resources')
                    ->badge(),
                TextColumn::make('default_resources.widgets')
                    ->label('Widgets')
                    ->badge(),
                TextColumn::make('default_resources.reports')
                    ->label('Reports')
                    ->badge(),
                TextColumn::make('default_resources.other')
                    ->label('Other')
                    ->badge(),

                TextColumn::make('default_permissions')
                    ->label('Default Permissions')
                    ->getStateUsing(fn ($record) => implode(', ', $record->default_permissions ?? []))
                    ->limit(50),

                TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->label('Created At'),
                TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->label('Updated At'),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),

                    Action::make('importPermissions')
                        ->label('Import Permissions')
                        ->icon('heroicon-o-cloud-arrow-down')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading(fn ($record) => "Import Permissions for {$record->role->value}")
                        ->modalDescription('This will import default permissions for this specific role.')
                        ->action(function ($record) {
                            dispatch(new ImportDefaultPermissionsJob($record->role->value, auth()->id()));

                            Notification::make()
                                ->title('Permissions Import Queued')
                                ->body("Default permissions are being imported for role: {$record->role->value}")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListPermissionSettings::route('/'),
            'create' => CreatePermissionSetting::route('/create'),
            'edit' => EditPermissionSetting::route('/{record}/edit'),
            'view' => ViewPermissionSetting::route('/{record}'),
        ];
    }
}
