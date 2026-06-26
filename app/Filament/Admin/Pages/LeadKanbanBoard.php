<?php

namespace App\Filament\Admin\Pages;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTag;
use App\Filament\Admin\Resources\LeadResource;
use App\Filament\Admin\Resources\LeadResource\RelationManagers\FollowUpsRelationManager;
use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendNotificationJob;
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
use Illuminate\Support\Facades\Bus;
use libphonenumber\PhoneNumberType;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class LeadKanbanBoard extends KanbanBoard implements HasActions, HasForms
{
    use HasTenantAccess, InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = 'CRM';

    protected static string $model = Lead::class;

    protected static string $statusEnum = LeadStatus::class;

    // Authorization handled by Policies
    // public static function canAccess(): bool
    // {
    //     $user = auth()->user();

    //     if (! $user) {
    //         return false;
    //     }

    //     if ($user->hasAnyRole([
    //         DefaultRole::SUPER_ADMIN->value,
    //         DefaultRole::ADMIN->value,
    //         DefaultRole::GYM_OWNER->value,
    //     ])) {
    //         return true;
    //     }

    //     return $user->can('view_any_lead');
    // }

    protected static string $recordTitleAttribute = 'name';

    protected static string $recordStatusAttribute = 'status';

    protected function records(): Collection
    {
        return Lead::with(['creator', 'membershipPlan'])
            ->orderBy('id', 'desc')
            ->get();
    }

    public function onSortChanged(int|string $recordId, string $status, array $orderedIds): void
    {
        Lead::setNewOrder($orderedIds);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->model(Lead::class)
                ->visible(fn () => auth()->user()?->can('create_lead'))
                ->authorize(fn () => auth()->user()?->can('create_lead'))
                ->form([
                    Section::make()
                        ->schema([
                            Hidden::make('gym_id')
                                ->default($this->getGymId()),

                            Hidden::make('created_by')
                                ->default(auth()->user()->id),

                            TextInput::make('name')
                                ->required(),

                            TextInput::make('email')
                                ->required()
                                ->email(),

                            PhoneInput::make('phone')
                                ->required()
                                ->defaultCountry('IN')
                                ->validateFor(
                                    type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE], // default: null
                                ),

                            Select::make('source')
                                ->options(LeadSource::class),

                            TagsInput::make('interest')
                                ->splitKeys(['Tab', ','])
                                ->placeholder(
                                    'Add tags (e.g., High Priority, Referral)',
                                )
                                ->suggestions(
                                    collect(LeadTag::cases())
                                        ->map(fn (LeadTag $tag) => $tag->getLabel())
                                        ->all(),
                                )
                                ->columnSpanFull(),

                            Textarea::make('notes')
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
                FollowUpsRelationManager::getFollowUpFormSchema(),
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
            ->modalWidth('5xl')
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

                $member = resolve(LeadConversionService::class)->convert($record, $data);

                // Send welcome notification and generate invoice if payment exists
                if ($member->latestPayment) {
                    Bus::chain([
                        new GenerateInvoiceJob($member->latestPayment),
                        new SendNotificationJob($member, new WelcomeNotification($member)),
                    ])->dispatch();
                } else {
                    dispatch(new SendNotificationJob($member, new WelcomeNotification($member)));
                }

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
            ->label('Lost')
            ->icon('heroicon-m-archive-box')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Lost Lead')
            ->modalDescription(function (array $arguments) {
                $record = Lead::find($arguments['id']);

                return $record ? "Are you sure you want to mark '{$record->name}' as lost? The lead will be moved to lost status." : 'Lead not found';
            })
            ->modalSubmitActionLabel('Lost Lead')
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
                    ->title('Lead lost')
                    ->body("{$record->name} has been lost")
                    ->success()
                    ->send();

                // Refresh the kanban board
                $this->dispatch('refresh');
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete Lead')
            ->modalDescription(function (array $arguments) {
                $record = Lead::find($arguments['id']);

                return $record ? "Are you sure you want to delete '{$record->name}'? This action cannot be undone." : 'Lead not found';
            })
            ->modalSubmitActionLabel('Delete Lead')
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

                $record->delete();

                Notification::make()
                    ->title('Lead deleted')
                    ->body("{$record->name} has been deleted")
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
