<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\BiometricDevice;

use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Filament\BasePages\BasePage;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class EnrollMember extends BasePage
{
    protected static string $view = 'filament.admin.pages.biometric-device.enroll-member';

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Device Management';

    public ?int $selectedDeviceId = null;

    public ?int $selectedMemberId = null;

    public ?string $biometricType = null;

    public ?string $rawBiometricData = null;

    public ?bool $sendToDeviceEnabled = true;

    /** @var array<int, array{id: int, type: string, enrolled_at: string, device: string}> */
    public array $enrolledTemplates = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * @return array<int|string, string>
     */
    public function getDevices(): array
    {
        /** @var Gym|null $tenant */
        $tenant = Filament::getTenant();

        /** @var array<int|string, string> */
        return BiometricDevice::query()
            ->where('gym_id', $tenant?->id)
            ->where('status', 'active')
            ->pluck('device_name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function getMembers(): array
    {
        if (! $this->selectedDeviceId) {
            return [];
        }

        /** @var Gym|null $tenant */
        $tenant = Filament::getTenant();

        /** @var array<int|string, string> */
        return GymMember::query()
            ->where('gym_id', $tenant?->id)
            ->where('is_archived', false)
            ->whereDoesntHave('biometricTemplates', function (Builder $q) {
                $q->where('enrolled_by_device_id', $this->selectedDeviceId)
                    ->where('is_active', true);
            })
            ->pluck('name', 'id')
            ->all();
    }

    public function updatedSelectedMemberId(): void
    {
        $this->loadEnrolledTemplates();
    }

    public function updatedSelectedDeviceId(): void
    {
        $this->biometricType = $this->getDefaultBiometricType();
        $this->loadEnrolledTemplates();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Device & Member Selection')
                    ->schema([
                        Select::make('selectedDeviceId')
                            ->label('Biometric Device')
                            ->options($this->getDevices())
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(fn () => $this->updatedSelectedDeviceId()),

                        Select::make('selectedMemberId')
                            ->label('Gym Member')
                            ->options($this->getMembers())
                            ->reactive()
                            ->required()
                            ->searchable()
                            ->afterStateUpdated(fn () => $this->updatedSelectedMemberId()),

                        Select::make('biometricType')
                            ->label('Biometric Type')
                            ->options(BiometricTypeEnum::class)
                            ->default(fn () => $this->getDefaultBiometricType())
                            ->required(),
                    ])->columns(3),

                Section::make('Already Enrolled')
                    ->schema([
                        Placeholder::make('enrolled_info')
                            ->content(fn () => $this->getEnrolledInfoContent()),
                    ])
                    ->visible(fn () => count($this->enrolledTemplates) > 0),

                Section::make('Biometric Enrollment')
                    ->schema([
                        Textarea::make('rawBiometricData')
                            ->label('Biometric Data (Base64)')
                            ->rows(5)
                            ->maxLength(16000)
                            ->required()
                            ->helperText('Paste the Base64 encoded biometric template data from the device'),

                        Toggle::make('sendToDeviceEnabled')
                            ->label('Send to Device for Verification')
                            ->default(true)
                            ->helperText('Automatically send enrolled template to device for verification'),
                    ]),
            ]);
    }

    protected function getDefaultBiometricType(): ?string
    {
        if (! $this->selectedDeviceId) {
            return null;
        }

        /** @var Gym|null $tenant */
        $tenant = Filament::getTenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return null;
        }

        $device = BiometricDevice::query()
            ->where('gym_id', $tenantId)
            ->find($this->selectedDeviceId);

        return $device?->device_type;
    }

    protected function loadEnrolledTemplates(): void
    {
        if (! $this->selectedMemberId) {
            $this->enrolledTemplates = [];

            return;
        }

        /** @var Gym|null $tenant */
        $tenant = Filament::getTenant();
        if (! $tenant) {
            $this->enrolledTemplates = [];

            return;
        }

        $this->enrolledTemplates = BiometricTemplate::query()
            ->where('gym_id', $tenant->id)
            ->where('gym_member_id', $this->selectedMemberId)
            ->where('is_active', true)
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'type' => $template->biometric_type->getLabel(),
                    'enrolled_at' => $template->enrolled_at->format('M d, Y H:i'),
                    'device' => $template->enrolledByDevice?->device_name ?? 'Admin',
                ];
            })
            ->all();
    }

    protected function getEnrolledInfoContent(): string
    {
        if (blank($this->enrolledTemplates)) {
            return 'No biometric templates enrolled yet.';
        }

        $html = '<ul class="list-disc list-inside space-y-1">';
        foreach ($this->enrolledTemplates as $template) {
            $type = e($template['type']);
            $enrolledAt = e($template['enrolled_at']);
            $device = e($template['device']);
            $html .= "<li><strong>{$type}</strong> - Enrolled {$enrolledAt} via {$device}</li>";
        }

        return $html . '</ul>';
    }

    public function enroll(): void
    {
        /** @var Gym|null $tenant */
        $tenant = Filament::getTenant();
        $tenantId = $tenant?->id;

        $this->validate([
            'selectedDeviceId' => ['required', Rule::exists('biometric_devices', 'id')->where('gym_id', $tenantId)],
            'selectedMemberId' => ['required', Rule::exists('gym_members', 'id')->where('gym_id', $tenantId)],
            'biometricType' => ['required', Rule::enum(BiometricTypeEnum::class)],
            'rawBiometricData' => ['required', 'string', 'max:16000'],
        ]);

        $biometricTypeEnum = BiometricTypeEnum::tryFrom((string) $this->biometricType);
        if (! $biometricTypeEnum) {
            Notification::make()->title('Invalid biometric type selected.')->danger()->send();

            return;
        }

        $device = BiometricDevice::query()
            ->where('gym_id', $tenantId)
            ->findOrFail($this->selectedDeviceId);
        $member = GymMember::query()
            ->where('gym_id', $tenantId)
            ->findOrFail($this->selectedMemberId);

        // Validate biometric data is valid base64
        if (! base64_decode((string) $this->rawBiometricData, true)) {
            Notification::make()
                ->title('Invalid Biometric Data')
                ->body('The provided biometric data is not valid Base64 encoded.')
                ->danger()
                ->send();

            return;
        }

        // Create template reference (encrypted JSON)
        $templateRef = json_encode([
            'provider' => $device->provider_type,
            'data' => $this->rawBiometricData,
            'device_id' => $device->id,
            'enrolled_at' => now()->toISOString(),
        ]);

        // Resolve command PINs (both member ID and any existing template refs)
        $existingTemplates = BiometricTemplate::query()
            ->where('gym_id', $tenantId)
            ->where('gym_member_id', $member->id)
            ->where('enrolled_by_device_id', $device->id)
            ->get();

        $pins = [(string) $member->id];
        foreach ($existingTemplates as $existingTemplate) {
            $pins[] = $existingTemplate->resolvedCommandPin();
        }
        $pins = array_values(array_unique($pins));

        /** @var BiometricTemplate $createdTemplate */
        $createdTemplate = DB::transaction(function () use ($device, $member, $tenantId, $pins, $templateRef): BiometricTemplate {
            // Cancel any pending or in-flight delete commands on this device for this member
            AdmsDeviceCommand::cancelDeleteCommands($device->id, $pins);

            BiometricTemplate::query()
                ->where('gym_id', $tenantId)
                ->where('gym_member_id', $member->id)
                ->where('enrolled_by_device_id', $device->id)
                ->where('sync_status', BiometricTemplateSyncStatusEnum::PendingDeletion)
                ->update([
                    'sync_status' => BiometricTemplateSyncStatusEnum::Superseded,
                    'is_active' => false,
                ]);

            // Create biometric template
            return BiometricTemplate::query()->create([
                'gym_member_id' => $member->id,
                'gym_id' => $device->gym_id,
                'enrolled_by_device_id' => $device->id,
                'biometric_type' => $this->biometricType,
                'provider_type' => $device->provider_type,
                'template_ref' => $templateRef,
                'quality_score' => null,
                'is_active' => true,
                'enrolled_at' => now(),
            ]);
        });

        // Optionally send to device for verification
        $shouldSendToDevice = $this->sendToDeviceEnabled ?? true;
        $deviceQueued = false;
        if ($shouldSendToDevice) {
            $deviceQueued = $this->sendToDevice($device, $member, (string) $templateRef, $createdTemplate->id);
        }

        if ($shouldSendToDevice && ! $deviceQueued && $device->adms_enabled) {
            Notification::make()
                ->title('Biometric Enrolled with Device Sync Warning')
                ->body("Member {$member->name} was enrolled in database, but the hardware sync command could not be queued.")
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Biometric Enrolled Successfully')
                ->body("Member {$member->name} has been enrolled with {$this->biometricType}.")
                ->success()
                ->send();
        }

        // Reset form
        $this->selectedMemberId = null;
        $this->rawBiometricData = null;
        $this->loadEnrolledTemplates();
    }

    protected function sendToDevice(BiometricDevice $device, GymMember $member, string $templateRef, int $templateId): bool
    {
        if (! $device->adms_enabled) {
            Log::info('sendToDevice skipped: device not ADMS-enabled', ['device_id' => $device->id]);

            return false;
        }

        try {
            AdmsDeviceCommand::query()->create([
                'gym_id' => $device->gym_id,
                'device_id' => $device->id,
                'command_type' => 'user_add',
                'command_payload' => [
                    'Pin' => BiometricTemplate::resolveCommandPin($templateRef, $member->id),
                    'Name' => $member->name,
                ],
                'status' => AdmsCommandStatusEnum::PENDING,
                'idempotency_key' => "user_add:{$device->id}:{$member->id}:{$templateId}",
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            Log::info('sendToDevice: user_add command already pending for member on device', [
                'device_id' => $device->id,
                'member_id' => $member->id,
                'template_id' => $templateId,
            ]);

            return false;
        } catch (Throwable $e) {
            Log::warning('sendToDevice: failed to queue user_add command', [
                'device_id' => $device->id,
                'member_id' => $member->id,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
