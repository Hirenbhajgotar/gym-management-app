<?php

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;

class AssignedMembersColumn extends Column
{
    protected string $view = 'tables.columns.assigned-members-column';

    public function getState(): mixed
    {
        $record = $this->getRecord();

        return [
            'members' => $record->members->take(3),
            'members_count' => $record->members_count,
        ];
    }
}
