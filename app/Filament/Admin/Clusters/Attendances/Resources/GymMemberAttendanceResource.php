<?php

namespace App\Filament\Admin\Clusters\Attendances\Resources;

use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Pages;
use App\Filament\Admin\Clusters\Attendances\Resources\GymMemberAttendanceResource\Widgets\MemberAttendanceStats;
use App\Filament\Admin\Resources\GymMemberResource;
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
