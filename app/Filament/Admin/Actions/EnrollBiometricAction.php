<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Biometrics\Exceptions\BiometricConsentRequiredException;
use App\Biometrics\Exceptions\MemberArchivedException;
use App\Biometrics\Services\BiometricTemplateSuperseder;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricProvenanceEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Filament\Actions\Action as PageAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Throwable;

class EnrollBiometricAction
{
    /**
     * Build table row action for GymMember table.
     */
    public static function make(?string $name = 'enroll_biometric'): TableAction
    {
        return TableAction::make($name)
            ->label('Enroll Biometric')
            ->icon('heroicon-o-finger-print')
            ->tooltip('Enroll member biometric in hardware device')
            ->modalHeading(function (?GymMember $record = null, mixed $livewire = null): string {
                $member = self::resolveMember($record, $livewire);

                return 'Enroll Biometric: ' . ($member !== null ? $member->name : 'Member');
            })
            ->modalDescription('Select an active biometric device to initiate native enrollment.')
            ->modalSubmitActionLabel('Initiate Enrollment')
            ->form(self::getFormSchema())
            ->action(function (GymMember $record, array $data): void {
                /** @var array<string, mixed> $data */
                self::handle($record, $data);
            })
            ->visible(fn (?GymMember $record = null, mixed $livewire = null): bool => self::isVisible($record, $livewire))
            ->authorize(fn (?GymMember $record = null, mixed $livewire = null): bool => self::canAuthorize(self::resolveMember($record, $livewire)));
    }

    /**
     * Build header action for GymMember edit page.
     */
    public static function makeForHeader(?string $name = 'enroll_biometric'): PageAction
    {
        return PageAction::make($name)
            ->label('Enroll Biometric')
            ->icon('heroicon-o-finger-print')
            ->color('info')
            ->modalHeading(function (?GymMember $record = null, mixed $livewire = null): string {
                $member = self::resolveMember($record, $livewire);

                return 'Enroll Biometric: ' . ($member !== null ? $member->name : 'Member');
            })
            ->modalDescription('Select an active biometric device to initiate native enrollment.')
            ->modalSubmitActionLabel('Initiate Enrollment')
            ->form(self::getFormSchema())
            ->action(function (array $data, ?GymMember $record = null, mixed $livewire = null): void {
                /** @var array<string, mixed> $data */
                $member = self::resolveMember($record, $livewire);
                if ($member) {
                    self::handle($member, $data);
                } else {
                    Notification::make()
                        ->title('Member Record Not Found')
                        ->body('Could not resolve the member record to enroll biometrics.')
                        ->danger()
                        ->send();
                }
            })
            ->visible(fn (?GymMember $record = null, mixed $livewire = null): bool => self::isVisible($record, $livewire))
            ->authorize(fn (?GymMember $record = null, mixed $livewire = null): bool => self::canAuthorize(self::resolveMember($record, $livewire)));
    }

