<?php

declare(strict_types=1);

namespace Tests\Unit\Biometrics;

use App\Biometrics\ValueObjects\AdmsCommandKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `AdmsCommandKey::tryParse()` is the sole interpreter of a durable wire contract whose parsed
 * result decides which template a hardware acknowledgement belongs to. A permissive parse
 * mis-routes that decision, so every non-canonical shape must fail closed.
 */
class AdmsCommandKeyTest extends TestCase
{
    #[Test]
    public function a_user_add_key_round_trips_through_the_parser(): void
    {
        $key = AdmsCommandKey::buildUserAdd(7, 3, 42, 11);
        $parsed = AdmsCommandKey::tryParse($key);

        $this->assertNotNull($parsed);
        $this->assertSame('user_add', $parsed->commandType);
        $this->assertSame(7, $parsed->gymId);
        $this->assertSame(3, $parsed->deviceId);
        $this->assertSame(42, $parsed->memberId);
        $this->assertSame(11, $parsed->templateId);
        $this->assertNull($parsed->stage);
        $this->assertFalse($parsed->isSiblingSync());
    }

    #[Test]
    public function a_sibling_fan_out_key_round_trips_and_is_recognised(): void
    {
        $key = AdmsCommandKey::buildUserAdd(7, 3, 42, 11, AdmsCommandKey::STAGE_SYNC);
        $parsed = AdmsCommandKey::tryParse($key);

        $this->assertNotNull($parsed);
        $this->assertSame(AdmsCommandKey::STAGE_SYNC, $parsed->stage);
        $this->assertSame(11, $parsed->templateId);
        $this->assertTrue($parsed->isSiblingSync());
    }

    #[Test]
    public function a_daily_reconciler_key_round_trips_and_is_not_a_sibling_fan_out(): void
    {
        $key = AdmsCommandKey::buildUserAdd(7, 3, 42, 11, AdmsCommandKey::STAGE_RECONCILE);
        $parsed = AdmsCommandKey::tryParse($key);

        $this->assertNotNull($parsed);
        $this->assertSame(AdmsCommandKey::STAGE_RECONCILE, $parsed->stage);
        $this->assertSame(11, $parsed->templateId);
        $this->assertFalse($parsed->isSiblingSync(), 'A reconciler re-drive targets its own device, not a sibling.');
    }

    #[Test]
    public function a_user_delete_key_round_trips_and_carries_the_pin(): void
    {
        $key = AdmsCommandKey::buildUserDelete(3, '42');
        $parsed = AdmsCommandKey::tryParse($key);

        $this->assertSame('user_delete:3:42', $key);
        $this->assertNotNull($parsed);
        $this->assertSame('user_delete', $parsed->commandType);
        $this->assertSame(3, $parsed->deviceId);
        $this->assertSame('42', $parsed->pin);
        $this->assertNull($parsed->templateId);
    }

    #[Test]
    public function a_legacy_template_scoped_user_delete_key_still_parses(): void
    {
        $parsed = AdmsCommandKey::tryParse('user_delete:3:42:11');

        $this->assertNotNull($parsed, 'Durable legacy rows must stay readable.');
        $this->assertSame(3, $parsed->deviceId);
        $this->assertSame('42', $parsed->pin);
        $this->assertSame(11, $parsed->templateId);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function malformedKeys(): array
    {
        return [
            'empty' => ['', 'blank key'],
            'whitespace' => ['   ', 'blank key'],
            'unknown command' => ['user_purge:1:2:3', 'unknown command type'],
            'missing member' => ['user_add:1:2', 'incomplete user_add'],
            'missing device' => ['user_add:1::3:4', 'non-numeric device id'],
            'zero gym' => ['user_add:0:2:3:4', 'non-positive gym id'],
            'negative device' => ['user_add:1:-2:3:4', 'non-numeric device id'],
            'non numeric member' => ['user_add:1:2:abc:4', 'non-numeric member id'],
            'tail without a template id' => ['user_add:1:2:3:sync', 'missing template id'],
            'tail is not a stage' => ['user_add:1:2:3:Sync', 'unknown stage shape'],
            'numeric before template id' => ['user_add:1:2:3:9:4', 'extra segment'],
            'unknown trailing segments' => ['user_add:1:2:3:sync:4:extra', 'extra segment'],
            'delete without a pin' => ['user_delete:3:', 'empty pin'],
            'delete without a device' => ['user_delete:', 'missing device id'],
        ];
    }

    #[Test]
    #[DataProvider('malformedKeys')]
    public function a_malformed_key_fails_closed(string $key, string $why): void
    {
        $this->assertNull(
            AdmsCommandKey::tryParse($key),
            "Expected a null parse ({$why}) for key: '{$key}'"
        );
    }

    #[Test]
    public function a_user_add_key_without_a_stage_or_template_id_is_accepted_as_unscoped(): void
    {
        $parsed = AdmsCommandKey::tryParse('user_add:1:2:3');

        $this->assertNotNull($parsed);
        $this->assertNull($parsed->stage);
        $this->assertNull($parsed->templateId);
        $this->assertFalse($parsed->isSiblingSync());
    }
}
