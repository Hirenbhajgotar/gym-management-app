<?php

declare(strict_types=1);

namespace App\Console\Commands\SuperAdmin;

use App\Enums\DefaultRole;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;

class GenerateSuperAdminTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:generate-token 
                            {email? : The email address of the Super Admin user}
                            {--token-name=log-viewer-access : Name for the API access token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Sanctum personal access token for a Super Admin user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $tokenName = (string) $this->option('token-name');

        if (! $email) {
            $superAdminUsers = User::role(DefaultRole::SUPER_ADMIN->value)->get();

            if ($superAdminUsers->isEmpty()) {
                $this->error('No users found with the Super Admin role.');

                return self::FAILURE;
            }

            if ($superAdminUsers->count() === 1 || ! $this->input->isInteractive()) {
                $user = $superAdminUsers->first();
            } else {
                $options = $superAdminUsers->pluck('name', 'email')->map(
                    function ($name, $email) {
                        /** @var string $name */
                        /** @var string $email */
                        return $name . " ({$email})";
                    }
                )->all();

                $email = select(
                    label: 'Select a Super Admin user:',
                    options: $options
                );

                $user = $superAdminUsers->firstWhere('email', $email);
            }
        } else {
            /** @var User|null $user */
            $user = User::where('email', $email)->first();
        }

        if (! $user) {
            $this->error("User with email [{$email}] not found.");

            return self::FAILURE;
        }

        if (! $user->hasRole(DefaultRole::SUPER_ADMIN->value)) {
            $this->error("User [{$user->email}] does not have the Super Admin role.");

            return self::FAILURE;
        }

        $token = $user->createToken($tokenName)->plainTextToken;

        $this->info('Personal access token generated successfully!');
        $this->newLine();
        $this->table(
            ['User', 'Role', 'Token Name', 'Token Secret'],
            [
                [$user->email, DefaultRole::SUPER_ADMIN->value, $tokenName, $token],
            ]
        );
        $this->newLine();
        $this->comment('Add this token to your .env file on the client host:');
        $this->line("LOG_VIEWER_PRODUCTION_TOKEN={$token}");

        return self::SUCCESS;
    }
}
