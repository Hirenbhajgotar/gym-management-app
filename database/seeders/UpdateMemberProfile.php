<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GymMember;
use App\Services\AvatarService;
use Illuminate\Database\Seeder;

class UpdateMemberProfile extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $GymMember = GymMember::query()->whereNull('profile_photo')->get();

        foreach ($GymMember as $member) {
            $member->profile_photo = AvatarService::generate($member->name);
            $member->save();
        }
    }
}
