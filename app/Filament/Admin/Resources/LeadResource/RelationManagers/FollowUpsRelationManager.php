<?php

namespace App\Filament\Admin\Resources\LeadResource\RelationManagers;

use App\Enums\FollowUpStatus;
use App\Enums\InteractionType;
use App\Models\User;
use Filament\Forms;
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
            ->schema([
                Forms\Components\Select::make('type')
                    ->options(InteractionType::class)
                    ->required(),
                Forms\Components\DateTimePicker::make('scheduled_date')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options(FollowUpStatus::class)
                    ->required()
                    ->default(FollowUpStatus::PENDING->value),
                Forms\Components\Select::make('user_id')
                    ->label('Assigned To')
                    ->options(User::query()->pluck('name', 'id'))
                    ->default(auth()->id())
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->required()
                    ->columnSpanFull(),
            ]);
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
                Tables\Actions\CreateAction::make(),
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
