<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Models\Gym;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppBroadcast>
 */
class WhatsAppBroadcastFactory extends Factory
{
    protected $model = WhatsAppBroadcast::class;

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'whatsapp_template_id' => WhatsAppTemplate::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'target_filters' => null,
            'target_count' => 0,
            'status' => WhatsAppBroadcastStatusEnum::DRAFT,
            'scheduled_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'sent_count' => 0,
            'failed_count' => 0,
            'credits_used' => 0,
            'message_variables' => null,
        ];
    }
}
