<?php

namespace Database\Seeders;

use App\Enums\LeadStatus;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadConversion;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Lead::factory(200)->create()->each(function ($lead) {
            FollowUp::factory(rand(0, 5))->create([
                'lead_id' => $lead->id,
            ]);
        });

        $leadsToConvert = Lead::inRandomOrder()->limit(rand(10, 20))->get();
        foreach ($leadsToConvert as $lead) {
            LeadConversion::factory()->create([
                'lead_id' => $lead->id,
            ]);
            $lead->status = LeadStatus::CONVERTED;
            $lead->save();
        }
    }
}
