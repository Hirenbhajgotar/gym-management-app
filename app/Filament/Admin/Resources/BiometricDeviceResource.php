<?php

namespace App\Filament\Admin\Resources;

use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages;
use App\Models\BiometricDevice;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

        $count = BiometricDevice::where('gym_id', $tenant->id)->count();

        return $count > 0 ? $count : null;
    }

    public static function canAccess(array $parameters = []): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (filament()?->getId() === 'admin') {
            return $user->hasAnyRole(['super_admin', 'gym_owner', 'manager']);
        } elseif (filament()?->getId() === 'staff') {
            return true;
        }

        return false;
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
                                TextInput::make('provider_config.collection_id')
                                    ->label('Face Collection ID')
                                    ->placeholder('gym-faces')
                                    ->required()
                                    ->live(),
                                TextInput::make('provider_config.similarity_threshold')
                                    ->label('Similarity Threshold (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(85)
                                    ->live(),
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
                                TextInput::make('provider_config.face_list_id')
                                    ->label('Face List ID')
                                    ->placeholder('gym-member-faces')
                                    ->required()
                                    ->live(),
                                TextInput::make('provider_config.confidence_threshold')
                                    ->label('Confidence Threshold (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(80)
                                    ->live(),
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('provider_type') === 'azure_face')
                            ->columns(2),

                        // BioID Configuration
                        Section::make('BioID Settings')
                            ->description('Configure BioID liveness detection')
                            ->schema([
                                TextInput::make('provider_config.wsdl_url')
                                    ->label('WSDL URL')
                                    ->placeholder('https://www.bioid.com/WS/BioIDWebService.WSLa')
                                    ->required()
                                    ->live(),
                                TextInput::make('provider_config.api_key')
                                    ->label('API Key')
                                    ->required()
                                    ->password()
                                    ->revealable()
                                    ->live(),
                                TextInput::make('provider_config.service_id')
                                    ->label('Service ID')
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
                                KeyValue::make('provider_config.headers')
                                    ->label('Additional Headers')
                                    ->helperText('Key-value pairs for custom headers')
                                    ->nullable(),
                                TextInput::make('provider_config.timeout')
                                    ->label('Request Timeout (seconds)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(300)
                                    ->default(30)
                                    ->live(),
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('provider_type') === 'custom')
                            ->columns(2),
                    ]),

                Section::make('Security & Access')
                    ->schema([
                        Placeholder::make('token_info')
                            ->label('API Token')
                            ->content(function (): string {
                                return 'A secure API token will be auto-generated on creation. You can view/regenerate it from the table actions.';
                            }),
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
                            ->title('Token Regenerated')
                            ->success()
                            ->body('New API token: ' . $plainToken)
                            ->persistent()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate API Token')
                    ->modalDescription('This will invalidate the current API token. A new one will be generated.')
                    ->modalSubmitActionLabel('Regenerate'),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
}
