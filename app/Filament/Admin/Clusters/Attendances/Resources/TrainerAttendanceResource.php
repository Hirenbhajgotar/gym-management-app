<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources;

use App\Enums\AttendanceStatus;
use App\Filament\Admin\Clusters\Attendances;
use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Pages;
use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\RelationManagers;
use App\Filament\Admin\Clusters\Attendances\Resources\TrainerAttendanceResource\Widgets\TrainerAttendanceStats;
use App\Filament\Admin\Resources\TrainerResource;
use App\Filament\BaseResources\BaseAttendanceResource;
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
            'index' => Pages\ListTrainerAttendances::route('/'),
            'create' => Pages\CreateTrainerAttendance::route('/create'),
            'edit' => Pages\EditTrainerAttendance::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            TrainerAttendanceStats::class,
        ];
    }
}
