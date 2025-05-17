<?php

namespace Database\Seeders;

use App\Models\GymMember;
use App\Services\AvatarService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UpdateMemberProfile extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $GymMember = GymMember::whereNull('profile_photo')->get();

        foreach ($GymMember as $member) {
            $member->profile_photo = AvatarService::generate($member->name);
            $member->save();
        }
    }
}
