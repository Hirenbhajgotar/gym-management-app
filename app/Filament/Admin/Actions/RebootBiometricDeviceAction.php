<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Enums\AdmsCommandStatusEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\User;
use Filament\Actions\Action as PageAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class RebootBiometricDeviceAction
{
    public const ACTION_NAME = 'rebootDevice';

    /**
     * Build table row action for BiometricDevice table.
     */
    public static function make(?string $name = self::ACTION_NAME): TableAction
    {
        return TableAction::make($name)
            ->label('Reboot Terminal')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (?BiometricDevice $record): bool => (bool) $record?->adms_enabled)
            ->authorize(fn (?BiometricDevice $record): bool => self::canAuthorize(Auth::user(), $record))
            ->requiresConfirmation()
            ->modalHeading('Reboot Biometric Terminal')
            ->modalDescription('Are you sure you want to reboot this terminal? It will receive the command on its next poll, restart, and temporarily pause member check-ins.')
            ->modalSubmitActionLabel('Reboot')
            ->action(function (BiometricDevice $record): void {
                self::handle($record);
            });
    }

    /**
     * Build header action for BiometricDevice edit/view page.
     */
    public static function makeForPage(?string $name = self::ACTION_NAME): PageAction
    {
        return PageAction::make($name)
            ->label('Reboot Terminal')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (?BiometricDevice $record = null, mixed $livewire = null): bool => (bool) self::resolveDevice($record, $livewire)?->adms_enabled)
            ->authorize(fn (?BiometricDevice $record = null, mixed $livewire = null): bool => self::canAuthorize(Auth::user(), self::resolveDevice($record, $livewire)))
            ->requiresConfirmation()
            ->modalHeading('Reboot Biometric Terminal')
            ->modalDescription('Are you sure you want to reboot this terminal? It will receive the command on its next poll, restart, and temporarily pause member check-ins.')
            ->modalSubmitActionLabel('Reboot')
            ->action(function (?BiometricDevice $record = null, mixed $livewire = null): void {
                $device = self::resolveDevice($record, $livewire);
                if ($device instanceof BiometricDevice) {
                    self::handle($device);
                }
            });
    }

    public static function resolveDevice(?BiometricDevice $record = null, mixed $livewire = null): ?BiometricDevice
    {
        if ($record instanceof BiometricDevice) {
            return $record;
        }

        if ($livewire instanceof EditRecord || $livewire instanceof ViewRecord) {
            $record = $livewire->getRecord();
            if ($record instanceof BiometricDevice) {
                return $record;
            }
        }

        return null;
    }

    public static function canAuthorize(?User $user, ?BiometricDevice $record): bool
    {
        if (! $user || ! $record) {
            return false;
        }

        return $user->can('reboot', $record);
    }

    public static function handle(BiometricDevice $record, ?int $userId = null): bool
    {
        if (! $record->adms_enabled) {
            Notification::make()
                ->danger()
                ->title('Terminal is not ADMS enabled')
                ->body("Device [{$record->device_serial}] is not configured for the ADMS protocol.")
                ->send();

            return false;
        }

        $inFlight = AdmsDeviceCommand::query()
            ->where('device_id', $record->id)
            ->where('command_type', 'reboot')
            ->whereIn('status', [AdmsCommandStatusEnum::PENDING, AdmsCommandStatusEnum::SENT])
            ->first();

        if ($inFlight) {
            Notification::make()
                ->warning()
                ->title('Reboot already in-flight')
                ->body("A reboot command [ID: {$inFlight->id}, Status: {$inFlight->status->value}] is already in-flight for this terminal.")
                ->send();

            return false;
        }

        $initiatorId = $userId ?? Auth::id();

        try {
            $command = AdmsDeviceCommand::query()->create([
                'gym_id' => $record->gym_id,
                'device_id' => $record->id,
                'device_serial' => $record->device_serial,
                'command_type' => 'reboot',
                'command_payload' => $initiatorId !== null ? [
                    'initiated_by_user_id' => $initiatorId,
                ] : null,
                'status' => AdmsCommandStatusEnum::PENDING,
                'idempotency_key' => sprintf('reboot:%d:%d:%d:%s', $record->gym_id, $record->id, time(), Str::random(6)),
            ]);

            try {
                activity()
                    ->performedOn($record)
                    ->withProperties([
                        'gym_id' => $record->gym_id,
                        'action' => 'adms_device_reboot_queued',
                        'command_id' => $command->id,
                        'device_serial' => $record->device_serial,
                        'initiated_by_user_id' => $initiatorId,
                    ])
                    ->log('ADMS device reboot command queued');
            } catch (Throwable $exception) {
                Log::warning('ADMS: device reboot audit failed', [
                    'device_id' => $record->getKey(),
                    'exception' => $exception::class,
                ]);
            }

            Notification::make()
                ->success()
                ->title('Reboot command queued')
                ->body('The terminal will restart on its next poll. You will receive a database notification once it reboots.')
                ->send();

            return true;
        } catch (Throwable $e) {
            Log::error('ADMS: Failed to queue reboot command', [
                'device_id' => $record->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('Failed to queue reboot')
                ->body('An error occurred while queuing the reboot command.')
                ->send();

            return false;
        }
    }
}
