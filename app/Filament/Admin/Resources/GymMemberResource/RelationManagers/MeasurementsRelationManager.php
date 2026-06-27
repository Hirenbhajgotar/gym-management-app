<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use App\Helpers\BmiHelper;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeasurementsRelationManager extends RelationManager
{
    protected static string $relationship = 'measurements';

    protected static ?string $title = '(BMI) Progress Tracking';

    protected static ?string $icon = 'heroicon-o-chart-bar';

    public function form(Form $form): Form
    {
        return $form
            ->schema(self::getMeasurementsFormSchema());
    }

    public static function getMeasurementsFormSchema(): array
    {
        return [
            DatePicker::make('measured_at')
                ->label('Measured Date')
                ->native(false)
                ->closeOnDateSelection(true)
                ->required()
                ->default(now())
                ->maxDate(now())
                ->columnSpanFull(),

            TextInput::make('weight')
                ->label('Weight (kg)')
                ->suffix('kg')
                ->numeric()
                ->required()
                ->minValue(0)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    self::updateBmi($get, $set);
                }),

            TextInput::make('height')
                ->label('Height (cm)')
                ->suffix('cm')
                ->numeric()
                ->required()
                ->minValue(0)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    self::updateBmi($get, $set);
                }),

            TextInput::make('bmi')
                ->label('BMI')
                ->disabled()
                ->dehydrated(false) // We calculate this in the model
                ->suffix(fn ($state) => self::getBmiStatus($state)),

            Textarea::make('notes')
                ->columnSpanFull(),
        ];
    }

    public static function updateBmi(callable $get, callable $set)
    {
        $weight = $get('weight');
        $height = $get('height');

        if ($weight && $height) {
            $bmi = BmiHelper::calculateBmi($weight, $height);
            $set('bmi', $bmi);
        }
    }

    public static function getBmiStatus($bmi)
    {
        if (! $bmi) {
            return '';
        }

        return BmiHelper::getStatus($bmi);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('measured_at')
            ->columns([
                TextColumn::make('measured_at')
                    ->date()
                    ->sortable()
                    ->label('Date'),

                TextColumn::make('weight')
                    ->label('Weight')
                    ->suffix(' kg')
                    ->sortable(),

                TextColumn::make('height')
                    ->label('Height')
                    ->suffix(' cm')
                    ->sortable(),

                TextColumn::make('bmi')
                    ->label('BMI')
                    ->badge()
                    ->color(fn ($state) => BmiHelper::getColor($state))
                    ->description(fn ($record) => $record->status ?? ''),

                TextColumn::make('notes')
                    ->limit(30),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record Measurement')
                    ->successNotificationTitle('Measurement Added')
                    ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
            ])
            ->actions([
                EditAction::make()
                    ->successNotificationTitle('Measurement Updated')
                    ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
                DeleteAction::make()
                    ->successNotificationTitle('Measurement Deleted')
                    ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
                ]),
            ])
            ->defaultSort('measured_at', 'desc');
    }
}
