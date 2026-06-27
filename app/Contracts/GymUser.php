<?php

declare(strict_types=1);

namespace App\Contracts;

interface GymUser
{
    public function gyms();

    public function canManageGym(int $gymId): bool;

    public function hasRole(string $role): bool;
}
