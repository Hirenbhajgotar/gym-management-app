<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources;

use App\Enums\DefaultRole;
use App\Enums\PermissionCategory;
use App\Filament\SuperAdmin\Clusters\PermissionManager;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\Pages;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\RelationManagers;
use App\Models\Permission;
use App\Models\PermissionSetting;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use ValueError;

class PermissionSettingResource extends Resource
{
    protected static ?string $model = PermissionSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $cluster = PermissionManager::class;

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()->schema([

                    Forms\Components\Select::make('role')
                        ->options(DefaultRole::class)
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Enter a unique role name (e.g., manager, trainer).'),

                    Forms\Components\Select::make('default_permissions')
                        ->multiple()
                        ->options(fn() => Permission::whereNotNull('category')
                            ->where('category', '!=', '')
                            ->orderBy('category')
                            ->orderBy('name')
                            ->get()
                            ->groupBy('category')
                            ->mapWithKeys(function ($permissions, $category) {
                                return [$category => $permissions->pluck('name', 'name')->toArray()];
                            })->toArray())
                        ->searchable()
                        ->optionsLimit(300)
                        ->searchingMessage('Searching permissions...')
                        ->noSearchResultsMessage('No permissions found.')
                        ->helperText('Select default permissions to assign to this role, grouped by category.'),


                    // Default Permissions (grouped by category)
                    // Section::make('Default Permissions')->schema(
                    //     static::buildPermissionsCheckbox(),
                    // )->collapsible(),


                    Forms\Components\Fieldset::make('Default Resources')
                        ->schema([
                            Forms\Components\Select::make('default_resources.resources')
                                ->label('Resources')
                                ->multiple()
                                ->options(PermissionCategory::class)
                                ->default([])
                                ->helperText('Select resource categories for this role.'),
                            Forms\Components\Select::make('default_resources.widgets')
                                ->label('Widgets')
                                ->multiple()
                                ->options(PermissionCategory::class)
                                ->default([])
                                ->helperText('Select widget categories for this role.'),
                            Forms\Components\Select::make('default_resources.reports')
                                ->label('Reports')
                                ->multiple()
                                ->options(PermissionCategory::class)
                                ->default([])
                                ->helperText('Select report categories for this role.'),
                            Forms\Components\Select::make('default_resources.other')
                                ->label('Other')
                                ->multiple()
                                ->options(PermissionCategory::class)
                                ->default([])
                                ->helperText('Select other categories for this role.'),
                        ])
                        ->columns(1),
                ])->columns(2)
            ]);
    }

    public static function extractPermissionsFromFormData(array $data): array
    {
        $permissions = [];

        // Get all category-based permission fields
        $categoryWisePermissions = Permission::whereNotNull('category')
            ->where('category', '!=', '')
            ->get()
            ->groupBy('category')
            ->keys()
            ->toArray();

        foreach ($categoryWisePermissions as $category) {
            $fieldName = $category . '_permissions';
            if (isset($data[$fieldName]) && is_array($data[$fieldName])) {
                $permissions = array_merge($permissions, $data[$fieldName]);
                // Remove the category-specific field from data to avoid saving it separately
                unset($data[$fieldName]);
            }
        }

        return array_values(array_unique($permissions));
    }

    private static function buildPermissionsCheckbox()
    {
        $categoryWisePermissions = Permission::whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category')
            ->toArray();

        $checkboxOptions = [];
        foreach ($categoryWisePermissions as $category => $permissions) {
            try {
                $permissionCategory = PermissionCategory::from($category);
                $categoryName = $permissionCategory->getLabel();
                $categoryIcon = $permissionCategory->getIcon();
            } catch (ValueError) {
                $categoryName = ucfirst(str_replace('_', ' ', $category));
                $categoryIcon = 'heroicon-o-folder';
            }

            $options = [];
            $descriptions = [];

            foreach ($permissions as $permission) {
                $options[$permission['name']] = $permission['display_name'] ?? ucfirst(str_replace('_', ' ', $permission['name']));
                $descriptions[$permission['name']] = $permission['description'] ?? '';
            }

            // dd($options);

            $checkboxOptions[] = CheckboxList::make($category . '_permissions')
                ->label($categoryName . ' Permissions')
                ->options($options)
                ->columns(3)
                ->descriptions($descriptions)
                ->gridDirection('row')
                ->bulkToggleable()
                ->afterStateHydrated(function (CheckboxList $component, ?Model $record) use ($category) {
                    if ($record && $record->default_permissions) {
                        // Get permissions for this category from the stored default_permissions
                        $categoryPermissions = Permission::where('category', $category)
                            ->pluck('name')
                            ->toArray();

                        $selectedPermissions = array_intersect($record->default_permissions, $categoryPermissions);
                        $component->state($selectedPermissions);
                    }
                });
        }
        return $checkboxOptions;
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
                    ->getStateUsing(fn($record) => implode(', ', $record->default_permissions ?? []))
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
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListPermissionSettings::route('/'),
            'create' => Pages\CreatePermissionSetting::route('/create'),
            'edit' => Pages\EditPermissionSetting::route('/{record}/edit'),
            'view' => Pages\ViewPermissionSetting::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }
}
