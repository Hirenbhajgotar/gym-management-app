<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources;

use App\Enums\AttendanceStatus;
use App\Filament\Admin\Clusters\Attendances;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\RelationManagers;
use App\Models\Attendances as ModelsAttendances;
use App\Models\GymMember;
use App\Models\GymMemberAttendance;
use App\Models\Trainer;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class GymMemberAttendanceResource extends Resource
{
    protected static ?string $model = ModelsAttendances::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $cluster = Attendances::class;

    public static function getLabel(): string
    {
        return 'Member Attendance';
    }

    public static function getPluralLabel(): string
    {
        return 'Member Attendances';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('attendanceable_type', GymMember::class);

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
                                return GymMember::where('gym_id', Filament::getTenant()->id)->pluck('name', 'id');
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
                        return GymMember::where('gym_id', Filament::getTenant()->id)
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

                    // Bulk mark absent
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGymMemberAttendances::route('/'),
            'create' => Pages\CreateGymMemberAttendance::route('/create'),
            'edit' => Pages\EditGymMemberAttendance::route('/{record}/edit'),
        ];
    }
}
