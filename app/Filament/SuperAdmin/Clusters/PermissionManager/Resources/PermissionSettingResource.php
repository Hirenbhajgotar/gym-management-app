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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
