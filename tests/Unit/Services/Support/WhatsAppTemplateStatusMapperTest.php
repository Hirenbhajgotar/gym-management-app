<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Support;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Services\Support\WhatsAppTemplateStatusMapper;
use PHPUnit\Framework\TestCase;

class WhatsAppTemplateStatusMapperTest extends TestCase
{
    public function test_maps_approved_status(): void
    {
        $this->assertSame(
            WhatsAppTemplateStatusEnum::APPROVED,
            WhatsAppTemplateStatusMapper::mapStatus('APPROVED')
        );
    }

    public function test_maps_approved_status_case_insensitive(): void
    {
        $this->assertSame(
            WhatsAppTemplateStatusEnum::APPROVED,
            WhatsAppTemplateStatusMapper::mapStatus('approved')
        );
        $this->assertSame(
            WhatsAppTemplateStatusEnum::APPROVED,
            WhatsAppTemplateStatusMapper::mapStatus('Approved')
        );
    }

    public function test_maps_rejected_status(): void
    {
        $this->assertSame(
            WhatsAppTemplateStatusEnum::REJECTED,
            WhatsAppTemplateStatusMapper::mapStatus('REJECTED')
        );
    }

    public function test_maps_flagged_to_rejected(): void
    {
        $this->assertSame(
            WhatsAppTemplateStatusEnum::REJECTED,
            WhatsAppTemplateStatusMapper::mapStatus('FLAGGED')
        );
    }

    public function test_maps_pending_status(): void
    {
        $this->assertSame(
            WhatsAppTemplateStatusEnum::PENDING,
            WhatsAppTemplateStatusMapper::mapStatus('PENDING')
        );
    }

    public function test_maps_pending_deletion_to_pending(): void
    {
        $this->assertSame(
            WhatsAppTemplateStatusEnum::PENDING,
            WhatsAppTemplateStatusMapper::mapStatus('PENDING_DELETION')
        );
    }

    public function test_maps_unapproved_to_pending(): void
    {
        $this->assertSame(
            WhatsAppTemplateStatusEnum::PENDING,
            WhatsAppTemplateStatusMapper::mapStatus('UNAPPROVED')
        );
    }

    public function test_maps_disabled_status(): void
    {
        $this->assertSame(
            WhatsAppTemplateStatusEnum::DISABLED,
            WhatsAppTemplateStatusMapper::mapStatus('DISABLED')
        );
    }

    public function test_maps_paused_to_disabled(): void
    {
        $this->assertSame(
            WhatsAppTemplateStatusEnum::DISABLED,
            WhatsAppTemplateStatusMapper::mapStatus('PAUSED')
        );
    }

    public function test_maps_unsubmitted_status(): void
    {
        $this->assertSame(
            WhatsAppTemplateStatusEnum::UNSUBMITTED,
            WhatsAppTemplateStatusMapper::mapStatus('UNSUBMITTED')
        );
    }

    public function test_returns_null_for_unknown_status(): void
    {
        $this->assertNull(WhatsAppTemplateStatusMapper::mapStatus('UNKNOWN'));
        $this->assertNull(WhatsAppTemplateStatusMapper::mapStatus(''));
        $this->assertNull(WhatsAppTemplateStatusMapper::mapStatus('random_text'));
    }
}
