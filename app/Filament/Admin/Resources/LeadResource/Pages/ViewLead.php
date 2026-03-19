<?php

namespace App\Filament\Admin\Resources\LeadResource\Pages;

use App\Filament\Admin\Resources\LeadResource;
use App\Models\Lead;
use App\Notifications\Member\WelcomeNotification;
use App\Services\LeadConversionService;
use Exception;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Lead Details')
                    ->tabs([
                        Tab::make('Overview')
                            ->icon('phosphor-user-circle')
                            ->schema([
                                Section::make('Lead Information')
                                    ->description('Basic contact and lead details')
                                    ->collapsible()
                                    ->schema([
                                        Split::make([
                                            Grid::make(2)
                                                ->schema([
                                                    Group::make([
                                                        TextEntry::make('name')
                                                            ->label('Full Name')
                                                            ->size('lg')
                                                            ->weight('bold'),
                                                        TextEntry::make('email')
                                                            ->label('Email Address')
                                                            ->icon('phosphor-envelope')
                                                            ->copyable()
                                                            ->copyMessage('Email copied to clipboard'),
                                                        TextEntry::make('phone')
                                                            ->label('Phone Number')
                                                            ->icon('phosphor-phone')
                                                            ->copyable()
                                                            ->copyMessage('Phone copied to clipboard'),
                                                    ]),
                                                    Group::make([
                                                        TextEntry::make('status')
                                                            ->label('Lead Status')
                                                            ->badge(),
                                                        TextEntry::make('source')
                                                            ->label('Lead Source')
                                                            ->badge()
                                                            ->color('primary'),
                                                        TextEntry::make('interest')
                                                            ->label('Interests')
                                                            ->badge()
                                                            ->separator(','),
                                                        TextEntry::make('tags')
                                                            ->label('Tags')
                                                            ->badge()
                                                            ->color('gray')
                                                            ->separator(','),
                                                    ]),
                                                ]),
                                        ]),
                                    ]),

                                Section::make('Additional Information')
                                    ->description('Lead metadata and tracking details')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('creator.name')
                                                    ->label('Created By')
                                                    ->icon('phosphor-user'),
                                                TextEntry::make('created_at')
                                                    ->label('Created Date')
                                                    ->icon('phosphor-calendar')
                                                    ->dateTime(),
                                                TextEntry::make('updated_at')
                                                    ->label('Last Updated')
                                                    ->icon('phosphor-clock')
                                                    ->dateTime(),
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Follow-ups')
                            ->icon('phosphor-calendar-check')
                            ->schema([
                                Section::make('Follow-up History')
                                    ->description('All follow-up interactions with this lead')
                                    ->schema([
                                        RepeatableEntry::make('followUps')
                                            ->label('')
                                            ->schema([
                                                Grid::make(4)
                                                    ->schema([
                                                        TextEntry::make('scheduled_date')
                                                            ->label('Scheduled Date')
                                                            ->dateTime()
                                                            ->icon('phosphor-calendar'),
                                                        TextEntry::make('status')
                                                            ->label('Status')
                                                            ->badge(),
                                                        TextEntry::make('type')
                                                            ->label('Interaction Type')
                                                            ->icon('phosphor-chat-circle'),
                                                        TextEntry::make('user.name')
                                                            ->label('Assigned To')
                                                            ->icon('phosphor-user'),
                                                    ]),
                                                TextEntry::make('notes')
                                                    ->label('Notes')
                                                    ->columnSpanFull()
                                                    ->markdown()
                                                    ->limit(200),
                                            ])
                                            ->contained(false)
                                            ->grid(2),
                                    ]),
                            ]),

                        Tab::make('Conversion')
                            ->icon('phosphor-check-circle')
                            ->visible(fn (Lead $record): bool => $record->status === \App\Enums\LeadStatus::CONVERTED)
                            ->schema([
                                Section::make('Conversion Details')
                                    ->description('Information about the lead conversion to member')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Group::make([
                                                    TextEntry::make('conversion.conversion_date')
                                                        ->label('Conversion Date')
                                                        ->icon('phosphor-calendar')
                                                        ->date(),
                                                    TextEntry::make('conversion.membershipPlan.name')
                                                        ->label('Membership Plan')
                                                        ->icon('phosphor-crown'),
                                                ]),
                                                Group::make([
                                                    TextEntry::make('conversion.gymMember.member_id')
                                                        ->label('Member ID')
                                                        ->icon('phosphor-identification-card')
                                                        ->copyable()
                                                        ->copyMessage('Member ID copied to clipboard'),
                                                    TextEntry::make('conversion.notes')
                                                        ->label('Conversion Notes')
                                                        ->markdown()
                                                        ->columnSpanFull(),
                                                ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ])->columns(1);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('phosphor-pencil'),

            Actions\Action::make('convertToMember')
                ->label('Convert to Member')
                ->icon('phosphor-check-circle')
                ->color('success')
                ->visible(fn (Lead $record): bool => $record->status !== \App\Enums\LeadStatus::CONVERTED)
                ->form(LeadResource::getConvertToMemberFormSchema())
                ->action(function (Lead $record, array $data) {
                    try {
                        $conversionService = app(LeadConversionService::class);
                        $gymMember = $conversionService->convert($record, [
                            'membership_plan_id' => $data['membership_plan_id'],
                            'conversion_date' => $data['conversion_date'] ?? now(),
                            'notes' => $data['notes'],
                        ]);

                        // Send welcome notification
                        $gymMember->notify(new WelcomeNotification($gymMember));

                        Notification::make()
                            ->title('Lead converted successfully!')
                            ->body('The lead has been converted to a member and welcome notification sent.')
                            ->success()
                            ->send();

                        return redirect()->route('filament.admin.resources.gym-members.edit', ['tenant' => $record->gym->slug, 'record' => $gymMember->id]);
                    } catch (Exception $e) {
                        Log::error($e->getMessage());

                        Notification::make()
                            ->title('Conversion failed!')
                            ->body('Something went wrong while converting the lead. Please try again later.')
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->icon('phosphor-trash'),
        ];
    }
}
