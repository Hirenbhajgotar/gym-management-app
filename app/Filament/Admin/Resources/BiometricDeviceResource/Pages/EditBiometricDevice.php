<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BiometricDeviceResource\Pages;

use App\Enums\BiometricProvidersEnum;
use App\Filament\Admin\Resources\BiometricDeviceResource;
use App\Models\BiometricDevice;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * @property BiometricDevice $record
 */
class EditBiometricDevice extends EditRecord
{
    protected static string $resource = BiometricDeviceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Normalize empty serial string to null to avoid unique key collision
        if (array_key_exists('device_serial', $data)) {
            $rawSerial = $data['device_serial'];
            $data['device_serial'] = blank($rawSerial)
                ? null
                : trim(is_scalar($rawSerial) ? (string) $rawSerial : '');
        }

        $tenantKey = Filament::getTenant()?->getKey();
        $tenantId = is_scalar($tenantKey) ? (string) $tenantKey : 'tenant';

        $existingConfig = is_array($this->record->provider_config) ? $this->record->provider_config : [];
        if (isset($data['provider_config']) && is_array($data['provider_config'])) {
            $data['provider_config'] = array_merge($existingConfig, $data['provider_config']);
        } elseif (filled($existingConfig) && ! isset($data['provider_config'])) {
            $data['provider_config'] = $existingConfig;
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
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Device updated')
            ->body('The device has been updated successfully.');
    }
}