    /**
     * @return array<int, Component>
     */
    public static function getFormSchema(): array
    {
        return [
            Placeholder::make('consent_notice')
                ->label('')
                ->visible(function (?GymMember $record = null, mixed $livewire = null): bool {
                    $member = self::resolveMember($record, $livewire);

                    return $member !== null && ! $member->hasActiveBiometricConsent();
                })
                ->content(new HtmlString(
                    '<div class="p-3 text-sm text-amber-800 bg-amber-50 dark:bg-amber-950/50 dark:text-amber-300 rounded-lg border border-amber-200 dark:border-amber-800">' .
                    '<strong>Consent Required:</strong> This member does not have active biometric consent on file. Hardware enrollment will be blocked until consent is provided.' .
                    '</div>'
                )),

            Placeholder::make('consent_verified')
                ->label('')
                ->visible(function (?GymMember $record = null, mixed $livewire = null): bool {
                    $member = self::resolveMember($record, $livewire);

                    return $member !== null && $member->hasActiveBiometricConsent();
                })
                ->content(new HtmlString(
                    '<div class="p-3 text-sm text-green-800 bg-green-50 dark:bg-green-950/50 dark:text-green-300 rounded-lg border border-green-200 dark:border-green-800">' .
                    '<strong>Consent Verified:</strong> Active biometric consent is on file for this member.' .
                    '</div>'
                )),

            Select::make('device_id')
                ->label('Biometric Device')
                ->placeholder('Select target device...')
                ->options(function (?GymMember $record = null, mixed $livewire = null): array {
                    $member = self::resolveMember($record, $livewire);
                    $tenant = Filament::getTenant();
                    $gymId = $tenant instanceof Gym ? $tenant->id : $member?->gym_id;

                    if (! $gymId) {
                        return [];
                    }

                    return BiometricDevice::query()
                        ->where('gym_id', $gymId)
                        ->active()
                        ->admsEnabled()
                        ->pluck('device_name', 'id')
                        ->all();
                })
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(function (mixed $state, callable $set, ?GymMember $record = null, mixed $livewire = null): void {
                    $member = self::resolveMember($record, $livewire);
                    $tenant = Filament::getTenant();
                    $gymId = $tenant instanceof Gym ? $tenant->id : $member?->gym_id;

                    if ($state && $gymId) {
                        /** @var BiometricDevice|null $device */
                        $device = BiometricDevice::query()
                            ->where('gym_id', $gymId)
                            ->whereKey($state)
                            ->first();

                        if ($device !== null) {
                            $matchingType = BiometricTypeEnum::tryFrom($device->device_type);
                            if ($matchingType) {
                                $set('biometric_type', $matchingType->value);
                            }
                        }
                    }
                }),

            Select::make('biometric_type')
                ->label('Biometric Type')
                ->options(BiometricTypeEnum::class)
                ->default(BiometricTypeEnum::Fingerprint->value)
                ->required(),

            Placeholder::make('enrollment_instructions')
                ->label('')
                ->content(new HtmlString(
                    '<div class="text-xs text-gray-500 dark:text-gray-400">' .
                    'A user profile (PIN and Name) will be created on the device via ADMS. The operator can then enroll the member\'s biometric credentials at the physical device keypad/menu for this PIN. Once confirmed, the profile will synchronize to sibling devices in the gym.' .
                    '</div>'
                )),
        ];
    }

