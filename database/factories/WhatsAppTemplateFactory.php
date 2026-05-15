<?php

namespace Database\Factories;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\Gym;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppTemplate>
 */
class WhatsAppTemplateFactory extends Factory
{
    protected $model = WhatsAppTemplate::class;

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'meta_template_id' => $this->faker->uuid(),
            'name' => $this->faker->unique()->word(),
            'display_name' => $this->faker->sentence(3),
            'body' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['marketing', 'utility', 'authentication']),
            'status' => WhatsAppTemplateStatusEnum::PENDING,
            'is_active' => true,
        ];
    }
}
