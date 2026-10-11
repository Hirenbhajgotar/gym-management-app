<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum BiometricProvidersEnum: string implements HasColor, HasDescription, HasLabel
{
    case DeviceNative = 'device_native';
    case Rekognition = 'rekognition';
    case AzureFace = 'azure_face';
    case BioID = 'bioid';
    case OnPremise = 'on_premise';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::DeviceNative => 'Device-Native (Hardware)',
            self::Rekognition => 'AWS Rekognition',
            self::AzureFace => 'Azure Face',
            self::BioID => 'BioID',
            self::OnPremise => 'Local/On-Premise',
            self::Custom => 'Custom API',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DeviceNative => 'success',
            self::Rekognition => 'warning',
            self::AzureFace => 'info',
            self::BioID => 'primary',
            self::OnPremise => 'gray',
            self::Custom => 'purple',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::DeviceNative => 'Hardware terminal performs on-chip biometric matching. No cloud AI required.',
            self::Rekognition => 'Amazon Web Services facial recognition service',
            self::AzureFace => 'Microsoft Azure Face API service',
            self::BioID => 'BioID cloud biometric authentication',
            self::OnPremise => 'Local on-premise AI model inference',
            self::Custom => 'Custom HTTP/REST biometric recognition endpoint',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value', 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function toOptionsArray(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function toDescriptionsArray(): array
    {
        $descriptions = [];
        foreach (self::cases() as $case) {
            if ($desc = $case->getDescription()) {
                $descriptions[$case->value] = $desc;
            }
        }

        return $descriptions;
    }
}
