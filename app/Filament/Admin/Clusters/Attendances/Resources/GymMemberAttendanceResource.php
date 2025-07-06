<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources;

use App\Enums\AttendanceStatus;
use App\Filament\Admin\Clusters\Attendances;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\RelationManagers;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Widgets\MemberAttendanceStats;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\BaseResources\BaseAttendanceResource;
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

class GymMemberAttendanceResource extends BaseAttendanceResource
{
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getLabel(): string
    {
        return 'Member Attendance';
    }

    public static function getPluralLabel(): string
    {
        return 'Member Attendances';
    }

    protected static function getAttendanceableType(): string
    {
        return GymMember::class;
    }

    protected static function getLabelForSelect(): string
    {
        return 'Member';
    }

    protected static function getEntityType(): string
    {
        return 'member';
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

    public static function getWidgets(): array
    {
        return [
            MemberAttendanceStats::class,
        ];
    }

    // public static function getOptionFormSchema()
    // {
    //     return GymMemberResource::getFormSchema();
    // }
}
