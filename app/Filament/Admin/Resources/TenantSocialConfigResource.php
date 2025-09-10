<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TenantSocialConfigResource\Pages;
use App\Filament\Admin\Resources\TenantSocialConfigResource\RelationManagers;
use App\Models\TenantSocialConfig;
use App\Models\Gym;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;

class TenantSocialConfigResource extends Resource
{
    protected static ?string $model = TenantSocialConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Social Media Config';

    protected static ?string $modelLabel = 'Social Media Configuration';

    protected static ?string $pluralModelLabel = 'Social Media Configurations';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Configuration')
                    ->description('Configure social media platform settings for lead generation')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('gym_id')
                                    ->label('Gym')
                                    ->relationship('gym', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(fn($state, callable $set) => $set('tenant_id', $state ? Gym::find($state)?->tenant_id : null)),

                                Select::make('tenant_id')
                                    ->label('Tenant')
                                    ->relationship('tenant', 'name')
                                    ->disabled()
                                    ->dehydrated()
                                    ->visible(fn($get) => $get('gym_id') !== null),
                            ]),

                        Select::make('platform')
                            ->label('Platform')
                            ->options([
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'whatsapp' => 'WhatsApp Business',
                                'indiamart' => 'IndiaMart',
                                'justdial' => 'JustDial',
                            ])
                            ->required()
                            ->reactive(),

                        TextInput::make('account_name')
                            ->label('Account Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('account_id')
                            ->label('Account ID')
                            ->required()
                            ->maxLength(255)
                            ->helperText('The unique identifier for your account on this platform'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),

                Tabs::make('Platform Settings')
                    ->tabs([
                        Tabs\Tab::make('API Configuration')
                            ->schema([
                                Section::make('API Credentials')
                                    ->description('Configure API access for the social media platform')
                                    ->schema([
                                        TextInput::make('api_key')
                                            ->label('API Key')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(500)
                                            ->visible(fn($get) => in_array($get('platform'), ['indiamart', 'justdial'])),

                                        TextInput::make('api_secret')
                                            ->label('API Secret')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(500)
                                            ->visible(fn($get) => in_array($get('platform'), ['facebook', 'instagram', 'whatsapp'])),

                                        TextInput::make('access_token')
                                            ->label('Access Token')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(1000)
                                            ->visible(fn($get) => in_array($get('platform'), ['facebook', 'instagram', 'whatsapp'])),

                                        TextInput::make('webhook_secret')
                                            ->label('Webhook Secret')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(500)
                                            ->helperText('Used to verify webhook requests from the platform')
                                            ->visible(fn($get) => in_array($get('platform'), ['facebook', 'instagram', 'whatsapp'])),

                                        TextInput::make('webhook_url')
                                            ->label('Webhook URL')
                                            ->url()
                                            ->maxLength(500)
                                            ->helperText('The URL where this platform will send webhook notifications')
                                            ->default(fn() => url('/api/webhooks/social-media'))
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('page_id')
                                            ->label('Page ID')
                                            ->maxLength(255)
                                            ->helperText('Facebook/Instagram Page ID')
                                            ->visible(fn($get) => in_array($get('platform'), ['facebook', 'instagram'])),

                                        TextInput::make('phone_number_id')
                                            ->label('Phone Number ID')
                                            ->maxLength(255)
                                            ->helperText('WhatsApp Business Phone Number ID')
                                            ->visible(fn($get) => $get('platform') === 'whatsapp'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Lead Processing')
                            ->schema([
                                Section::make('Lead Processing Settings')
                                    ->description('Configure how leads from this platform are processed')
                                    ->schema([
                                        Toggle::make('auto_process')
                                            ->label('Auto Process Leads')
                                            ->default(true)
                                            ->helperText('Automatically process incoming leads from this platform'),

                                        Toggle::make('auto_assign')
                                            ->label('Auto Assign Leads')
                                            ->default(true)
                                            ->helperText('Automatically assign leads to staff members'),

                                        Select::make('assignment_rule')
                                            ->label('Assignment Rule')
                                            ->options([
                                                'round_robin' => 'Round Robin',
                                                'least_busy' => 'Least Busy',
                                                'random' => 'Random',
                                                'specific_user' => 'Specific User',
                                            ])
                                            ->default('round_robin')
                                            ->visible(fn($get) => $get('auto_assign')),

                                        Select::make('assigned_user_id')
                                            ->label('Assigned User')
                                            ->relationship('assignedUser', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn($get) => $get('assignment_rule') === 'specific_user'),

                                        Select::make('default_status')
                                            ->label('Default Lead Status')
                                            ->options([
                                                'new' => 'New',
                                                'contacted' => 'Contacted',
                                                'qualified' => 'Qualified',
                                                'proposal_sent' => 'Proposal Sent',
                                                'won' => 'Won',
                                                'lost' => 'Lost',
                                            ])
                                            ->default('new'),

                                        Select::make('default_priority')
                                            ->label('Default Priority')
                                            ->options([
                                                'low' => 'Low',
                                                'medium' => 'Medium',
                                                'high' => 'High',
                                                'urgent' => 'Urgent',
                                            ])
                                            ->default('medium'),
                                    ]),

                                Section::make('Lead Validation')
                                    ->description('Configure validation rules for incoming leads')
                                    ->schema([
                                        Toggle::make('validate_phone')
                                            ->label('Validate Phone Numbers')
                                            ->default(true),

                                        Toggle::make('validate_email')
                                            ->label('Validate Email Addresses')
                                            ->default(true),

                                        Toggle::make('require_phone')
                                            ->label('Require Phone Number')
                                            ->default(true),

                                        Toggle::make('require_email')
                                            ->label('Require Email Address')
                                            ->default(false),

                                        TextInput::make('minimum_name_length')
                                            ->label('Minimum Name Length')
                                            ->numeric()
                                            ->default(2)
                                            ->minValue(1)
                                            ->maxValue(50),

                                        Textarea::make('blocked_keywords')
                                            ->label('Blocked Keywords')
                                            ->helperText('Comma-separated list of keywords that will cause leads to be rejected')
                                            ->rows(3),
                                    ]),
                            ]),

                        Tabs\Tab::make('Auto Reply')
                            ->schema([
                                Section::make('Automatic Responses')
                                    ->description('Configure automatic responses to leads from this platform')
                                    ->schema([
                                        Toggle::make('auto_reply_enabled')
                                            ->label('Enable Auto Reply')
                                            ->default(false)
                                            ->reactive(),

                                        Toggle::make('sms_acknowledgment_enabled')
                                            ->label('Send SMS Acknowledgment')
                                            ->default(true)
                                            ->visible(fn($get) => $get('auto_reply_enabled')),

                                        Toggle::make('email_acknowledgment_enabled')
                                            ->label('Send Email Acknowledgment')
                                            ->default(true)
                                            ->visible(fn($get) => $get('auto_reply_enabled')),

                                        Textarea::make('sms_template')
                                            ->label('SMS Template')
                                            ->helperText('Available variables: {name}, {gym_name}, {phone}')
                                            ->rows(3)
                                            ->default('Thank you {name} for your inquiry. We will contact you soon regarding your fitness requirements.')
                                            ->visible(fn($get) => $get('sms_acknowledgment_enabled')),

                                        Textarea::make('email_template')
                                            ->label('Email Template')
                                            ->helperText('Available variables: {name}, {gym_name}, {email}')
                                            ->rows(5)
                                            ->default('Dear {name},\n\nThank you for your inquiry regarding fitness services. We have received your request and will contact you soon to discuss your requirements.\n\nBest regards,\n{gym_name} Team')
                                            ->visible(fn($get) => $get('email_acknowledgment_enabled')),

                                        TextInput::make('reply_delay_minutes')
                                            ->label('Reply Delay (Minutes)')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(1440)
                                            ->helperText('Delay before sending auto-reply (0 for immediate)')
                                            ->visible(fn($get) => $get('auto_reply_enabled')),
                                    ]),
                            ]),

                        Tabs\Tab::make('Advanced')
                            ->schema([
                                Section::make('Advanced Settings')
                                    ->description('Advanced configuration options')
                                    ->schema([
                                        Toggle::make('debug_mode')
                                            ->label('Debug Mode')
                                            ->default(false)
                                            ->helperText('Enable detailed logging for troubleshooting'),

                                        TextInput::make('rate_limit')
                                            ->label('Rate Limit (per hour)')
                                            ->numeric()
                                            ->default(100)
                                            ->minValue(1)
                                            ->maxValue(10000)
                                            ->helperText('Maximum number of leads to process per hour'),

                                        Textarea::make('custom_fields_mapping')
                                            ->label('Custom Fields Mapping')
                                            ->helperText('JSON configuration for mapping platform-specific fields to lead attributes')
                                            ->rows(5)
                                            ->formatStateUsing(fn($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                                            ->dehydrateStateUsing(fn($state) => is_string($state) ? json_decode($state, true) : $state),

                                        Textarea::make('webhook_filters')
                                            ->label('Webhook Filters')
                                            ->helperText('JSON configuration for filtering incoming webhooks')
                                            ->rows(5)
                                            ->formatStateUsing(fn($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                                            ->dehydrateStateUsing(fn($state) => is_string($state) ? json_decode($state, true) : $state),
                                    ]),
                            ]),
                    ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('gym.name')
                    ->label('Gym')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'facebook' => 'blue',
                        'instagram' => 'pink',
                        'whatsapp' => 'green',
                        'indiamart' => 'orange',
                        'justdial' => 'purple',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('account_name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('account_id')
                    ->label('Account ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('webhook_logs_count')
                    ->label('Webhooks')
                    ->counts('webhookLogs')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_webhook_at')
                    ->label('Last Webhook')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->options([
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'whatsapp' => 'WhatsApp Business',
                        'indiamart' => 'IndiaMart',
                        'justdial' => 'JustDial',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Filters\SelectFilter::make('gym')
                    ->relationship('gym', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('test_webhook')
                    ->label('Test Webhook')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->action(fn(TenantSocialConfig $record) => self::testWebhook($record))
                    ->requiresConfirmation()
                    ->modalHeading('Test Webhook')
                    ->modalDescription('This will send a test webhook to verify the configuration.')
                    ->modalSubmitActionLabel('Send Test'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('toggle_active')
                        ->label('Toggle Active')
                        ->icon('phosphor-toggle-left')
                        ->action(fn($records) => $records->each->update(['is_active' => ! $records->first()->is_active])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WebhookLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantSocialConfigs::route('/'),
            'create' => Pages\CreateTenantSocialConfig::route('/create'),
            'edit' => Pages\EditTenantSocialConfig::route('/{record}/edit'),
        ];
    }

    /**
     * Test webhook configuration
     */
    private static function testWebhook(TenantSocialConfig $config): void
    {
        try {
            // Create test lead data
            $testData = [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => '+919999999999',
                'notes' => 'Test webhook from Filament admin',
                'platform' => $config->platform,
                'account_id' => $config->account_id,
            ];

            // Dispatch test job
            \App\Jobs\ProcessSocialMediaLead::dispatch(
                $testData,
                $config->platform,
                'test_' . uniqid()
            );

            \Filament\Notifications\Notification::make()
                ->title('Test webhook sent successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Test webhook failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
