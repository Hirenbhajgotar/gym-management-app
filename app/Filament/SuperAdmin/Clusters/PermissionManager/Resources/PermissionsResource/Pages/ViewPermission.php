<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionsResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\SuperAdmin\Clusters\PermissionManager\Resources\PermissionsResource;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPermission extends ViewRecord
{
    protected static string $resource = PermissionsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Permission Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Permission Name')
                                    ->weight('medium'),

                                TextEntry::make('display_name')
                                    ->label('Display Name')
                                    ->placeholder('N/A'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('guard_name')
                                    ->label('Guard Name')
                                    ->badge(),

                                TextEntry::make('category')
                                    ->label('Category')
                                    ->badge(),
                            ]),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('No description available')
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('roles_count')
                                    ->label('Assigned Roles Count')
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Assigned Roles')
                    ->schema([
                        RepeatableEntry::make('roles')
                            ->label('Roles with this Permission')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Role Name')
                                    ->weight('medium'),

                                TextEntry::make('guard_name')
                                    ->label('Guard')
                                    ->badge(),

                                TextEntry::make('users_count')
                                    ->label('Users in Role')
                                    ->badge()
                                    ->color('info'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Direct Users')
                    ->schema([
                        RepeatableEntry::make('users')
                            ->label('Users with Direct Permission')
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
