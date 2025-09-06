<?php

namespace App\Filament\Admin\Pages;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Filament\Admin\Resources\LeadResource\Pages\CreateLead;
use App\Filament\Admin\Resources\LeadResource\RelationManagers\FollowUpsRelationManager;
use App\Models\FollowUp;
use App\Models\GymMember;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;

class LeadKanbanBoard extends KanbanBoard implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-columns';

    protected static ?string $navigationGroup = 'CRM';

    protected static string $model = Lead::class;

    protected static string $statusEnum = LeadStatus::class;

    protected static string $recordTitleAttribute = 'name';

    protected static string $recordStatusAttribute = 'status';

    // public bool $disableEditModal = true;

    protected function records(): Collection
    {
        return Lead::with(['creator'])->withCount('followUps')->get();
    }

    // public function getRecordStats(): array
    // {
    //     return [
    //         'total_leads' => Lead::where('status', '!=', 'archived')->count(),
    //         'pending_followups' => FollowUp::where('status', 'pending')->count(),
    //         'converted_this_month' => Lead::where('status', 'converted')
    //             ->whereMonth('converted_at', now()->month)
    //             ->count(),
    //     ];
    // }

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

    public function addFollowUpAction(): Action
    {
        return Action::make('add_follow_up')
            ->label('Add Follow-Up')
            // ->icon('heroicon-m-plus-circle')
            ->color('info')
            ->form(
                app(FollowUpsRelationManager::class)->getFormSchema(),
            )
            ->action(function (array $data, array $arguments): void {

                $record = Lead::find($arguments['record']);

                if (! $record) {
                    Notification::make()
                        ->title('Error')
                        ->body('Lead not found')
                        ->danger()
                        ->send();

                    return;
                }

                FollowUp::create([
                    'lead_id' => $record->id,
                    'type' => $data['type'],
                    'scheduled_date' => $data['scheduled_date'],
                    'notes' => $data['notes'] ?? null,
                    'status' => 'pending',
                    'created_by' => auth()->id(),
                ]);

                Notification::make()
                    ->title('Follow-up added successfully')
                    ->body("Follow-up scheduled for {$data['scheduled_date']}")
                    ->success()
                    ->send();

                // Refresh the kanban board
                // $this->dispatch('refresh');
            })
            ->modalHeading('Add Follow-Up')
            ->modalSubmitActionLabel('Schedule Follow-Up')
            ->modalWidth('2xl');
    }

    protected function convertToMemberAction(): Action
    {
        return Action::make('convert_to_member')
            ->label('Convert to Member')
            ->icon('heroicon-m-user-plus')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Convert Lead to Member')
            ->modalDescription(function (array $arguments) {
                $record = Lead::find($arguments['record']);

                return $record ? "Are you sure you want to convert '{$record->name}' to a member? This action cannot be undone." : 'Lead not found';
            })
            ->modalSubmitActionLabel('Convert to Member')
            ->action(function (array $arguments): void {
                Log::info('Executing convert_to_member action', ['arguments' => $arguments]);

                $record = Lead::find($arguments['record']);

                if (! $record) {
                    Notification::make()
                        ->title('Error')
                        ->body('Lead not found')
                        ->danger()
                        ->send();

                    return;
                }

                // Create member from lead
                $member = GymMember::create([
                    'name' => $record->name,
                    'email' => $record->email,
                    'phone' => $record->phone,
                    'source' => 'lead_conversion',
                    'converted_from_lead_id' => $record->id,
                    'joined_at' => now(),
                ]);

                // Update lead status to converted
                $record->update([
                    'status' => 'converted',
                    'converted_at' => now(),
                    'converted_to_member_id' => $member->id,
                ]);

                Notification::make()
                    ->title('Lead converted successfully')
                    ->body("{$record->name} has been converted to a member")
                    ->success()
                    ->send();

                // Refresh the kanban board
                $this->dispatch('refresh');
            });
    }

    protected function archiveAction(): Action
    {
        return Action::make('archive')
            ->label('Archive')
            ->icon('heroicon-m-archive-box')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Archive Lead')
            ->modalDescription(function (array $arguments) {
                $record = Lead::find($arguments['record']);

                return $record ? "Are you sure you want to archive '{$record->name}'? The lead will be moved to archived status." : 'Lead not found';
            })
            ->modalSubmitActionLabel('Archive Lead')
            ->action(function (array $arguments): void {
                Log::info('Executing archive action', ['arguments' => $arguments]);

                $record = Lead::find($arguments['record']);

                if (! $record) {
                    Notification::make()
                        ->title('Error')
                        ->body('Lead not found')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update([
                    'status' => 'archived',
                    'archived_at' => now(),
                    'archived_by' => auth()->id(),
                ]);

                Notification::make()
                    ->title('Lead archived')
                    ->body("{$record->name} has been archived")
                    ->success()
                    ->send();

                // Refresh the kanban board
                $this->dispatch('refresh');
            });
    }
}
