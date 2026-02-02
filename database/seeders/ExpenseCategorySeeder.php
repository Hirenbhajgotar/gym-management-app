<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Default expense categories with icons and colors.
     * These are seeded as system-wide defaults (gym_id = NULL, is_system = true)
     */
    protected array $defaultCategories = [
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

    /**
     * Run the database seeds.
     * Seeds system-wide default categories (gym_id = NULL)
     */
    public function run(): void
    {
        $this->seedSystemDefaults();
    }

    /**
     * Seed system-wide default categories
     */
    public function seedSystemDefaults(): void
    {
        $order = 0;

        foreach ($this->defaultCategories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $category = ExpenseCategory::firstOrCreate(
                [
                    'gym_id' => null,
                    'name' => $categoryData['name'],
                    'parent_id' => null,
                ],
                [
                    ...$categoryData,
                    'order' => $order++,
                    'is_active' => true,
                    'is_system' => true,
                ]
            );

            // Ensure is_system is set for existing records
            if (! $category->is_system) {
                $category->update(['is_system' => true]);
            }

            // Create child categories
            $childOrder = 0;
            foreach ($children as $childData) {
                $child = ExpenseCategory::firstOrCreate(
                    [
                        'gym_id' => null,
                        'name' => $childData['name'],
                        'parent_id' => $category->id,
                    ],
                    [
                        ...$childData,
                        'description' => null,
                        'order' => $childOrder++,
                        'is_active' => true,
                        'is_system' => true,
                    ]
                );

                if (! $child->is_system) {
                    $child->update(['is_system' => true]);
                }
            }
        }
    }

    /**
     * Get the default categories array (useful for migrations)
     */
    public function getDefaultCategories(): array
    {
        return $this->defaultCategories;
    }
}
