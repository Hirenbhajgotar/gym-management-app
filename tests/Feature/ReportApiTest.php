<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->gym = Gym::factory()->create();
        $this->user->gyms()->attach($this->gym->id, ['role' => 'admin']);

        // Set current gym for user context
        Filament::setTenant($this->gym);

        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_api_requires_authentication(): void
    {
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/reports/members');
        $response->assertUnauthorized();
    }

    public function test_api_returns_members_report(): void
    {
        // Create test data
        GymMember::factory()->count(5)->create(['gym_id' => $this->gym->id]);

        $response = $this->getJson('/api/reports/members');

        $response->assertOk()
            ->assertJsonStructure([
                'stats',
                'growth',
                'plan_distribution',
                'churn_stats',
                'filters',
                'generated_at',
            ])
            ->assertJsonPath('generated_at', fn ($value) => filled($value));
    }

    public function test_api_returns_revenue_report(): void
    {
        $response = $this->getJson('/api/reports/revenue');

        $response->assertOk()
            ->assertJsonStructure([
                'stats',
                'pnl',
                'revenue_breakdown',
                'expense_breakdown',
                'filters',
                'generated_at',
            ]);
    }

    public function test_api_returns_leads_report(): void
    {
        $response = $this->getJson('/api/reports/leads');

        $response->assertOk()
            ->assertJsonStructure([
                'stats',
                'lead_funnel',
                'conversion_rates',
                'lead_sources',
                'filters',
                'generated_at',
            ]);
    }

    public function test_api_returns_attendance_report(): void
    {
        $response = $this->getJson('/api/reports/attendance');

        $response->assertOk()
            ->assertJsonStructure([
                'stats',
                'hourly_distribution',
                'daily_patterns',
                'weekly_trends',
                'filters',
                'generated_at',
            ]);
    }

    public function test_api_returns_dashboard_report(): void
    {
        $response = $this->getJson('/api/reports/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'member_stats',
                'revenue_stats',
                'lead_stats',
                'attendance_stats',
                'insights',
                'filters',
                'generated_at',
            ]);
    }

    public function test_api_accepts_filter_parameters(): void
    {
        $response = $this->getJson('/api/reports/members?date_range=last_7_days&start_date=2026-01-01&end_date=2026-01-07');

        $response->assertOk()
            ->assertJsonStructure([
                'stats',
                'filters',
                'generated_at',
            ])
            ->assertJsonPath('filters.date_range', 'last_7_days')
            ->assertJsonPath('filters.start_date', '2026-01-01')
            ->assertJsonPath('filters.end_date', '2026-01-07');
    }

    public function test_api_returns_json_content_type(): void
    {
        $response = $this->getJson('/api/reports/members');

        $response->assertHeader('content-type', 'application/json');
    }

    public function test_api_includes_timestamp(): void
    {
        $response = $this->getJson('/api/reports/members');

        $response->assertOk();
        $this->assertNotEmpty($response->json('generated_at'));

        // Verify it's a valid ISO 8601 date
        $generatedAt = $response->json('generated_at');
        $this->assertTrue(strtotime($generatedAt) !== false);
    }

    public function test_api_handles_invalid_endpoint(): void
    {
        $response = $this->getJson('/api/reports/invalid');

        $response->assertNotFound();
    }
}
