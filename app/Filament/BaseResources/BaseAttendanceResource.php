<?php

namespace App\Filament\BaseResources;

use App\Enums\AttendanceStatus;
use App\Filament\Admin\Clusters\Attendances as ClustersAttendances;
use App\Models\Attendances;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

abstract class BaseAttendanceResource extends Resource
{
    protected static ?string $model = Attendances::class;

    protected static ?string $cluster = ClustersAttendances::class;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('attendanceable_type', static::getAttendanceableType());
    }

    abstract protected static function getAttendanceableType(): string;

    public static function form(Form $form): Form
    {
        /**
         * @var \App\Models\Gym $currentGym
         */
        $currentGym = Filament::getTenant();
        $attendanceableModel = static::getAttendanceableType();

        $select = Select::make('attendanceable_id')
            ->label(static::getLabelForSelect())
            ->required()
            ->searchable()
            ->options(function () use ($currentGym, $attendanceableModel) {
                $modelClass = $attendanceableModel;

                return $modelClass::where('gym_id', $currentGym->id)->orderBy('name', 'asc')->pluck('name', 'id');
            });

        // Conditionally add createOptionForm if getOptionFormSchema() exists in the child class
        if (method_exists(static::class, 'getOptionFormSchema')) {
            $select
                ->createOptionForm(static::getOptionFormSchema())
                ->createOptionUsing(function (array $data) use ($currentGym) {
                    $data['gym_id'] = $currentGym->id;
                    $modelClass = static::getAttendanceableType();
                    $attendance = $modelClass::create($data);

                    return $attendance->id;
                });
        }

        return $form
            ->schema([
                Section::make('Attendance Details')
                    ->schema([
                        $select,

                        ToggleButtons::make('status')
                            ->inline()
                            ->options(AttendanceStatus::class)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                if ($get('status') === AttendanceStatus::Absent->value) {
                                    $set('check_in_time', null);
                                    $set('check_out_time', null);
                                }
                            })
                            ->reactive()
                            ->required(),

                        DateTimePicker::make('check_in_time')
                            ->label('Check-In Time')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->required(fn (Get $get) => $get('status') !== AttendanceStatus::Absent->value)
                            ->default(now()),

                        DateTimePicker::make('check_out_time')
                            ->label('Check-Out Time')
                            ->native(false)
                            ->closeOnDateSelection(true)
                            ->nullable(),

                        Textarea::make('note')
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
                    ->color('warning'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceStatus::class),

                SelectFilter::make('attendanceable_id')
                    ->label('Member')
                    ->options(function () {
                        /** @var \App\Models\Gym $currentGym */
                        $currentGym = Filament::getTenant();
                        $modelClass = static::getAttendanceableType();

                        return $modelClass::where('gym_id', $currentGym->id)->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('checkIn')
                    ->label('Check-In')
                    ->color('success')
                    ->action(function (Attendances $record) {
                        if (! $record->check_in_time) {
                            $record->update([
                                'check_in_time' => now(),
                                'status' => AttendanceStatus::Present,
                            ]);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Check-In')
                    ->modalDescription('Are you sure you want to check in this ' . static::getEntityType() . '?')
                    ->modalSubmitActionLabel('Yes, Check-In')
                    ->visible(fn (Attendances $record) => (! $record->check_in_time && $record->status !== AttendanceStatus::Absent)),

                Action::make('checkOut')
                    ->label('Check-Out')
                    ->color('success')
                    ->action(function (Attendances $record) {
                        if ($record->check_in_time && ! $record->check_out_time) {
                            $record->update([
                                'check_out_time' => now(),
                                'status' => AttendanceStatus::Completed,
                            ]);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Check-Out')
                    ->modalDescription('Are you sure you want to check out this ' . static::getEntityType() . '?')
                    ->modalSubmitActionLabel('Yes, Check-Out')
                    ->visible(fn (Attendances $record) => ($record->check_in_time && ! $record->check_out_time) && $record->status !== AttendanceStatus::Absent),

                Action::make('markAbsent')
                    ->label('Mark Absent')
                    ->color('danger')
                    ->action(function (Attendances $record) {
                        if (! $record->check_in_time && ! $record->check_out_time) {
                            $record->update([
                                'status' => AttendanceStatus::Absent,
                            ]);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Absent')
                    ->modalDescription('Are you sure you want to mark this ' . static::getEntityType() . ' as absent?')
                    ->modalSubmitActionLabel('Yes, Mark Absent')
                    ->visible(fn (Attendances $record) => ! $record->check_in_time && ! $record->check_out_time),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    BulkAction::make('markAbsent')
                        ->label('Mark as Absent')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            $records->each(function (Attendances $record) {
                                if (! $record->check_in_time && ! $record->check_out_time) {
                                    $record->update(['status' => AttendanceStatus::Absent]);
                                }
                            });
                            Notification::make()
                                ->title('Bulk action completed')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    abstract protected static function getLabelForSelect(): string;

    abstract protected static function getEntityType(): string;
}
