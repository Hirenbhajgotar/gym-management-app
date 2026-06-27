<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PermissionSetting;
use App\Models\User;
use App\Services\RolesAndPermissionsService;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportDefaultPermissionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?string $targetRole;

    protected int $userId;

    /**
     * Create a new job instance.
     *
     * @param  string|null  $role  The specific role to import permissions for, or null for all roles
     * @param  int|null  $userId  The ID of the user who triggered the import
     */
    public function __construct(?string $role = null, ?int $userId = null)
    {
        $this->targetRole = $role;
        $this->userId = $userId ?? auth()->id();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting default permissions import', [
                'target_role' => $this->targetRole,
                'user_id' => $this->userId,
            ]);

            if ($this->targetRole) {
                $this->importPermissionsForRole($this->targetRole);
            } else {
                $this->importPermissionsForAllRoles();
            }

            $this->showNotification();

            Log::info('Default permissions import completed successfully', [
                'target_role' => $this->targetRole,
            ]);
        } catch (\Exception $e) {
            Log::error($e);

            throw $e;
        }
    }

    private function showNotification(): void
    {
        $recipient = User::query()->where('id', $this->userId)->first();

        if (! $recipient) {
            return;
        }
        Log::info('Notification user calling notification');

        $bodyMessage = 'Default permissions imported are being imported for all roles';

        if ($this->targetRole) {
            $bodyMessage = "Default permissions are being imported for role: {$this->targetRole}";
        }

        Notification::make()
            ->title('Permissions Imported')
            ->body($bodyMessage)
            ->success()
            ->sendToDatabase($recipient);

        event(new DatabaseNotificationsSent($recipient));

    }

    /**
     * Import permissions for a specific role
     */
    protected function importPermissionsForRole(string $roleName): void
    {
        $defaultPermissions = resolve(RolesAndPermissionsService::class)->getDefaultPermissions($roleName);

        if (filled($defaultPermissions)) {
            PermissionSetting::query()->updateOrCreate([
                'role' => $roleName,
            ], [
                'default_permissions' => $defaultPermissions,
            ]);
        }

        Log::info("Permissions imported for role: {$roleName}");
    }

    /**
     * Import permissions for all roles that have permission settings
     */
    protected function importPermissionsForAllRoles(): void
    {
        $defaultRolesPermissions = resolve(RolesAndPermissionsService::class)->getDefaultPermissions();

        foreach ($defaultRolesPermissions as $role => $permissions) {
            PermissionSetting::query()->updateOrCreate([
                'role' => $role,
            ], [
                'default_permissions' => $permissions,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ImportDefaultPermissionsJob failed', [
            'target_role' => $this->targetRole,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
