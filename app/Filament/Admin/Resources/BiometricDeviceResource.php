<?php

namespace App\Filament\Admin\Resources;

use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricDoorControlType;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages;
use App\Models\BiometricDevice;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as ComponentsActionsAction;
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
use Filament\Notifications\Actions\Action as ActionsAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Js;

class BiometricDeviceResource extends Resource
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

        return Cache::flexible(BiometricDevice::NAVIGATION_BADGE_CACHE_KEY . $tenant->id, [1800, 3600], function () use ($tenant) {
            return (string) static::getModel()::where('gym_id', $tenant->id)->count();
        }) ?: null;
    }

    public static function canAccess(array $parameters = []): bool
    {
        // Temporary disabled this resource.
        return false;

        /** @var \App\Models\User $user */
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

        return $user->can('view_any_biometric_device');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return parent::getEloquentQuery();
        }

        return parent::getEloquentQuery()->where('gym_id', $tenant->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Device Information')
                    ->schema([
                        Select::make('gym_id')
                            ->label('Gym')
                            ->relationship('gym', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->visible(fn () => auth()->user()->hasRole('super_admin')),

                        TextInput::make('device_name')
                            ->label('Device Name')
                            ->required()
                            ->maxLength(255),

                        Select::make('device_type')
                            ->label('Biometric Type')
                            ->options(BiometricTypeEnum::class)
                            ->required()
                            ->live(),

                        ToggleButtons::make('status')
                            ->label('Status')
                            ->options(BiometricDeviceStatusEnum::class)
                            ->inline()
                            ->default(BiometricDeviceStatusEnum::Active),

                        TextInput::make('location')
                            ->label('Location')
                            ->maxLength(255)
                            ->nullable(),

                        Toggle::make('offline_capable')
                            ->label('Offline Mode')
                            ->helperText('Allow device to operate without internet connection'),

                        Toggle::make('supports_multi_modal')
                            ->label('Multi-Modal')
                            ->helperText('Support for multiple biometric types'),

                        TextInput::make('firmware_version')
                            ->label('Firmware Version')
                            ->placeholder('v2.1.0')
                            ->nullable()
                            ->maxLength(50),
                    ])->columns(2),

                Section::make('Provider Configuration')
                    ->schema([
                        Select::make('provider_type')
                            ->label('Provider')
                            ->options([
                                'rekognition' => 'AWS Rekognition',
                                'azure_face' => 'Azure Face',
                                'bioid' => 'BioID',
                                'on_premise' => 'Local/On-Premise',
                                'custom' => 'Custom API',
                            ])
                            ->required()
                            ->live(),

                        // AWS Rekognition Configuration
                        Section::make('AWS Rekognition Settings')
                            ->description('Configure AWS Rekognition face recognition')
                            ->schema([
                                TextInput::make('provider_config.region')
                                    ->label('AWS Region')
                                    ->placeholder('us-east-1')
                                    ->required()
                                    ->live(),
                                TextInput::make('provider_config.access_key')
                                    ->label('AWS Access Key ID')
                                    ->required()
                                    ->password()
                                    ->revealable()
                                    ->live(),
                                TextInput::make('provider_config.secret_key')
                                    ->label('AWS Secret Access Key')
                                    ->required()
                                    ->password()
                                    ->revealable()
                                    ->live(),
                                TextInput::make('provider_config.similarity_threshold')
                                    ->label('Similarity Threshold (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(config('biometrics.similarity_threshold') * 100)
                                    ->live()
                                    ->helperText('Override global threshold (' . config('biometrics.similarity_threshold') * 100 . '%). Leave default if unsure.'),

                                Placeholder::make('collection_info')
                                    ->label('Face Collection ID')
                                    ->content(fn () => 'Auto-generated: flux_gym_' . Filament::getTenant()->id)
                                    ->helperText('Collection is automatically created in AWS when first member enrolls'),
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('provider_type') === 'rekognition')
                            ->columns(2),

                        // Azure Face Configuration
                        Section::make('Azure Face Settings')
                            ->description('Configure Azure Face API recognition')
                            ->schema([
                                TextInput::make('provider_config.endpoint')
                                    ->label('Azure Endpoint')
                                    ->placeholder('https://<region>.api.cognitive.microsoft.com/')
                                    ->required()
                                    ->live(),
                                TextInput::make('provider_config.subscription_key')
                                    ->label('Subscription Key')
                                    ->required()
                                    ->password()
                                    ->revealable()
                                    ->live(),
                                TextInput::make('provider_config.confidence_threshold')
                                    ->label('Confidence Threshold (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(config('biometrics.confidence_threshold') * 100)
                                    ->live()
                                    ->helperText('Override global threshold (' . config('biometrics.confidence_threshold') * 100 . '%). Leave default if unsure.'),

                                // face_list_id TextInput hatao, replace karo with:
                                Placeholder::make('person_group_info')
                                    ->label('Person Group ID')
                                    ->content(fn () => 'Auto-generated: flux_gym-' . Filament::getTenant()->id),
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('provider_type') === 'azure_face')
                            ->columns(2),

                        // BioID Configuration
                        Section::make('BioID Settings')
                            ->description('Configure BioID liveness detection')
                            ->schema([
                                TextInput::make('provider_config.wsdl_url')
                                    ->label('BWS API URL')
                                    ->placeholder('https://bws.bioid.com/extension')
                                    ->helperText('BioID Web Service 3.0 base URL')
                                    ->required()
                                    ->live(),

                                TextInput::make('provider_config.app_identifier')
                                    ->label('App Identifier')
                                    ->required()
                                    ->helperText('From BioID portal — your application ID'),

                                TextInput::make('provider_config.api_key')
                                    ->label('API Key')
                                    ->helperText('App secret from BioID portal')
                                    ->required()
                                    ->password()
                                    ->revealable()
                                    ->live(),

                                TextInput::make('provider_config.service_id')
                                    ->label('Service ID')
                                    ->helperText('Storage ID from BioID portal (used in BCID)')
                                    ->nullable()
                                    ->live(),

                                Toggle::make('provider_config.enable_liveness')
                                    ->label('Enable Liveness Detection')
                                    ->default(true)
                                    ->live(),
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('provider_type') === 'bioid')
                            ->columns(2),

                        // On-Premise/Local Configuration
                        Section::make('Local Model Settings')
                            ->description('Configure self-hosted model (DeepFace, InsightFace, etc.)')
                            ->schema([
                                TextInput::make('provider_config.server_url')
                                    ->label('Server URL')
                                    ->placeholder('http://localhost:5000')
                                    ->required()
                                    ->live(),
                                TextInput::make('provider_config.model_type')
                                    ->label('Model Type')
                                    ->placeholder('facenet, arcface, vggface')
                                    ->required()
                                    ->live(),
                                TextInput::make('provider_config.model_path')
                                    ->label('Model Path (optional)')
                                    ->nullable()
                                    ->live(),
                                TextInput::make('provider_config.timeout')
                                    ->label('Request Timeout (seconds)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(300)
                                    ->default(30)
                                    ->live(),
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('provider_type') === 'on_premise')
                            ->columns(2),

                        // Custom API Configuration
                        Section::make('Custom API Settings')
                            ->description('Configure a generic HTTP biometric API')
                            ->schema([
                                TextInput::make('provider_config.api_url')
                                    ->label('API URL')
                                    ->placeholder('https://api.example.com/biometric')
                                    ->required()
                                    ->live(),
                                TextInput::make('provider_config.api_key')
                                    ->label('API Key')
                                    ->nullable()
                                    ->password()
                                    ->revealable()
                                    ->live(),
                                TextInput::make('provider_config.secret')
                                    ->label('Secret')
                                    ->nullable()
                                    ->password()
                                    ->revealable()
                                    ->live(),

                                Select::make('provider_config.auth_type')
                                    ->label('Authentication Type')
                                    ->options([
                                        'bearer' => 'Bearer Token',
                                        'x-api-key' => 'X-API-Key Header',
                                        'basic' => 'Basic Auth',
                                        'custom' => 'Custom Headers Only',
                                    ])
                                    ->default('bearer')
                                    ->live(),

                                TextInput::make('provider_config.timeout')
                                    ->label('Request Timeout (seconds)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(300)
                                    ->default(30)
                                    ->live(),

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
                            ->visible(fn (Forms\Get $get): bool => $get('provider_type') === 'custom')
                            ->columns(2),
                    ]),

                // ── SECTION: Device Purpose — THIS DRIVES EVERYTHING ─────
                Section::make('What is this device for?')
                    ->description('Select the primary purpose. Fields below will update accordingly.')
                    ->schema([
                        Radio::make('purpose')
                            ->label('Device Purpose')
                            ->options(BiometricDevicePurpose::class)
                            ->live()
                            ->required()
                            ->default(BiometricDevicePurpose::AttendanceOnly)
                            ->descriptions([
                                BiometricDevicePurpose::AttendanceOnly->value => 'This device will record member check-ins only. No door control.',
                                BiometricDevicePurpose::DoorAccess->value => 'This device will unlock the door AND record attendance.',
                                BiometricDevicePurpose::DoorAccessNoAttendance->value => 'This device will only unlock the door. No attendance will be recorded.',
                            ]),
                    ]),

                // ── SECTION: Door Control ─────────────────────────────────
                Section::make('Door Control Configuration')
                    ->description('Configure how this device controls the door lock.')
                    ->schema([
                        Select::make('door_control_type')
                            ->label('Door Control Method')
                            ->options(BiometricDoorControlType::toSelectOptions())
                            ->required()
                            ->live()
                            ->helperText(fn (Forms\Get $get) => $get('door_control_type')
                                ? BiometricDoorControlType::from($get('door_control_type'))->getShortDescription()
                                : ''),

                        TextInput::make('door_controller_url')
                            ->label('Door Controller API URL')
                            ->placeholder('http://192.168.1.200:8080/door/unlock')
                            ->url()
                            ->required()
                            ->visible(fn (Forms\Get $get): bool => $get('door_control_type') === BiometricDoorControlType::Api->value),

                        TextInput::make('door_controller_token')
                            ->label('Door Controller Token')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->visible(fn (Forms\Get $get): bool => $get('door_control_type') === BiometricDoorControlType::Api->value),

                        TextInput::make('unlock_duration_seconds')
                            ->label('Unlock Duration (seconds)')
                            ->numeric()
                            ->default(5)
                            ->minValue(1)
                            ->maxValue(30)
                            ->helperText('How long the door stays unlocked after a successful check-in'),

                        TextInput::make('door_zone')
                            ->label('Door Zone Label')
                            ->placeholder('main_entrance')
                            ->helperText('e.g. main_entrance, gym_floor, pool, locker_room')
                            ->nullable(),

                        Select::make('connection_mode')
                            ->label('Connection Mode')
                            ->options([
                                'webhook' => 'Device sends data to FluxFitness (Recommended)',
                                'poll' => 'FluxFitness fetches data from device',
                            ])
                            ->default('webhook')
                            ->live()
                            ->required(),

                        TextInput::make('poll_interval_seconds')
                            ->label('Poll Interval (seconds)')
                            ->numeric()
                            ->default(60)
                            ->minValue(10)
                            ->maxValue(300)
                            ->visible(fn (Forms\Get $get): bool => $get('connection_mode') === 'poll')
                            ->helperText('How often FluxFitness checks the device for new events'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::isDoorAccessPurpose($get('purpose')))
                    ->collapsible(),

                Section::make('Security & Access')
                    ->schema([
                        Placeholder::make('token_info')
                            ->label('API Token')
                            ->content(function (): string {
                                return 'A secure API token will be auto-generated on creation. You can view/regenerate it from the table actions.';
                            }),

                        Placeholder::make('webhook_endpoint_info')
                            ->label('Webhook Endpoint (for device vendors)')
                            ->content(function (): string {
                                return config('app.url') . '/api/v1/biometric/webhook/{device_token}';
                            })
                            ->helperText('Device vendors send attendance events to this URL with HMAC signature in X-Signature header.'),

                        TextInput::make('provider_config.webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->hint('Generate webhook secret')
                            ->hintAction(
                                ComponentsActionsAction::make('generateWebhookSecret')
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
                            ->helperText('Header name containing HMAC-SHA256 signature (without sha256= prefix)'),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('device_name')
                    ->label('Device Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('device_type')
                    ->label('Biometric Type')
                    ->badge()
                    ->color('info'),

                TextColumn::make('provider_type')
                    ->label('Provider')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'rekognition' => 'AWS Rekognition',
                        'azure_face' => 'Azure Face',
                        'bioid' => 'BioID',
                        'on_premise' => 'Local/On-Premise',
                        'custom' => 'Custom API',
                        default => $state,
                    })
                    ->badge()
                    ->color('warning'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('offline_capable')
                    ->label('Offline')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->toggleable(),

                // TextColumn::make('last_heartbeat_at')
                //     ->label('Last Seen')
                //     ->dateTime()
                //     ->since()
                //     ->toggleable(),

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
                    ->options([
                        'rekognition' => 'AWS Rekognition',
                        'azure_face' => 'Azure Face',
                        'bioid' => 'BioID',
                        'on_premise' => 'Local/On-Premise',
                        'custom' => 'Custom API',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(BiometricDeviceStatusEnum::class),

                SelectFilter::make('offline_capable')
                    ->label('Offline Capable')
                    ->options([
                        1 => 'Yes',
                        0 => 'No',
                    ]),
            ])
            ->actions([
                ActionGroup::make([

                    // Action::make('test')
                    //     ->label('Test')
                    //     ->icon('heroicon-o-play')
                    //     ->color('info')
                    //     ->action(function (BiometricDevice $record) {
                    //         $record->update(['last_heartbeat_at' => now()]);
                    //         Notification::make()
                    //             ->title('Device Test Successful')
                    //             ->success()
                    //             ->body('Device is responding correctly.')
                    //             ->send();
                    //     })
                    //     ->requiresConfirmation(),

                    Action::make('regenerateToken')
                        ->label('Regenerate Token')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->action(function (BiometricDevice $record) {
                            $plainToken = bin2hex(random_bytes(32));
                            $record->update(['device_token' => $plainToken]);
                            Notification::make()
                                ->title('API Token Generated')
                                ->success()
                                ->body('Your new API token has been generated. Make sure to copy it now as you won\'t be able to see it again!')
                                ->actions([
                                    ActionsAction::make('copy')
                                        ->label('Copy to Clipboard')
                                        ->color('primary')
                                        ->icon('heroicon-m-clipboard')
                                        ->link()
                                        ->url('javascript:navigator.clipboard.writeText(' . Js::from($plainToken) . ')')
                                        ->close(),
                                ])
                                ->persistent()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Regenerate API Token')
                        ->modalDescription('This will invalidate the current API token. A new one will be generated.')
                        ->modalSubmitActionLabel('Regenerate'),

                    Action::make('generateWebhookSecret')
                        ->label('Generate Webhook Secret')
                        ->icon('heroicon-o-shield-check')
                        ->color('info')
                        ->action(function (BiometricDevice $record) {
                            $secret = bin2hex(random_bytes(24)); // 48 character hex string
                            $config = $record->provider_config ?? [];
                            $config['webhook_secret'] = $secret;
                            $record->update(['provider_config' => $config]);
                            Notification::make()
                                ->title('Webhook Secret Generated')
                                ->success()
                                ->body('A new webhook secret has been generated. Copy it now - you won\'t see it again!')
                                ->actions([
                                    ActionsAction::make('copy')
                                        ->label('Copy Secret')
                                        ->color('primary')
                                        ->icon('heroicon-m-clipboard')
                                        ->link()
                                        ->url('javascript:navigator.clipboard.writeText(' . Js::from($secret) . ')')
                                        ->close(),
                                ])
                                ->persistent()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Generate Webhook Secret')
                        ->modalDescription('This will generate a new webhook secret. The old secret will no longer work. Make sure to update your device configuration.'),

                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBiometricDevices::route('/'),
            'create' => Pages\CreateBiometricDevice::route('/create'),
            'edit' => Pages\EditBiometricDevice::route('/{record}/edit'),
        ];
    }

    /**
     * Check if the purpose field indicates door access (handles both enum and string).
     */
    public static function isDoorAccessPurpose(mixed $purpose): bool
    {
        if ($purpose === null) {
            return false;
        }

        // If it's already an enum instance
        if ($purpose instanceof BiometricDevicePurpose) {
            return in_array($purpose, [
                BiometricDevicePurpose::DoorAccess,
                BiometricDevicePurpose::DoorAccessNoAttendance,
            ], true);
        }

        // If it's a string (from form input)
        return in_array($purpose, ['door_access', 'door_access_no_attendance'], true);
    }
}
