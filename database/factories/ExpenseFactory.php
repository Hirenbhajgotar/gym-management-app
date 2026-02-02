<?php

namespace Database\Factories;

use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
use App\Enums\RecurringFrequency;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $expenseDate = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'gym_id' => Gym::factory(),
            'expense_category_id' => null,
            'user_id' => User::factory(),
            'approved_by_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'currency' => 'INR',
            'expense_date' => $expenseDate,
            'payment_method' => fake()->randomElement(ExpensePaymentMethod::cases()),
            'status' => ExpenseStatus::DRAFT,
            'vendor_name' => fake()->optional()->company(),
            'vendor_contact' => fake()->optional()->phoneNumber(),
            'reference_number' => fake()->optional()->bothify('INV-####-??'),
            'attachments' => null,
            'metadata' => null,
            'is_recurring' => false,
            'recurring_frequency' => null,
            'next_due_date' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'paid_at' => null,
        ];
    }

    /**
     * Draft status
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::DRAFT,
        ]);
    }

    /**
     * Pending approval status
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::PENDING,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Approved status
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::APPROVED,
            'submitted_at' => now()->subDays(2),
            'approved_at' => now()->subDay(),
            'approved_by_id' => User::factory(),
        ]);
    }

    /**
     * Rejected status
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::REJECTED,
            'submitted_at' => now()->subDays(2),
            'rejected_at' => now()->subDay(),
            'rejection_reason' => fake()->sentence(),
            'approved_by_id' => User::factory(),
        ]);
    }

    /**
     * Paid status
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExpenseStatus::PAID,
            'submitted_at' => now()->subDays(3),
            'approved_at' => now()->subDays(2),
            'paid_at' => now()->subDay(),
            'approved_by_id' => User::factory(),
        ]);
    }

    /**
     * Recurring expense
     */
    public function recurring(RecurringFrequency $frequency = RecurringFrequency::MONTHLY): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
            'recurring_frequency' => $frequency,
            'next_due_date' => $frequency->getNextDueDate(now()),
        ]);
    }

    /**
     * With vendor information
     */
    public function withVendor(): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_name' => fake()->company(),
            'vendor_contact' => fake()->phoneNumber(),
        ]);
    }

    /**
     * High amount expense
     */
    public function highAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fake()->randomFloat(2, 50000, 500000),
        ]);
    }

    /**
     * Low amount expense
     */
    public function lowAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fake()->randomFloat(2, 100, 5000),
        ]);
    }

    /**
     * For specific gym
     */
    public function forGym(Gym $gym): static
    {
        return $this->state(fn (array $attributes) => [
            'gym_id' => $gym->id,
        ]);
    }

    /**
     * For specific user
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * For specific category
     */
    public function forCategory(ExpenseCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_category_id' => $category->id,
            'gym_id' => $category->gym_id,
        ]);
    }

    /**
     * This month's expense
     */
    public function thisMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_date' => fake()->dateTimeBetween(now()->startOfMonth(), now()),
        ]);
    }

    /**
     * Last month's expense
     */
    public function lastMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_date' => fake()->dateTimeBetween(
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            ),
        ]);
    }
}
