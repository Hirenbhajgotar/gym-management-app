<?php

declare(strict_types=1);

namespace Tests\Feature\Feature;

use App\Filament\Admin\Resources\TrainerResource\Pages\ManageTrainers;
use App\Models\Gym;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class TrainerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_import_trainers()
    {
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->actingAs($user = User::factory()->create());
        $gym = Gym::factory()->create();
        $user->gyms()->attach($gym);
        Filament::setTenant($gym);

        $fileContent = <<<'CSV'
name,email,phone,specialization,experience_years,certifications,is_available,available_hours,basic_salary,pt_salary_per_member,currency
John Doe,john.doe@example.com,1234567890,Yoga,5,Certified Yoga Trainer,1,"Mon-Fri 9am-5pm",50000,100,USD
Jane Smith,jane.smith@example.com,0987654321,CrossFit,3,CrossFit Level 1,0,"Sat-Sun 10am-4pm",45000,120,USD
CSV;

        $file = UploadedFile::fake()->createWithContent('trainers.csv', $fileContent);

        Livewire::test(ManageTrainers::class)
            ->callPageAction('import', data: [
                'file' => $file,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('trainers', [
            'gym_id' => $gym->id,
            'email' => 'john.doe@example.com',
        ]);

        $this->assertDatabaseHas('trainers', [
            'gym_id' => $gym->id,
            'email' => 'jane.smith@example.com',
        ]);
    }
}
