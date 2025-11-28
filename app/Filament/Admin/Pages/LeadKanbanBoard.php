<?php

namespace App\Filament\Admin\Pages;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTag;
use App\Filament\Admin\Resources\LeadResource;
use App\Filament\Admin\Resources\LeadResource\RelationManagers\FollowUpsRelationManager;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Notifications\Member\WelcomeNotification;
use App\Services\LeadConversionService;
use App\Traits\HasTenantAccess;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as FacadesNotification;
use libphonenumber\PhoneNumberType;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class LeadKanbanBoard extends KanbanBoard implements HasActions, HasForms
{
    use HasTenantAccess, InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'phosphor-columns';

    protected static ?string $navigationGroup = 'CRM';

    protected static string $model = Lead::class;

    protected static string $statusEnum = LeadStatus::class;

    protected static string $recordTitleAttribute = 'name';

    protected static string $recordStatusAttribute = 'status';

    protected function records(): Collection
    {
        return Lead::with(['creator', 'membershipPlan'])->ordered()->get();
    }

    public function onSortChanged(int|string $recordId, string $status, array $orderedIds): void
    {
        Lead::setNewOrder($orderedIds);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->model(Lead::class)
                ->form([
                    \Filament\Forms\Components\Section::make()
                        ->schema([
                            \Filament\Forms\Components\Hidden::make('gym_id')
                                ->default($this->getGymId()),

                            \Filament\Forms\Components\Hidden::make('created_by')
                                ->default(auth()->user()->id),

                            \Filament\Forms\Components\TextInput::make('name')
                                ->required(),

                            \Filament\Forms\Components\TextInput::make('email')
                                ->required()
                                ->email(),

                            PhoneInput::make('phone')
                                ->required()
                                ->defaultCountry('IN')
                                ->validateFor(
                                    type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE], // default: null
                                ),

                            \Filament\Forms\Components\Select::make('source')
                                ->options(LeadSource::class),

                            \Filament\Forms\Components\TagsInput::make('interest')
                                ->splitKeys(['Tab', ','])
                                ->placeholder(
                                    'Add tags (e.g., High Priority, Referral)',
                                )
                                ->suggestions(
                                    collect(LeadTag::cases())
                                        ->map(fn (LeadTag $tag) => $tag->getLabel())
                                        ->toArray(),
                                )
                                ->columnSpanFull(),

                            \Filament\Forms\Components\Textarea::make('notes')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ]),
        ];
    }

    public function addFollowUpAction(): Action
    {
        return Action::make('addFollowUp')
            ->label('Add Follow-Up')
            ->icon('heroicon-m-plus-circle')
            ->color('primary')
            ->form(
                app(FollowUpsRelationManager::class)->getFormSchema(),
            )
            ->action(function (array $data, array $arguments): void {
                $record = Lead::find($arguments['id']);

                if (! $record) {
                    Notification::make()
                        ->title('Error')
                        ->body('Lead not found')
                        ->danger()
                        ->send();

                    return;
                }

                FollowUp::create([
                    'gym_id' => $this->getGym()->id,
                    'lead_id' => $record->id,
                    'type' => $data['type'],
                    'scheduled_date' => $data['scheduled_date'],
                    'notes' => $data['notes'] ?? null,
                    'status' => $data['status'] ?? 'pending',
                    'user_id' => $data['user_id'] ?? null,
                ]);

                $record->update([
                    'last_follow_up_date' => now(),
                ]);

                Notification::make()
                    ->title('Follow-up added successfully')
                    ->body("Follow-up scheduled for {$data['scheduled_date']}")
                    ->success()
                    ->send();
            })
            ->modalHeading('Add Follow-Up')
            ->modalSubmitActionLabel('Schedule Follow-Up')
            ->modalWidth('2xl');
    }

    protected function convertToMemberAction(): Action
    {
        return Action::make('convertToMember')
            ->label('Convert to Member')
            ->icon('heroicon-m-user-plus')
            ->color('primary')
            ->modalHeading('Convert Lead to Member')
            ->modalSubmitActionLabel('Convert to Member')
            ->modalWidth('2xl')
            ->form(
                LeadResource::getConvertToMemberFormSchema(),
            )
            ->modalSubmitActionLabel('Convert to Member')
            ->action(function (array $data, array $arguments): void {
                $record = Lead::find($arguments['id']);

                if (! $record) {
                    Notification::make()
                        ->title('Error')
                        ->body('Lead not found')
                        ->danger()
                        ->send();

                    return;
                }

                $member = app(LeadConversionService::class)->convert($record, $data);

                FacadesNotification::send($member, new WelcomeNotification($member));

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
                $record = Lead::find($arguments['id']);

                return $record ? "Are you sure you want to archive '{$record->name}'? The lead will be moved to archived status." : 'Lead not found';
            })
            ->modalSubmitActionLabel('Archive Lead')
            ->action(function (array $arguments): void {
                $record = Lead::find($arguments['id']);

                if (! $record) {
                    Notification::make()
                        ->title('Error')
                        ->body('Lead not found')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update([
                    'status' => LeadStatus::LOST,
                    'updated_by' => auth()->id(),
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

    public function onStatusChanged(int|string $recordId, string $status, array $fromOrderedIds, array $toOrderedIds): void
    {
        $currentLead = Lead::find($recordId);

        if (! $currentLead) {
            Notification::make()
                ->title('Error')
                ->body('Lead not found')
                ->danger()
                ->send();

            // Refresh the kanban board to revert the change
            $this->dispatch('refresh');

            return;
        }

        if (in_array($currentLead->status, [LeadStatus::CONVERTED, LeadStatus::LOST]) && $currentLead->status->value !== $status) {
            // Prevent direct drag-and-drop to 'Converted' column
            Notification::make()
                ->title('Action Not Allowed')
                ->body("You can't update a Lead from '{$currentLead->status->getLabel()}' status.")
                ->warning()
                ->send();

            // Refresh the kanban board to revert the change
            $this->dispatch('refresh');

            return;
        }

        if ($status === LeadStatus::CONVERTED->value) {
            Notification::make()
                ->title('Action Not Allowed')
                ->body("You can't move a Lead directly to " . LeadStatus::CONVERTED->getLabel() . " status. Please use the 'Convert to Member' action.")
                ->warning()
                ->send();

            // Refresh the kanban board to revert the change
            $this->dispatch('refresh');

            return;
        }

        $currentLead->update(['status' => $status]);
        Lead::setNewOrder($toOrderedIds);
    }
}
