<?php

namespace App\Filament\Admin\Resources\LeadResource\RelationManagers;

use App\Enums\FollowUpStatus;
use App\Enums\InteractionType;
use App\Models\FollowUp;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\LeadFollowUpNotification;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FollowUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'followUps';

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema());
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\Hidden::make('gym_id')
                ->default(Filament::getTenant()->id),
            Section::make()
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\Select::make('type')
                            ->options(InteractionType::class)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options(FollowUpStatus::class)
                            ->required()
                            ->default(FollowUpStatus::PENDING->value),

                        Forms\Components\DateTimePicker::make('scheduled_date')
                            ->required(),

                        Forms\Components\Select::make('user_id')
                            ->label('Assigned To')
                            ->options(fn() => Gym::getCurrentTenantUsers()->pluck('users.name', 'users.id'))
                            ->default(auth()->id())
                            ->required(),
                    ]),
                    Forms\Components\RichEditor::make('notes')
                        ->columnSpanFull(),
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('scheduled_date')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Assigned To')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function (FollowUp $followUp) {
                        $followUp->user->notify(new LeadFollowUpNotification($followUp));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
