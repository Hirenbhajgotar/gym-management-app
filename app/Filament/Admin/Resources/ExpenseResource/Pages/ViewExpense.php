<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExpenseResource\Pages;

use App\Filament\Admin\Resources\ExpenseResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewExpense extends ViewRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return [
            EditAction::make()
                ->visible(fn () => $this->record->canEdit()),

            Action::make('submit')
                ->label('Submit for Approval')
                ->icon('phosphor-paper-plane-tilt')
                ->color('info')
                ->visible(fn () => $this->record->canSubmit())
                ->requiresConfirmation()
                ->action(fn () => $this->record->submit()),

            Action::make('approve')
                ->label('Approve')
                ->icon('phosphor-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->canApprove() && $user->can('approve', $this->record))
                ->requiresConfirmation()
                ->action(fn () => $this->record->approve($user)),

            Action::make('reject')
                ->label('Reject')
                ->icon('phosphor-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canApprove() && $user->can('reject', $this->record))
                ->form([
                    Textarea::make('rejection_reason')
                        ->label('Reason for Rejection')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(fn (array $data) => $this->record->reject($user, $data['rejection_reason'])),

            Action::make('mark_paid')
                ->label('Mark as Paid')
                ->icon('phosphor-coins')
                ->color('info')
                ->visible(fn () => $this->record->isApproved())
                ->requiresConfirmation()
                ->action(fn () => $this->record->markAsPaid()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Expense Details')
                    ->icon('phosphor-money')
                    ->schema([
                        TextEntry::make('title')
                            ->weight('bold')
                            ->size('lg'),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('category.name')
                            ->label('Category')
                            ->badge()
                            ->color(fn ($record) => $record->category?->color ?? 'gray'),

                        TextEntry::make('expense_date')
                            ->label('Date')
                            ->date('d M Y'),

                        TextEntry::make('amount')
                            ->money(fn ($record) => $record->currency)
                            ->weight('bold')
                            ->size('lg'),

                        TextEntry::make('payment_method')
                            ->formatStateUsing(fn ($state) => $state?->getLabel()),

                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description'),
                    ])
                    ->columns(3),

                Section::make('Vendor Information')
                    ->icon('phosphor-storefront')
                    ->schema([
                        TextEntry::make('vendor_name')
                            ->placeholder('—'),

                        TextEntry::make('vendor_contact')
                            ->placeholder('—'),

                        TextEntry::make('reference_number')
                            ->label('Invoice/Receipt #')
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->collapsed()
                    ->visible(fn ($record) => $record->vendor_name || $record->reference_number),

                Section::make('Attachments')
                    ->icon('phosphor-paperclip')
                    ->schema([
                        ImageEntry::make('attachments')
                            ->label('')
                            ->columnSpanFull()
                            ->size(200),
                    ])
                    ->collapsed()
                    ->visible(fn ($record) => filled($record->attachments)),

                Section::make('Workflow')
                    ->icon('phosphor-flow-arrow')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Submitted By'),

                        TextEntry::make('submitted_at')
                            ->label('Submitted At')
                            ->dateTime()
                            ->placeholder('Not submitted'),

                        TextEntry::make('approvedBy.name')
                            ->label('Approved/Rejected By')
                            ->placeholder('—'),

                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('rejected_at')
                            ->label('Rejected At')
                            ->dateTime()
                            ->placeholder('—')
                            ->visible(fn ($record) => $record->isRejected()),

                        TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->isRejected())
                            ->color('danger'),

                        TextEntry::make('paid_at')
                            ->label('Paid At')
                            ->dateTime()
                            ->placeholder('—')
                            ->visible(fn ($record) => $record->isPaid()),
                    ])
                    ->columns(3),

                Section::make('Recurring Settings')
                    ->icon('phosphor-repeat')
                    ->schema([
                        IconEntry::make('is_recurring')
                            ->boolean(),

                        TextEntry::make('recurring_frequency')
                            ->formatStateUsing(fn ($state) => $state?->getLabel())
                            ->placeholder('—'),

                        TextEntry::make('next_due_date')
                            ->date()
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record->is_recurring),
            ]);
    }
}
