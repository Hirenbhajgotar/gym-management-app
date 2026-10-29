<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\SuperAdmin;

use App\Enums\DefaultRole;
use App\Enums\ErrorPriority;
use App\Enums\ErrorStatus;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages\CreateErrorReport;
use App\Models\ErrorReport;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateErrorReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create();
        $role = Role::findOrCreate(DefaultRole::SUPER_ADMIN->value, 'web');
        $this->superAdmin->assignRole($role);

        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('super-admin'));
    }

    public function test_create_defaults_metadata_and_redacts_operator_text(): void
    {
        Livewire::test(CreateErrorReport::class)
            ->fillForm([
                'error_message' => 'failed for pushcommkey=749201',
                'user_description' => 'operator saw pushcommkey=749201',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $report = ErrorReport::query()->latest('id')->firstOrFail();

        $this->assertSame(ErrorStatus::PENDING, $report->status);
        $this->assertSame(ErrorPriority::MEDIUM, $report->priority);
        $this->assertSame('user_reported', $report->error_type);
        $this->assertSame($this->superAdmin->id, $report->user_id);
        $this->assertNotNull($report->occurred_at);
        $this->assertNotNull($report->last_occurrence);

        $this->assertStringNotContainsString('749201', (string) $report->error_message);
        $this->assertStringNotContainsString('749201', (string) $report->user_description);
    }

    public function test_create_accepts_a_description_without_an_error_message(): void
    {
        Livewire::test(CreateErrorReport::class)
            ->fillForm([
                'user_description' => 'The terminal could not connect.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $report = ErrorReport::query()->latest('id')->firstOrFail();

        $this->assertNull($report->error_message);
        $this->assertSame('The terminal could not connect.', $report->user_description);
    }

    public function test_create_requires_at_least_one_message_or_description(): void
    {
        Livewire::test(CreateErrorReport::class)
            ->fillForm([
                'error_message' => null,
                'user_description' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['error_message', 'user_description']);
    }
}
