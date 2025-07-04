<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources;

use App\Enums\AttendanceStatus;
use App\Filament\Admin\Clusters\Attendances;
use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages;
use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\RelationManagers;
use App\Models\Attendances as ModelsAttendances;
use App\Models\Trainer;
use App\Models\TrainerAttendance;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TrainerAttendanceResource extends Resource
{
    protected static ?string $model = ModelsAttendances::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $cluster = Attendances::class;

    public static function getLabel(): string
    {
        return 'Trainer Attendance';
    }

    public static function getPluralLabel(): string
    {
        return 'Trainer Attendances';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('attendanceable_type', Trainer::class);

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Attendance Details')
                    ->schema([
                        Select::make('attendanceable_id')
                            ->label('User')
                            ->options(function (callable $get) {
                                return Trainer::where('gym_id', Filament::getTenant()->id)->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable(),

                        Forms\Components\ToggleButtons::make('status')
                            ->inline()
                            ->options(AttendanceStatus::class)
                            ->required(),

                        DateTimePicker::make('check_in_time')
                            ->label('Check-In Time')
                            ->required()
                            ->default(now()),

                        DateTimePicker::make('check_out_time')
                            ->label('Check-Out Time')
                            ->nullable(),

                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendanceable.name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('check_in_time')
                    ->label('Check-In Time')
                    ->badge('info')
                    ->dateTime(),

                TextColumn::make('check_out_time')
                    ->label('Check-Out Time')
                    ->badge('info')
                    ->dateTime(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->badge()
                    ->color('warning')
                    ->placeholder('In progress'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceStatus::class),

                // DateFilter::make('check_in_time')
                //     ->label('Check-in Date'),

                SelectFilter::make('attendanceable_id')
                    ->label('Member')
                    ->options(function () {
                        return Trainer::where('gym_id', Filament::getTenant()->id)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('checkIn')
                    ->label('Check-In')
                    ->color('success')
                    ->action(function (Model $record) {
                        if (!$record->check_in_time) {
                            $record->update([
                                'check_in_time' => now(),
                                'status' => 'Present',
                            ]);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Check-In')
                    ->modalDescription('Are you sure you want to check in this user?')
                    ->modalSubmitActionLabel('Yes, Check-In')
                    ->visible(fn(Model $record) => !$record->check_in_time),

                Action::make('checkOut')
                    ->label('Check-Out')
                    ->color('success')
                    ->action(function (Model $record) {
                        if ($record->check_in_time && !$record->check_out_time) {
                            $record->update([
                                'check_out_time' => now(),
                                'status' => 'Completed',
                            ]);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Check-Out')
                    ->modalDescription('Are you sure you want to check out this user?')
                    ->modalSubmitActionLabel('Yes, Check-Out')
                    ->visible(fn(Model $record) => $record->check_in_time && !$record->check_out_time),

                Action::make('markAbsent')
                    ->label('Mark Absent')
                    ->color('danger')
                    ->action(function (Model $record) {
                        if (!$record->check_in_time && !$record->check_out_time) {
                            $record->update([
                                'status' => 'Absent',
                            ]);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Absent')
                    ->modalDescription('Are you sure you want to mark this user as absent?')
                    ->modalSubmitActionLabel('Yes, Mark Absent')
                    ->visible(fn(Model $record) => !$record->check_in_time && !$record->check_out_time),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainerAttendances::route('/'),
            'create' => Pages\CreateTrainerAttendance::route('/create'),
            'edit' => Pages\EditTrainerAttendance::route('/{record}/edit'),
        ];
    }
}
