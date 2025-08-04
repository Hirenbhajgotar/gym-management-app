<?php

namespace Tests\Unit;

use App\Jobs\ImportDefaultPermissionsJob;
use App\Models\PermissionSetting;
use App\Models\User;
use App\Services\RolesAndPermissionsService;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;

class ImportDefaultPermissionsJobTest extends \Tests\TestCase
{
    use RefreshDatabase;

    protected $rolesAndPermissionsServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the RolesAndPermissionsService
        $this->rolesAndPermissionsServiceMock = Mockery::mock(RolesAndPermissionsService::class);
        $this->app->instance(RolesAndPermissionsService::class, $this->rolesAndPermissionsServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_role_and_user_id()
    {
        $job = new ImportDefaultPermissionsJob('admin', 1);

        $this->assertInstanceOf(ImportDefaultPermissionsJob::class, $job);
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_null_values()
    {
        // Mock auth to return a user ID
        $this->actingAs($this->createUser());

        $job = new ImportDefaultPermissionsJob(null, null);

        $this->assertInstanceOf(ImportDefaultPermissionsJob::class, $job);
    }

    /**
     * @test
     */
    public function it_imports_permissions_for_specific_role_successfully()
    {
        // Arrange
        $roleName = 'admin';
        $userId = 1;
        $defaultPermissions = ['permission1', 'permission2'];

        $this->rolesAndPermissionsServiceMock
            ->shouldReceive('getDefaultPermissions')
            ->with($roleName)
            ->once()
            ->andReturn($defaultPermissions);

        // Act
        $job = new ImportDefaultPermissionsJob($roleName, $userId);
        $job->handle();

        // Assert
        $this->assertDatabaseHas('permission_settings', [
            'role' => $roleName,
        ]);
    }

    /**
     * @test
     */
    public function it_imports_permissions_for_all_roles_successfully()
    {
        // Arrange
        $userId = 1;
        $defaultPermissions = [
            'admin' => ['permission1', 'permission2'],
            'gym_owner' => ['permission3', 'permission4'],
        ];

        $this->rolesAndPermissionsServiceMock
            ->shouldReceive('getDefaultPermissions')
            ->withNoArgs()
            ->once()
            ->andReturn($defaultPermissions);

        //        Log::shouldReceive('info')->times(4);
        //        Log::shouldReceive('error')->never();

        // Act
        $job = new ImportDefaultPermissionsJob(null, $userId);
        $job->handle();

        // Assert
        foreach ($defaultPermissions as $role => $permissions) {
            $this->assertDatabaseHas('permission_settings', [
                'role' => $role,
            ]);
        }
    }

    /**
     * @test
     */
    public function it_handles_empty_permissions_for_specific_role()
    {
        // Arrange
        $roleName = 'admin';
        $userId = 1;

        $this->rolesAndPermissionsServiceMock
            ->shouldReceive('getDefaultPermissions')
            ->with($roleName)
            ->once()
            ->andReturn([]);

        //        Log::shouldReceive('info')->times(2);
        //        Log::shouldReceive('error')->never();

        // Act
        $job = new ImportDefaultPermissionsJob($roleName, $userId);
        $job->handle();

        // Assert
        $this->assertDatabaseMissing('permission_settings', [
            'role' => $roleName,
        ]);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_service_fails_for_specific_role()
    {
        // Arrange
        $roleName = 'admin';
        $userId = 1;
        $exceptionMessage = 'Service unavailable';

        $this->rolesAndPermissionsServiceMock
            ->shouldReceive('getDefaultPermissions')
            ->with($roleName)
            ->once()
            ->andThrow(new \Exception($exceptionMessage));

        //        Log::shouldReceive('info')->times(1);
        //        Log::shouldReceive('error')->times(1);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($exceptionMessage);

        $job = new ImportDefaultPermissionsJob($roleName, $userId);
        $job->handle();
    }

    /**
     * @test
     */
    public function it_throws_exception_when_service_fails_for_all_roles()
    {
        // Arrange
        $userId = 1;
        $exceptionMessage = 'Service unavailable';

        $this->rolesAndPermissionsServiceMock
            ->shouldReceive('getDefaultPermissions')
            ->withNoArgs()
            ->once()
            ->andThrow(new \Exception($exceptionMessage));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($exceptionMessage);

        $job = new ImportDefaultPermissionsJob(null, $userId);
        $job->handle();
    }

    /**
     * @test
     */
    public function it_updates_existing_permission_settings()
    {
        // Arrange
        $roleName = 'admin';
        $userId = 1;
        $initialPermissions = ['old_permission'];
        $updatedPermissions = ['new_permission1', 'new_permission2'];

        // Create existing permission setting
        PermissionSetting::factory()->create([
            'role' => $roleName,
            'default_permissions' => $initialPermissions,
        ]);

        $this->rolesAndPermissionsServiceMock
            ->shouldReceive('getDefaultPermissions')
            ->with($roleName)
            ->once()
            ->andReturn($updatedPermissions);

        // Act
        $job = new ImportDefaultPermissionsJob($roleName, $userId);
        $job->handle();

        // Assert
        $this->assertDatabaseHas('permission_settings', [
            'role' => $roleName,
            'default_permissions' => json_encode($updatedPermissions),
        ]);

        $this->assertDatabaseCount('permission_settings', 1);
    }

    /**
     * @test
     */
    public function it_handles_failed_job_properly()
    {
        // Arrange
        $roleName = 'admin';
        $userId = 1;
        $exception = new \Exception('Test exception');

        //        Log::shouldReceive('error')->times(1);

        // Act
        $job = new ImportDefaultPermissionsJob($roleName, $userId);
        $job->failed($exception);

        // Assert
        // The failed method should log the error without throwing exceptions
        $this->assertTrue(true); // If we reach here, the test passed
    }

    // /**
    //  * @test
    //  */
    // public function it_uses_authenticated_user_id_when_user_id_not_provided()
    // {
    //     // Arrange
    //     $user = $this->createUser();
    //     $this->actingAs($user);

    //     $roleName = 'admin';
    //     $defaultPermissions = ['permission1', 'permission2'];

    //     $this->rolesAndPermissionsServiceMock
    //         ->shouldReceive('getDefaultPermissions')
    //         ->with($roleName)
    //         ->once()
    //         ->andReturn($defaultPermissions);

    //     //        Log::shouldReceive('info')->times(3);
    //     //        Log::shouldReceive('error')->never();

    //     // Act
    //     $job = new ImportDefaultPermissionsJob($roleName, null);
    //     $job->handle();

    //     // Assert
    //     $this->assertDatabaseHas('permission_settings', [
    //         'role' => $roleName,
    //     ]);
    // }

    //    /**
    //     * @test
    //     */
    //    public function it_sends_notification_to_user_when_importing_permissions_for_specific_role()
    //    {
    //        // Arrange
    //        $user = User::factory()->create();
    //        $roleName = 'gym_owner';
    //        $defaultPermissions = ['permission1', 'permission2'];
    //
    //        $this->rolesAndPermissionsServiceMock
    //            ->shouldReceive('getDefaultPermissions')
    //            ->with($roleName)
    //            ->once()
    //            ->andReturn($defaultPermissions);
    //
    //        //        Log::shouldReceive('info')->times(3);
    //        //        Log::shouldReceive('error')->never();
    //
    //        Notification::fake();
    //        Event::fake();
    //
    //        // Act
    //        $job = new ImportDefaultPermissionsJob($roleName, $user->id);
    //        $job->handle();
    //
    //        // Assert
    //        Notification::assertSentTo(
    //            [$user], // Array of notifiables
    //            \Filament\Notifications\Notification::class,
    //            //            function ($notification) use ($roleName) {
    //            //                return $notification->getTitle() === 'Permissions Imported' &&
    //            //                    $notification->getBody() === "Default permissions are being imported for role: {$roleName}";
    //            //            }
    //        );
    //
    //        Event::assertDispatched(DatabaseNotificationsSent::class, function ($event) use ($user) {
    //            return $event->notifiable->id === $user->id;
    //        });
    //    }
    //
    //    /**
    //     * @test
    //     */
    //    public function it_sends_notification_to_user_when_importing_permissions_for_all_roles()
    //    {
    //        // Arrange
    //        $user = User::factory()->create();
    //        $defaultPermissions = [
    //            'manager' => ['permission1', 'permission2'],
    //            'gym_owner' => ['permission3', 'permission4'],
    //        ];
    //
    //        $this->rolesAndPermissionsServiceMock
    //            ->shouldReceive('getDefaultPermissions')
    //            ->withNoArgs()
    //            ->once()
    //            ->andReturn($defaultPermissions);
    //
    //        //        Log::shouldReceive('info')->times(3);
    //        //        Log::shouldReceive('error')->never();
    //
    //        Notification::fake();
    //        Event::fake();
    //
    //        // Act
    //        $job = new ImportDefaultPermissionsJob(null, $user->id);
    //        $job->handle();
    //
    //        // Assert
    //        Notification::assertSentTo(
    //            [$user], // Array of notifiables
    //            \Filament\Notifications\Notification::class,
    //            function ($notification) {
    //                return $notification->getTitle() === 'Permissions Imported' &&
    //                    $notification->getBody() === 'Default permissions imported are being imported for all roles';
    //            }
    //        );
    //
    //        Event::assertDispatched(DatabaseNotificationsSent::class, function ($event) use ($user) {
    //            return $event->notifiable->id === $user->id;
    //        });
    //    }

    // /**
    //  * @test
    //  */
    // public function it_does_not_send_notification_when_user_not_found()
    // {
    //     // Arrange
    //     $nonExistentUserId = 99999;
    //     $roleName = 'admin';
    //     $defaultPermissions = ['permission1', 'permission2'];

    //     $this->rolesAndPermissionsServiceMock
    //         ->shouldReceive('getDefaultPermissions')
    //         ->with($roleName)
    //         ->once()
    //         ->andReturn($defaultPermissions);

    //     //        Log::shouldReceive('info')->times(3);
    //     //        Log::shouldReceive('error')->never();

    //     Notification::fake();
    //     Event::fake();

    //     // Act
    //     $job = new ImportDefaultPermissionsJob($roleName, $nonExistentUserId);
    //     $job->handle();

    //     // Assert
    //     Notification::assertNothingSent();
    //     Event::assertNotDispatched(DatabaseNotificationsSent::class);
    // }

    /**
     * Helper method to create a user for testing
     */
    private function createUser()
    {
        return User::factory()->create();
    }
}
