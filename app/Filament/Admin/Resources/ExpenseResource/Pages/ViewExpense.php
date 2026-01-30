<?php

namespace App\Filament\Admin\Resources\ExpenseResource\Pages;

use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewExpense extends ViewRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->canEdit()),

            Actions\Action::make('submit')
                ->label('Submit for Approval')
                ->icon('phosphor-paper-plane-tilt')
                ->color('info')
                ->visible(fn () => $this->record->canSubmit())
                ->requiresConfirmation()
                ->action(fn () => $this->record->submit()),

            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('phosphor-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->canApprove() && $user->can('approve', $this->record))
                ->requiresConfirmation()
                ->action(fn () => $this->record->approve($user)),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('phosphor-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canApprove() && $user->can('reject', $this->record))
                ->form([
                    \Filament\Forms\Components\Textarea::make('rejection_reason')
                        ->label('Reason for Rejection')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(fn (array $data) => $this->record->reject($user, $data['rejection_reason'])),

            Actions\Action::make('mark_paid')
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
                Infolists\Components\Section::make('Expense Details')
                    ->icon('phosphor-money')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->weight('bold')
                            ->size('lg'),

                        Infolists\Components\TextEntry::make('status')
                            ->badge(),

                        Infolists\Components\TextEntry::make('category.name')
                            ->label('Category')
                            ->badge()
                            ->color(fn ($record) => $record->category?->color ?? 'gray'),

                        Infolists\Components\TextEntry::make('expense_date')
                            ->label('Date')
                            ->date('d M Y'),

                        Infolists\Components\TextEntry::make('amount')
                            ->money(fn ($record) => $record->currency)
                            ->weight('bold')
                            ->size('lg'),

                        Infolists\Components\TextEntry::make('payment_method')
                            ->formatStateUsing(fn ($state) => $state?->getLabel()),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Vendor Information')
                    ->icon('phosphor-storefront')
                    ->schema([
                        Infolists\Components\TextEntry::make('vendor_name')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('vendor_contact')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('reference_number')
                            ->label('Invoice/Receipt #')
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->collapsed()
                    ->visible(fn ($record) => $record->vendor_name || $record->reference_number),

                Infolists\Components\Section::make('Attachments')
                    ->icon('phosphor-paperclip')
                    ->schema([
                        Infolists\Components\ImageEntry::make('attachments')
                            ->label('')
                            ->columnSpanFull()
                            ->size(200),
                    ])
                    ->collapsed()
                    ->visible(fn ($record) => ! empty($record->attachments)),

                Infolists\Components\Section::make('Workflow')
                    ->icon('phosphor-flow-arrow')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Submitted By'),

                        Infolists\Components\TextEntry::make('submitted_at')
                            ->label('Submitted At')
                            ->dateTime()
                            ->placeholder('Not submitted'),

                        Infolists\Components\TextEntry::make('approvedBy.name')
                            ->label('Approved/Rejected By')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime()
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('rejected_at')
                            ->label('Rejected At')
                            ->dateTime()
                            ->placeholder('—')
                            ->visible(fn ($record) => $record->isRejected()),

                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->isRejected())
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('paid_at')
                            ->label('Paid At')
                            ->dateTime()
                            ->placeholder('—')
                            ->visible(fn ($record) => $record->isPaid()),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Recurring Settings')
                    ->icon('phosphor-repeat')
                    ->schema([
                        Infolists\Components\IconEntry::make('is_recurring')
                            ->boolean(),

                        Infolists\Components\TextEntry::make('recurring_frequency')
                            ->formatStateUsing(fn ($state) => $state?->getLabel())
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('next_due_date')
                            ->date()
                            ->placeholder('—'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record->is_recurring),
            ]);
    }
}
