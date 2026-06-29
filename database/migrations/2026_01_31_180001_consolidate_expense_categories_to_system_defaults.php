<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\ExpenseCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration consolidates duplicate per-gym expense categories into system defaults.
     */
    public function up(): void
    {
        // Get the default categories from the seeder (we'll create them fresh as system defaults)
        $defaultCategories = $this->getDefaultCategories();

        // Create system default categories
        $categoryMapping = []; // Maps old gym-specific category names to new system category IDs

        DB::transaction(function () use ($defaultCategories, &$categoryMapping) {
            $order = 0;

            foreach ($defaultCategories as $categoryData) {
                $children = $categoryData['children'] ?? [];
                unset($categoryData['children']);

                // Check if system category already exists
                $systemCategory = ExpenseCategory::query()->firstOrCreate([
                    'gym_id' => null,
                    'name' => $categoryData['name'],
                    'parent_id' => null,
                ], [
                    ...$categoryData,
                    'order' => $order++,
                    'is_active' => true,
                    'is_system' => true,
                ]);

                // Update is_system flag if it already existed
                $systemCategory->update(['is_system' => true]);

                $categoryMapping[$categoryData['name']] = [
                    'id' => $systemCategory->id,
                    'children' => [],
                ];

                // Create child categories
                $childOrder = 0;
                foreach ($children as $childData) {
                    $childCategory = ExpenseCategory::query()->firstOrCreate([
                        'gym_id' => null,
                        'name' => $childData['name'],
                        'parent_id' => $systemCategory->id,
                    ], [
                        ...$childData,
                        'description' => null,
                        'order' => $childOrder++,
                        'is_active' => true,
                        'is_system' => true,
                    ]);

                    $childCategory->update(['is_system' => true]);
                    $categoryMapping[$categoryData['name']]['children'][$childData['name']] = $childCategory->id;
                }
            }

            // Now update all expenses to point to system categories
            $this->migrateExpensesToSystemCategories($categoryMapping);

            // Update expense budgets to point to system categories
            $this->migrateBudgetsToSystemCategories($categoryMapping);

            // Delete duplicate gym-specific categories (only default ones, keep custom)
            $this->deleteDuplicateGymCategories($categoryMapping);
        });
    }

    /**
     * Migrate expenses from gym-specific categories to system categories
     */
    private function migrateExpensesToSystemCategories(array $categoryMapping): void
    {
        foreach ($categoryMapping as $parentName => $parentData) {
            // Update expenses pointing to gym-specific parent categories
            $gymCategoryIds = ExpenseCategory::query()->whereNotNull('gym_id')
                ->where('name', $parentName)
                ->whereNull('parent_id')
                ->pluck('id');

            if ($gymCategoryIds->isNotEmpty()) {
                Expense::query()->whereIn('expense_category_id', $gymCategoryIds)
                    ->update(['expense_category_id' => $parentData['id']]);
            }

            // Update expenses pointing to gym-specific child categories
            foreach ($parentData['children'] as $childName => $childId) {
                $gymChildIds = ExpenseCategory::query()->whereNotNull('gym_id')
                    ->where('name', $childName)
                    ->whereNotNull('parent_id')
                    ->pluck('id');

                if ($gymChildIds->isNotEmpty()) {
                    Expense::query()->whereIn('expense_category_id', $gymChildIds)
                        ->update(['expense_category_id' => $childId]);
                }
            }
        }
    }

    /**
     * Migrate expense budgets from gym-specific categories to system categories
     */
    private function migrateBudgetsToSystemCategories(array $categoryMapping): void
    {
        foreach ($categoryMapping as $parentName => $parentData) {
            $gymCategoryIds = ExpenseCategory::query()->whereNotNull('gym_id')
                ->where('name', $parentName)
                ->whereNull('parent_id')
                ->pluck('id');

            if ($gymCategoryIds->isNotEmpty()) {
                ExpenseBudget::query()->whereIn('expense_category_id', $gymCategoryIds)
                    ->update(['expense_category_id' => $parentData['id']]);
            }

            foreach ($parentData['children'] as $childName => $childId) {
                $gymChildIds = ExpenseCategory::query()->whereNotNull('gym_id')
                    ->where('name', $childName)
                    ->whereNotNull('parent_id')
                    ->pluck('id');

                if ($gymChildIds->isNotEmpty()) {
                    ExpenseBudget::query()->whereIn('expense_category_id', $gymChildIds)
                        ->update(['expense_category_id' => $childId]);
                }
            }
        }
    }

    /**
     * Delete duplicate gym-specific categories that match system defaults
     */
    private function deleteDuplicateGymCategories(array $categoryMapping): void
    {
        $defaultNames = array_keys($categoryMapping);
        $childNames = [];

        foreach ($categoryMapping as $parentData) {
            $childNames = array_merge($childNames, array_keys($parentData['children']));
        }

        // Delete children first (due to foreign key)
        ExpenseCategory::query()->whereNotNull('gym_id')
            ->whereIn('name', $childNames)
            ->whereNotNull('parent_id')
            ->delete();

        // Delete parent categories
        ExpenseCategory::query()->whereNotNull('gym_id')
            ->whereIn('name', $defaultNames)
            ->whereNull('parent_id')
            ->delete();
    }

    /**
     * Get the default expense categories (same as seeder)
     */
    private function getDefaultCategories(): array
    {
        return [
            [
                'name' => 'Utilities',
                'description' => 'Electricity, Water, Internet, Gas bills',
                'icon' => 'phosphor-lightning',
                'color' => 'warning',
                'children' => [
                    ['name' => 'Electricity', 'icon' => 'phosphor-lightning', 'color' => 'warning'],
                    ['name' => 'Water', 'icon' => 'phosphor-drop', 'color' => 'info'],
                    ['name' => 'Internet & Phone', 'icon' => 'phosphor-wifi-high', 'color' => 'primary'],
                    ['name' => 'Gas', 'icon' => 'phosphor-fire', 'color' => 'danger'],
                ],
            ],
            [
                'name' => 'Equipment & Maintenance',
                'description' => 'Gym equipment purchase and maintenance',
                'icon' => 'phosphor-barbell',
                'color' => 'primary',
                'children' => [
                    ['name' => 'Equipment Purchase', 'icon' => 'phosphor-barbell', 'color' => 'primary'],
                    ['name' => 'Equipment Repair', 'icon' => 'phosphor-wrench', 'color' => 'gray'],
                    ['name' => 'Equipment Maintenance', 'icon' => 'phosphor-gear', 'color' => 'gray'],
                ],
            ],
            [
                'name' => 'Staff Salaries & Wages',
                'description' => 'Employee salaries, wages, and benefits',
                'icon' => 'phosphor-users',
                'color' => 'success',
                'children' => [
                    ['name' => 'Trainer Salaries', 'icon' => 'phosphor-user', 'color' => 'success'],
                    ['name' => 'Staff Wages', 'icon' => 'phosphor-user', 'color' => 'success'],
                    ['name' => 'Bonuses & Incentives', 'icon' => 'phosphor-gift', 'color' => 'warning'],
                ],
            ],
            [
                'name' => 'Marketing & Advertising',
                'description' => 'Promotional activities and advertising',
                'icon' => 'phosphor-megaphone',
                'color' => 'danger',
                'children' => [
                    ['name' => 'Digital Marketing', 'icon' => 'phosphor-devices', 'color' => 'info'],
                    ['name' => 'Print Advertising', 'icon' => 'phosphor-newspaper', 'color' => 'gray'],
                    ['name' => 'Events & Promotions', 'icon' => 'phosphor-confetti', 'color' => 'warning'],
                ],
            ],
            [
                'name' => 'Rent & Lease',
                'description' => 'Property rent and lease payments',
                'icon' => 'phosphor-buildings',
                'color' => 'info',
            ],
            [
                'name' => 'Cleaning & Housekeeping',
                'description' => 'Cleaning services and supplies',
                'icon' => 'phosphor-broom',
                'color' => 'success',
            ],
            [
                'name' => 'Office Supplies',
                'description' => 'Stationery, printer supplies, office items',
                'icon' => 'phosphor-clipboard-text',
                'color' => 'gray',
            ],
            [
                'name' => 'Repairs & Maintenance',
                'description' => 'Building and facility repairs',
                'icon' => 'phosphor-hammer',
                'color' => 'warning',
            ],
            [
                'name' => 'Licenses & Permits',
                'description' => 'Business licenses, permits, registrations',
                'icon' => 'phosphor-certificate',
                'color' => 'primary',
            ],
            [
                'name' => 'Training & Development',
                'description' => 'Staff training and certification costs',
                'icon' => 'phosphor-graduation-cap',
                'color' => 'info',
            ],
            [
                'name' => 'Insurance',
                'description' => 'Business, liability, and health insurance',
                'icon' => 'phosphor-shield-check',
                'color' => 'success',
            ],
            [
                'name' => 'Taxes & Government Fees',
                'description' => 'GST, property tax, government fees',
                'icon' => 'phosphor-receipt',
                'color' => 'danger',
            ],
            [
                'name' => 'Transportation',
                'description' => 'Vehicle, fuel, and travel expenses',
                'icon' => 'phosphor-car',
                'color' => 'gray',
            ],
            [
                'name' => 'Inventory & Stock',
                'description' => 'Supplements, merchandise, retail items',
                'icon' => 'phosphor-package',
                'color' => 'warning',
            ],
            [
                'name' => 'Miscellaneous',
                'description' => 'Other uncategorized expenses',
                'icon' => 'phosphor-dots-three',
                'color' => 'gray',
            ],
        ];
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot safely reverse this migration as data has been consolidated
        // Would need to re-seed categories for all gyms
    }
};
