<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource\Pages;

use App\Enums\PermissionCategory;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPermissionSetting extends ViewRecord
{
    protected static string $resource = PermissionSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Permission Setting Details')
                    ->schema([
                        TextEntry::make('role')
                            ->label('Role Name'),

                        TextEntry::make('default_permissions')
                            ->label('Default Permissions')
                            ->getStateUsing(fn ($record) => implode(', ', $record->default_permissions ?? [])),

                        Fieldset::make('Default Resources')
                            ->schema([
                                TextEntry::make('resources')
                                    ->label('Resources')
                                    ->getStateUsing(fn ($record) => implode(', ', array_map(fn ($cat) => PermissionCategory::tryFrom($cat)->getLabel() ?? $cat, $record->default_resources['resources'] ?? []))),
                                TextEntry::make('widgets')
                                    ->label('Widgets')
                                    ->getStateUsing(fn ($record) => implode(', ', array_map(fn ($cat) => PermissionCategory::tryFrom($cat)->getLabel() ?? $cat, $record->default_resources['widgets'] ?? []))),
                                TextEntry::make('reports')
                                    ->label('reports')
                                    ->getStateUsing(fn ($record) => implode(', ', array_map(fn ($cat) => PermissionCategory::tryFrom($cat)->getLabel() ?? $cat, $record->default_resources['reports'] ?? []))),
                                TextEntry::make('other')
                                    ->label('Other')
                                    ->getStateUsing(fn ($record) => implode(', ', array_map(fn ($cat) => PermissionCategory::tryFrom($cat)->getLabel() ?? $cat, $record->default_resources['other'] ?? []))),
                            ]),
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime('d M Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }
}
