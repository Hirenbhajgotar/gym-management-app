<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Models\GymMember;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppBroadcastRecipient>
 */
class WhatsAppBroadcastRecipientFactory extends Factory
{
    protected $model = WhatsAppBroadcastRecipient::class;

    public function definition(): array
    {
        return [
            'whatsapp_broadcast_id' => WhatsAppBroadcast::factory(),
            'member_id' => GymMember::factory(),
            'phone_number' => $this->faker->phoneNumber(),
            'member_name' => $this->faker->name(),
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            'twilio_message_sid' => null,
            'failure_reason' => null,
            'sent_at' => null,
            'delivered_at' => null,
        ];
    }
}
