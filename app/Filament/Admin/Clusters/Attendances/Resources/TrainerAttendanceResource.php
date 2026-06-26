<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources;

use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages\ListTrainerAttendances;
use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages\CreateTrainerAttendance;
use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages\EditTrainerAttendance;
use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Widgets\TrainerAttendanceStats;
use App\Filament\Admin\Resources\TrainerResource;
use App\Filament\BaseResources\BaseAttendanceResource;
use App\Models\Trainer;

class TrainerAttendanceResource extends BaseAttendanceResource
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    public static function getLabel(): string
    {
        return 'Trainer Attendance';
    }

    public static function getPluralLabel(): string
    {
        return 'Trainer Attendances';
    }

    protected static function getAttendanceableType(): string
    {
        return Trainer::class;
    }

    protected static function getLabelForSelect(): string
    {
        return 'Trainer';
    }

    protected static function getEntityType(): string
    {
        return 'trainer';
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getOptionFormSchema()
    {
        return TrainerResource::getFormSchema();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrainerAttendances::route('/'),
            'create' => CreateTrainerAttendance::route('/create'),
            'edit' => EditTrainerAttendance::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            TrainerAttendanceStats::class,
        ];
    }
}
