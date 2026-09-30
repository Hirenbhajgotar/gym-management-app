<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\CreateBiometricDevice;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\EditBiometricDevice;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages\ListBiometricDevices;
use App\Filament\BaseResources\BaseResource;
use App\Models\BiometricDevice;
use App\Models\User;
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
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class BiometricDeviceResource extends BaseResource
{
    protected static ?string $model = BiometricDevice::class;

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
                                Select::make('provider_type')
                                    ->label('Biometric Recognition Provider')
                                    ->placeholder('None — Device-Native (Hardware does verification)')
                                    ->options(BiometricProvidersEnum::class)
                                    ->nullable()
                                    ->live()
                                    ->helperText('Select a recognition provider. All cloud AI services are managed by the platform with zero configuration needed from you.'),

                                Section::make('')
                                    ->schema([
                                        Placeholder::make('device_native_info')
                                            ->label('Mode: Device-Native (On-Device Verification)')
                                            ->content('Hardware terminal performs biometric matching on-chip.'),

                                        Placeholder::make('webhook_endpoint_info')
                                            ->label('Webhook Endpoint (for device vendors)')
                                            ->content(function (): string {
                                                return config('app.url') . '/api/v1/biometric/webhook/{device_token}';
                                            })
                                            ->helperText('Device vendors send attendance events to this URL with HMAC signature in X-Signature header.'),
                                    ])
                                    ->visible(fn (Get $get): bool => static::isDeviceNative($get)),

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
                                    ->visible(fn (Get $get): bool => static::isProvider($get, BiometricProvidersEnum::Rekognition))
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
                                    ->visible(fn (Get $get): bool => static::isProvider($get, BiometricProvidersEnum::AzureFace))
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
                                    ->visible(fn (Get $get): bool => static::isProvider($get, BiometricProvidersEnum::BioID))
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
                                    ->visible(fn (Get $get): bool => static::isProvider($get, BiometricProvidersEnum::OnPremise))
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
                                            ->revealable(),
                                        TextInput::make('provider_config.secret')
                                            ->label('Secret')
                                            ->nullable()
                                            ->password()
                                            ->revealable(),

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
                                            ->helperText('Key-value pairs for custom headers')
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
                                    ->visible(fn (Get $get): bool => static::isProvider($get, BiometricProvidersEnum::Custom))
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
                                    ->maxLength(64)
                                    ->live(onBlur: true)
                                    ->required(fn (string $operation, Get $get, ?BiometricDevice $record): bool => (bool) $get('adms_enabled') && ($operation === 'create' || blank($record?->adms_comm_key_hash)))
                                    ->hintAction(
                                        FormAction::make('generateCommKey')
                                            ->label('Generate')
                                            ->icon('heroicon-o-key')
                                            ->action(function (Set $set) {
                                                $key = bin2hex(random_bytes(16));
                                                $set('adms_comm_key', $key);
                                            })
                                    )
                                    ->helperText('Required for ADMS push authentication. Must match the Comm Key / pushcommkey set on the terminal under Menu > Comm > Cloud Server.')
                                    ->visible(fn (Get $get): bool => (bool) $get('adms_enabled')),

                                Placeholder::make('adms_setup_info')
                                    ->label('ADMS Configuration')
                                    ->content(function (): string {
                                        $appUrl = config('app.url');
                                        $url = is_string($appUrl) ? $appUrl : '';

                                        return $url . '/api/iclock/cdata';
                                    })
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
                                    ->hintAction(
                                        FormAction::make('generateWebhookSecret')
                                            ->label('Generate')
                                            ->icon('heroicon-o-key')
                                            ->action(function (Set $set) {
                                                $secret = bin2hex(random_bytes(24)); // 48 character hex string
                                                $set('provider_config.webhook_secret', $secret);
                                            })
                                    )
                                    ->helperText('Used to verify HMAC signatures on incoming webhooks.'),

                                TextInput::make('provider_config.webhook_signature_header')
                                    ->label('Signature Header')
                                    ->placeholder('X-Signature')
                                    ->default('X-Signature')
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

                ActionGroup::make([
                    Action::make('regenerateToken')
                        ->label('Regenerate Token')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->authorize(fn (BiometricDevice $record): bool => auth()->user()?->can('rotateCredentials', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Regenerate API Token')
                        ->modalDescription('This will invalidate the current API token. A new one will be generated and copied to your clipboard.')
                        ->modalSubmitActionLabel('Regenerate')
                        ->action(function (BiometricDevice $record, Component $livewire): void {
                            $oldHash = $record->token_hash;
                            $plainToken = bin2hex(random_bytes(32));
                            $newHash = hash('sha256', $plainToken);

                            $record->update([
                                'device_token' => $plainToken,
                                'token_hash' => $newHash,
                            ]);

                            if ($oldHash) {
                                Cache::forget("biometric:device:token:{$oldHash}");
                            }
                            Cache::forget("biometric:device:token:{$newHash}");

                            $escapedToken = json_encode($plainToken);

                            $livewire->js("
                                window.navigator.clipboard.writeText({$escapedToken});
                                new FilamentNotification()
                                    .title('API Token Regenerated')
                                    .body('New token has been copied to your clipboard.')
                                    .success()
                                    .send();
                            ");
                        }),

                    Action::make('generateWebhookSecret')
                        ->label('Generate Webhook Secret')
                        ->icon('heroicon-o-shield-check')
                        ->color('info')
                        ->authorize(fn (BiometricDevice $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Generate Webhook Secret')
                        ->modalDescription('This will generate a new webhook secret and copy it to your clipboard. The old secret will no longer work.')
                        ->modalSubmitActionLabel('Generate')
                        ->action(function (BiometricDevice $record, Component $livewire): void {
                            $secret = bin2hex(random_bytes(24));
                            $config = $record->provider_config ?? [];
                            $config['webhook_secret'] = $secret;
                            $record->update(['provider_config' => $config]);

                            $escapedSecret = json_encode($secret);

                            $livewire->js("
                                window.navigator.clipboard.writeText({$escapedSecret});
                                new FilamentNotification()
                                    .title('Webhook Secret Generated')
                                    .body('New webhook secret has been copied to your clipboard.')
                                    .success()
                                    .send();
                            ");
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

                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
                                    ->state(fn (): string => parse_url(config('app.url') ?: 'http://localhost', PHP_URL_HOST) ?: request()->getHost())
                                    ->copyable()
                                    ->helperText('Enter in terminal without http:// or path'),

                                TextEntry::make('adms_server_port')
                                    ->label('Server Port')
                                    ->state(function (): string {
                                        $appUrl = config('app.url') ?: 'http://localhost';
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
                                    ->state(fn (): string => rtrim(config('app.url') ?: request()->root(), '/') . '/api/iclock/cdata')
                                    ->copyable(),

                                TextEntry::make('adms_comm_key_status')
                                    ->label('Communication Key (pushcommkey)')
                                    ->state(fn (BiometricDevice $record): string => filled($record->adms_comm_key_hash) ? 'Configured (Active)' : 'Not Configured')
                                    ->badge()
                                    ->color(fn (BiometricDevice $record): string => filled($record->adms_comm_key_hash) ? 'success' : 'warning'),
                            ]),

                        InfolistGrid::make(2)
                            ->schema([
                                TextEntry::make('adms_getrequest_url')
                                    ->label('Command Polling Endpoint')
                                    ->state(fn (): string => rtrim(config('app.url') ?: request()->root(), '/') . '/api/iclock/getrequest')
                                    ->copyable(),

                                TextEntry::make('adms_devicecmd_url')
                                    ->label('Command Acknowledgment Endpoint')
                                    ->state(fn (): string => rtrim(config('app.url') ?: request()->root(), '/') . '/api/iclock/devicecmd')
                                    ->copyable(),
                            ]),

                        ViewEntry::make('adms_setup_instructions')
                            ->view('filament.forms.components.adms-setup-card')
                            ->viewData(fn (BiometricDevice $record): array => [
                                'commKey' => filled($record->adms_comm_key_hash) ? 'Configured (Active)' : null,
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (BiometricDevice $record): bool => (bool) $record->adms_enabled),

                InfolistSection::make('API & Dual-Channel Webhook')
                    ->icon('heroicon-o-key')
                    ->schema([
                        TextEntry::make('webhook_endpoint')
                            ->label('Vendor Webhook Endpoint (Door Relay)')
                            ->state(fn (): string => rtrim(config('app.url') ?: request()->root(), '/') . '/api/v1/biometric/webhook/{device_token}')
                            ->copyable()
                            ->columnSpanFull()
                            ->helperText('Send attendance events to this endpoint for sub-second door relay control.'),

                        TextEntry::make('provider_config.webhook_signature_header')
                            ->label('Signature Header')
                            ->placeholder('X-Signature'),

                        TextEntry::make('webhook_secret_status')
                            ->label('Webhook Secret')
                            ->state(fn (BiometricDevice $record): string => filled($record->provider_config['webhook_secret'] ?? null) ? 'Configured' : 'Not Set')
                            ->badge()
                            ->color(fn (BiometricDevice $record): string => filled($record->provider_config['webhook_secret'] ?? null) ? 'success' : 'gray'),
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

    protected static function isProvider(Get $get, BiometricProvidersEnum $provider): bool
    {
        $value = $get('provider_type');

        return $value === $provider || $value === $provider->value;
    }

    protected static function isDeviceNative(Get $get): bool
    {
        $value = $get('provider_type');

        return blank($value) || $value === BiometricProvidersEnum::DeviceNative || $value === BiometricProvidersEnum::DeviceNative->value;
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
