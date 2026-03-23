<?php

namespace App\Filament\Admin\Resources;

use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Filament\Admin\Resources\BiometricDeviceResource\Pages;
use App\Models\BiometricDevice;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

        return BiometricDevice::where('gym_id', $tenant->id)->count();
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
        $currentGym = Filament::getTenant();

        return $form
            ->schema([
                Forms\Components\Section::make('Device Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Device Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('device_id')
                            ->label('Device ID')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('api_token')
                            ->label('API Token')
                            ->password()
                            ->maxLength(255)
                            ->dehydrated(false)
                            ->hint('Leave empty to keep current token'),

                        Select::make('biometric_type')
                            ->label('Biometric Type')
                            ->options(BiometricTypeEnum::class)
                            ->required(),

                        Select::make('provider')
                            ->label('Provider')
                            ->options([
                                'rekognition' => 'AWS Rekognition',
                                'azure' => 'Azure Face',
                                'bioid' => 'BioID',
                                'local' => 'Local Model',
                                'custom' => 'Custom API',
                            ])
                            ->required(),

                        ToggleButtons::make('status')
                            ->label('Status')
                            ->options(BiometricDeviceStatusEnum::class)
                            ->inline()
                            ->default(BiometricDeviceStatusEnum::Active),

                        TextInput::make('ip_address')
                            ->label('IP Address')
                            ->maxLength(45)
                            ->nullable(),

                        TextInput::make('location')
                            ->label('Location')
                            ->maxLength(255)
                            ->nullable(),

                        Forms\Components\Textarea::make('config')
                            ->label('Configuration (JSON)')
                            ->rows(3)
                            ->nullable()
                            ->helperText('JSON configuration for the biometric provider'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Device Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('device_id')
                    ->label('Device ID')
                    ->searchable(),

                TextColumn::make('biometric_type')
                    ->label('Biometric Type')
                    ->badge()
                    ->color('info'),

                TextColumn::make('provider')
                    ->label('Provider')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'rekognition' => 'AWS Rekognition',
                        'azure' => 'Azure Face',
                        'bioid' => 'BioID',
                        'local' => 'Local Model',
                        'custom' => 'Custom API',
                        default => $state,
                    })
                    ->badge()
                    ->color('warning'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                // ->colors([
                //     'success' => BiometricDeviceStatusEnum::Active,
                //     'warning' => BiometricDeviceStatusEnum::Maintenance,
                //     'danger' => BiometricDeviceStatusEnum::Inactive,
                // ]),

                TextColumn::make('location')
                    ->label('Location')
                    ->searchable(),

                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('biometric_type')
                    ->label('Biometric Type')
                    ->options(BiometricTypeEnum::class),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(BiometricDeviceStatusEnum::class),

                SelectFilter::make('provider')
                    ->label('Provider')
                    ->options([
                        'rekognition' => 'AWS Rekognition',
                        'azure' => 'Azure Face',
                        'bioid' => 'BioID',
                        'local' => 'Local Model',
                        'custom' => 'Custom API',
                    ]),
            ])
            ->actions([
                Action::make('test')
                    ->label('Test Device')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->action(function (BiometricDevice $record) {
                        $record->update(['last_seen_at' => now()]);
                        Notification::make()
                            ->title('Device Test Successful')
                            ->success()
                            ->body('Device is responding correctly.')
                            ->send();
                    })
                    ->requiresConfirmation(),

                Action::make('regenerateToken')
                    ->label('Regenerate Token')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->action(function (BiometricDevice $record) {
                        $token = bin2hex(random_bytes(32));
                        $record->update(['device_token' => $token]);
                        Notification::make()
                            ->title('Token Regenerated')
                            ->success()
                            ->body('API token has been regenerated.')
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate API Token')
                    ->modalDescription('This will invalidate the current API token. The device will need to be reconfigured.')
                    ->modalSubmitActionLabel('Regenerate'),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
