<?php

namespace Database\Factories;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\Gym;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppTemplate>
 */
class WhatsAppTemplateFactory extends Factory
{
    protected $model = WhatsAppTemplate::class;

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'meta_template_id' => fake()->uuid(),
            'name' => fake()->unique()->word(),
            'display_name' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'category' => fake()->randomElement(['marketing', 'utility', 'authentication']),
            'status' => WhatsAppTemplateStatusEnum::PENDING,
            'is_active' => true,
        ];
    }
}
