<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\WhatsAppAccountStatusEnum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppAccountStatusEnumTest extends TestCase
{
    #[Test]
    public function it_maps_null_to_pending(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::PENDING,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus(null)
        );
    }

    #[Test]
    public function it_maps_empty_string_to_pending(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::PENDING,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('')
        );
    }

    #[Test]
    public function it_maps_uppercase_none_to_pending(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::PENDING,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('NONE')
        );
    }

    #[Test]
    public function it_maps_lowercase_none_to_pending(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::PENDING,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('none')
        );
    }

    #[Test]
    public function it_maps_approved_to_active(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::ACTIVE,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('APPROVED')
        );
    }

    #[Test]
    public function it_maps_active_to_active(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::ACTIVE,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('ACTIVE')
        );
    }

    #[Test]
    public function it_maps_rejected_to_rejected(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::REJECTED,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('REJECTED')
        );
    }

    #[Test]
    public function it_maps_pending_to_pending(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::PENDING,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('PENDING')
        );
    }

    #[Test]
    public function it_maps_in_review_to_pending(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::PENDING,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('IN_REVIEW')
        );
    }

    #[Test]
    public function it_maps_disabled_to_restricted(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::RESTRICTED,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('DISABLED')
        );
    }

    #[Test]
    public function it_maps_suspended_to_suspended(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::SUSPENDED,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('SUSPENDED')
        );
    }

    #[Test]
    public function it_maps_blocked_to_blocked(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::BLOCKED,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('BLOCKED')
        );
    }

    #[Test]
    public function it_maps_banned_to_banned(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::BANNED,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('BANNED')
        );
    }

    #[Test]
    public function it_is_case_insensitive_for_known_statuses(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::ACTIVE,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('approved')
        );
        $this->assertSame(
            WhatsAppAccountStatusEnum::ACTIVE,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('Approved')
        );
        $this->assertSame(
            WhatsAppAccountStatusEnum::REJECTED,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('rejected')
        );
        $this->assertSame(
            WhatsAppAccountStatusEnum::SUSPENDED,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('suspended')
        );
    }

    #[Test]
    public function it_maps_unknown_status_to_pending(): void
    {
        $this->assertSame(
            WhatsAppAccountStatusEnum::PENDING,
            WhatsAppAccountStatusEnum::fromMetaReviewStatus('TOTALLY_UNKNOWN')
        );
    }
}
