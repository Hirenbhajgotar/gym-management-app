<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $icons = [
            'phosphor-lightning',
            'phosphor-barbell',
            'phosphor-users',
            'phosphor-megaphone',
            'phosphor-buildings',
            'phosphor-broom',
            'phosphor-hammer',
            'phosphor-receipt',
            'phosphor-car',
            'phosphor-package',
        ];

        $colors = ['primary', 'success', 'warning', 'danger', 'info', 'gray'];

        return [
            'gym_id' => Gym::factory(),
            'parent_id' => null,
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'icon' => fake()->randomElement($icons),
            'color' => fake()->randomElement($colors),
            'is_active' => true,
            'order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a child category.
     */
    public function child(ExpenseCategory $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'gym_id' => $parent->gym_id,
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Create category for a specific gym.
     */
    public function forGym(Gym $gym): static
    {
        return $this->state(fn (array $attributes) => [
            'gym_id' => $gym->id,
        ]);
    }
}
