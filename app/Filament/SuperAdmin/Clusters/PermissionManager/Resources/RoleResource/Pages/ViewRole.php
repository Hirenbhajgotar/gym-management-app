<?php

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\RoleResource\Pages;

use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\RoleResource;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Role Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Role Name')
                                    ->weight('medium'),

                                TextEntry::make('guard_name')
                                    ->label('Guard Name')
                                    ->badge(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('users_count')
                                    ->label('Total Users')
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Permissions')
                    ->schema([
                        RepeatableEntry::make('permissions')
                            ->label('Assigned Permissions')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Permission Name')
                                    ->weight('medium'),

                                TextEntry::make('display_name')
                                    ->label('Display Name')
                                    ->placeholder('N/A'),

                                TextEntry::make('description')
                                    ->label('Description')
                                    ->placeholder('No description available')
                                    ->limit(100),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Assigned Users')
                    ->schema([
                        RepeatableEntry::make('users')
                            ->label('Users with this Role')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('User Name')
                                    ->weight('medium'),

                                TextEntry::make('email')
                                    ->label('Email')
                                    ->icon('heroicon-o-envelope'),

                                TextEntry::make('created_at')
                                    ->label('User Since')
                                    ->dateTime()
                                    ->since(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
