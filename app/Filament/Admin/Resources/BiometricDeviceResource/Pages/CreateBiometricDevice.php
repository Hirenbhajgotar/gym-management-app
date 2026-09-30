<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BiometricDeviceResource\Pages;

use App\Enums\BiometricProvidersEnum;
use App\Filament\Admin\Resources\BiometricDeviceResource;
use App\Models\BiometricDevice;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Js;

class CreateBiometricDevice extends CreateRecord
{
    protected static string $resource = BiometricDeviceResource::class;

    protected ?string $generatedToken = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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
        if (filled($providerType) && ! in_array($providerType, [BiometricProvidersEnum::DeviceNative, BiometricProvidersEnum::DeviceNative->value, 'device_native'], true)) {
            $data['provider_config'] = is_array($data['provider_config'] ?? null) ? $data['provider_config'] : [];
            $data['provider_config']['collection_id'] = 'flux_gym_' . $tenantId;
            $data['provider_config']['face_list_id'] = 'flux_gym_' . $tenantId;
        }

        if (filled($data['adms_comm_key'] ?? null)) {
            $rawKey = $data['adms_comm_key'];
            $salt = BiometricDevice::generateCommKeySalt();
            $data['adms_comm_key_salt'] = $salt;
            $data['adms_comm_key_hash'] = BiometricDevice::hashCommKey(is_scalar($rawKey) ? (string) $rawKey : '', $salt);
            unset($data['adms_comm_key']);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return BiometricDeviceResource::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var BiometricDevice|null $record */
        $record = $this->record;
        $isAdms = (bool) ($record?->adms_enabled ?? false);

        $notification = Notification::make()
            ->success()
            ->title('Device registered')
            ->body('The device has been added successfully.');

        $actions = [];

        if ($this->generatedToken) {
            $notification->body('Device API token: ' . $this->generatedToken . '. Copy it now as you won\'t see it again!');
            $actions[] = NotificationAction::make('copyToken')
                ->label('Copy API Token')
                ->icon('heroicon-m-clipboard')
                ->link()
                ->url('javascript:navigator.clipboard.writeText(' . Js::from($this->generatedToken) . ')')
                ->close();
        }

        if ($isAdms) {
            $appUrl = config('app.url') ?: 'http://localhost';
            $parsedUrl = parse_url($appUrl);
            $serverHost = $parsedUrl['host'] ?? request()->getHost();
            $scheme = $parsedUrl['scheme'] ?? (request()->isSecure() ? 'https' : 'http');
            $serverPort = $parsedUrl['port'] ?? ($scheme === 'https' ? '443' : '80');

            $notification
                ->title('Device registered (ADMS Active)')
                ->body("Device added! Set ADMS Server: {$serverHost}:{$serverPort} on your terminal under Menu > Comm > Cloud Server Setting. Use 'ADMS Setup' in table actions to view full instructions.");
        }

        if (! empty($actions)) {
            $notification->actions($actions)->persistent();
        }

        return $notification;
    }
}
