<?php

namespace App\Filament\Admin\Resources\DealResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InteractionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options(\App\Enums\InteractionType::class)
                    ->required(),
                Forms\Components\DateTimePicker::make('interaction_date')
                    ->default(now())
                    ->required(),
                Forms\Components\RichEditor::make('notes')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Hidden::make('user_id')
                    ->default(auth()->id()),
                Forms\Components\Hidden::make('gym_id')
                    ->default(fn () => filament()->getTenant()->id),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('user.name')->label('Staff'),
                Tables\Columns\TextColumn::make('interaction_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->html()
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\AssociateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DissociateAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DissociateBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
