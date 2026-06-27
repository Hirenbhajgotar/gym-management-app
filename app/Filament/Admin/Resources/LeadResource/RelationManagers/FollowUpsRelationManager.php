<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LeadResource\RelationManagers;

use App\Enums\FollowUpStatus;
use App\Enums\InteractionType;
use App\Models\FollowUp;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\LeadFollowUpNotification;
use App\Traits\HasTenantAccess;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class FollowUpsRelationManager extends RelationManager
{
    use HasTenantAccess;

    protected static string $relationship = 'followUps';

    public function form(Form $form): Form
    {
        return $form
            ->schema(self::getFollowUpFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('scheduled_date')
            ->columns($this->getTableColumns())
            ->filters($this->getTableFilters())
            ->headerActions($this->getHeaderActions())
            ->actions($this->getTableActions())
            ->bulkActions($this->getBulkActions())
            ->defaultSort('scheduled_date', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $this->sortFollowUps($query));
    }

    /**
     * Get form schema for follow-up creation/editing
     */
    public static function getFollowUpFormSchema(): array
    {
        /**
         * @var Gym $currentGym
         */
        $currentGym = Filament::getTenant();

        return [
            Hidden::make('gym_id')
                ->default($currentGym?->id ?? null),

            Section::make('Follow-Up Details')
                ->icon('heroicon-o-calendar')
                ->description('Schedule and track follow-ups with leads')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('type')
                            ->label('Interaction Type')
                            ->options(InteractionType::class)
                            ->required()
                            ->searchable()
                            ->placeholder('Select interaction type')
                            ->helperText('Choose how you will interact with this lead'),

                        Select::make('status')
                            ->options(FollowUpStatus::class)
                            ->required()
                            ->default(FollowUpStatus::PENDING->value)
                            ->helperText('Current status of this follow-up'),

                        DateTimePicker::make('scheduled_date')
                            ->label('Scheduled Date & Time')
                            ->required()
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->seconds(false)
                            ->default(now()->addDay())
                            ->minDate(now())
                            ->helperText('When should this follow-up happen?'),

                        Select::make('user_id')
                            ->label('Assigned To')
                            ->options(fn () => Gym::getCurrentTenantUsers()
                                ->pluck('users.name', 'users.id')
                                ->toArray())
                            ->default(auth()->id())
                            ->required()
                            ->searchable()
                            ->placeholder('Select staff member')
                            ->helperText('Who is responsible for this follow-up?'),
                    ]),

                    RichEditor::make('notes')
                        ->label('Follow-Up Notes')
                        ->placeholder('Add any relevant notes or context for this follow-up...')
                        ->columnSpanFull()
                        ->maxLength(65535)
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'bulletList',
                            'orderedList',
                        ]),
                ])
                ->columns(2),
        ];
    }

    /**
     * Get form schema for follow-up creation/editing (deprecated)
     */
    protected function getFormSchema(): array
    {
        return self::getFollowUpFormSchema();
    }

    /**
     * Get table columns configuration
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('type')
                ->label('Type')
                ->badge()
                ->sortable()
                ->searchable(),

            TextColumn::make('scheduled_date')
                ->label('Scheduled')
                ->dateTime('M d, Y h:i A')
                ->sortable()
                ->badge()
                ->color(fn (FollowUp $record) => $this->getScheduleDateColor($record)),

            TextColumn::make('status')
                ->badge()
                ->sortable()
                ->searchable(),

            TextColumn::make('user.name')
                ->label('Assigned To')
                ->sortable()
                ->searchable()
                ->icon('heroicon-o-user')
                ->iconColor('primary'),

            TextColumn::make('created_at')
                ->label('Created')
                ->dateTime('M d, Y')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * Get table filters configuration
     */
    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('status')
                ->options(FollowUpStatus::class)
                ->placeholder('All Statuses')
                ->label('Status'),

            SelectFilter::make('type')
                ->options(InteractionType::class)
                ->placeholder('All Types')
                ->label('Interaction Type'),

            SelectFilter::make('user_id')
                ->options(fn () => $this->getTenantUsers())
                ->placeholder('All Users')
                ->label('Assigned To'),

            Filter::make('overdue')
                ->label('Overdue')
                ->query(fn (Builder $query) => $query
                    ->where('scheduled_date', '<', now())
                    ->where('status', FollowUpStatus::PENDING->value)
                )
                ->toggle(),

            Filter::make('upcoming')
                ->label('Upcoming (Next 7 Days)')
                ->query(fn (Builder $query) => $query
                    ->whereBetween('scheduled_date', [now(), now()->addDays(7)])
                    ->where('status', FollowUpStatus::PENDING->value)
                )
                ->toggle(),
        ];
    }

    /**
     * Get header actions configuration
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->modalHeading('Schedule New Follow-Up')
                ->successNotificationTitle('Follow-Up Scheduled')
                ->after(fn (FollowUp $followUp) => $this->handleFollowUpCreated($followUp)),
        ];
    }

    /**
     * Get table actions configuration
     */
    protected function getTableActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary')
                    ->modalHeading(fn (FollowUp $record) => "Edit Follow-Up - {$record->type->getLabel()}")
                    ->successNotificationTitle('Follow-Up Updated')
                    ->after(fn (FollowUp $followUp) => $this->handleFollowUpUpdated($followUp)),

                Action::make('complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (FollowUp $record) => $record->status === FollowUpStatus::PENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Completed')
                    ->modalDescription('Are you sure you want to mark this follow-up as completed?')
                    ->modalSubmitActionLabel('Yes, Complete It')
                    ->action(fn (FollowUp $record) => $this->markAsCompleted($record)),

                Action::make('miss')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (FollowUp $record) => $record->status === FollowUpStatus::PENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Missed')
                    ->modalDescription('Are you sure you want to mark this follow-up as missed?')
                    ->modalSubmitActionLabel('Yes, Mark as Missed')
                    ->action(fn (FollowUp $record) => $this->markAsMissed($record)),

                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading('Delete Follow-Up')
                    ->modalDescription('Are you sure you want to delete this follow-up? This action cannot be undone.')
                    ->successNotificationTitle('Follow-Up Deleted'),
            ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->size('sm')
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Get bulk actions configuration
     */
    protected function getBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(fn ($records) => $this->bulkMarkAsCompleted($records)),

                DeleteBulkAction::make()
                    ->modalHeading('Delete Follow-Ups')
                    ->modalDescription('Are you sure you want to delete the selected follow-ups?'),
            ]),
        ];
    }

    /**
     * Get tenant users for assignment dropdown
     */
    protected function getTenantUsers(): array
    {
        return Gym::getCurrentTenantUsers()
            ->pluck('users.name', 'users.id')
            ->toArray();
    }

    /**
     * Handle post-creation logic for follow-ups
     */
    protected function handleFollowUpCreated(FollowUp $followUp): void
    {
        // Notify assigned user
        $this->sendFollowUpNotification($followUp);

        // Send success notification to creator
        $this->sendNotification(
            'Follow-Up Scheduled Successfully',
            "Follow-up has been scheduled for {$followUp->scheduled_date->format('M d, Y h:i A')}"
        );
    }

    /**
     * Handle post-update logic for follow-ups
     */
    protected function handleFollowUpUpdated(FollowUp $followUp): void
    {
        // Notify assigned user if assignment changed
        if ($followUp->wasChanged('user_id')) {
            $this->sendFollowUpNotification($followUp);
        }

        $this->sendNotification(
            'Follow-Up Updated',
            'The follow-up has been updated successfully.'
        );
    }

    /**
     * Send notification to assigned user about the follow-up
     */
    protected function sendFollowUpNotification(FollowUp $followUp): void
    {
        try {
            $followUp->user->notify(new LeadFollowUpNotification($followUp));
        } catch (\Exception $e) {
            Log::error('Failed to send follow-up notification', [
                'follow_up_id' => $followUp->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark a follow-up as completed
     */
    protected function markAsCompleted(FollowUp $record): void
    {
        $record->update(['status' => FollowUpStatus::COMPLETED]);

        $this->sendNotification(
            'Follow-Up Completed',
            'The follow-up has been marked as completed.'
        );
    }

    /**
     * Mark a follow-up as missed
     */
    protected function markAsMissed(FollowUp $record): void
    {
        $record->update(['status' => FollowUpStatus::MISSED]);

        $this->sendNotification(
            'Follow-Up Marked as Missed',
            'The follow-up has been marked as missed.',
            'warning'
        );
    }

    /**
     * Bulk mark follow-ups as completed
     */
    protected function bulkMarkAsCompleted($records): void
    {
        $count = $records->count();

        foreach ($records as $record) {
            $record->update(['status' => FollowUpStatus::COMPLETED]);
        }

        $this->sendNotification(
            'Follow-Ups Completed',
            "{$count} follow-up(s) have been marked as completed."
        );
    }

    /**
     * Get color for scheduled date based on status and timing
     */
    protected function getScheduleDateColor(FollowUp $record): string
    {
        if ($record->status === FollowUpStatus::COMPLETED) {
            return 'success';
        }

        if ($record->status === FollowUpStatus::MISSED) {
            return 'danger';
        }

        // Pending status - check if overdue
        if ($record->scheduled_date->isPast()) {
            return 'danger';
        }

        // Within next 24 hours
        if ($record->scheduled_date->isBefore(now()->addDay())) {
            return 'warning';
        }

        return 'gray';
    }

    /**
     * Sort follow-ups by priority (overdue first, then by date)
     */
    protected function sortFollowUps(Builder $query): Builder
    {
        return $query
            ->orderByRaw("
                CASE 
                    WHEN status = 'pending' AND scheduled_date < NOW() THEN 0
                    WHEN status = 'pending' THEN 1
                    WHEN status = 'completed' THEN 2
                    ELSE 3
                END
            ")
            ->oldest('scheduled_date');
    }

    /**
     * Send success notification to current user
     */
    protected function sendNotification(string $title, string $body, string $type = 'success'): void
    {
        /** @var User $recipient */
        $recipient = auth()->user();

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->sendToDatabase($recipient);

        // Set notification type
        match ($type) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
            default => $notification->info(),
        };

        $notification->send();

        event(new DatabaseNotificationsSent($recipient));
    }
}
