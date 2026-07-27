<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\WhatsAppAccountVerificationStatusEnum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppAccountVerificationStatusEnumTest extends TestCase
{
    #[Test]
    public function it_maps_null_to_not_verified(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus(null)
        );
    }

    #[Test]
    public function it_maps_empty_string_to_not_verified(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('')
        );
    }

    #[Test]
    public function it_maps_uppercase_none_to_not_verified(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('NONE')
        );
    }

    #[Test]
    public function it_maps_lowercase_none_to_not_verified(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('none')
        );
    }

    #[Test]
    public function it_maps_verified_to_verified(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::VERIFIED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('VERIFIED')
        );
    }

    #[Test]
    public function it_maps_not_verified_to_not_verified(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('NOT_VERIFIED')
        );
    }

    #[Test]
    public function it_maps_unverified_alias_to_not_verified(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('UNVERIFIED')
        );
    }

    #[Test]
    public function it_maps_in_review_to_in_review(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::IN_REVIEW,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('IN_REVIEW')
        );
    }

    #[Test]
    public function it_maps_pending_alias_to_in_review(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::IN_REVIEW,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('PENDING')
        );
    }

    #[Test]
    public function it_maps_rejected_to_rejected(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::REJECTED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('REJECTED')
        );
    }

    #[Test]
    public function it_maps_failed_to_failed(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::FAILED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('FAILED')
        );
    }

    #[Test]
    public function it_maps_expired_to_expired(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::EXPIRED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('EXPIRED')
        );
    }

    #[Test]
    public function it_is_case_insensitive_for_known_statuses(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::VERIFIED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('verified')
        );
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::VERIFIED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('Verified')
        );
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::FAILED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('failed')
        );
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::EXPIRED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('expired')
        );
    }

    #[Test]
    public function it_maps_unknown_status_to_not_verified(): void
    {
        $this->assertSame(
            WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED,
            WhatsAppAccountVerificationStatusEnum::fromMetaVerificationStatus('TOTALLY_UNKNOWN')
        );
    }
}
