<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Frontend;

use App\Actions\Frontend\RequestCallbackAction;
use App\Models\RequestContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequestCallbackActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_callback_request_record_with_provided_details(): void
    {
        $action = new RequestCallbackAction();

        $record = $action(
            phone: '+919820012345',
            name: 'Rahul Sharma',
            gymName: 'Powerhouse Gym',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0 TestBrowser',
        );

        $this->assertInstanceOf(RequestContact::class, $record);
        $this->assertSame('Rahul Sharma', $record->name);
        $this->assertSame('+919820012345', (string) $record->phone);
        $this->assertSame('Powerhouse Gym', $record->company_name);
        $this->assertSame('callback_919820012345@fluxfitness.in', $record->email);
        $this->assertSame('192.168.1.1', $record->ip_address);
        $this->assertSame('Mozilla/5.0 TestBrowser', $record->user_agent);

        $this->assertDatabaseHas('request_contacts', [
            'id' => $record->id,
            'name' => 'Rahul Sharma',
            'phone' => '+919820012345',
            'company_name' => 'Powerhouse Gym',
        ]);
    }

    #[Test]
    public function it_normalizes_national_format_phone_number_to_e164_and_reads_back_without_exception(): void
    {
        $action = new RequestCallbackAction();

        // 10-digit national Indian number without '+'
        $record = $action(
            phone: '9820012345',
            name: 'Pooja Patel',
            gymName: 'FitLife Gym',
        );

        // Verify normalized to E.164
        $this->assertSame('+919820012345', (string) $record->phone);

        // Reload from database to test Eloquent RawPhoneNumberCast reading
        $fresh = RequestContact::query()->findOrFail($record->id);
        $this->assertSame('+919820012345', (string) $fresh->phone);
    }

    #[Test]
    public function it_uses_defaults_when_optional_details_are_empty(): void
    {
        $action = new RequestCallbackAction();

        $record = $action(
            phone: '+919820011111',
            name: null,
            gymName: null,
        );

        $this->assertSame('Callback Request', $record->name);
        $this->assertNull($record->company_name);
        $this->assertSame('callback_919820011111@fluxfitness.in', $record->email);
    }

    #[Test]
    public function it_truncates_oversized_fields_safely(): void
    {
        $action = new RequestCallbackAction();

        $longName = str_repeat('A', 300);
        $longGym = str_repeat('B', 300);
        $longUA = str_repeat('C', 500);

        $record = $action(
            phone: '+919820099999',
            name: $longName,
            gymName: $longGym,
            ipAddress: '127.0.0.1',
            userAgent: $longUA,
        );

        $this->assertSame(255, mb_strlen($record->name));
        $this->assertSame(255, mb_strlen((string) $record->company_name));
        $this->assertSame(255, mb_strlen((string) $record->user_agent));
    }

    #[Test]
    public function it_throws_invalid_argument_exception_for_empty_phone(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $action = new RequestCallbackAction();
        $action(phone: '   ');
    }

    #[Test]
    public function it_throws_invalid_argument_exception_for_unparseable_phone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid phone number format for callback: not-a-phone');

        $action = new RequestCallbackAction();
        $action(phone: 'not-a-phone');
    }

    #[Test]
    public function it_returns_existing_record_idempotently_when_submitted_within_fifteen_minutes(): void
    {
        $action = new RequestCallbackAction();

        $first = $action(
            phone: '+919820055555',
            name: 'Priya Sharma',
            gymName: 'Fitness Hub',
        );

        $second = $action(
            phone: '+919820055555',
            name: 'Priya Sharma',
            gymName: 'Fitness Hub',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, RequestContact::query()->where('phone', '+919820055555')->count());
    }
}
