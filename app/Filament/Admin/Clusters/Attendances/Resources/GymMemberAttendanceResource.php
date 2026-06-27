<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Attendances\Resources;

use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages\ListGymMemberAttendances;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages\CreateGymMemberAttendance;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages\EditGymMemberAttendance;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Widgets\MemberAttendanceStats;
use App\Filament\BaseResources\BaseAttendanceResource;
use App\Models\GymMember;

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

    public static function getAttendanceableType(): string
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
            'index' => ListGymMemberAttendances::route('/'),
            'create' => CreateGymMemberAttendance::route('/create'),
            'edit' => EditGymMemberAttendance::route('/{record}/edit'),
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