    /**
     * Execute the enrollment initiation logic.
     *
     * Self-authorizing: `handle()` asserts `canAuthorize()` before performing any
     * work, so callers do not need to pre-check the policy (and a caller that
     * forgets to cannot bypass it).
     *
     * @param  array<string, mixed>  $data
     */
    public static function handle(GymMember $member, array $data): void
    {
        // Defense-in-depth authorization check: reject unauthorized operators before performing any work
        throw_unless(static::canAuthorize($member), AuthorizationException::class, 'Unauthorized biometric enrollment initiation.');

        // Archived members are access-revoked: they must never gain (or regain) a live
        // credential, so the panel path is refused exactly like the two API
        // endpoints (see MemberArchiveService). Without this the operator could
        // still queue a user_add for a member whose door access had already been
        // withdrawn. The shared predicate keeps this answer identical to every
        // other grant-side producer.
        if (! $member->mayHoldBiometricCredential()) {
            Notification::make()
                ->title('Member Archived')
                ->body('Member ' . e($member->name) . ' is archived. Enrollment is not permitted for archived members.')
                ->danger()
                ->send();

            return;
        }

        // 1. Consent Gate: Deny if active consent is missing
        if (! $member->hasActiveBiometricConsent()) {
            Notification::make()
                ->title('Biometric Consent Required')
                ->body('Member ' . e($member->name) . ' does not have active biometric consent on file. Please send or collect biometric consent before initiating hardware enrollment.')
                ->danger()
                ->send();

            return;
        }

        $deviceId = is_numeric($data['device_id'] ?? null) ? (int) $data['device_id'] : 0;
        $rawBiometricType = is_string($data['biometric_type'] ?? null) ? $data['biometric_type'] : '';
        $biometricType = BiometricTypeEnum::tryFrom($rawBiometricType);

        if (! $biometricType) {
            Notification::make()
                ->title('Invalid Biometric Type')
                ->body('The selected biometric modality is invalid.')
                ->danger()
                ->send();

            return;
        }

        /** @var BiometricDevice|null $device */
        $device = BiometricDevice::query()
            ->where('gym_id', $member->gym_id)
            ->active()
            ->admsEnabled()
            ->find($deviceId);

        if (! $device) {
            Notification::make()
                ->title('Device Not Available')
                ->body('The selected device is not active or ADMS-enabled.')
                ->danger()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($device, $member, $biometricType): void {
                // Lock the member row and re-assert archive eligibility inside
                // the transaction. This is the same row lock
                // MemberArchiveService::archive() takes, so a concurrent archive
                // either commits before this read (and is seen here) or waits
                // for this transaction to commit. If it fires, the transaction
                // rolls back the placeholder template and its queued user_add,
                // so no credential is ever delivered to the hardware.
                $lockedMember = GymMember::query()
                    ->whereKey($member->getKey())
                    ->lockForUpdate()
                    ->first();

                throw_unless($lockedMember instanceof GymMember, MemberArchivedException::class);

                $lockedMember->assertMayHoldBiometricCredential();

                // Verify fresh database consent inside transaction without mutating caller model
                throw_unless(
                    $member->hasActiveBiometricConsent(fresh: true),
                    BiometricConsentRequiredException::class
                );

                // Lock existing templates for this member on this device
                $existingTemplates = BiometricTemplate::query()
                    ->where('gym_id', $device->gym_id)
                    ->where('gym_member_id', $member->id)
                    ->where('enrolled_by_device_id', $device->id)
                    ->lockForUpdate()
                    ->get();

                $pins = [(string) $member->id];
                foreach ($existingTemplates as $existingTemplate) {
                    $pins[] = $existingTemplate->resolvedCommandPin();
                }
                $pins = array_values(array_unique($pins));
                AdmsDeviceCommand::cancelDeleteCommands($device->id, $pins);

                // Supersede older templates for this member, device and modality.
                //
                // Restricted to terminal-held rows: this path provisions a *hardware*
                // credential, so a cloud row sharing the same PIN is a different credential
                // and must stay live for its own provider-side erasure. Demoting it here
                // would strand the provider reference (see BiometricTemplate::
                // supersedableForNewEnrolment()).
                resolve(BiometricTemplateSuperseder::class)->supersedeForNewEnrolment(
                    $device->gym_id,
                    $member,
                    $device->id,
                    $biometricType->value,
                    BiometricProvenanceEnum::Hardware
                );

                // Create placeholder template with sync_status = pending, enrolled_at = null
                //
                // The provider is the *hardware* one, not the device's configured cloud
                // provider. This path queues an ADMS `user_add` for a PIN the operator enrols at
                // the terminal keypad, so the credential lives on the terminal. Storing the
                // dual-mode device's cloud `provider_type` here made `hasCloudProvider()` route
                // the erasure to the provider, which never held this credential — the terminal
                // kept it while the app reported the member revoked.
                /** @var BiometricTemplate $template */
                $template = BiometricTemplate::query()->create([
                    'gym_member_id' => $member->id,
                    'gym_id' => $device->gym_id,
                    'enrolled_by_device_id' => $device->id,
                    'biometric_type' => $biometricType,
                    'provider_type' => BiometricProvidersEnum::DeviceNative->value,
                    'template_ref' => null,
                    'is_active' => false,
                    'sync_status' => BiometricTemplateSyncStatusEnum::Pending,
                    'enrolled_at' => null,
                ]);

                // Canonical idempotency key
                $idempotencyKey = AdmsDeviceCommand::idempotencyKeyFor(
                    $device->gym_id,
                    $device->id,
                    $member->id,
                    $template->id
                );

                AdmsDeviceCommand::query()->create([
                    'gym_id' => $device->gym_id,
                    'device_id' => $device->id,
                    'command_type' => 'user_add',
                    'command_payload' => [
                        'Pin' => (string) $member->id,
                        'Name' => $member->name,
                    ],
                    'status' => AdmsCommandStatusEnum::PENDING,
                    'idempotency_key' => $idempotencyKey,
                ]);

                // Cancel prior in-flight user_add commands while protecting newly queued key
                AdmsDeviceCommand::cancelInFlightFor($device->id, (string) $member->id, $idempotencyKey);

                // Audit log with operator attribution using Spatie activity log
                activity('biometric')
                    ->causedBy(Auth::user())
                    ->performedOn($member)
                    ->withProperties([
                        'gym_id' => $device->gym_id,
                        'device_id' => $device->id,
                        'member_id' => $member->id,
                        'template_id' => $template->id,
                    ])
                    ->log('Biometric enrollment initiated');

                Log::info('Biometric enrollment initiated', [
                    'gym_id' => $device->gym_id,
                    'device_id' => $device->id,
                    'member_id' => $member->id,
                    'template_id' => $template->id,
                    'operator_id' => Auth::id(),
                ]);
            });
        } catch (Throwable $e) {
            if ($e instanceof MemberArchivedException) {
                Log::warning('EnrollBiometricAction: refused to initiate enrollment for an archived member', [
                    'device_id' => $device->id,
                    'member_id' => $member->id,
                ]);

                Notification::make()
                    ->title('Member Archived')
                    ->body('Member ' . e($member->name) . ' was archived while enrollment was being initiated. Enrollment was not created.')
                    ->danger()
                    ->send();

                return;
            }

            $isConsentRefusal = $e instanceof BiometricConsentRequiredException;

            if ($isConsentRefusal) {
                Log::warning('EnrollBiometricAction: failed to initiate enrollment due to missing consent', [
                    'device_id' => $device->id,
                    'member_id' => $member->id,
                    'message' => $e->getMessage(),
                ]);
            } else {
                Log::error('EnrollBiometricAction: failed to initiate enrollment', [
                    'device_id' => $device->id,
                    'member_id' => $member->id,
                    'error_type' => $e::class,
                ]);
            }

            Notification::make()
                ->title($isConsentRefusal ? 'Biometric Consent Required' : 'Enrollment Initiation Failed')
                ->body($isConsentRefusal
                    ? 'Member ' . e($member->name) . ' no longer has active biometric consent. The consent may have been revoked after the page loaded.'
                    : 'Failed to queue enrollment command on device. Please try again.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Biometric Enrollment Initiated')
            ->body('Enrollment profile queued for ' . e($device->device_name) . '. An operator can now complete biometric enrollment at the physical device.')
            ->success()
            ->send();
    }

