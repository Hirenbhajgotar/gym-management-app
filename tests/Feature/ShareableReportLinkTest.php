<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\ShareableReportLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareableReportLinkTest extends TestCase
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
    }

    public function test_shareable_report_link_can_be_created(): void
    {
        $link = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class,
            'filters' => ['date_range' => 'last_30_days'],
        ]);

        $this->assertNotNull($link->token);
        $this->assertEquals(64, strlen($link->token));
        $this->assertTrue($link->isValid());
        $this->assertEquals($this->gym->id, $link->gym_id);
        $this->assertEquals($this->user->id, $link->user_id);
    }

    public function test_shareable_report_link_generates_unique_token(): void
    {
        $link1 = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class,
        ]);

        $link2 = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\RevenueReports::class,
        ]);

        $this->assertNotEquals($link1->token, $link2->token);
    }

    public function test_shareable_report_link_can_expire(): void
    {
        $link = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($link->isExpired());
        $this->assertFalse($link->isValid());
    }

    public function test_shareable_report_link_can_be_deactivated(): void
    {
        $link = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class,
            'is_active' => false,
        ]);

        $this->assertFalse($link->is_active);
        $this->assertFalse($link->isValid());
    }

    public function test_shareable_report_link_returns_shareable_url(): void
    {
        $link = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class,
        ]);

        $expectedUrl = route('shared-reports.show', $link->token);
        $this->assertEquals($expectedUrl, $link->getShareableUrl());
    }

    public function test_shared_report_controller_shows_valid_link(): void
    {
        $link = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class,
            'filters' => ['date_range' => 'last_30_days'],
        ]);

        $response = $this->get(route('shared-reports.show', $link->token));

        $response->assertStatus(200);
        $response->assertViewIs('shared-reports.show');
        $response->assertViewHas('link', function ($viewLink) use ($link) {
            return $viewLink->id === $link->id;
        });
    }

    public function test_shared_report_controller_returns_404_for_invalid_token(): void
    {
        $response = $this->get(route('shared-reports.show', 'invalid-token'));

        $response->assertStatus(404);
    }

    public function test_shared_report_controller_returns_404_for_expired_link(): void
    {
        $link = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->get(route('shared-reports.show', $link->token));

        $response->assertStatus(404);
    }

    public function test_shared_report_controller_returns_404_for_inactive_link(): void
    {
        $link = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class,
            'is_active' => false,
        ]);

        $response = $this->get(route('shared-reports.show', $link->token));

        $response->assertStatus(404);
    }

    public function test_shareable_report_link_scopes(): void
    {
        // Create active links
        $activeLink1 = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class,
        ]);

        $activeLink2 = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\RevenueReports::class,
        ]);

        // Create inactive link
        $inactiveLink = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\LeadReports::class,
            'is_active' => false,
        ]);

        // Create expired link
        $expiredLink = ShareableReportLink::create([
            'gym_id' => $this->gym->id,
            'user_id' => $this->user->id,
            'report_type' => \App\Filament\Admin\Clusters\Reports\Pages\AttendanceReports::class,
            'expires_at' => now()->subDay(),
        ]);

        // Test active scope
        $activeLinks = ShareableReportLink::active()->get();
        $this->assertCount(2, $activeLinks);
        $this->assertTrue($activeLinks->contains('id', $activeLink1->id));
        $this->assertTrue($activeLinks->contains('id', $activeLink2->id));
        $this->assertFalse($activeLinks->contains('id', $inactiveLink->id));

        // Test not expired scope
        $notExpiredLinks = ShareableReportLink::notExpired()->get();
        $this->assertCount(2, $notExpiredLinks);
        $this->assertTrue($notExpiredLinks->contains('id', $activeLink1->id));
        $this->assertTrue($notExpiredLinks->contains('id', $activeLink2->id));
        $this->assertFalse($notExpiredLinks->contains('id', $expiredLink->id));

        // Test valid scope (active and not expired)
        $validLinks = ShareableReportLink::valid()->get();
        $this->assertCount(2, $validLinks);
        $this->assertTrue($validLinks->contains('id', $activeLink1->id));
        $this->assertTrue($validLinks->contains('id', $activeLink2->id));
        $this->assertFalse($validLinks->contains('id', $inactiveLink->id));
        $this->assertFalse($validLinks->contains('id', $expiredLink->id));
    }
}
