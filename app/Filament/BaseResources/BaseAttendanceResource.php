<?php

namespace App\Filament\BaseResources;

use App\Enums\AttendanceStatus;
use App\Filament\Admin\Clusters\Attendances as ClustersAttendances;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Widgets\MemberAttendanceStats as WidgetsMemberAttendanceStats;
use App\Filament\Widgets\MemberAttendanceStats;
use App\Models\Attendance;
use App\Models\Attendances;
use App\Models\GymMember;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
        $currentGymId = Filament::getTenant()->id;
        $attendanceableModel = static::getAttendanceableType();

        return $form
            ->schema([
                Section::make('Attendance Details')
                    ->schema([
                        Select::make('attendanceable_id')
                            ->label(static::getLabelForSelect())
                            ->required()
                            ->searchable()
                            // ->options(function () use ($currentGymId, $attendanceableModel) {
                            //     $modelClass = $attendanceableModel;
                            //     return $modelClass::where('gym_id', $currentGymId)->pluck('name', 'id');
                            // })
                            ->getSearchResultsUsing(function (string $search) use ($attendanceableModel, $currentGymId) {
                                $modelClass = $attendanceableModel;
                                return $modelClass::where('name', 'like', "%{$search}%")->where('gym_id', $currentGymId)->limit(50)->pluck('name', 'id')->toArray();
                            }),

                        ToggleButtons::make('status')
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

                SelectFilter::make('attendanceable_id')
                    ->label('Member')
                    ->options(function () {
                        $currentGymId = Filament::getTenant()->id;
                        $modelClass = static::getAttendanceableType();
                        return $modelClass::where('gym_id', $currentGymId)->pluck('name', 'id');
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
                                'status' => AttendanceStatus::Present,
                            ]);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Check-In')
                    ->modalDescription('Are you sure you want to check in this ' . static::getEntityType() . '?')
                    ->modalSubmitActionLabel('Yes, Check-In')
                    ->visible(fn(Model $record) => !$record->check_in_time),

                Action::make('checkOut')
                    ->label('Check-Out')
                    ->color('success')
                    ->action(function (Model $record) {
                        if ($record->check_in_time && !$record->check_out_time) {
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
                    ->visible(fn(Model $record) => $record->check_in_time && !$record->check_out_time),

                Action::make('markAbsent')
                    ->label('Mark Absent')
                    ->color('danger')
                    ->action(function (Model $record) {
                        if (!$record->check_in_time && !$record->check_out_time) {
                            $record->update([
                                'status' => AttendanceStatus::Absent,
                            ]);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Absent')
                    ->modalDescription('Are you sure you want to mark this ' . static::getEntityType() . ' as absent?')
                    ->modalSubmitActionLabel('Yes, Mark Absent')
                    ->visible(fn(Model $record) => !$record->check_in_time && !$record->check_out_time),
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
                            $records->each(function (Model $record) {
                                if (!$record->check_in_time && !$record->check_out_time) {
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
