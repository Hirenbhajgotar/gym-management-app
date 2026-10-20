<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use App\Helpers\BmiHelper;
use App\Models\MemberMeasurement;
use App\Traits\GuardsArchivedOwner;
use Filament\Forms\Components\Component;
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

/**
 * Body measurements — the clinical history behind a member's BMI trend.
 *
 * Archiving the member freezes the record but must not let the trend be
 * rewritten underneath it: a re-measurement added, or an old weight edited,
 * after archiving silently changes every chart, report and progress percentage
 * that reads from this table. Reads stay open because archived measurements
 * are exactly what a trainer or accountant still needs;
 * {@see GuardsArchivedOwner} closes mutation only.
 */
class MeasurementsRelationManager extends RelationManager
{
    use GuardsArchivedOwner;

    protected static string $relationship = 'measurements';

    protected static ?string $title = '(BMI) Progress Tracking';

    protected static ?string $icon = 'heroicon-o-chart-bar';

    protected function archivedOwnerSubject(): string
    {
        return 'Measurements';
    }

    protected function archivedOwnerNoticeBody(): string
    {
        return 'Measurements stay visible as the record behind this member\'s progress history, but nothing here can be added, edited or deleted until the member is restored.';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(self::getMeasurementsFormSchema());
    }

    /**
     * @return array<int, Component>
     */
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
                ->minValue(50)
                ->maxValue(250)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    self::updateBmi($get, $set);
                }),

            TextInput::make('bmi')
                ->label('BMI')
                ->disabled()
                ->dehydrated(false) // We calculate this in the model
                ->suffix(fn (?float $state) => $state ? self::getBmiStatus($state) : null),

            Textarea::make('notes')
                ->columnSpanFull(),
        ];
    }

    public static function updateBmi(callable $get, callable $set)
    {
        $weight = is_numeric($get('weight')) ? (float) $get('weight') : 0.0;
        $height = is_numeric($get('height')) ? (float) $get('height') : 0.0;

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
            ->header(fn () => $this->archivedOwnerHeader())
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
                    ->color(fn (float $state) => BmiHelper::getColor($state))
                    ->description(fn (MemberMeasurement $record): string => $record->status ?? ''),

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
                    ->before(fn () => $this->guardArchivedOwner())
                    ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
            ])
            ->actions([
                EditAction::make()
                    ->successNotificationTitle('Measurement Updated')
                    ->before(fn () => $this->guardArchivedOwner())
                    ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
                DeleteAction::make()
                    ->successNotificationTitle('Measurement Deleted')
                    ->before(fn () => $this->guardArchivedOwner())
                    ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(fn () => $this->guardArchivedOwner())
                        ->after(fn ($livewire) => $livewire->dispatch('member-measurement-updated')),
                ]),
            ])
            ->defaultSort('measured_at', 'desc');
    }
}
