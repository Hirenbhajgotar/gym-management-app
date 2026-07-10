<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use App\Livewire\Frontend\HomeController;
use App\Services\ErrorReportService;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportReleaseTokens\ReleaseToken;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    #[Test]
    public function selected_plan_is_null_and_locked_against_client_updates(): void
    {
        /** @var Testable $component */
        $component = Livewire::test(HomeController::class);
        $component->assertSet('selectedPlan', null);

        // selectedPlan is never set by legitimate client code (the view uses
        // wire:click="selectPlan($planId)"), so a client-supplied update must
        // be rejected. This mirrors the #[Locked] hardening on the Toast component.
        try {
            /** @var Testable $malicious */
            $malicious = Livewire::test(HomeController::class);
            $malicious->set('selectedPlan', 5);
            $this->fail('Expected CannotUpdateLockedPropertyException was not thrown.');
        } catch (CannotUpdateLockedPropertyException $e) {
            $this->assertStringContainsString('selectedPlan', $e->getMessage());
        }
    }

    #[Test]
    public function it_skips_auto_reporting_for_corrupt_livewire_payloads(): void
    {
        // Tampered/corrupted Livewire payloads are attacker probes, not app bugs,
        // so they must not create error reports (or page on-call).
        /** @var ErrorReportService $service */
        $service = resolve(ErrorReportService::class);

        $this->assertTrue($service->shouldSkipAutoReporting(new CorruptComponentPayloadException()));
    }

    #[Test]
    public function it_rejects_a_tampered_livewire_payload_with_a_400_instead_of_a_500(): void
    {
        // Mirrors the production attack: a hand-crafted /livewire/update POST whose
        // snapshot checksum cannot be recomputed without the app key. Livewire's
        // checksum verification must reject it, and our handler must respond 400
        // (not a 500 with a stack trace / error report).
        $snapshot = [
            'data' => ['selectedPlan' => ['malicious' => 'object']],
            'memo' => ['name' => 'frontend.home-controller', 'id' => 'abc123'],
            'checksum' => 'tampered-checksum-value',
        ];

        // The production attack supplied a valid CSRF token, so CSRF passed and
        // the request reached Livewire. Mirror that here with a valid token so we
        // exercise Livewire's checksum verification + our 400 handler. The
        // `release` value must match the current deployment or Livewire short-
        // circuits with its own 419 ("release token mismatch") before reaching
        // the checksum check.
        $token = 'test-csrf-token';
        $snapshot['memo']['release'] = ReleaseToken::generate(HomeController::class);

        $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->withHeader('X-Livewire', 'true')
            ->postJson('/livewire/update', [
                'components' => [
                    [
                        'snapshot' => json_encode($snapshot),
                        'updates' => [],
                        'calls' => [],
                    ],
                ],
                '_token' => $token,
            ])
            ->assertStatus(400);
    }
}
