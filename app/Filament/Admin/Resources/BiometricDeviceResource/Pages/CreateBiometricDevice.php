<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BiometricDeviceResource\Pages;

use App\Biometrics\Services\AdmsDeviceAuthenticator;
use App\Biometrics\Services\CommKeyReveal;
use App\Biometrics\Services\DeviceTokenReveal;
use App\Biometrics\Services\WebhookSecretRotationService;
use App\Filament\Admin\Resources\BiometricDeviceResource;
use App\Models\BiometricDevice;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateBiometricDevice extends CreateRecord
{
    protected static string $resource = BiometricDeviceResource::class;

    protected ?string $generatedToken = null;

    protected ?string $generatedCommKey = null;

    protected ?string $generatedCommKeyHash = null;

    protected ?string $generatedCommKeySalt = null;

    protected bool $commKeyRevealFailed = false;

    protected bool $tokenRevealFailed = false;

    protected bool $removedCommKeyAlias = false;

    protected bool $revealPublished = false;

    protected bool $manualWebhookSecretSubmitted = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->removedCommKeyAlias = false;
        $rawConfig = $data['provider_config'] ?? null;
        /** @var array<string, mixed> $providerConfig */
        $providerConfig = is_array($rawConfig) ? $rawConfig : [];
        $this->manualWebhookSecretSubmitted = is_array($rawConfig)
            && BiometricDeviceResource::manualWebhookSecretWasSubmitted($providerConfig);

        // Set created_by to the current authenticated user
        $data['created_by'] = auth()->id();
        $tenantKey = Filament::getTenant()?->getKey();
        $tenantId = is_scalar($tenantKey) ? (string) $tenantKey : 'tenant';
        $data['gym_id'] = $data['gym_id'] ?? $tenantId;

        // Normalize empty serial string to null to avoid unique key collision
        $rawSerial = $data['device_serial'] ?? null;
        $data['device_serial'] = blank($rawSerial)
            ? null
            : trim(is_scalar($rawSerial) ? (string) $rawSerial : '');

        // Auto-generate device token if not provided (model cast will hash it)
        $rawToken = $data['device_token'] ?? null;
        if (blank($rawToken)) {
            $this->generatedToken = bin2hex(random_bytes(32));
            $data['device_token'] = $this->generatedToken; // Model cast handles hashing
            $data['token_hash'] = hash('sha256', $this->generatedToken);
        } else {
            $data['token_hash'] = hash('sha256', is_scalar($rawToken) ? (string) $rawToken : '');
        }

        $providerType = $data['provider_type'] ?? null;
        if (filled($providerType) && ! BiometricDevice::providerTypeIsDeviceNative($providerType)) {
            $data['provider_config'] = is_array($data['provider_config'] ?? null) ? $data['provider_config'] : [];
            $data['provider_config']['collection_id'] = 'flux_gym_' . $tenantId;
            $data['provider_config']['face_list_id'] = 'flux_gym_' . $tenantId;
        }

        if (filled($data['adms_comm_key'] ?? null)) {
            $rawKey = BiometricDevice::sanitizeCommKey($data['adms_comm_key']);
            $validationError = BiometricDevice::commKeyValidationError($rawKey);

            if ($validationError !== null) {
                throw ValidationException::withMessages(['data.adms_comm_key' => $validationError]);
            }

            $this->generatedCommKey = $rawKey;
            $this->generatedCommKeySalt = BiometricDevice::generateCommKeySalt();
            $this->generatedCommKeyHash = BiometricDevice::hashCommKey($rawKey, $this->generatedCommKeySalt);

            $data['provider_config'] = is_array($data['provider_config'] ?? null) ? $data['provider_config'] : [];
            unset($data['provider_config']['pushcommkey']);

            if (BiometricDevice::providerTypeIsDeviceNative($data['provider_type'] ?? null)) {
                if (array_key_exists('comm_key', $data['provider_config'])) {
                    $this->removedCommKeyAlias = true;
                }

                unset($data['provider_config']['comm_key']);
            }

            unset($data['adms_comm_key']);
        }

        return $data;
    }

    /**
     * Install the verifier in the same INSERT as the device row.
     *
     * afterCreate() used to perform a second save. A failure between the two
     * writes could leave a device with no verifier while the terminal had the
     * new key.
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var BiometricDevice $record */
        $record = new ($this->getModel())();

        // Use fill() for the form payload so non-column fields (for example the
        // virtual `adms_comm_key` input) are discarded by mass-assignment
        // protection. Only the non-fillable verifier needs forceFill().
        $record->fill($data);

        if ($this->generatedCommKeyHash !== null && $this->generatedCommKeySalt !== null) {
            $record->forceFill([
                'adms_comm_key_hash' => $this->generatedCommKeyHash,
                'adms_comm_key_salt' => $this->generatedCommKeySalt,
            ]);
        }

        $record->save();

        return $record;
    }

    /**
     * Publish the one-time values only after the outermost transaction commits.
     *
     * afterCreate() runs before commit. Writing the reveal cache there could
     * leave an orphaned value if the transaction rolls back, and the reveal
     * service contract says the verifier is already committed.
     */
    protected function commitDatabaseTransaction(): void
    {
        parent::commitDatabaseTransaction();

        if (DB::transactionLevel() > 0 && ! (app()->runningUnitTests() && DB::transactionLevel() === 1)) {
            DB::afterCommit(fn () => $this->publishPendingReveal());

            return;
        }

        $this->publishPendingReveal();
    }

    protected function publishPendingReveal(): void
    {
        if ($this->revealPublished) {
            return;
        }

        $this->revealPublished = true;

        /** @var BiometricDevice|null $record */
        $record = $this->record;

        if (! $record instanceof BiometricDevice || ! $record->exists) {
            return;
        }

        if ($this->manualWebhookSecretSubmitted) {
            resolve(WebhookSecretRotationService::class)->recordManualWebhookSecretWrite($record);
        }

        if ($this->generatedToken !== null) {
            DeviceTokenReveal::forget($record->id);
            $this->tokenRevealFailed = ! DeviceTokenReveal::put($record->id, $this->generatedToken);
        }

        if ($this->generatedCommKey === null || $this->generatedCommKeyHash === null || $this->generatedCommKeySalt === null) {
            return;
        }

        $this->commKeyRevealFailed = ! CommKeyReveal::put($record->id, $this->generatedCommKey);

        resolve(AdmsDeviceAuthenticator::class)->resetFailures($record);

        try {
            activity()
                ->performedOn($record)
                ->withProperties([
                    'gym_id' => $record->gym_id,
                    'action' => 'adms_comm_key_created',
                    'removed_comm_key_alias' => $this->removedCommKeyAlias,
                    'provider_type' => $record->provider_type?->value,
                ])
                ->log('ADMS device comm key created');
        } catch (Throwable $exception) {
            Log::warning('ADMS: device created but the comm key audit log failed', [
                'device_id' => $record->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return BiometricDeviceResource::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var BiometricDevice $record */
        $record = $this->record;
        $isAdms = $record->adms_enabled;

        $notification = Notification::make()
            ->success()
            ->title('Device registered')
            ->body('The device has been added successfully.');

        $body = ['The device has been added successfully.'];

        if ($this->generatedToken) {
            $body[] = $this->tokenRevealFailed
                ? 'The device API token could not be stored for one-time reveal. Regenerate it from the table actions.'
                : 'Device API token generated. Use the "Reveal API Token" action in the devices table within ' . DeviceTokenReveal::TTL_MINUTES . ' minutes; it will not be shown again after that.';
        }

        if ($this->generatedCommKey) {
            $body[] = $this->commKeyRevealFailed
                ? 'The ADMS Communication Key could not be stored for one-time reveal. Rotate it from the table actions.'
                : 'ADMS Communication Key generated. Use the "Reveal Comm Key" action in the devices table within ' . CommKeyReveal::TTL_MINUTES . ' minutes; it will not be shown again after that.';
        }

        $notification->body(implode(' ', $body));

        if ($isAdms) {
            $appUrl = BiometricDeviceResource::resolvedAppUrl();
            $parsedUrl = parse_url($appUrl) ?: [];
            $serverHost = $parsedUrl['host'] ?? request()->getHost();
            $scheme = $parsedUrl['scheme'] ?? (request()->isSecure() ? 'https' : 'http');
            $serverPort = $parsedUrl['port'] ?? ($scheme === 'https' ? '443' : '80');

            $notification->title('Device registered (ADMS Active)');
            $notification->body(implode(' ', $body) . " Set ADMS Server: {$serverHost}:{$serverPort} on your terminal under Menu > Comm > Cloud Server Setting. Use 'ADMS Setup' in table actions to view full instructions.");
        }

        return $notification;
    }
}
