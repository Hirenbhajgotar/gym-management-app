<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Biometrics\Services\AdmsDeviceAuthenticator;
use App\Biometrics\Services\CommKeyReveal;
use App\Biometrics\Services\DeviceTokenReveal;
use App\Biometrics\Services\WebhookSecretReveal;
use App\Biometrics\Services\WebhookSecretRotationService;
use App\Enums\AdmsUserTableEnum;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\CreateBiometricDevice;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\EditBiometricDevice;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\ListBiometricDevices;
use App\Filament\BaseResources\BaseResource;
use App\Helpers\ClipboardHelper;
use App\Models\BiometricDevice;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Grid as InfolistGrid;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class BiometricDeviceResource extends BaseResource
{
    protected static ?string $model = BiometricDevice::class;

    /**
     * Per-request memo for the (expensive) decryptability check. Filament
     * evaluates provider field visibility for every field on every render.
     *
     * @var array<string, bool>
     */
    private static array $providerConfigUnreadableCache = [];

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationLabel = 'Biometric Devices';

    protected static ?string $navigationGroup = 'Device Management';

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        $tenantId = $tenant->getKey();
        $tenantKey = is_scalar($tenantId) ? (string) $tenantId : '';

        return Cache::flexible(BiometricDevice::NAVIGATION_BADGE_CACHE_KEY . $tenantKey, [1800, 3600], function () use ($tenantKey): string {
            return (string) BiometricDevice::query()->where('gym_id', $tenantKey)->count();
        }) ?: null;
    }

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
                'create',
                'delete',
                'delete_any',
                'update',
                'restore',
                'replicate',
                'reorder',
                'export',
                'import',
            ],

            DefaultRole::RECEPTIONIST->value => [],
            DefaultRole::TRAINER->value => [],
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::ADMIN->value,
            DefaultRole::GYM_OWNER->value,
        ])) {
            return true;
        }

        $slug = (new static())->getPermissionSlug();

        return $user->can("view_any_{$slug}");
    }

    /**
     * @return Builder<BiometricDevice>
     */
    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            /** @var Builder<BiometricDevice> $query */
            $query = parent::getEloquentQuery();

            return $query;
        }

        /** @var Builder<BiometricDevice> $query */
        $query = parent::getEloquentQuery()->where('gym_id', $tenant->getKey());

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Device Information')
                            ->schema([
                                Select::make('gym_id')
                                    ->label('Gym')
                                    ->relationship('gym', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn () => auth()->user()?->hasRole('super_admin')),

                                TextInput::make('device_name')
                                    ->label('Device Name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('device_serial')
                                    ->label('Device Serial Number (SN)')
                                    ->maxLength(255)
                                    ->required(fn (Get $get): bool => (bool) $get('adms_enabled'))
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Found under Menu > Comm > ADMS or on the hardware sticker.'),

                                TextInput::make('location')
                                    ->label('Location')
                                    ->maxLength(255)
                                    ->nullable(),

                                Select::make('device_type')
                                    ->label('Biometric Type')
                                    ->options(BiometricTypeEnum::class)
                                    ->default(BiometricTypeEnum::AllInOne)
                                    ->required(),
                            ])->columns(2),

                        Section::make('What is this device for?')
                            ->description('Select the primary purpose of this biometric device.')
                            ->schema([
                                Radio::make('purpose')
                                    ->label('Device Purpose')
                                    ->options(BiometricDevicePurpose::class)
                                    ->required()
                                    ->default(BiometricDevicePurpose::AttendanceOnly)
                                    ->descriptions([
                                        BiometricDevicePurpose::AttendanceOnly->value => 'This device will record member check-ins only. No door control.',
                                        BiometricDevicePurpose::DoorAccess->value => 'This device will unlock the door AND record attendance.',
                                        BiometricDevicePurpose::DoorAccessNoAttendance->value => 'This device will only unlock the door. No attendance will be recorded.',
                                    ]),
                            ]),

                        Section::make('Provider Configuration')
                            ->description('Configure biometric recognition. Cloud AI services (AWS Rekognition, Azure Face, BioID) are managed centrally by the platform — no API keys or accounts required from you. For smart hardware terminals (Hikvision, ZKTeco, Suprema), keep Device-Native.')
                            ->schema([
                                Placeholder::make('provider_config_unreadable_warning')
                                    ->label('Provider configuration is unreadable')
                                    ->content(fn (Get $get): string => (bool) $get('replace_unreadable_provider_config')
                                        ? 'The stored configuration will be replaced with the values submitted for the selected provider. This cannot be undone.'
                                        : 'The stored provider configuration cannot be decrypted. It is preserved on save so the ciphertext can still be recovered from a backup or an old application key.')
                                    ->visible(fn (?BiometricDevice $record): bool => self::providerConfigIsUnreadable($record))
                                    ->columnSpanFull(),

                                Toggle::make('replace_unreadable_provider_config')
                                    ->label('Replace unreadable provider configuration')
                                    ->helperText('Only tick this if you intend to discard the existing encrypted value permanently. Leave it off to keep the current ciphertext.')
                                    ->visible(fn (?BiometricDevice $record): bool => self::providerConfigIsUnreadable($record))
                                    ->live()
                                    ->columnSpanFull(),

                                Select::make('provider_type')
                                    ->label('Biometric Recognition Provider')
                                    ->placeholder('None — Device-Native (Hardware does verification)')
                                    ->options(BiometricProvidersEnum::class)
                                    ->nullable()
                                    ->live()
                                    ->helperText(fn (Get $get, ?BiometricDevice $record): string => self::providerConfigIsUnreadable($record)
                                        && ! (bool) $get('replace_unreadable_provider_config')
                                        ? 'Warning: the stored configuration is unreadable. Changing providers without the replacement toggle preserves the old ciphertext and may leave the new provider misconfigured.'
                                        : 'Select a recognition provider. Cloud AI services are managed by the platform.'),

                                Section::make('')
                                    ->schema([
                                        Placeholder::make('device_native_info')
                                            ->label('Mode: Device-Native (On-Device Verification)')
                                            ->content('Hardware terminal performs biometric matching on-chip.'),

                                        Placeholder::make('webhook_endpoint_info')
                                            ->label('Webhook Endpoint (for device vendors)')
                                            ->content(function (): string {
                                                return rtrim(self::resolvedAppUrl(), '/') . '/api/v1/biometric/webhook/{device_token}';
                                            })
                                            ->helperText('Device vendors send attendance events to this URL with HMAC signature in X-Signature header.'),
                                    ])
                                    ->visible(fn (Get $get, ?BiometricDevice $record): bool => self::providerConfigEditable($get, $record)
                                        && static::isDeviceNative($get)),

                                // AWS Rekognition Configuration
                                Section::make('AWS Rekognition Settings')
                                    ->description('Cloud AI facial recognition powered by AWS Rekognition (Managed by Platform)')
                                    ->schema([
                                        Placeholder::make('aws_status')
                                            ->label('Connection Status')
                                            ->content('Connected (Managed by Platform)')
                                            ->helperText('AWS Rekognition credentials and infrastructure are securely connected and managed at the platform level.'),

                                        Placeholder::make('collection_info')
                                            ->label('Face Collection ID')
                                            ->content(function (): string {
                                                $tenantId = Filament::getTenant()?->getKey();
                                                $tenantKey = is_scalar($tenantId) ? (string) $tenantId : 'tenant';

                                                return 'flux_gym_' . $tenantKey;
                                            })
                                            ->helperText("Your members' facial biometric templates are securely isolated in your gym's private collection."),

                                        TextInput::make('provider_config.similarity_threshold')
                                            ->label('Match Similarity Threshold (%)')
                                            ->numeric()
                                            ->minValue(50)
                                            ->maxValue(100)
                                            ->default(config('biometrics.similarity_threshold') * 100)
                                            ->helperText('Minimum confidence percentage required to grant access (Default: ' . (config('biometrics.similarity_threshold') * 100) . '%).'),
                                    ])
                                    ->visible(fn (Get $get, ?BiometricDevice $record): bool => self::providerConfigEditable($get, $record)
                                        && static::isProvider($get, BiometricProvidersEnum::Rekognition))
                                    ->columns(2),

                                // Azure Face Configuration
                                Section::make('Azure Face Settings')
                                    ->description('Cloud AI facial recognition powered by Azure Face API (Managed by Platform)')
                                    ->schema([
                                        Placeholder::make('azure_status')
                                            ->label('Connection Status')
                                            ->content('Connected (Managed by Platform)')
                                            ->helperText('Azure Face API credentials and infrastructure are securely connected and managed at the platform level.'),

                                        Placeholder::make('person_group_info')
                                            ->label('Person Group ID')
                                            ->content(function (): string {
                                                $tenantId = Filament::getTenant()?->getKey();
                                                $tenantKey = is_scalar($tenantId) ? (string) $tenantId : 'tenant';

                                                return 'fluxfitness-gym-' . $tenantKey;
                                            })
                                            ->helperText('Your members\' facial biometric templates are securely isolated in your gym\'s private person group.'),

                                        TextInput::make('provider_config.confidence_threshold')
                                            ->label('Match Confidence Threshold (%)')
                                            ->numeric()
                                            ->minValue(50)
                                            ->maxValue(100)
                                            ->default(config('biometrics.confidence_threshold') * 100)
                                            ->helperText('Minimum confidence percentage required to grant access (Default: ' . (config('biometrics.confidence_threshold') * 100) . '%).'),
                                    ])
                                    ->visible(fn (Get $get, ?BiometricDevice $record): bool => self::providerConfigEditable($get, $record)
                                        && static::isProvider($get, BiometricProvidersEnum::AzureFace))
                                    ->columns(2),

                                // BioID Configuration
                                Section::make('BioID Settings')
                                    ->description('Anti-spoofing and liveness detection powered by BioID Web Service (Managed by Platform)')
                                    ->schema([
                                        Placeholder::make('bioid_status')
                                            ->label('Connection Status')
                                            ->content('Connected (Managed by Platform)')
                                            ->helperText('BioID credentials and anti-spoofing services are securely connected and managed at the platform level.'),

                                        Toggle::make('provider_config.enable_liveness')
                                            ->label('Enable Liveness Detection')
                                            ->default(true)
                                            ->helperText('Verifies real human presence to prevent photo or video spoofing.'),
                                    ])
                                    ->visible(fn (Get $get, ?BiometricDevice $record): bool => self::providerConfigEditable($get, $record)
                                        && static::isProvider($get, BiometricProvidersEnum::BioID))
                                    ->columns(2),

                                // On-Premise/Local Configuration
                                Section::make('Local Model Settings')
                                    ->description('Configure self-hosted model (DeepFace, InsightFace, etc.)')
                                    ->schema([
                                        TextInput::make('provider_config.server_url')
                                            ->label('Server URL')
                                            ->placeholder('http://localhost:5000')
                                            ->required(),
                                        TextInput::make('provider_config.model_type')
                                            ->label('Model Type')
                                            ->placeholder('facenet, arcface, vggface')
                                            ->required(),
                                        TextInput::make('provider_config.model_path')
                                            ->label('Model Path (optional)')
                                            ->nullable(),
                                        TextInput::make('provider_config.timeout')
                                            ->label('Request Timeout (seconds)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(300)
                                            ->default(30),
                                    ])
                                    ->visible(fn (Get $get, ?BiometricDevice $record): bool => self::providerConfigEditable($get, $record)
                                        && static::isProvider($get, BiometricProvidersEnum::OnPremise))
                                    ->columns(2),

                                // Custom API Configuration
                                Section::make('Custom API Settings')
                                    ->description('Configure a generic HTTP biometric API')
                                    ->schema([
                                        TextInput::make('provider_config.api_url')
                                            ->label('API URL')
                                            ->placeholder('https://api.example.com/biometric')
                                            ->required(),
                                        TextInput::make('provider_config.api_key')
                                            ->label('API Key')
                                            ->nullable()
                                            ->password()
                                            ->revealable()
                                            ->helperText('Leave blank to keep the stored value. Existing credentials are never shown in the form.'),
                                        TextInput::make('provider_config.secret')
                                            ->label('Secret')
                                            ->nullable()
                                            ->password()
                                            ->revealable()
                                            ->helperText('Leave blank to keep the stored value. Existing credentials are never shown in the form.'),

                                        Select::make('provider_config.auth_type')
                                            ->label('Authentication Type')
                                            ->options([
                                                'bearer' => 'Bearer Token',
                                                'x-api-key' => 'X-API-Key Header',
                                                'basic' => 'Basic Auth',
                                                'custom' => 'Custom Headers Only',
                                            ])
                                            ->default('bearer'),

                                        TextInput::make('provider_config.timeout')
                                            ->label('Request Timeout (seconds)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(300)
                                            ->default(30),

                                        Toggle::make('provider_config.skip_liveness')
                                            ->label('Skip Liveness Check')
                                            ->helperText('Enable only if vendor does not support liveness detection')
                                            ->default(false),

                                        KeyValue::make('provider_config.headers')
                                            ->label('Additional Headers')
                                            ->helperText('Key-value pairs for custom headers. Leave blank to keep the stored headers; adding a pair replaces the stored map.')
                                            ->nullable()
                                            ->columnSpanFull(),

                                        Section::make('Endpoint Paths')
                                            ->description('Optional: Override default paths if vendor API uses different routes')
                                            ->schema([
                                                TextInput::make('provider_config.path_enroll')
                                                    ->label('Enroll Path')
                                                    ->placeholder('/enroll')  // default
                                                    ->nullable(),

                                                TextInput::make('provider_config.path_verify')
                                                    ->label('Verify Path')
                                                    ->placeholder('/verify')  // default
                                                    ->nullable(),

                                                TextInput::make('provider_config.path_liveness')
                                                    ->label('Liveness Path')
                                                    ->placeholder('/liveness')  // default
                                                    ->nullable(),

                                                TextInput::make('provider_config.path_delete')
                                                    ->label('Delete Path')
                                                    ->placeholder('/delete')  // default
                                                    ->nullable(),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->collapsed(),

                                        Section::make('Response Mapping')
                                            ->description('Map vendor response fields to FluxFitness expected fields')
                                            ->schema([
                                                TextInput::make('provider_config.response_template_id_key')
                                                    ->label('Template ID Field')
                                                    ->placeholder('template_id')
                                                    ->nullable(),
                                                TextInput::make('provider_config.response_matched_key')
                                                    ->label('Match Result Field')
                                                    ->placeholder('matched')
                                                    ->nullable(),
                                                TextInput::make('provider_config.response_confidence_key')
                                                    ->label('Confidence Score Field')
                                                    ->placeholder('confidence')
                                                    ->nullable(),
                                                TextInput::make('provider_config.response_liveness_key')
                                                    ->label('Liveness Score Field')
                                                    ->placeholder('liveness_score')
                                                    ->nullable(),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->collapsed(),
                                    ])
                                    ->visible(fn (Get $get, ?BiometricDevice $record): bool => self::providerConfigEditable($get, $record)
                                        && static::isProvider($get, BiometricProvidersEnum::Custom))
                                    ->columns(2),
                            ]),
                    ])->columnSpan(['lg' => 2]),
                Group::make()
                    ->schema([
                        Section::make('Status & Protocol')
                            ->schema([
                                Toggle::make('is_enabled')
                                    ->label('Administratively Enabled')
                                    ->helperText('When disabled, all check-ins and device communications are rejected.')
                                    ->default(true),

                                ToggleButtons::make('status')
                                    ->label('Status')
                                    ->options(BiometricDeviceStatusEnum::class)
                                    ->inline()
                                    ->default(BiometricDeviceStatusEnum::Active),

                                Toggle::make('adms_enabled')
                                    ->label('Enable ADMS Protocol')
                                    ->helperText('Enable eSSL / ZKTeco push protocol for attendance sync & member management.')
                                    ->live()
                                    ->default(false),

                                TextInput::make('adms_comm_key')
                                    ->label('ADMS Communication Key (pushcommkey)')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(BiometricDevice::COMM_KEY_PASTE_BUFFER)
                                    ->placeholder('e.g. 123456')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                        if (! is_string($state)) {
                                            // Leave malformed Livewire state untouched so the
                                            // field rule rejects it, instead of silently
                                            // normalising it to "blank" and saving.
                                            return;
                                        }

                                        $cleaned = BiometricDevice::sanitizeCommKey($state);
                                        if ($cleaned !== $state) {
                                            $set('adms_comm_key', $cleaned);
                                        }
                                    })
                                    ->dehydrateStateUsing(fn (mixed $state): ?string => is_string($state) && $state !== '' ? BiometricDevice::sanitizeCommKey($state) : null)
                                    ->rules([
                                        fn (Get $get, ?BiometricDevice $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                            if ($value !== null && ! is_string($value)) {
                                                $fail('The ADMS Communication Key must be a string.');

                                                return;
                                            }

                                            $cleaned = BiometricDevice::sanitizeCommKey($value);
                                            $transitionRequiresKey = (bool) $get('adms_enabled')
                                                && self::commKeyTransitionRequiresKey($get, $record);

                                            if ($cleaned === '') {
                                                if ($transitionRequiresKey) {
                                                    $fail('Enter a new ADMS Communication Key before switching away from device-native. The existing plaintext comm_key alias is not read for a cloud provider.');
                                                }

                                                return;
                                            }

                                            $validationError = BiometricDevice::commKeyValidationError($cleaned);

                                            if ($validationError !== null) {
                                                $fail($validationError);
                                            }
                                        },
                                    ])
                                    ->hintAction(
                                        FormAction::make('generateCommKey')
                                            ->label('Generate')
                                            ->icon('heroicon-o-key')
                                            ->action(function (Set $set) {
                                                $set('adms_comm_key', BiometricDevice::generateCommKey());
                                            })
                                    )
                                    ->helperText(fn (string $operation): string => $operation === 'create'
                                        ? 'Optional. Leave blank for devices that do not send pushcommkey (e.g. eSSL MB20) to authenticate by Serial Number only. For devices supporting a push communication key, enter exactly 6 letters or numbers (a 6-digit number is recommended).'
                                        : 'Leave blank to keep the current key. Enter a new 6-digit key to rotate. When switching from a device-native device to a cloud provider you must enter a new key: the plaintext comm_key alias is not read for a cloud provider. A new key is shown only in the one-time reveal window; the stored value is never displayed.')
                                    ->visible(fn (Get $get): bool => (bool) $get('adms_enabled')),

                                Select::make('provider_config.adms_user_table')
                                    ->label('ADMS User Table Format')
                                    ->options(AdmsUserTableEnum::class)
                                    ->placeholder('Inherit global default (' . config()->string('biometrics.adms.user_table', AdmsUserTableEnum::UserInfo->value) . ')')
                                    ->nullable()
                                    // On create, a blank selector must not persist the key at
                                    // all, so the terminal inherits the global default. On edit,
                                    // the key is always dehydrated so clearing the selector
                                    // removes an existing override.
                                    ->dehydrated(fn (string $operation, mixed $state): bool => $operation === 'edit' || filled($state))
                                    ->helperText('Leave blank to inherit ADMS_USER_TABLE. Choose USERINFO for standard eSSL/ZKTeco firmware, or user for modern SpeedFace/Linux/Android terminals.')
                                    ->visible(fn (Get $get, ?BiometricDevice $record): bool => self::providerConfigEditable($get, $record)
                                        && (bool) $get('adms_enabled')),

                                Placeholder::make('adms_setup_info')
                                    ->label('ADMS Configuration')
                                    ->content(fn (): string => rtrim(self::resolvedAppUrl(), '/') . '/api/iclock/cdata')
                                    ->helperText('On device: Menu > Comm > ADMS / Cloud Server > set Server Address to this URL.')
                                    ->visible(fn (Get $get): bool => (bool) $get('adms_enabled')),
                            ]),
                        Section::make('Security & Access')
                            ->schema([

                                TextInput::make('provider_config.webhook_secret')
                                    ->label('Webhook Secret')
                                    ->password()
                                    ->revealable()
                                    ->nullable()
                                    ->visible(fn (Get $get, ?BiometricDevice $record): bool => self::providerConfigEditable($get, $record))
                                    ->rules([
                                        fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                                            $error = self::manualWebhookSecretValidationError($value);

                                            if ($error !== null) {
                                                $fail($error);
                                            }
                                        },
                                    ])
                                    ->helperText('Used to verify HMAC signatures on incoming webhooks. At least ' . WebhookSecretRotationService::MANUAL_WEBHOOK_SECRET_MIN_LENGTH . ' characters; letters, numbers, and . _ ~ + / = - only. Leave blank to keep the stored secret. Generated secrets are 64 hex characters. The stored value is never displayed; use the audited table action to rotate it.'),

                                TextInput::make('provider_config.webhook_signature_header')
                                    ->label('Signature Header')
                                    ->placeholder('X-Signature')
                                    ->default('X-Signature')
                                    ->visible(fn (Get $get, ?BiometricDevice $record): bool => self::providerConfigEditable($get, $record))
                                    ->helperText('Header name containing HMAC-SHA256 signature (accepts sha256=... or raw hex)'),
                            ]),
                        Section::make('API Credentials')
                            ->schema([
                                Placeholder::make('token_notice')
                                    ->content(function (): string {
                                        return 'A secure API token will be auto-generated on creation. You can view/regenerate it from the table actions.';
                                    }),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('device_name')
                    ->label('Device Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('device_serial')
                    ->label('Serial (SN)')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('adms_enabled')
                    ->label('ADMS')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('device_type')
                    ->label('Biometric Type')
                    ->badge()
                    ->color('info'),

                TextColumn::make('provider_type')
                    ->label('Provider')
                    ->badge()
                    ->placeholder('Device-Native (Hardware)')
                    ->color(fn (?BiometricProvidersEnum $state): string => $state ? $state->getColor() : 'success'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('device_type')
                    ->label('Biometric Type')
                    ->options(BiometricTypeEnum::class),

                SelectFilter::make('provider_type')
                    ->label('Provider')
                    ->options(BiometricProvidersEnum::class),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(BiometricDeviceStatusEnum::class),
            ])
            ->actions([
                ViewAction::make()
                    ->slideOver(),

                Action::make('admsGuide')
                    ->label('ADMS Setup')
                    ->icon('heroicon-o-signal')
                    ->color('success')
                    ->visible(fn (BiometricDevice $record): bool => $record->adms_enabled)
                    ->modalHeading('ADMS Terminal Setup')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (BiometricDevice $record) => view('filament.forms.components.adms-setup-card', [
                        'commKeyStatus' => self::commKeyStatusLabel($record),
                        'appUrl' => self::resolvedAppUrl(),
                    ])),

                ActionGroup::make([
                    Action::make('regenerateToken')
                        ->label('Regenerate Token')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->authorize(fn (BiometricDevice $record): bool => auth()->user()?->can('rotateCredentials', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Regenerate API Token')
                        ->modalDescription('This will invalidate the current API token. A new one will be generated and stored for one-time reveal.')
                        ->modalSubmitActionLabel('Regenerate')
                        ->action(function (BiometricDevice $record, Component $livewire): void {
                            $result = resolve(WebhookSecretRotationService::class)->regenerateToken($record);

                            if (! $result['rotated']) {
                                Notification::make()
                                    ->danger()
                                    ->title('Token was not regenerated')
                                    ->body($result['failure'] ?? 'The API token could not be regenerated. The existing token is unchanged.')
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            try {
                                activity()
                                    ->performedOn($record)
                                    ->withProperties([
                                        'gym_id' => $record->gym_id,
                                        'action' => 'adms_device_token_regenerated',
                                        'reveal_available' => $result['reveal_available'],
                                    ])
                                    ->log('ADMS device API token regenerated');
                            } catch (Throwable $exception) {
                                Log::warning('ADMS: device token regeneration audit failed', [
                                    'device_id' => $record->getKey(),
                                    'exception' => $exception::class,
                                ]);
                            }

                            if (! $result['reveal_available']) {
                                Notification::make()
                                    ->danger()
                                    ->title('Token regenerated but not revealable')
                                    ->body($result['failure'] ?? 'The database token was updated, but it could not be stored for one-time reveal. Regenerate again once the cache is healthy.')
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            $plainToken = (string) $result['secret'];

                            // The modal is the primary delivery path. The
                            // clipboard is a convenience and may be blocked on
                            // HTTP LAN deployments.
                            $livewire->js(ClipboardHelper::copyScript(
                                $plainToken,
                                'API Token Regenerated',
                                'Token regenerated — use Reveal API Token',
                                'New token copied to the clipboard. Use Reveal API Token to read it again within ' . DeviceTokenReveal::TTL_MINUTES . ' minutes.',
                                'The clipboard is unavailable. Use Reveal API Token to read the new token within ' . DeviceTokenReveal::TTL_MINUTES . ' minutes.'
                            ));
                        }),

                    Action::make('generateWebhookSecret')
                        ->label('Generate Webhook Secret')
                        ->icon('heroicon-o-shield-check')
                        ->color('info')
                        ->authorize(fn (BiometricDevice $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Generate Webhook Secret')
                        ->modalDescription('This generates a new 64-character webhook secret. The old secret stops working immediately; use "Reveal Webhook Secret" to read the new one.')
                        ->modalSubmitActionLabel('Generate')
                        ->action(function (BiometricDevice $record): void {
                            if (self::providerConfigIsUnreadable($record)) {
                                Notification::make()
                                    ->danger()
                                    ->title('Provider configuration is unreadable')
                                    ->body('The stored configuration cannot be decrypted and was left untouched. Open Edit and tick "Replace unreadable provider configuration" before generating a webhook secret.')
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            $result = resolve(WebhookSecretRotationService::class)->rotateWebhookSecret($record);

                            if (! $result['rotated']) {
                                Notification::make()
                                    ->danger()
                                    ->title('Webhook secret was not rotated')
                                    ->body($result['failure'] ?? 'The webhook secret could not be rotated. The existing secret is unchanged.')
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            try {
                                activity()
                                    ->performedOn($record)
                                    ->withProperties([
                                        'gym_id' => $record->gym_id,
                                        'action' => 'adms_webhook_secret_generated',
                                        'reveal_available' => $result['reveal_available'],
                                    ])
                                    ->log('ADMS device webhook secret generated');
                            } catch (Throwable $exception) {
                                Log::warning('ADMS: webhook secret generation audit failed', [
                                    'device_id' => $record->getKey(),
                                    'exception' => $exception::class,
                                ]);
                            }

                            if (! $result['reveal_available']) {
                                Notification::make()
                                    ->danger()
                                    ->title('Webhook secret rotated but not revealable')
                                    ->body($result['failure'] ?? 'The database secret was updated, but it could not be stored for one-time reveal. Generate a new secret once the cache is healthy.')
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title('Webhook secret generated')
                                ->body('Use "Reveal Webhook Secret" within ' . WebhookSecretReveal::TTL_MINUTES . ' minutes to read it.')
                                ->send();
                        }),

                    Action::make('revealWebhookSecret')
                        ->label('Reveal Webhook Secret')
                        ->icon('heroicon-o-eye')
                        ->color('warning')
                        ->visible(fn (BiometricDevice $record): bool => WebhookSecretReveal::exists($record->id, self::revealViewerKey()))
                        ->authorize(fn (BiometricDevice $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->modalHeading('Reveal one-time webhook secret')
                        ->modalDescription('The secret is not stored in your session. It remains available in this dialog for a few minutes after the first reveal; copy it now.')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->modalContent(function (BiometricDevice $record) {
                            $claim = WebhookSecretReveal::claimForDisplay($record->id, self::revealViewerKey());

                            if ($claim['claimed'] || $claim['first_delivery_read']) {
                                try {
                                    activity()
                                        ->performedOn($record)
                                        ->withProperties([
                                            'gym_id' => $record->gym_id,
                                            'action' => 'adms_webhook_secret_revealed',
                                        ])
                                        ->log('ADMS device webhook secret revealed');
                                } catch (Throwable $exception) {
                                    Log::warning('ADMS: webhook secret reveal audit failed', [
                                        'device_id' => $record->getKey(),
                                        'exception' => $exception::class,
                                    ]);
                                }
                            }

                            return view('filament.biometric.one-time-secret', [
                                'secret' => $claim['secret'],
                                'description' => 'Webhook HMAC secret for this device:',
                                'unavailableAction' => 'Regenerate',
                            ]);
                        }),

                    Action::make('viewCommands')
                        ->label('View Commands')
                        ->icon('heroicon-o-command-line')
                        ->color('info')
                        ->visible(fn (BiometricDevice $record): bool => $record->adms_enabled)
                        ->url(fn (BiometricDevice $record): string => AdmsDeviceCommandResource::getUrl('index', [
                            'tableFilters' => [
                                'device_id' => ['value' => $record->id],
                            ],
                        ])),

                    Action::make('revealCommKey')
                        ->label('Reveal Comm Key')
                        ->icon('heroicon-o-eye')
                        ->color('warning')
                        ->visible(fn (BiometricDevice $record): bool => CommKeyReveal::exists($record->id, self::revealViewerKey()))
                        ->authorize(fn (BiometricDevice $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->modalHeading('Reveal one-time communication key')
                        ->modalDescription('The key is not stored in your session. It remains available in this dialog for a few minutes after the first reveal; copy it now.')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->modalContent(function (BiometricDevice $record) {
                            // Claim once, then render from the encrypted delivery
                            // copy so a table re-render (search, pagination,
                            // retry) cannot blank the modal. Nothing is written
                            // to the session or the Livewire snapshot.
                            $claim = CommKeyReveal::claimForDisplay($record->id, self::revealViewerKey());

                            if ($claim['claimed'] || $claim['first_delivery_read']) {
                                try {
                                    activity()
                                        ->performedOn($record)
                                        ->withProperties([
                                            'gym_id' => $record->gym_id,
                                            'action' => 'adms_comm_key_revealed',
                                        ])
                                        ->log('ADMS device comm key revealed');
                                } catch (Throwable $exception) {
                                    // The reveal must still render if the audit store is down.
                                    Log::warning('ADMS: comm key reveal audit failed', [
                                        'device_id' => $record->getKey(),
                                        'exception' => $exception::class,
                                    ]);
                                }
                            }

                            return view('filament.biometric.one-time-secret', [
                                'secret' => $claim['secret'],
                                'description' => 'ADMS communication key for this device:',
                                'unavailableAction' => 'Rotate',
                            ]);
                        }),

                    Action::make('revealToken')
                        ->label('Reveal API Token')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->visible(fn (BiometricDevice $record): bool => DeviceTokenReveal::exists($record->id, self::revealViewerKey()))
                        ->authorize(fn (BiometricDevice $record): bool => auth()->user()?->can('rotateCredentials', $record) ?? false)
                        ->modalHeading('Reveal one-time API token')
                        ->modalDescription('The token is not stored in your session. It remains available in this dialog for a few minutes after the first reveal; copy it now.')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->modalContent(function (BiometricDevice $record) {
                            $claim = DeviceTokenReveal::claimForDisplay($record->id, self::revealViewerKey());

                            if ($claim['claimed'] || $claim['first_delivery_read']) {
                                try {
                                    activity()
                                        ->performedOn($record)
                                        ->withProperties([
                                            'gym_id' => $record->gym_id,
                                            'action' => 'adms_device_token_revealed',
                                        ])
                                        ->log('ADMS device API token revealed');
                                } catch (Throwable $exception) {
                                    Log::warning('ADMS: device token reveal audit failed', [
                                        'device_id' => $record->getKey(),
                                        'exception' => $exception::class,
                                    ]);
                                }
                            }

                            return view('filament.biometric.one-time-secret', [
                                'secret' => $claim['secret'],
                                'description' => 'API bearer token for this device:',
                                'unavailableAction' => 'Regenerate',
                            ]);
                        }),

                    Action::make('rotateCommKey')
                        ->label('Rotate Comm Key')
                        ->icon('heroicon-o-arrow-path')
                        ->color('danger')
                        ->visible(fn (BiometricDevice $record): bool => $record->adms_enabled)
                        ->authorize(fn (BiometricDevice $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Rotate communication key')
                        ->modalDescription('This invalidates the current key. You must update the physical terminal immediately; use Reveal Comm Key to read the new key.')
                        ->modalSubmitActionLabel('Rotate')
                        ->action(function (BiometricDevice $record): void {
                            $plainKey = BiometricDevice::generateCommKey();
                            $salt = BiometricDevice::generateCommKeySalt();
                            $removedAliases = [];

                            $rotated = self::withProviderConfigLock($record, 'Communication key was not rotated', function () use ($record, $plainKey, $salt, &$removedAliases): bool {
                                try {
                                    $config = is_array($record->provider_config) ? $record->provider_config : null;
                                } catch (Throwable) {
                                    // A corrupt provider_config must not block the
                                    // rotation that is meant to recover the device.
                                    $config = null;
                                }

                                $attributes = [
                                    'adms_comm_key_hash' => BiometricDevice::hashCommKey($plainKey, $salt),
                                    'adms_comm_key_salt' => $salt,
                                ];

                                if ($config !== null) {
                                    $stripping = $record->stripCommKeyAliases($config);
                                    $attributes['provider_config'] = $stripping->config;
                                    $removedAliases = $stripping->removedAliases;
                                }

                                $record->forceFill($attributes)->saveQuietly();

                                return true;
                            });

                            if (! $rotated) {
                                return;
                            }

                            CommKeyReveal::forget($record->id);
                            $stored = CommKeyReveal::put($record->id, $plainKey);

                            resolve(AdmsDeviceAuthenticator::class)->resetFailures($record);

                            try {
                                activity()
                                    ->performedOn($record)
                                    ->withProperties([
                                        'gym_id' => $record->gym_id,
                                        'action' => 'adms_comm_key_rotated',
                                        'reveal_available' => $stored,
                                        'removed_aliases' => $removedAliases,
                                        'provider_type' => $record->provider_type?->value,
                                    ])
                                    ->log('ADMS device comm key rotated');
                            } catch (Throwable $exception) {
                                Log::warning('ADMS: comm key rotation audit failed', [
                                    'device_id' => $record->getKey(),
                                    'exception' => $exception::class,
                                ]);
                            }

                            if (! $stored) {
                                Notification::make()
                                    ->danger()
                                    ->title('Key rotated but not revealable')
                                    ->body('The cache is unavailable. Rotate again once the cache is healthy, or set the key manually from the edit page.')
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title('Communication key rotated')
                                ->body('Use Reveal Comm Key to read the new one-time key.')
                                ->send();
                        }),

                    Action::make('removeCommKey')
                        ->label('Remove Comm Key')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (BiometricDevice $record): bool => $record->adms_enabled
                            && $record->hasCommKeyConfigured()
                            && ! $record->providerConfigIsUnreadable())
                        ->authorize(fn (BiometricDevice $record): bool => auth()->user()?->can('rotateCredentials', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Remove communication key')
                        ->modalDescription('This device will authenticate by Serial Number only. Clear the key on the terminal\'s own menu as well, or the terminal will keep sending the old one.')
                        ->modalSubmitActionLabel('Remove key')
                        ->form([
                            TextInput::make('reason')
                                ->label('Reason (optional)')
                                ->maxLength(255),
                        ])
                        ->action(function (BiometricDevice $record, array $data): void {
                            if (config()->boolean('biometrics.adms.require_comm_key', false)) {
                                Notification::make()
                                    ->danger()
                                    ->title('Communication key was not removed')
                                    ->body('Server-wide key enforcement (ADMS_REQUIRE_COMM_KEY) is enabled, so this device would stop authenticating. Disable enforcement first.')
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            $removedAliases = [];

                            $removed = self::withProviderConfigLock($record, 'Communication key was not removed', function () use ($record, &$removedAliases): bool {
                                try {
                                    $rawConfig = $record->provider_config;
                                    $config = is_array($rawConfig) ? $rawConfig : [];
                                    $hadConfig = $rawConfig !== null;
                                } catch (Throwable $exception) {
                                    Log::warning('ADMS: comm key removal refused on an unreadable provider_config', [
                                        'device_id' => $record->getKey(),
                                        'exception' => $exception::class,
                                    ]);

                                    Notification::make()
                                        ->danger()
                                        ->title('Communication key was not removed')
                                        ->body('The provider configuration cannot be decrypted. Repair or replace it before removing the key.')
                                        ->persistent()
                                        ->send();

                                    return false;
                                }

                                $stripping = $record->stripCommKeyAliases($config);

                                $attributes = [
                                    'adms_comm_key_hash' => null,
                                    'adms_comm_key_salt' => null,
                                ];

                                if ($hadConfig) {
                                    $attributes['provider_config'] = $stripping->config;
                                }

                                $removedAliases = $stripping->removedAliases;

                                $record->forceFill($attributes)->saveQuietly();

                                return true;
                            });

                            if (! $removed) {
                                return;
                            }

                            CommKeyReveal::forget($record->id);
                            resolve(AdmsDeviceAuthenticator::class)->resetFailures($record);

                            $reason = is_string($data['reason'] ?? null) ? trim($data['reason']) : '';

                            try {
                                activity()
                                    ->performedOn($record)
                                    ->withProperties([
                                        'gym_id' => $record->gym_id,
                                        'action' => 'adms_comm_key_removed',
                                        'removed_aliases' => $removedAliases,
                                        'provider_type' => $record->provider_type?->value,
                                        'reason' => $reason === '' ? null : $reason,
                                    ])
                                    ->log('ADMS device comm key removed');
                            } catch (Throwable $exception) {
                                Log::warning('ADMS: comm key removal audit failed', [
                                    'device_id' => $record->getKey(),
                                    'exception' => $exception::class,
                                ]);
                            }

                            Notification::make()
                                ->success()
                                ->title('Communication key removed')
                                ->body('The terminal now authenticates by Serial Number. Clear the key on the device itself if it still sends one.')
                                ->send();
                        }),

                    EditAction::make(),
                    DeleteAction::make()
                        ->modalHeading('Delete biometric terminal')
                        ->modalDescription(fn (BiometricDevice $record): string => self::deleteModalDescription($record))
                        ->action(function (BiometricDevice $record, DeleteAction $action): void {
                            $parkedTemplateCount = self::parkedTemplateCount($record);

                            try {
                                if (! $record->delete()) {
                                    Notification::make()
                                        ->title('Terminal Was Not Deleted')
                                        ->body('The deletion was refused. Refresh the table and retry, or contact support if it keeps failing.')
                                        ->danger()
                                        ->send();

                                    return;
                                }
                            } catch (Throwable $exception) {
                                // A misconfigured audit sink or a future listener must surface
                                // as a legible red notification, not a Livewire error page.
                                Log::error('BiometricDeviceResource: row delete failed for one terminal', [
                                    'device_id' => $record->getKey(),
                                    'gym_id' => $record->gym_id,
                                    'error_type' => $exception::class,
                                ]);

                                Notification::make()
                                    ->title('Terminal Was Not Deleted')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            if ($parkedTemplateCount > 0) {
                                $action->successNotification(
                                    fn (Notification $notification): Notification => $notification->body(
                                        "{$parkedTemplateCount} enrolled template(s) were parked for manual erasure."
                                    )
                                );
                            }

                            $action->success();
                        }),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Delete biometric terminals')
                        ->modalDescription(self::bulkDeleteModalDescription())
                        ->action(function (Collection $records, DeleteBulkAction $action): void {
                            $deletedCount = 0;
                            $refusedCount = 0;
                            $failedCount = 0;

                            /** @var BiometricDevice $record */
                            foreach ($records as $record) {
                                // The permission-only deleteAny() gate does not carry the
                                // tenant predicate, so every record must clear the policy's
                                // per-record delete() check before it is destroyed.
                                if (! Gate::allows('delete', $record)) {
                                    $refusedCount++;

                                    continue;
                                }

                                // Each delete is atomic on its own (tombstone, ledger
                                // cancellation, DELETE and audit share one transaction), but
                                // the selection as a whole is not: record N failing must not
                                // be counted as success, and must not abort the rows that
                                // could be deleted. Filament's own DeleteBulkAction reports a
                                // single success unconditionally and has no per-record
                                // buckets, which is why this override exists.
                                try {
                                    if ($record->delete() === true) {
                                        $deletedCount++;
                                    } else {
                                        $failedCount++;
                                    }
                                } catch (Throwable $exception) {
                                    $failedCount++;

                                    Log::error('BiometricDeviceResource: bulk delete failed for one terminal', [
                                        'device_id' => $record->getKey(),
                                        'gym_id' => $record->gym_id,
                                        'error_type' => $exception::class,
                                    ]);
                                }
                            }

                            // Only claim success for terminals that were actually deleted.
                            if ($deletedCount > 0) {
                                $action->success();
                            }

                            if ($refusedCount > 0) {
                                Notification::make()
                                    ->danger()
                                    ->title("{$refusedCount} terminal(s) refused")
                                    ->body("{$refusedCount} selected terminal(s) could not be deleted because they belong to another gym or your policy does not permit it. {$deletedCount} terminal(s) were deleted.")
                                    ->persistent()
                                    ->send();
                            }

                            if ($failedCount > 0) {
                                Notification::make()
                                    ->danger()
                                    ->title("{$failedCount} terminal(s) failed")
                                    ->body("{$failedCount} selected terminal(s) could not be deleted: the audit sink refused the deletion or the record changed while it was being deleted. {$deletedCount} terminal(s) were deleted.")
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private const DELETE_RETENTION_MESSAGE = 'Door unlock events and webhook receipts are retained for audit; they are no longer cascade-deleted with the terminal.';

    /**
     * The number of templates the delete hook will park.
     *
     * Delegated to the model so the modal and the hook read one candidate set and one
     * evidence rule: the modal used to pre-filter `liveOrPendingCredentialStates()`, which
     * excluded an already-`DeletionUnresolvable` row the hook re-parks, and it ran the
     * delivery check per row.
     */
    private static function parkedTemplateCount(BiometricDevice $record): int
    {
        return $record->parkedTemplateCount();
    }

    private static function deleteModalDescription(BiometricDevice $record): string
    {
        $parkedTemplateCount = self::parkedTemplateCount($record);

        $description = 'This action cannot be undone. Deleting the terminal removes it from this application, but it cannot erase credentials stored in the terminal\'s flash memory.';

        $description .= $parkedTemplateCount > 0
            ? " {$parkedTemplateCount} enrolled template(s) will be parked for manual erasure and will need an operator."
            : ' No live enrolled templates are recorded for it.';

        return $description . ' ' . self::DELETE_RETENTION_MESSAGE;
    }

    private static function bulkDeleteModalDescription(): string
    {
        return 'This action cannot be undone. Deleting the terminals removes them from this application, but it cannot erase credentials stored in their flash memory. Enrolled templates are parked for manual erasure and will need an operator. Only records you are authorised to delete in this tenant will be deleted. ' . self::DELETE_RETENTION_MESSAGE;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Device Overview')
                    ->icon('heroicon-o-cpu-chip')
                    ->schema([
                        TextEntry::make('device_name')
                            ->label('Device Name')
                            ->weight('bold'),

                        TextEntry::make('device_serial')
                            ->label('Device Serial Number (SN)')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('location')
                            ->label('Location')
                            ->placeholder('Not specified'),

                        TextEntry::make('purpose')
                            ->label('Purpose')
                            ->badge(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),

                        TextEntry::make('device_type')
                            ->label('Biometric Type')
                            ->badge(),

                        TextEntry::make('is_enabled')
                            ->label('Administrative Access')
                            ->badge()
                            ->state(fn (BiometricDevice $record): string => $record->is_enabled ? 'Enabled' : 'Disabled')
                            ->color(fn (BiometricDevice $record): string => $record->is_enabled ? 'success' : 'danger'),

                        TextEntry::make('gym.name')
                            ->label('Assigned Gym')
                            ->placeholder('—'),

                        TextEntry::make('last_heartbeat_at')
                            ->label('Last Heartbeat / Activity')
                            ->dateTime('M d, Y H:i:s')
                            ->placeholder('No activity recorded yet'),

                        TextEntry::make('created_at')
                            ->label('Registered At')
                            ->dateTime('M d, Y H:i:s'),
                    ])->columns(2),

                InfolistSection::make('ADMS Protocol & Terminal Configuration')
                    ->icon('heroicon-o-signal')
                    ->description('Settings for eSSL / ZKTeco push protocol terminals.')
                    ->schema([
                        InfolistGrid::make(3)
                            ->schema([
                                TextEntry::make('adms_server_host')
                                    ->label('Server Address / Domain')
                                    ->state(fn (): string => parse_url(self::resolvedAppUrl(), PHP_URL_HOST) ?: request()->getHost())
                                    ->copyable()
                                    ->helperText('Enter in terminal without http:// or path'),

                                TextEntry::make('adms_server_port')
                                    ->label('Server Port')
                                    ->state(function (): string {
                                        $appUrl = self::resolvedAppUrl();
                                        $port = parse_url($appUrl, PHP_URL_PORT);

                                        if ($port) {
                                            return (string) $port;
                                        }

                                        return parse_url($appUrl, PHP_URL_SCHEME) === 'https' ? '443' : '80';
                                    })
                                    ->copyable()
                                    ->helperText('HTTP: 80 / HTTPS: 443'),

                                TextEntry::make('adms_web_path')
                                    ->label('Web Path / Directory')
                                    ->state(fn (): string => '/api')
                                    ->copyable()
                                    ->helperText('If supported by terminal firmware'),
                            ]),

                        InfolistGrid::make(2)
                            ->schema([
                                TextEntry::make('adms_cdata_url')
                                    ->label('Attendance Push URL (Handshake)')
                                    ->state(fn (): string => rtrim(self::resolvedAppUrl(), '/') . '/api/iclock/cdata')
                                    ->copyable(),

                                TextEntry::make('adms_comm_key_status')
                                    ->label('Communication Key (pushcommkey)')
                                    ->state(fn (BiometricDevice $record): string => self::commKeyStatusLabel($record))
                                    ->badge()
                                    ->color(fn (BiometricDevice $record): string => self::commKeyStatusColor($record)),
                            ]),

                        InfolistGrid::make(2)
                            ->schema([
                                TextEntry::make('adms_getrequest_url')
                                    ->label('Command Polling Endpoint')
                                    ->state(fn (): string => rtrim(self::resolvedAppUrl(), '/') . '/api/iclock/getrequest')
                                    ->copyable(),

                                TextEntry::make('adms_devicecmd_url')
                                    ->label('Command Acknowledgment Endpoint')
                                    ->state(fn (): string => rtrim(self::resolvedAppUrl(), '/') . '/api/iclock/devicecmd')
                                    ->copyable(),
                            ]),

                        ViewEntry::make('adms_setup_instructions')
                            ->view('filament.forms.components.adms-setup-card')
                            ->viewData(fn (BiometricDevice $record): array => [
                                'commKeyStatus' => self::commKeyStatusLabel($record),
                                'appUrl' => self::resolvedAppUrl(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (BiometricDevice $record): bool => $record->adms_enabled),

                InfolistSection::make('API & Dual-Channel Webhook')
                    ->icon('heroicon-o-key')
                    ->schema([
                        TextEntry::make('webhook_endpoint')
                            ->label('Vendor Webhook Endpoint (Door Relay)')
                            ->state(fn (): string => rtrim(self::resolvedAppUrl(), '/') . '/api/v1/biometric/webhook/{device_token}')
                            ->copyable()
                            ->columnSpanFull()
                            ->helperText('Send attendance events to this endpoint for sub-second door relay control.'),

                        TextEntry::make('provider_config.webhook_signature_header')
                            ->label('Signature Header')
                            ->placeholder('X-Signature'),

                        TextEntry::make('webhook_secret_status')
                            ->label('Webhook Secret')
                            ->state(fn (BiometricDevice $record): string => self::webhookSecretStatus($record)['label'])
                            ->badge()
                            ->color(fn (BiometricDevice $record): string => self::webhookSecretStatus($record)['color']),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBiometricDevices::route('/'),
            'create' => CreateBiometricDevice::route('/create'),
            'edit' => EditBiometricDevice::route('/{record}/edit'),
        ];
    }

    /**
     * @return array{label: string, color: string}
     */
    public static function webhookSecretStatus(BiometricDevice $record): array
    {
        if (self::providerConfigIsUnreadable($record)) {
            return ['label' => 'Unreadable', 'color' => 'danger'];
        }

        $config = $record->provider_config;

        if (! is_array($config) || ! array_key_exists('webhook_secret', $config)) {
            return ['label' => 'Not Set', 'color' => 'gray'];
        }

        $secret = $config['webhook_secret'];

        if ($secret === null || $secret === '') {
            return ['label' => 'Not Set', 'color' => 'gray'];
        }

        if (! is_string($secret)) {
            return ['label' => 'Invalid — regenerate', 'color' => 'danger'];
        }

        // A non-empty string is not necessarily usable: the HMAC verifier
        // must meet the same minimum length/charset policy as a manual entry,
        // otherwise a legacy short value shows a misleading green badge.
        if (self::manualWebhookSecretValidationError($secret) !== null) {
            return ['label' => 'Weak — regenerate', 'color' => 'danger'];
        }

        return ['label' => 'Configured', 'color' => 'success'];
    }

    private static function commKeyStatusLabel(BiometricDevice $record): string
    {
        // Check the unversioned shape first. The verifier is usable, but it
        // carries no iteration count; the UI should say so rather than imply a
        // fully versioned verifier. Defensive probe: this resource can be
        // deployed independently of the model method.
        if ($record->commKeyVerifierIsUnversioned()) {
            return 'Configured (unversioned)';
        }

        // A valid verifier still authenticates even when provider_config can no
        // longer be decrypted; do not suggest a destructive rotation for it.
        if ($record->hasRecognizedCommKeyVerifier()) {
            return 'Configured';
        }

        if ($record->hasRealCommKeyHash()) {
            return 'Unrecognized verifier — rotate the key';
        }

        if (self::providerConfigIsUnreadable($record)) {
            return 'Unreadable — re-enter or rotate the key';
        }

        return $record->hasCommKeyConfigured() ? 'Configured' : 'Not configured';
    }

    private static function commKeyStatusColor(BiometricDevice $record): string
    {
        if ($record->commKeyVerifierIsUnversioned()) {
            return 'success';
        }

        if ($record->hasRecognizedCommKeyVerifier()) {
            return 'success';
        }

        if ($record->hasRealCommKeyHash()) {
            return 'danger';
        }

        if (self::providerConfigIsUnreadable($record)) {
            return 'danger';
        }

        return $record->hasCommKeyConfigured() ? 'success' : 'warning';
    }

    /**
     * One-time delivery copies are keyed by the authenticated viewer so a
     * second operator cannot read a colleague's copy without an audit entry.
     */
    private static function revealViewerKey(): string
    {
        $userId = auth()->id();

        return $userId === null ? 'guest' : (string) $userId;
    }

    /**
     * Whether the submitted provider_config contains a non-blank manual
     * webhook secret. Call this after the Edit page has stripped blank secret
     * inputs; a true result needs the audit/forget hook after commit.
     *
     * @param  array<string, mixed>  $submittedConfig
     */
    public static function manualWebhookSecretWasSubmitted(array $submittedConfig): bool
    {
        return array_key_exists('webhook_secret', $submittedConfig)
            && filled($submittedConfig['webhook_secret']);
    }

    /**
     * Validate a manually entered webhook secret.
     *
     * Blank means "keep the stored value". Anything else must be at least 32
     * characters of `[A-Za-z0-9._~+/=-]` with no whitespace or control
     * characters, matching the strength of the generated 64-hex secret.
     */
    public static function manualWebhookSecretValidationError(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            return 'The webhook secret must be a valid string.';
        }

        if (strlen($value) < WebhookSecretRotationService::MANUAL_WEBHOOK_SECRET_MIN_LENGTH) {
            return 'The webhook secret must be at least ' . WebhookSecretRotationService::MANUAL_WEBHOOK_SECRET_MIN_LENGTH . ' characters.';
        }

        if (preg_match('/[\s\x00-\x1F\x7F]/', $value) === 1) {
            return 'The webhook secret must not contain whitespace or control characters.';
        }

        if (preg_match(WebhookSecretRotationService::MANUAL_WEBHOOK_SECRET_PATTERN, $value) !== 1) {
            return 'The webhook secret may contain only letters, numbers, and . _ ~ + / = - characters.';
        }

        return null;
    }

    /**
     * Provider configuration inputs stay hidden while the stored payload
     * cannot be decrypted. Replacing it requires the explicit toggle, which
     * also prevents the save path from silently re-encrypting an empty array
     * over the recoverable ciphertext.
     */
    protected static function providerConfigEditable(Get $get, ?BiometricDevice $record): bool
    {
        if ($record === null || ! self::providerConfigIsUnreadable($record)) {
            return true;
        }

        return (bool) $get('replace_unreadable_provider_config');
    }

    private static function providerConfigIsUnreadable(?BiometricDevice $record): bool
    {
        if ($record === null) {
            return false;
        }

        $raw = $record->getRawOriginal('provider_config');
        $key = self::deviceKey($record) . ':' . (is_string($raw) ? md5($raw) : 'null');

        return self::$providerConfigUnreadableCache[$key] ??= $record->providerConfigIsUnreadable();
    }

    /**
     * Normalize an Eloquent key for the typed lock/reveal helpers.
     */
    private static function deviceKey(BiometricDevice $record): int|string
    {
        $key = $record->getKey();

        return is_int($key) || is_string($key) ? $key : 0;
    }

    /**
     * Run a provider_config mutation while holding this device's lock.
     *
     * The callback returns true when it changed the row; false means it already
     * reported why the mutation was refused. Lock acquisition failures are
     * reported here so rotation and removal cannot drift.
     *
     * @param  Closure(): bool  $callback
     */
    private static function withProviderConfigLock(BiometricDevice $record, string $failureTitle, Closure $callback): bool
    {
        try {
            $lock = Cache::lock(
                WebhookSecretRotationService::providerConfigLockKey(self::deviceKey($record)),
                WebhookSecretRotationService::LOCK_TTL_SECONDS
            );

            $acquired = $lock->get();
        } catch (Throwable $exception) {
            Log::warning('ADMS: could not create the provider configuration lock', [
                'device_id' => $record->getKey(),
                'exception' => $exception::class,
            ]);

            self::notifyLockFailure($failureTitle, 'The provider configuration lock could not be created. Nothing was changed; try again once the cache is healthy.');

            return false;
        }

        if (! $acquired) {
            self::notifyLockFailure($failureTitle, 'Another change to this device is in progress. Nothing was changed; wait a moment and try again.');

            return false;
        }

        try {
            // Re-read under the lock so the mutation operates on the config
            // another writer may have changed.
            $record->refresh();

            return $callback();
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
                // The lock TTL is the backstop.
            }
        }
    }

    private static function notifyLockFailure(string $title, string $body): void
    {
        Notification::make()
            ->danger()
            ->title($title)
            ->body($body)
            ->persistent()
            ->send();
    }

    public static function resolvedAppUrl(): string
    {
        return self::configString('app.url', request()->root() ?: 'http://localhost');
    }

    private static function configString(string $key, string $default): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Non-secret provider_config fields rendered by the form. Edit-page fill
     * and the drift test derive from this list.
     *
     * @return array<int, string>
     */
    public static function providerConfigFormFields(?BiometricProvidersEnum $provider): array
    {
        return match ($provider) {
            BiometricProvidersEnum::Rekognition => ['similarity_threshold'],
            BiometricProvidersEnum::AzureFace => ['confidence_threshold'],
            BiometricProvidersEnum::BioID => ['enable_liveness'],
            BiometricProvidersEnum::OnPremise => ['server_url', 'model_type', 'model_path', 'timeout'],
            BiometricProvidersEnum::Custom => [
                'api_url',
                'auth_type',
                'timeout',
                'skip_liveness',
                'path_enroll',
                'path_verify',
                'path_liveness',
                'path_delete',
                'response_template_id_key',
                'response_matched_key',
                'response_confidence_key',
                'response_liveness_key',
            ],
            default => [],
        };
    }

    /**
     * Non-secret provider_config fields that are rendered for every provider.
     *
     * These fields are only rendered while {@see self::providerConfigEditable()}
     * permits it: an unreadable stored payload hides them until the operator
     * opts into replacing it. Edit-page fill and the form drift test derive from
     * this list; the edit page must not hardcode the signature header.
     *
     * `adms_user_table` is deliberately not here: it is ADMS terminal state, not
     * provider configuration, and it is rendered only when ADMS is enabled. It is
     * hydrated by {@see EditBiometricDevice::providerConfigForForm()} explicitly.
     *
     * @return array<int, string>
     */
    public static function providerConfigCommonFields(): array
    {
        return ['webhook_signature_header'];
    }

    /**
     * Secret provider_config fields, the providers that render them, and why
     * any provider excludes them.
     *
     * The form drift test fails when a field remains in this map but the form
     * stops rendering it for a provider that should support it.
     *
     * @return array<string, array{providers: array<int, BiometricProvidersEnum>, reason: string}>
     */
    public static function providerConfigSecretFieldOwners(): array
    {
        return [
            'webhook_secret' => [
                'providers' => BiometricProvidersEnum::cases(),
                'reason' => 'Shared webhook HMAC secret for every provider.',
            ],
            'api_key' => [
                'providers' => [BiometricProvidersEnum::Custom],
                'reason' => 'Custom HTTP providers only; cloud provider credentials are managed by the platform.',
            ],
            'secret' => [
                'providers' => [BiometricProvidersEnum::Custom],
                'reason' => 'Custom HTTP providers only; cloud provider credentials are managed by the platform.',
            ],
            'headers' => [
                'providers' => [BiometricProvidersEnum::Custom],
                'reason' => 'Custom HTTP providers only; cloud providers do not accept arbitrary headers.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function providerConfigSecretFields(): array
    {
        return array_keys(self::providerConfigSecretFieldOwners());
    }

    protected static function isProvider(Get $get, BiometricProvidersEnum $provider): bool
    {
        $value = $get('provider_type');

        return $value === $provider || $value === $provider->value;
    }

    protected static function isDeviceNative(Get $get): bool
    {
        return BiometricDevice::providerTypeIsDeviceNative($get('provider_type'));
    }

    /**
     * A bare `comm_key` alias is only dropped when the provider leaves
     * device-native mode, so the switch is the only place a replacement key is
     * mandatory. Normal create and edit allow a serial-only terminal.
     */
    private static function commKeyTransitionRequiresKey(Get $get, ?BiometricDevice $record): bool
    {
        if ($record === null || ! $record->hasOnlyPlaintextCommKeyAlias()) {
            return false;
        }

        return BiometricDevice::providerTypeIsDeviceNative($record->provider_type)
            && ! BiometricDevice::providerTypeIsDeviceNative($get('provider_type'));
    }

    /**
     * Check if the purpose field indicates door access (handles both enum and string).
     */
    public static function isDoorAccessPurpose(mixed $purpose): bool
    {
        $enum = $purpose instanceof BiometricDevicePurpose
            ? $purpose
            : (is_string($purpose) ? BiometricDevicePurpose::tryFrom($purpose) : null);

        return $enum === BiometricDevicePurpose::DoorAccess
            || $enum === BiometricDevicePurpose::DoorAccessNoAttendance;
    }
}
