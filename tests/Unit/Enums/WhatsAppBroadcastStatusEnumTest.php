<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\WhatsAppBroadcastStatusEnum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WhatsAppBroadcastStatusEnumTest extends TestCase
{
    #[Test]
    public function it_computes_completed_when_all_succeed(): void
    {
        $status = WhatsAppBroadcastStatusEnum::fromCounts(10, 0, 10);
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED, $status);
    }

    #[Test]
    public function it_computes_completed_with_errors_when_mixed(): void
    {
        $status = WhatsAppBroadcastStatusEnum::fromCounts(7, 3, 10);
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED_WITH_ERRORS, $status);
    }

    #[Test]
    public function it_computes_failed_when_all_fail(): void
    {
        $status = WhatsAppBroadcastStatusEnum::fromCounts(0, 10, 10);
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $status);
    }

    #[Test]
    public function it_computes_failed_when_zero_sent_and_some_failed(): void
    {
        $status = WhatsAppBroadcastStatusEnum::fromCounts(0, 5, 10);
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $status);
    }

    #[Test]
    public function it_computes_failed_when_all_opted_out_zero_sent_and_target_exists(): void
    {
        $status = WhatsAppBroadcastStatusEnum::fromCounts(0, 0, 10);
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $status);
    }

    #[Test]
    public function it_computes_completed_when_zero_sent_zero_failed_and_zero_target(): void
    {
        $status = WhatsAppBroadcastStatusEnum::fromCounts(0, 0, 0);
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED, $status);
    }

    #[Test]
    public function it_identifies_finished_states(): void
    {
        $this->assertTrue(WhatsAppBroadcastStatusEnum::COMPLETED->isFinished());
        $this->assertTrue(WhatsAppBroadcastStatusEnum::COMPLETED_WITH_ERRORS->isFinished());
        $this->assertTrue(WhatsAppBroadcastStatusEnum::FAILED->isFinished());
        $this->assertTrue(WhatsAppBroadcastStatusEnum::CANCELLED->isFinished());

        $this->assertFalse(WhatsAppBroadcastStatusEnum::DRAFT->isFinished());
        $this->assertFalse(WhatsAppBroadcastStatusEnum::SENDING->isFinished());
    }

    #[Test]
    public function it_identifies_non_counting_states(): void
    {
        $this->assertTrue(WhatsAppBroadcastStatusEnum::DRAFT->isNonCounting());
        $this->assertTrue(WhatsAppBroadcastStatusEnum::CANCELLED->isNonCounting());
        $this->assertTrue(WhatsAppBroadcastStatusEnum::FAILED->isNonCounting());

        $this->assertFalse(WhatsAppBroadcastStatusEnum::SENDING->isNonCounting());
        $this->assertFalse(WhatsAppBroadcastStatusEnum::COMPLETED->isNonCounting());
        $this->assertFalse(WhatsAppBroadcastStatusEnum::COMPLETED_WITH_ERRORS->isNonCounting());

        $this->assertEquals(['draft', 'cancelled', 'failed'], WhatsAppBroadcastStatusEnum::nonCountingValues());
    }
}
