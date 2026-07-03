<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Imports\GymMemberImport;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GymMemberImportTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->actingAs(User::factory()->create());
        Filament::setTenant($this->gym);
    }

    #[Test]
    public function it_imports_unique_rows(): void
    {
        // Given
        MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Basic Plan',
            'duration' => 3,
            'price' => 3000,
        ]);

        $importer = new GymMemberImport($this->gym->id);

        $mockData = new Collection([
            new Collection([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '9876543210',
            ]),
            new Collection([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '9876543211',
            ]),
        ]);

        // When
        $importer->collection($mockData);

        // Then
        $this->assertDatabaseHas('gym_members', ['email' => 'john@example.com']);
        $this->assertDatabaseHas('gym_members', ['email' => 'jane@example.com']);
    }

    #[Test]
    public function it_fails_when_duplicate_phone_exists_in_database(): void
    {
        // Given — existing member with a formatted phone (+91 prefix)
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'email' => 'existing@example.com',
            'phone' => '+919876543210',
        ]);

        $importer = new GymMemberImport($this->gym->id);

        // Import row with the raw phone "9876543210".
        // transformForValidation will format it to "+919876543210",
        // then the unique rule on phone will catch the collision.
        $mockData = new Collection([
            new Collection([
                'name' => 'Duplicate Attempt',
                'email' => 'new@example.com',
                'phone' => '9876543210',
            ]),
        ]);

        // When
        $importer->collection($mockData);

        // Then — no new member created, failed row recorded
        $this->assertDatabaseMissing('gym_members', [
            'email' => 'new@example.com',
        ]);

        $this->assertDatabaseHas('failed_import_rows', [
            'validation_error' => 'The phone has already been taken.',
        ]);
    }

    #[Test]
    public function it_fails_when_duplicate_phone_in_same_file(): void
    {
        // Given
        $importer = new GymMemberImport($this->gym->id);

        // Two rows sharing the same raw phone number
        $mockData = new Collection([
            new Collection([
                'name' => 'First Member',
                'email' => 'first@example.com',
                'phone' => '9876543210',
            ]),
            new Collection([
                'name' => 'Second Member',
                'email' => 'second@example.com',
                'phone' => '9876543210',  // Same raw phone
            ]),
        ]);

        // When
        $importer->collection($mockData);

        // Then — first row succeeds, second row fails
        $this->assertDatabaseHas('gym_members', ['email' => 'first@example.com']);
        $this->assertDatabaseMissing('gym_members', ['email' => 'second@example.com']);

        $this->assertDatabaseHas('failed_import_rows', [
            'validation_error' => 'The phone has already been taken.',
        ]);
    }

    #[Test]
    public function it_fails_when_duplicate_email_and_phone_match_existing_member(): void
    {
        // Given — existing member
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'email' => 'existing@example.com',
            'phone' => '+919876543210',
        ]);

        $importer = new GymMemberImport($this->gym->id);

        // Row matches existing member on BOTH email AND phone
        $mockData = new Collection([
            new Collection([
                'name' => 'Duplicate Member',
                'email' => 'existing@example.com',
                'phone' => '9876543210',  // Formats to +919876543210, matching existing
            ]),
        ]);

        // When
        $importer->collection($mockData);

        // Then — caught by unique validation on phone
        $this->assertDatabaseMissing('gym_members', [
            'email' => 'existing@example.com',
            'name' => 'Duplicate Member',
        ]);

        $this->assertDatabaseHas('failed_import_rows', [
            'validation_error' => 'The phone has already been taken.',
        ]);
    }

    #[Test]
    public function it_tracks_correct_success_and_failure_counts(): void
    {
        // Given
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876543210',
        ]);

        $importer = new GymMemberImport($this->gym->id);

        // One unique + one duplicate
        $mockData = new Collection([
            new Collection([
                'name' => 'Good Member',
                'email' => 'good@example.com',
                'phone' => '1111111111',
            ]),
            new Collection([
                'name' => 'Duplicate Member',
                'email' => 'bad@example.com',
                'phone' => '9876543210',  // Duplicate of existing
            ]),
        ]);

        // When
        $importer->collection($mockData);

        // Then
        $import = $importer->getImportRecord();

        $this->assertNotNull($import);
        $this->assertEquals(1, $import->successful_rows);
        $this->assertEquals(1, $import->processed_rows - $import->successful_rows);

        $this->assertDatabaseHas('gym_members', ['email' => 'good@example.com']);
    }
}
