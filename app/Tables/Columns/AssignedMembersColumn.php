<?php

namespace App\Tables\Columns;

use App\Models\Gym;
use App\Models\GymMember;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Collection;

/**
 * @mixin Gym
 * @mixin GymMember
 *
 * @property-read Collection $members
 * @property-read int $members_count
 */
class AssignedMembersColumn extends Column
{
    protected string $view = 'tables.columns.assigned-members-column';

    public function getState(): mixed
    {

        /**
         * @var Gym $record
         */
        $record = $this->getRecord();

        return [
            'members' => $record->members->take(3),
            'members_count' => $record->members_count,
        ];
    }
}
