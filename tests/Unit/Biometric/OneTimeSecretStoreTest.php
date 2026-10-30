<?php

declare(strict_types=1);

namespace Tests\Unit\Biometric;

use App\Biometrics\Services\OneTimeSecretStore;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OneTimeSecretStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_staging_does_not_publish_until_promote(): void
    {
        $this->assertTrue(OneTimeSecretStore::stage('probe', 1, 'staged-secret', 15));
        $this->assertFalse(OneTimeSecretStore::exists('probe', 1));
        $this->assertTrue(OneTimeSecretStore::hasPending('probe', 1));
        $this->assertNull(OneTimeSecretStore::pull('probe', 1));

        $this->assertTrue(OneTimeSecretStore::promote('probe', 1, 15));
        $this->assertFalse(OneTimeSecretStore::hasPending('probe', 1));
        $this->assertTrue(OneTimeSecretStore::exists('probe', 1));
        $this->assertSame('staged-secret', OneTimeSecretStore::pull('probe', 1));
    }

    public function test_forget_pending_leaves_an_existing_primary_untouched(): void
    {
        OneTimeSecretStore::put('probe', 2, 'old-secret', 15);
        $this->assertTrue(OneTimeSecretStore::stage('probe', 2, 'new-secret', 15));

        OneTimeSecretStore::forgetPending('probe', 2);

        $this->assertFalse(OneTimeSecretStore::hasPending('probe', 2));
        $this->assertSame('old-secret', OneTimeSecretStore::pull('probe', 2));
    }

    public function test_promote_without_a_staged_value_fails_closed(): void
    {
        $this->assertFalse(OneTimeSecretStore::promote('probe', 3, 15));
        $this->assertFalse(OneTimeSecretStore::exists('probe', 3));
    }

    public function test_delivery_copy_is_scoped_to_the_viewer(): void
    {
        OneTimeSecretStore::put('probe', 4, 'viewer-secret', 15);

        $first = OneTimeSecretStore::claimForDisplay('probe', 4, 'user-a');
        $this->assertSame('viewer-secret', $first['secret']);
        $this->assertTrue($first['claimed']);
        $this->assertTrue($first['first_delivery_read']);

        $rerender = OneTimeSecretStore::claimForDisplay('probe', 4, 'user-a');
        $this->assertSame('viewer-secret', $rerender['secret']);
        $this->assertFalse($rerender['claimed']);
        $this->assertFalse($rerender['first_delivery_read']);

        $otherUser = OneTimeSecretStore::claimForDisplay('probe', 4, 'user-b');
        $this->assertNull($otherUser['secret']);
        $this->assertFalse($otherUser['claimed']);
        $this->assertFalse($otherUser['first_delivery_read']);

        $this->assertTrue(OneTimeSecretStore::deliveryExists('probe', 4, 'user-a'));
        $this->assertFalse(OneTimeSecretStore::deliveryExists('probe', 4, 'user-b'));
    }

    public function test_a_new_primary_invalidates_old_viewer_delivery_copies(): void
    {
        OneTimeSecretStore::put('probe', 5, 'old-secret', 15);
        OneTimeSecretStore::claimForDisplay('probe', 5, 'user-a');

        OneTimeSecretStore::put('probe', 5, 'new-secret', 15);

        $claim = OneTimeSecretStore::claimForDisplay('probe', 5, 'user-a');

        $this->assertSame('new-secret', $claim['secret']);
        $this->assertTrue($claim['claimed']);
    }

    public function test_shared_legacy_reads_remain_available_without_a_viewer_key(): void
    {
        OneTimeSecretStore::put('probe', 6, 'shared-secret', 15);

        $claim = OneTimeSecretStore::claimForDisplay('probe', 6);

        $this->assertSame('shared-secret', $claim['secret']);
        $this->assertTrue($claim['claimed']);
        $this->assertSame('shared-secret', OneTimeSecretStore::claimForDisplay('probe', 6)['secret']);
    }

    public function test_forget_removes_primary_pending_and_delivery_copies(): void
    {
        OneTimeSecretStore::put('probe', 7, 'secret', 15);
        OneTimeSecretStore::claimForDisplay('probe', 7, 'user-a');
        OneTimeSecretStore::stage('probe', 7, 'pending', 15);

        OneTimeSecretStore::forget('probe', 7);

        $this->assertFalse(OneTimeSecretStore::exists('probe', 7));
        $this->assertFalse(OneTimeSecretStore::hasPending('probe', 7));
        $this->assertFalse(OneTimeSecretStore::deliveryExists('probe', 7, 'user-a'));
        $this->assertNull(OneTimeSecretStore::claimForDisplay('probe', 7, 'user-a')['secret']);
    }

    public function test_a_failed_promote_invalidates_the_previous_reveal(): void
    {
        OneTimeSecretStore::put('probe', 8, 'old-secret', 15);
        OneTimeSecretStore::claimForDisplay('probe', 8, 'user-a');

        $this->assertTrue(OneTimeSecretStore::stage('probe', 8, 'new-secret', 15));
        OneTimeSecretStore::forgetPending('probe', 8);

        // A staged value can disappear after the caller's database write
        // committed. The old primary must not stay readable in that case.
        $this->assertFalse(OneTimeSecretStore::promote('probe', 8, 15));
        $this->assertFalse(OneTimeSecretStore::exists('probe', 8));
        $this->assertNull(OneTimeSecretStore::pull('probe', 8));
        $this->assertFalse(OneTimeSecretStore::deliveryExists('probe', 8, 'user-a'));
        $this->assertNull(OneTimeSecretStore::claimForDisplay('probe', 8, 'user-a')['secret']);
    }

    public function test_put_clears_a_staged_value_so_promote_cannot_resurrect_it(): void
    {
        $this->assertTrue(OneTimeSecretStore::stage('probe', 9, 'staged-secret', 15));
        $this->assertTrue(OneTimeSecretStore::put('probe', 9, 'primary-secret', 15));

        $this->assertFalse(OneTimeSecretStore::hasPending('probe', 9));
        $this->assertFalse(OneTimeSecretStore::promote('probe', 9, 15));
        $this->assertFalse(OneTimeSecretStore::exists('probe', 9));
        $this->assertNull(OneTimeSecretStore::pull('probe', 9));
    }

    public function test_stage_and_put_fail_closed_while_the_store_lock_is_held(): void
    {
        $lock = Cache::lock('probe:10:lock', 10);
        $this->assertTrue($lock->get());

        try {
            $this->assertFalse(OneTimeSecretStore::stage('probe', 10, 'staged-secret', 15));
            $this->assertFalse(OneTimeSecretStore::put('probe', 10, 'primary-secret', 15));
            $this->assertFalse(OneTimeSecretStore::hasPending('probe', 10));
            $this->assertFalse(OneTimeSecretStore::exists('probe', 10));
        } finally {
            $lock->release();
        }

        $this->assertTrue(OneTimeSecretStore::stage('probe', 10, 'staged-secret', 15));
        $this->assertTrue(OneTimeSecretStore::put('probe', 10, 'primary-secret', 15));
        $this->assertSame('primary-secret', OneTimeSecretStore::pull('probe', 10));
    }
}
