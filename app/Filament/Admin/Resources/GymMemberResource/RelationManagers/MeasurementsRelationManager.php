<?php

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MeasurementsRelationManager extends RelationManager
{
    protected static string $relationship = 'measurements';

    protected static ?string $title = '(BMI) Progress Tracking';

    protected static ?string $icon = 'heroicon-o-chart-bar';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('measured_at')
                    ->label('Measured Date')
                    ->native(false)
                    ->closeOnDateSelection(true)
                    ->required()
                    ->default(now())
                    ->maxDate(now())
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('weight')
                    ->label('Weight (kg)')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        self::updateBmi($get, $set);
                    }),

                Forms\Components\TextInput::make('height')
                    ->label('Height (cm)')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        self::updateBmi($get, $set);
                    }),

                Forms\Components\TextInput::make('bmi')
                    ->label('BMI')
                    ->disabled()
                    ->dehydrated(false) // We calculate this in the model
                    ->suffix(fn ($state) => self::getBmiStatus($state)),

                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function updateBmi(callable $get, callable $set)
    {
        $weight = $get('weight');
        $height = $get('height');

        if ($weight && $height) {
            $heightInMeters = $height / 100;
            if ($heightInMeters > 0) {
                $bmi = round($weight / ($heightInMeters * $heightInMeters), 2);
                $set('bmi', $bmi);
            }
        }
    }

    public static function getBmiStatus($bmi)
    {
        if (! $bmi) {
            return null;
        }

        return match (true) {
            $bmi < 18.5 => 'Underweight',
            $bmi < 25 => 'Normal',
            $bmi < 30 => 'Overweight',
            default => 'Obese',
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('measured_at')
            ->columns([
                Tables\Columns\TextColumn::make('measured_at')
                    ->date()
                    ->sortable()
                    ->label('Date'),

                Tables\Columns\TextColumn::make('weight')
                    ->label('Weight')
                    ->suffix(' kg')
                    ->sortable(),

                Tables\Columns\TextColumn::make('height')
                    ->label('Height')
                    ->suffix(' cm')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bmi')
                    ->label('BMI')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state < 18.5 => 'warning',
                        $state < 25 => 'success',
                        $state < 30 => 'warning',
                        default => 'danger',
                    })
                    ->description(fn ($record) => $record->status ?? ''),

                Tables\Columns\TextColumn::make('notes')
                    ->limit(30),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Record Measurement')
                    ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
                Tables\Actions\DeleteAction::make()
                    ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
                ]),
            ])
            ->defaultSort('measured_at', 'desc');
    }
}