    /**
     * Determine if action should be visible.
     */
    public static function isVisible(mixed $record = null, mixed $livewire = null): bool
    {
        $member = self::resolveMember($record, $livewire);
        $tenant = Filament::getTenant();
        $gymId = $tenant instanceof Gym ? $tenant->id : $member?->gym_id;

        if (! $gymId) {
            return false;
        }

        return Cache::remember("biometric:gym:{$gymId}:has_adms_devices", 60, fn (): bool => BiometricDevice::query()
            ->where('gym_id', $gymId)
            ->active()
            ->admsEnabled()
            ->exists());
    }

    /**
     * Determine if current user is authorized to trigger biometric enrollment.
     */
    public static function canAuthorize(?GymMember $member = null): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $member !== null
            ? $user->can('enrollBiometric', $member)
            : $user->can('enrollBiometric', GymMember::class);
    }

    /**
     * Resolve GymMember instance from record or livewire component.
     */
    public static function resolveMember(mixed $record = null, mixed $livewire = null): ?GymMember
    {
        if ($record instanceof GymMember) {
            return $record;
        }

        if ($livewire instanceof EditRecord || $livewire instanceof ViewRecord) {
            $rec = $livewire->getRecord();
            if ($rec instanceof GymMember) {
                return $rec;
            }
        }

        return null;
    }
}
