<?php

namespace App\Filament\Admin\Pages;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Collection;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;

class LeadKanbanBoard extends KanbanBoard
{
    protected static ?string $navigationIcon = 'phosphor-columns';

    protected static ?string $navigationGroup = 'CRM';

    protected static string $model = Lead::class;

    protected static string $statusEnum = LeadStatus::class;

    protected static string $recordTitleAttribute = 'name';

    protected static string $recordStatusAttribute = 'status';

    public bool $disableEditModal = true;

    protected function records(): Collection
    {
        return Lead::with(['creator'])
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->model(Lead::class)
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')->required(),
                    \Filament\Forms\Components\TextInput::make('email')->email(),
                    \Filament\Forms\Components\TextInput::make('phone'),
                    \Filament\Forms\Components\Select::make('source')->options(LeadSource::class),
                    \Filament\Forms\Components\Textarea::make('interest')->columnSpanFull(),
                    \Filament\Forms\Components\Textarea::make('notes')->columnSpanFull(),
                ]),
        ];
    }

    public function getCardActions(): array
    {
        return [
            Action::make('add_follow_up')
                ->label('Add Follow-up')
                ->modalHeading('Add Follow-up')
                ->size('sm')
                ->form([
                    Textarea::make('notes')->required(),
                    DatePicker::make('next_follow_up_date'),
                    Select::make('status')->options(LeadStatus::class)->required(),
                ])
                ->action(function (Lead $record, array $data) {
                    $record->followUps()->create([
                        'notes' => $data['notes'],
                        'next_follow_up_date' => $data['next_follow_up_date'],
                        'user_id' => auth()->id(),
                    ]);
                    $record->update([
                        'status' => $data['status'],
                        'last_follow_up_date' => now(),
                    ]);
                })
                ->extraAttributes([
                    'wire:click.stop' => '',
                ]),

            Action::make('convert_to_member')
                ->label('Convert')
                ->color('success')
                ->size('sm')
                ->action(function (Lead $record) {
                    $record->update(['status' => LeadStatus::CONVERTED]);
                })
                ->requiresConfirmation()
                ->extraAttributes([
                    'wire:click.stop' => '',
                ]),

            Action::make('archive')
                ->label('Archive')
                ->color('danger')
                ->size('sm')
                ->action(function (Lead $record) {
                    $record->update(['status' => LeadStatus::LOST]);
                })
                ->requiresConfirmation()
                ->extraAttributes([
                    'wire:click.stop' => '',
                ]),
        ];
    }
}
