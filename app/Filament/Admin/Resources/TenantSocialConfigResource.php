<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\SocialPlatform;
use App\Filament\Admin\Resources\TenantSocialConfigResource\Pages\CreateTenantSocialConfig;
use App\Filament\Admin\Resources\TenantSocialConfigResource\Pages\EditTenantSocialConfig;
use App\Filament\Admin\Resources\TenantSocialConfigResource\Pages\ListTenantSocialConfigs;
use App\Filament\Admin\Resources\TenantSocialConfigResource\RelationManagers\WebhookLogsRelationManager;
use App\Filament\BaseResources\BaseResource;
use App\Jobs\ProcessSocialMediaLead;
use App\Models\Gym;
use App\Models\TenantSocialConfig;
use App\Services\SocialMediaCredentialVerifier;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TenantSocialConfigResource extends BaseResource
{
    protected static ?string $model = TenantSocialConfig::class;

    protected static ?string $tenantRelationshipName = 'socialConfigs';

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Social Media Config';

    protected static ?string $modelLabel = 'Social Media Configuration';

    protected static ?string $pluralModelLabel = 'Social Media Configurations';

    protected static ?int $navigationSort = 30;

    // Temporarily disable access to this resource
    public static function canAccess(array $parameters = []): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        /**
         * Get the current tenant
         *
         * @var Gym $tenant
         */
        $tenant = Filament::getTenant();

        return $form
            ->schema([
                Section::make('Basic Configuration')
                    ->description('Configure social media platform settings for lead generation')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Hidden::make('gym_id')
                                    ->default($tenant->id),

                                Select::make('platform')
                                    ->label('Platform')
                                    ->options(SocialPlatform::class)
                                    ->required()
                                    ->reactive(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('page_name')
                                    ->label('Account/Page Name')
                                    ->maxLength(255)
                                    ->helperText('The name of your Facebook Page, Instagram Account, or WhatsApp Business'),

                                TextInput::make('page_id')
                                    ->label('Page/Account ID')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('The unique identifier for your account on this platform'),
                            ]),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),

                Tabs::make('Platform Settings')
                    ->tabs([
                        Tab::make('API Configuration')
                            ->schema([
                                Section::make('API Credentials')
                                    ->description('Configure API access for the social media platform')
                                    ->schema([
                                        TextInput::make('app_id')
                                            ->label('App ID')
                                            ->maxLength(255)
                                            ->helperText('Facebook App ID or Business Account ID')
                                            ->visible(fn ($get) => in_array($get('platform'), [SocialPlatform::Facebook->value, SocialPlatform::Instagram->value, SocialPlatform::WhatsApp->value])),

                                        TextInput::make('app_secret')
                                            ->label('App Secret')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Facebook App Secret')
                                            ->visible(fn ($get) => in_array($get('platform'), [SocialPlatform::Facebook->value, SocialPlatform::Instagram->value, SocialPlatform::WhatsApp->value])),

                                        TextInput::make('access_token')
                                            ->label('Access Token')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(1000)
                                            ->visible(fn ($get) => in_array($get('platform'), [SocialPlatform::Facebook->value, SocialPlatform::Instagram->value, SocialPlatform::WhatsApp->value])),

                                        TextInput::make('webhook_verify_token')
                                            ->label('Webhook Verify Token')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(500)
                                            ->helperText('Used to verify webhook subscription requests from the platform')
                                            ->visible(fn ($get) => in_array($get('platform'), [SocialPlatform::Facebook->value, SocialPlatform::Instagram->value, SocialPlatform::WhatsApp->value])),

                                        TextInput::make('webhook_url')
                                            ->label('Webhook URL')
                                            ->url()
                                            ->maxLength(500)
                                            ->helperText('The URL where this platform will send webhook notifications')
                                            ->default(fn () => url('/api/webhooks/social-media'))
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('page_id')
                                            ->label('Page ID')
                                            ->maxLength(255)
                                            ->helperText('Facebook/Instagram Page ID')
                                            ->visible(fn ($get) => in_array($get('platform'), [SocialPlatform::Facebook->value, SocialPlatform::Instagram->value])),

                                        TextInput::make('phone_number_id')
                                            ->label('Phone Number ID')
                                            ->maxLength(255)
                                            ->helperText('WhatsApp Business Phone Number ID')
                                            ->visible(fn ($get) => $get('platform') === SocialPlatform::WhatsApp->value),
                                    ]),
                            ]),

                        Tab::make('Lead Processing')
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
                                            ->visible(fn ($get) => $get('auto_assign')),

                                        Select::make('assigned_user_id')
                                            ->label('Assigned User')
                                            ->relationship('assignedUser', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn ($get) => $get('assignment_rule') === 'specific_user'),

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

                        Tab::make('Auto Reply')
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
                                            ->visible(fn ($get) => $get('auto_reply_enabled')),

                                        Toggle::make('email_acknowledgment_enabled')
                                            ->label('Send Email Acknowledgment')
                                            ->default(true)
                                            ->visible(fn ($get) => $get('auto_reply_enabled')),

                                        Textarea::make('sms_template')
                                            ->label('SMS Template')
                                            ->helperText('Available variables: {name}, {gym_name}, {phone}')
                                            ->rows(3)
                                            ->default('Thank you {name} for your inquiry. We will contact you soon regarding your fitness requirements.')
                                            ->visible(fn ($get) => $get('sms_acknowledgment_enabled')),

                                        Textarea::make('email_template')
                                            ->label('Email Template')
                                            ->helperText('Available variables: {name}, {gym_name}, {email}')
                                            ->rows(5)
                                            ->default('Dear {name},\n\nThank you for your inquiry regarding fitness services. We have received your request and will contact you soon to discuss your requirements.\n\nBest regards,\n{gym_name} Team')
                                            ->visible(fn ($get) => $get('email_acknowledgment_enabled')),

                                        TextInput::make('reply_delay_minutes')
                                            ->label('Reply Delay (Minutes)')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(1440)
                                            ->helperText('Delay before sending auto-reply (0 for immediate)')
                                            ->visible(fn ($get) => $get('auto_reply_enabled')),
                                    ]),
                            ]),

                        Tab::make('Advanced')
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

                                        // Textarea::make('custom_fields_mapping')
                                        //     ->label('Custom Fields Mapping')
                                        //     ->helperText('JSON configuration for mapping platform-specific fields to lead attributes')
                                        //     ->rows(5)
                                        //     ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                                        //     ->dehydrateStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state),

                                        // Textarea::make('webhook_filters')
                                        //     ->label('Webhook Filters')
                                        //     ->helperText('JSON configuration for filtering incoming webhooks')
                                        //     ->rows(5)
                                        //     ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                                        //     ->dehydrateStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state),
                                    ]),
                            ]),
                    ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->sortable(),

                TextColumn::make('page_name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('page_id')
                    ->label('Account ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_active')
                    ->label('Active'),

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
                SelectFilter::make('platform')
                    ->options(SocialPlatform::class),

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),

                Action::make('verify_credentials')
                    ->label('Verify Credentials')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->action(fn (TenantSocialConfig $record) => self::verifyCredentials($record))
                    ->requiresConfirmation()
                    ->modalHeading('Verify Credentials')
                    ->modalDescription('This will verify your credentials against the social media platform API.')
                    ->modalSubmitActionLabel('Verify'),

                Action::make('test_webhook')
                    ->label('Test Webhook')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->action(fn (TenantSocialConfig $record) => self::testWebhook($record))
                    ->requiresConfirmation()
                    ->modalHeading('Test Webhook')
                    ->modalDescription('This will send a test webhook to verify the configuration.')
                    ->modalSubmitActionLabel('Send Test'),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    BulkAction::make('toggle_active')
                        ->label('Toggle Active')
                        ->icon('phosphor-toggle-right')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),

                    BulkAction::make('toggle_inactive')
                        ->label('Toggle Inactive')
                        ->icon('phosphor-toggle-left')
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            WebhookLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenantSocialConfigs::route('/'),
            'create' => CreateTenantSocialConfig::route('/create'),
            'edit' => EditTenantSocialConfig::route('/{record}/edit'),
        ];
    }

    /**
     * Verify social media credentials
     */
    private static function verifyCredentials(TenantSocialConfig $config): void
    {
        try {
            $verifier = new SocialMediaCredentialVerifier;
            $result = $verifier->verify($config);

            if ($result['success']) {
                Notification::make()
                    ->title('Credentials verified successfully')
                    ->body($result['message'])
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Credential verification failed')
                    ->body($result['message'])
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Credential verification failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Test webhook configuration
     */
    private static function testWebhook(TenantSocialConfig $config): void
    {
        try {
            // Generate realistic webhook payload based on platform
            $webhookData = self::generateTestWebhookPayload($config);

            // Dispatch test job with realistic data
            dispatch(new ProcessSocialMediaLead($webhookData, $config->platform->value, 'test_' . uniqid()));

            Notification::make()
                ->title('Test webhook dispatched')
                ->body("A test lead for {$config->platform->getLabel()} has been queued for processing. Check the Leads page and Webhook Logs tab.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Test webhook failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Generate realistic webhook payload for testing
     */
    private static function generateTestWebhookPayload(TenantSocialConfig $config): array
    {
        $timestamp = time();
        $uniqueId = uniqid('test_', true);

        /**
         * @var SocialPlatform $platform
         */
        $platform = $config->platform;

        return match ($platform) {
            SocialPlatform::Facebook,
            SocialPlatform::Instagram => [
                'object' => 'page',
                'entry' => [
                    [
                        'id' => $config->page_id ?? '123456789',
                        'time' => $timestamp,
                        'changes' => [
                            [
                                'field' => 'leadgen',
                                'value' => [
                                    'leadgen_id' => 'lead_' . $uniqueId,
                                    'page_id' => $config->page_id ?? '123456789',
                                    'form_id' => $config->form_id ?? '987654321',
                                    'adgroup_id' => '456789123',
                                    'ad_id' => '789123456',
                                    'created_time' => $timestamp,
                                    'field_data' => [
                                        ['name' => 'full_name', 'values' => ['Test User ' . date('His')]],
                                        ['name' => 'email', 'values' => ["test.{$uniqueId}@example.com"]],
                                        ['name' => 'phone_number', 'values' => ['+91' . rand(7000000000, 9999999999)]],
                                        ['name' => 'city', 'values' => ['Mumbai']],
                                        ['name' => 'fitness_goals', 'values' => ['Weight Loss']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            SocialPlatform::WhatsApp => [
                'object' => 'whatsapp_business_account',
                'entry' => [
                    [
                        'id' => $config->app_id ?? '123456789',
                        'changes' => [
                            [
                                'field' => 'messages',
                                'value' => [
                                    'messaging_product' => 'whatsapp',
                                    'metadata' => [
                                        'display_phone_number' => $config->phone_number ?? '919999999999',
                                        'phone_number_id' => $config->phone_number_id ?? 'phone_' . $uniqueId,
                                    ],
                                    'contacts' => [
                                        [
                                            'profile' => ['name' => 'Test User ' . date('His')],
                                            'wa_id' => '91' . rand(7000000000, 9999999999),
                                        ],
                                    ],
                                    'messages' => [
                                        [
                                            'from' => '91' . rand(7000000000, 9999999999),
                                            'id' => 'msg_' . $uniqueId,
                                            'timestamp' => $timestamp,
                                            'type' => 'text',
                                            'text' => [
                                                'body' => 'Hi, I want to know about gym membership plans and pricing.',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            SocialPlatform::YouTube => [
                // YouTube doesn't have lead ads, but we can create a test payload
                'name' => 'Test User ' . date('His'),
                'email' => "test.{$uniqueId}@example.com",
                'phone' => '+91' . rand(7000000000, 9999999999),
                'notes' => 'Test YouTube inquiry - interested in fitness programs',
                'platform' => SocialPlatform::YouTube->value,
            ],

            default => [
                'name' => 'Test User ' . date('His'),
                'email' => "test.{$uniqueId}@example.com",
                'phone' => '+91' . rand(7000000000, 9999999999),
                'notes' => 'Test webhook from Filament admin',
                'platform' => $platform->value,
                'page_id' => $config->page_id,
            ],
        };
    }
}
