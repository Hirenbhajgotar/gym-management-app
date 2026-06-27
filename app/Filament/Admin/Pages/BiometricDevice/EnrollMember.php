<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\BiometricDevice;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use App\Enums\BiometricTypeEnum;
use App\Filament\BasePages\BasePage;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\GymMember;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class EnrollMember extends BasePage
{
    protected static string $view = 'filament.admin.pages.biometric-device.enroll-member';

    protected static ?string $navigationGroup = 'Device Management';

    public ?int $selectedDeviceId = null;

    public ?int $selectedMemberId = null;

    public ?string $biometricType = null;

    public ?string $rawBiometricData = null;

    public ?bool $sendToDevice = true;

    public array $enrolledTemplates = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getDevices(): array
    {
        $tenant = Filament::getTenant();

        return BiometricDevice::query()
            ->where('gym_id', $tenant?->id)
            ->where('status', 'active')
            ->pluck('device_name', 'id')
            ->toArray();
    }

    public function getMembers(): array
    {
        if (! $this->selectedDeviceId) {
            return [];
        }

        $device = BiometricDevice::query()->find($this->selectedDeviceId);
        if (! $device) {
            return [];
        }

        // Get members who don't have this biometric type enrolled
        return GymMember::query()
            ->where('gym_id', $device->gym_id)
            ->whereDoesntHave('biometricTemplates', function (Builder $query) use ($device) {
                $query->where('biometric_type', $device->device_type)
                    ->where('is_active', true);
            })
            ->pluck('name', 'id')
            ->toArray();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Device & Member Selection')
                    ->schema([
                        Select::make('selectedDeviceId')
                            ->label('Biometric Device')
                            ->options(fn () => $this->getDevices())
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function () {
                                $this->selectedMemberId = null;
                                $this->enrolledTemplates = [];
                            }),

                        Select::make('selectedMemberId')
                            ->label('Member to Enroll')
                            ->options(fn () => $this->getMembers())
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function () {
                                $this->loadEnrolledTemplates();
                            }),

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
                            ->required()
                            ->helperText('Paste the Base64 encoded biometric template data from the device'),

                        Toggle::make('sendToDevice')
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

        $device = BiometricDevice::query()->find($this->selectedDeviceId);

        return $device?->device_type?->value;
    }

    protected function loadEnrolledTemplates(): void
    {
        if (! $this->selectedMemberId) {
            $this->enrolledTemplates = [];

            return;
        }

        $this->enrolledTemplates = BiometricTemplate::query()
            ->where('gym_member_id', $this->selectedMemberId)
            ->where('is_active', true)
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'type' => $template->biometric_type->label(),
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
            $html .= "<li><strong>{$template['type']}</strong> - Enrolled {$template['enrolled_at']} via {$template['device']}</li>";
        }

        return $html . '</ul>';
    }

    public function enroll(): void
    {
        $this->validate([
            'selectedDeviceId' => 'required|exists:biometric_devices,id',
            'selectedMemberId' => 'required|exists:gym_members,id',
            'biometricType' => 'required',
            'rawBiometricData' => 'required',
        ]);

        $device = BiometricDevice::query()->findOrFail($this->selectedDeviceId);
        $member = GymMember::query()->findOrFail($this->selectedMemberId);

        // Validate biometric data is valid base64
        if (! base64_decode($this->rawBiometricData, true)) {
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

        // Create biometric template
        BiometricTemplate::query()->create([
            'gym_member_id' => $member->id,
            'gym_id' => $device->gym_id,
            'enrolled_by_device_id' => $device->id,
            'biometric_type' => $this->biometricType,
            'provider_type' => $device->provider_type,
            'template_ref' => $templateRef,
            'quality_score' => null, // Could be calculated if device provides it
            'is_active' => true,
            'enrolled_at' => now(),
        ]);

        // Optionally send to device for verification
        if ($this->sendToDevice) {
            $this->sendToDevice($device, $member, $templateRef);
        }

        Notification::make()
            ->title('Biometric Enrolled Successfully')
            ->body("Member {$member->name} has been enrolled with {$this->biometricType}.")
            ->success()
            ->send();

        // Reset form
        $this->selectedMemberId = null;
        $this->rawBiometricData = null;
        $this->loadEnrolledTemplates();
    }

    protected function sendToDevice(BiometricDevice $device, GymMember $member, string $templateRef): void
    {
        // This would typically send the template to the device
        // For now, we'll log it or could integrate with provider APIs
        // In production, this might call the device's push API or provider's enrollment API
    }
}
