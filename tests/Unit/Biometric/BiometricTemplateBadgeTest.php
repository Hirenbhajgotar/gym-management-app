<?php

declare(strict_types=1);

namespace Tests\Unit\Biometric;

use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\BiometricTemplate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiometricTemplateBadgeTest extends TestCase
{
    #[Test]
    public function badge_label_and_color_reflect_hardware_delivery_for_propagated_template(): void
    {
        // Propagated without hardware confirmation
        $template = new BiometricTemplate();
        $template->sync_status = BiometricTemplateSyncStatusEnum::Propagated;
        $template->hardware_confirmed_at = null;
        $template->enrolled_at = null;

        $this->assertSame('Propagated (Awaiting Device)', $template->getSyncStatusBadgeLabel());
        $this->assertSame('warning', $template->getSyncStatusBadgeColor());

        // Propagated with hardware confirmation
        $templateConfirmed = new BiometricTemplate();
        $templateConfirmed->sync_status = BiometricTemplateSyncStatusEnum::Propagated;
        $templateConfirmed->hardware_confirmed_at = now();

        $this->assertSame('Propagated (Confirmed)', $templateConfirmed->getSyncStatusBadgeLabel());
        $this->assertSame('success', $templateConfirmed->getSyncStatusBadgeColor());
    }

    #[Test]
    public function badge_label_and_color_return_enum_defaults_for_other_statuses(): void
    {
        $synced = new BiometricTemplate();
        $synced->sync_status = BiometricTemplateSyncStatusEnum::Synced;
        $this->assertSame('Synced', $synced->getSyncStatusBadgeLabel());
        $this->assertSame('success', $synced->getSyncStatusBadgeColor());

        $pending = new BiometricTemplate();
        $pending->sync_status = BiometricTemplateSyncStatusEnum::Pending;
        $this->assertSame('Pending', $pending->getSyncStatusBadgeLabel());
        $this->assertSame('info', $pending->getSyncStatusBadgeColor());

        $pendingDeletion = new BiometricTemplate();
        $pendingDeletion->sync_status = BiometricTemplateSyncStatusEnum::PendingDeletion;
        $this->assertSame('Pending Hardware Deletion', $pendingDeletion->getSyncStatusBadgeLabel());
        $this->assertSame('danger', $pendingDeletion->getSyncStatusBadgeColor());

        $deleted = new BiometricTemplate();
        $deleted->sync_status = BiometricTemplateSyncStatusEnum::Deleted;
        $this->assertSame('Deleted', $deleted->getSyncStatusBadgeLabel());
        $this->assertSame('danger', $deleted->getSyncStatusBadgeColor());

        $superseded = new BiometricTemplate();
        $superseded->sync_status = BiometricTemplateSyncStatusEnum::Superseded;
        $this->assertSame('Superseded', $superseded->getSyncStatusBadgeLabel());
        $this->assertSame('gray', $superseded->getSyncStatusBadgeColor());
    }

    #[Test]
    public function null_status_falls_back_gracefully(): void
    {
        $template = new BiometricTemplate();
        $template->sync_status = null;

        $this->assertSame('Unknown', $template->getSyncStatusBadgeLabel());
        $this->assertSame('gray', $template->getSyncStatusBadgeColor());
    }
}
