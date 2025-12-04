<?php

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;

/**
 * @mixin \App\Models\Gym
 * @mixin \App\Models\GymMember
 *
 * @property-read \Illuminate\Database\Eloquent\Collection $members
 * @property-read int $members_count
 */
class AssignedMembersColumn extends Column
{
    protected string $view = 'tables.columns.assigned-members-column';

    public function getState(): mixed
    {

        /**
         * @var \App\Models\Gym $record
         */
        $record = $this->getRecord();

        return [
            'members' => $record->members->take(3),
            'members_count' => $record->members_count,
        ];
    }
}
