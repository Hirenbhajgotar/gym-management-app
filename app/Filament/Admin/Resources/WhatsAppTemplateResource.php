<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages\CreateWhatsAppTemplate;
use App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages\EditWhatsAppTemplate;
use App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages\ListWhatsAppTemplates;
use App\Filament\BaseResources\BaseResource;
use App\Interfaces\TemplateManagementInterface;
use App\Models\WhatsAppTemplate;
use App\Providers\WhatsAppProviderFactory;
use App\Services\Support\TemplateVariableHelper;
use App\Services\WhatsAppVariableResolver;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use RuntimeException;

class WhatsAppTemplateResource extends BaseResource
{
    protected static ?string $model = WhatsAppTemplate::class;

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $modelLabel = 'Template';

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
                'reorder',
            ],

            DefaultRole::RECEPTIONIST->value => [],
            DefaultRole::TRAINER->value => [],
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        return Cache::flexible(WhatsAppTemplate::NAVIGATION_BADGE_CACHE_KEY . $tenant->id, [1800, 3600], function () use ($tenant) {
            return (string) static::getModel()::query()
                ->where(function ($query) use ($tenant) {
                    $query->where('gym_id', $tenant->id);
                    if (config('services.twilio.use_sandbox')) {
                        $query->orWhereNull('gym_id');
                    }
                })
                ->count();
        }) ?: null;
    }

    public static function isScopedToTenant(): bool
    {
        if (config('services.twilio.use_sandbox')) {
            return false;
        }

        return true;
    }

    /**
     * @return Builder<WhatsAppTemplate>
     */
    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->where(function (\Illuminate\Contracts\Database\Query\Builder $query) use ($tenant) {
                $query->where('gym_id', $tenant?->id);

                if (config('services.twilio.use_sandbox')) {
                    $query->orWhereNull('gym_id');
                }
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ──────────────────────────────────────────
                // Default Template Selection (only for create)
                // ──────────────────────────────────────────
                Section::make('Start from Default Template')
                    ->description('Choose a pre-built template and customize it for your gym')
                    ->schema([
                        Select::make('_default_template')
                            ->label('Select a Default Template')
                            ->placeholder('Choose a template to get started...')
                            ->options(static::getDefaultTemplateOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (blank($state)) {
                                    return;
                                }

                                $templates = static::getDefaultTemplatesForForm();
                                if (isset($templates[$state])) {
                                    $template = $templates[$state];
                                    $set('name', $template['name']);
                                    $set('display_name', $template['display_name'] ?? ucwords(str_replace('_', ' ', $template['name'])));
                                    $set('body', $template['body']);
                                    $set('category', $template['category'] ?? 'utility');

                                    // Set variable_placeholder from the default template
                                    if (filled($template['variable_placeholder'])) {
                                        $set('variable_placeholder', $template['variable_placeholder']);
                                    }
                                }
                            }),

                        Placeholder::make('_template_preview')
                            ->label('')
                            ->content(function (Get $get) {
                                $selected = $get('_default_template');
                                if (blank($selected)) {
                                    return new HtmlString('<div class="text-gray-400 text-sm">Select a template above to see a preview</div>');
                                }

                                $templates = static::getDefaultTemplatesForForm();
                                $template = $templates[$selected] ?? null;

                                if (! $template) {
                                    return '';
                                }

                                $sampleValues = [
                                    '{{1}}' => 'John Doe',
                                    '{{2}}' => 'FitZone Gym',
                                    '{{3}}' => 'March 15, 2026',
                                    '{{4}}' => '₹1,499',
                                    '{{5}}' => '30 days',
                                    '{{6}}' => 'Premium Plan',
                                ];
                                $preview = str_replace(array_keys($sampleValues), array_values($sampleValues), e($template['body']));
                                $preview = nl2br($preview);

                                return new HtmlString('
                                    <div class="max-w-sm mt-2">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Preview:</div>
                                        <div class="bg-[#e7fedd] dark:bg-emerald-900/40 rounded-lg rounded-tr-none p-3 shadow-sm border border-emerald-200/50 dark:border-emerald-800/50">
                                            <div class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">' . $preview . '</div>
                                        </div>
                                    </div>
                                ');
                            })
                            ->visible(fn (Get $get) => filled($get('_default_template'))),
                    ])
                    ->collapsed()
                    ->visible(fn ($record) => $record === null), // Only show on create

                // ──────────────────────────────────────────
                // Category Selection & Guidance
                // ──────────────────────────────────────────
                Section::make('Template Category')
                    ->description('Choose the right category for your template. This affects Meta approval speed and how you can use the template.')
                    ->schema([
                        Select::make('category')
                            ->options([
                                'utility' => '🔧 Utility — Transactional messages',
                                'marketing' => '📢 Marketing — Promotional messages',
                            ])
                            ->required()
                            ->default('utility')
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('_category_warning_shown', true))
                            ->helperText('Utility templates are for transactional messages (reminders, updates, confirmations). Marketing templates are for promotions and offers.'),

                        // Utility category guidance
                        Placeholder::make('utility_guide')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/50 p-4 text-sm space-y-3">
                                    <div class="font-semibold text-emerald-700 dark:text-emerald-400">✅ Utility Template Best Practices</div>
                                    <div class="text-emerald-600 dark:text-emerald-400 space-y-1">
                                        <p>• <strong>Keep it informational</strong> — payment reminders, membership expiry, class schedules, booking confirmations</p>
                                        <p>• <strong>Avoid emojis</strong> — Excessive emojis may cause Meta to recategorize your template as marketing</p>
                                        <p>• <strong>No promotional content</strong> — Don\'t include offers, discounts, or "limited time" language</p>
                                        <p>• <strong>No call-to-action selling</strong> — Avoid "Buy now", "Get 50% off", "Special offer" type phrases</p>
                                        <p>• <strong>Be factual</strong> — Stick to facts: dates, amounts, names, statuses</p>
                                    </div>
                                    <div class="text-amber-600 dark:text-amber-400 text-xs mt-2 border-t border-emerald-200 dark:border-emerald-700 pt-2">
                                        ⚠️ <strong>Warning:</strong> If Meta detects promotional language in a utility template, it will be recategorized as marketing. This adds extra charges and delivery delays.
                                    </div>
                                </div>
                            '))
                            ->visible(fn (Get $get) => $get('category') === 'utility'),

                        // Marketing category warning
                        Placeholder::make('marketing_guide')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/50 p-4 text-sm space-y-3">
                                    <div class="font-semibold text-amber-700 dark:text-amber-400">📢 Marketing Template — Important Limitations</div>
                                    <div class="text-amber-600 dark:text-amber-400 space-y-1">
                                        <p>• <strong>Cannot be used for bulk broadcasts</strong> — Marketing templates have strict sending limits from Meta and are not suitable for mass messaging</p>
                                        <p>• <strong>Slower delivery</strong> — Marketing messages may take longer to deliver compared to utility messages</p>
                                        <p>• <strong>Higher cost per message</strong> — Meta charges more for marketing conversations</p>
                                        <p>• <strong>User can opt-out</strong> — Recipients can block marketing messages, reducing your reach over time</p>
                                        <p>• <strong>Quality rating impacts</strong> — Low engagement or blocks can lower your quality rating, further limiting sends</p>
                                    </div>
                                    <div class="text-red-600 dark:text-red-400 text-xs mt-2 border-t border-amber-200 dark:border-amber-700 pt-2">
                                        🚫 <strong>Recommendation:</strong> Use utility templates for transactional messages like reminders, updates, and confirmations. Only use marketing for genuine promotional campaigns.
                                    </div>
                                </div>
                            '))
                            ->visible(fn (Get $get) => $get('category') === 'marketing'),
                    ])
                    ->columns(1)
                    ->collapsible(),

                // ──────────────────────────────────────────
                // Template Details
                // ──────────────────────────────────────────
                Section::make('Template Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('gym_id', Filament::getTenant()?->id))
                            ->regex('/^[a-z][a-z0-9_]*$/')
                            ->placeholder('e.g., payment_reminder')
                            ->disabled(fn (Get $get) => filled($get('_default_template')))
                            ->dehydrated()
                            ->helperText('Lowercase, underscores only. e.g., "payment_reminder", "welcome_message". This is the internal name used by WhatsApp API.'),

                        TextInput::make('display_name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Payment Reminder')
                            ->helperText('A friendly name for easy identification. This is what you\'ll see in the template picker.'),
                    ])
                    ->columns(2),

                // ──────────────────────────────────────────
                // Template Body Builder
                // ──────────────────────────────────────────
                Section::make('Template Body')
                    ->description('Write your message template. Use {{1}}, {{2}}, etc. for dynamic variables like member name, amount, dates.')
                    ->schema([
                        Textarea::make('body')
                            ->required()
                            ->rows(6)
                            ->maxLength(1024)
                            ->live(debounce: 500)
                            ->rules([
                                fn (): Closure => function (string $attribute, $value, Closure $fail) {
                                    $trimmed = trim((string) $value);
                                    if (preg_match('/^\{\{\d+\}\}/', $trimmed)) {
                                        $fail('The template body cannot start with a variable.');
                                    }
                                    if (preg_match('/\{\{\d+\}\}$/', $trimmed)) {
                                        $fail('The template body cannot end with a variable.');
                                    }
                                },
                            ])
                            ->placeholder('Hi {{1}}, your membership at {{2}} is due for renewal on {{3}}. Please renew to continue enjoying our services.')
                            ->helperText(fn (Get $get) => new HtmlString(
                                '<span class="text-xs">'
                                . 'Characters: <strong>' . strlen($get('body') ?? '') . '/1024</strong>'
                                . ' | Variables detected: <strong>' . preg_match_all('/\{\{\d+\}\}/', $get('body') ?? '') . '</strong>'
                                . '</span>'
                            )),

                        Placeholder::make('variable_helper')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800 p-3 text-xs space-y-2">
                                    <div class="font-semibold text-gray-600 dark:text-gray-300">💡 Variable Guide</div>
                                    <div class="text-gray-500 dark:text-gray-400 space-y-1">
                                        <p>Use <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{1}}</code>, <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{2}}</code>, etc. for dynamic values like member name, gym name, dates, and amounts.</p>
                                        <p>Each variable is mapped to a <strong>data source</strong> below — the system auto-fills values when sending messages.</p>
                                    </div>
                                    <div class="text-gray-400 dark:text-gray-500 mt-1">Variables must be sequential: {{1}}, {{2}}, {{3}}. Don\'t skip numbers.</div>
                                </div>
                            ')),

                        // Dynamic Variable Placeholders
                        Section::make('Variable Placeholders')
                            ->description('Map each variable to a data source. Auto-resolved sources (member name, gym, plan, etc.) are filled automatically when sending. Use "Custom Value" for variables you\'ll enter manually.')
                            ->schema([
                                Placeholder::make('variable_placeholder_info')
                                    ->label('')
                                    ->content(new HtmlString('
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                            Select a <strong>Data Source</strong> for each variable. Sources like Member Name, Gym Name, and Plan are auto-filled from member data. Choose "Custom Value" for values you\'ll provide manually at send time.
                                        </div>
                                    ')),
                            ])
                            ->schema(function (Get $get) {
                                $body = $get('body') ?? '';
                                preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
                                $variableNumbers = array_unique($matches[1] ?? []);

                                if (blank($variableNumbers)) {
                                    return [];
                                }

                                $fields = [];
                                $defaultData = [
                                    '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'member.name'],
                                    '2' => ['placeholder' => 'Gym Name', 'name' => 'gym_name', 'example' => 'FitZone Gym', 'type' => 'text', 'source' => 'gym.name'],
                                    '3' => ['placeholder' => 'Date', 'name' => 'date', 'example' => 'January 15, 2026', 'type' => 'text', 'source' => 'member.membership_end'],
                                    '4' => ['placeholder' => 'Amount', 'name' => 'amount', 'example' => '₹1,499', 'type' => 'number', 'source' => 'payment.formatted_amount'],
                                    '5' => ['placeholder' => 'Time', 'name' => 'time', 'example' => '7:00 AM', 'type' => 'text', 'source' => 'custom'],
                                    '6' => ['placeholder' => 'Plan Name', 'name' => 'plan_name', 'example' => 'Premium Plan', 'type' => 'text', 'source' => 'plan.name'],
                                ];

                                foreach ($variableNumbers as $num) {
                                    $defaults = $defaultData[$num] ?? ['placeholder' => 'Variable ' . $num, 'name' => 'variable_' . $num, 'example' => 'Example ' . $num, 'type' => 'text', 'source' => 'custom'];

                                    $fields[] = Grid::make(5)
                                        ->schema([
                                            TextInput::make('variable_placeholder.' . $num . '.variable')
                                                ->label('{{' . $num . '}}')
                                                ->readonly()
                                                ->default('{{' . $num . '}}')
                                                ->columnSpan(1),
                                            Select::make('variable_placeholder.' . $num . '.source')
                                                ->label('Data Source')
                                                ->options(WhatsAppVariableResolver::getGroupedSelectOptions())
                                                ->default($defaults['source'])
                                                ->searchable()
                                                ->live()
                                                ->afterStateUpdated(function ($state, Set $set) use ($num) {
                                                    if (! $state) {
                                                        return;
                                                    }

                                                    $sources = WhatsAppVariableResolver::getAvailableDataSources();
                                                    if (isset($sources[$state])) {
                                                        $set('variable_placeholder.' . $num . '.example', $sources[$state]['example']);
                                                        $set('variable_placeholder.' . $num . '.placeholder', $sources[$state]['label']);
                                                    }
                                                })
                                                ->helperText('Auto-fill source')
                                                ->columnSpan(1),
                                            TextInput::make('variable_placeholder.' . $num . '.placeholder')
                                                ->label('Label')
                                                ->placeholder($defaults['placeholder'])
                                                ->live(debounce: 500)
                                                ->helperText('Display name')
                                                ->columnSpan(1),
                                            TextInput::make('variable_placeholder.' . $num . '.example')
                                                ->label('Example')
                                                ->placeholder($defaults['example'])
                                                ->live(debounce: 500)
                                                ->helperText('Sample value')
                                                ->columnSpan(1),
                                            Select::make('variable_placeholder.' . $num . '.type')
                                                ->label('Type')
                                                ->options([
                                                    'text' => 'Text',
                                                    'number' => 'Number',
                                                    'date' => 'Date',
                                                    'currency' => 'Currency',
                                                ])
                                                ->default($defaults['type'])
                                                ->live(debounce: 500)
                                                ->columnSpan(1),
                                        ]);
                                }

                                return $fields;
                            })
                            ->visible(fn (Get $get) => filled($get('body')) && preg_match_all('/\{\{(\d+)\}\}/', $get('body') ?? '', $matches) > 0),

                        // Live message preview
                        Placeholder::make('message_preview')
                            ->label('📱 Message Preview')
                            ->content(function (Get $get) {
                                $body = $get('body') ?? '';
                                if (blank($body)) {
                                    return new HtmlString('<div class="text-gray-400 italic text-sm">Start typing your message to see a preview...</div>');
                                }

                                $defaultSampleValues = [
                                    '{{1}}' => 'John Doe',
                                    '{{2}}' => 'FitZone Gym',
                                    '{{3}}' => 'March 15, 2026',
                                    '{{4}}' => '₹1,499',
                                    '{{5}}' => '30 days',
                                    '{{6}}' => 'Premium',
                                ];

                                $variablePlaceholder = $get('variable_placeholder') ?? [];
                                $sampleValues = [];

                                foreach (range(1, 20) as $num) {
                                    $key = '{{' . $num . '}}';
                                    if (str_contains($body, $key)) {
                                        if (isset($variablePlaceholder[$num]['example']) && filled($variablePlaceholder[$num]['example'])) {
                                            $sampleValues[$key] = $variablePlaceholder[$num]['example'];
                                        } elseif (isset($variablePlaceholder[$num]['placeholder']) && filled($variablePlaceholder[$num]['placeholder'])) {
                                            $sampleValues[$key] = $variablePlaceholder[$num]['placeholder'];
                                        } else {
                                            $sampleValues[$key] = $defaultSampleValues[$key] ?? 'Variable ' . $num;
                                        }
                                    }
                                }

                                $preview = str_replace(array_keys($sampleValues), array_values($sampleValues), e($body));
                                $preview = nl2br($preview);

                                return new HtmlString('
                                    <div class="bg-[#E5DDD5] dark:bg-[#0b141a] min-h-[200px] p-4 rounded-lg relative" style="background-image: url(\'https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png\'); background-repeat: repeat; background-size: contain;">
                                        <div class="absolute inset-0 opacity-10 pointer-events-none rounded-lg"></div>
                                        <div class="relative z-10 self-start max-w-[90%] bg-white dark:bg-[#202c33] rounded-lg shadow-sm p-3 rounded-tl-none">
                                            <div class="text-sm text-gray-900 dark:text-gray-700 whitespace-pre-wrap leading-relaxed">' . $preview . '</div>
                                            <div class="text-[10px] text-gray-500 dark:text-gray-400 text-right mt-1 flex justify-end items-center gap-1">
                                                ' . now()->format('H:i') . '
                                                <span class="text-blue-500">
                                                    <svg viewBox="0 0 16 15" width="16" height="15" class="w-3 h-3 fill-current"><path d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.879a.32.32 0 0 1-.484.033l-.358-.325a.319.319 0 0 0-.484.032l-.378.483a.418.418 0 0 0 .036.541l1.32 1.266c.143.14.361.125.484-.033l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.879a.32.32 0 0 1-.484.033L1.891 7.769a.366.366 0 0 0-.515.006l-.423.433a.364.364 0 0 0 .006.514l3.258 3.185c.143.14.361.125.484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"></path></svg>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                ');
                            })
                            ->visible(fn (Get $get) => filled($get('body'))),
                    ])
                    ->columns(1),

                // ──────────────────────────────────────────
                // Rejection Reason (shown only for rejected templates)
                // ──────────────────────────────────────────
                Section::make('⚠️ Template Rejected')
                    ->schema([
                        Placeholder::make('rejection_banner')
                            ->label('')
                            ->content(function ($record) {
                                if (! $record || ! $record->rejection_reason) {
                                    return '';
                                }

                                return new HtmlString('
                                    <div class="rounded-lg border-2 border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/50 p-4 space-y-3">
                                        <div class="font-semibold text-red-700 dark:text-red-300 flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            Meta Rejection Reason
                                        </div>
                                        <div class="text-red-600 dark:text-red-300 text-sm">' . e($record->rejection_reason) . '</div>
                                        <div class="text-gray-600 dark:text-gray-400 text-xs border-t border-red-200 dark:border-red-700 pt-2 space-y-1">
                                            <p><strong>What to do:</strong></p>
                                            <p>1. Review and fix the content based on the rejection reason above</p>
                                            <p>2. Make sure utility templates don\'t contain promotional language</p>
                                            <p>3. Save your changes, then click "Resubmit for Approval"</p>
                                        </div>
                                    </div>
                                ');
                            }),
                    ])
                    ->visible(fn ($record) => $record?->status === WhatsAppTemplateStatusEnum::REJECTED)
                    ->collapsed(false),

                // ──────────────────────────────────────────
                // Settings
                // ──────────────────────────────────────────
                Section::make('Settings')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive templates won\'t appear in the broadcast template picker.'),
                    ])
                    ->collapsed()
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->description(fn (WhatsAppTemplate $record) => $record->name)
                    ->weight('bold'),

                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        'utility' => 'success',
                        'marketing' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'utility' => 'heroicon-o-wrench-screwdriver',
                        'marketing' => 'heroicon-o-megaphone',
                        default => 'heroicon-o-chat-bubble-left',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->limit(40)
                    ->tooltip(fn (WhatsAppTemplate $record) => $record->rejection_reason)
                    ->color('danger'),

                TextColumn::make('variable_count')
                    ->label('Variables')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('usage_count')
                    ->label('Used')
                    ->sortable()
                    ->alignCenter()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WhatsAppTemplateStatusEnum::class),
                SelectFilter::make('category')
                    ->options([
                        'utility' => 'Utility',
                        'marketing' => 'Marketing',
                    ]),
                TernaryFilter::make('is_default')
                    ->label('Default Templates'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TrashedFilter::make(),
            ])
            ->actions([
                ActionGroup::make([
                    // Preview action (WhatsApp-style message preview)
                    Action::make('preview')
                        ->label('Preview')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->modalHeading('Template Preview')
                        ->modalDescription(fn (WhatsAppTemplate $record) => $record->display_name)
                        ->modalContent(function (WhatsAppTemplate $record) {
                            $sampleValues = [
                                '{{1}}' => 'John Doe',
                                '{{2}}' => 'FitZone Gym',
                                '{{3}}' => 'March 15, 2026',
                                '{{4}}' => '₹1,499',
                                '{{5}}' => '30 days',
                                '{{6}}' => 'Premium Plan',
                            ];
                            $preview = str_replace(array_keys($sampleValues), array_values($sampleValues), e($record->body));
                            $preview = nl2br($preview);

                            $statusColor = match ($record->status) {
                                WhatsAppTemplateStatusEnum::APPROVED => 'emerald',
                                WhatsAppTemplateStatusEnum::PENDING => 'amber',
                                WhatsAppTemplateStatusEnum::REJECTED => 'red',
                                default => 'gray',
                            };

                            return new HtmlString('
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <span class="px-2 py-1 rounded text-xs font-medium bg-' . $statusColor . '-100 text-' . $statusColor . '-700 dark:bg-' . $statusColor . '-900/30 dark:text-' . $statusColor . '-400">'
                                            . $record->status->getLabel() .
                                        '</span>
                                        <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">'
                                            . ucfirst($record->category) .
                                        '</span>
                                    </div>
    
                                    <div class="bg-[#ece5dd] dark:bg-gray-800 rounded-xl p-6 flex justify-center">
                                        <div class="max-w-xs w-full">
                                            <div class="bg-[#e7fedd] dark:bg-emerald-900/40 rounded-lg rounded-tr-none p-3 shadow-sm">
                                                <div class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">' . $preview . '</div>
                                                <div class="text-[10px] text-gray-400 text-right mt-1">' . now()->format('h:i A') . ' ✓✓</div>
                                            </div>
                                        </div>
                                    </div>
    
                                    <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                                        <p><strong>Internal Name:</strong> <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">' . e($record->name) . '</code></p>
                                        <p><strong>Variables:</strong> ' . ($record->variable_count > 0 ? $record->variable_count . ' variables' : 'None') . '</p>
                                        <p><strong>Times Used:</strong> ' . number_format($record->usage_count) . '</p>
                                        <p class="italic text-gray-400">Sample values: John Doe, FitZone Gym, March 15, 2026, ₹1,499</p>
                                    </div>
                                    ' . ($record->rejection_reason ? '
                                    <div class="rounded-lg border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/50 p-3">
                                        <div class="font-semibold text-red-600 dark:text-red-400 text-sm">Rejection Reason</div>
                                        <div class="text-red-500 dark:text-red-400 text-sm mt-1">' . e($record->rejection_reason) . '</div>
                                    </div>' : '') . '
                                </div>
                            ');
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false),

                    // Submit for approval
                    Action::make('submit')
                        ->label('Submit for Approval')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Submit Template for Meta Approval')
                        ->modalDescription('Your template will be submitted to Meta (WhatsApp) for review. This typically takes a few minutes to a few hours. You\'ll be notified when it\'s approved or rejected.')
                        ->visible(fn (WhatsAppTemplate $record) => $record->status === WhatsAppTemplateStatusEnum::DRAFT && $record->gym_id === Filament::getTenant()?->id)
                        ->action(function (WhatsAppTemplate $record) {
                            try {
                                $provider = self::getTemplateProvider($record->gym_id);
                                $result = $provider->submitTemplate($record->fresh());

                                if ($result->success) {
                                    Notification::make()
                                        ->success()
                                        ->title('Template Submitted!')
                                        ->body('Your template has been submitted for approval. You\'ll be notified of the result.')
                                        ->send();
                                } else {
                                    Log::info('Template submission failed', ['template_id' => $record->id, 'error' => $result->error]);
                                    Notification::make()
                                        ->danger()
                                        ->title('Submission Failed')
                                        ->body('Your template has not been submitted for approval.')
                                        ->send();
                                }
                            } catch (InvalidArgumentException|RuntimeException $e) {
                                Log::error('Template submission failed with exception', ['template_id' => $record->id, 'error' => $e->getMessage()]);
                                Notification::make()
                                    ->danger()
                                    ->title('Submission Failed')
                                    ->body('Template submission failed. Please try again later.')
                                    ->send();
                            }
                        }),

                    // Resubmit (for rejected templates)
                    Action::make('resubmit')
                        ->label('Resubmit')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Resubmit Template for Approval')
                        ->modalDescription(fn (WhatsAppTemplate $record) => 'Previous rejection reason: "' . ($record->rejection_reason ?? 'Unknown') . '". Please ensure you\'ve fixed the issue before resubmitting.')
                        ->visible(fn (WhatsAppTemplate $record) => $record->status === WhatsAppTemplateStatusEnum::REJECTED && $record->gym_id === Filament::getTenant()?->id)
                        ->action(function (WhatsAppTemplate $record) {
                            // Reset status to draft, clear rejection, then resubmit
                            $record->update([
                                'status' => WhatsAppTemplateStatusEnum::DRAFT,
                                'rejection_reason' => null,
                            ]);

                            try {
                                $provider = self::getTemplateProvider($record->gym_id);
                                $result = $provider->submitTemplate($record->fresh());

                                if ($result->success) {
                                    Notification::make()
                                        ->success()
                                        ->title('Template Resubmitted!')
                                        ->body('Your template has been resubmitted for approval.')
                                        ->send();
                                } else {
                                    Log::info('Template resubmission failed', ['template_id' => $record->id, 'error' => $result->error]);
                                    Notification::make()
                                        ->danger()
                                        ->title('Resubmission Failed')
                                        ->body('Your updated template has not been submitted for approval.')
                                        ->send();
                                }
                            } catch (InvalidArgumentException|RuntimeException $e) {
                                Log::error('Template resubmission failed with exception', ['template_id' => $record->id, 'error' => $e->getMessage()]);
                                Notification::make()
                                    ->danger()
                                    ->title('Resubmission Failed')
                                    ->body('Your updated template has not been submitted for approval. Please try again later.')
                                    ->send();
                            }
                        }),

                    // Sync status
                    Action::make('syncStatus')
                        ->label('Sync Status')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->visible(fn (WhatsAppTemplate $record) => filled($record->twilio_template_sid) && $record->gym_id === Filament::getTenant()?->id)
                        ->requiresConfirmation()
                        ->modalHeading('Sync Template Status')
                        ->modalDescription('This will fetch the latest status from Meta/WhatsApp. Use this if your template is stuck in "pending" status.')
                        ->action(function (WhatsAppTemplate $record) {
                            try {
                                $provider = self::getTemplateProvider($record->gym_id);
                                $provider->syncTemplateStatus($record);
                                $record->refresh();

                                Notification::make()
                                    ->info()
                                    ->title('Status Synced')
                                    ->body('Template status: ' . ($record->status?->getLabel() ?? 'Unknown'))
                                    ->send();
                            } catch (InvalidArgumentException|RuntimeException $e) {
                                Log::error('Template status sync failed with exception', ['template_id' => $record->id, 'error' => $e->getMessage()]);
                                Notification::make()
                                    ->danger()
                                    ->title('Status Sync Failed')
                                    ->body('Failed to sync template status. Please try again later.')
                                    ->send();
                            }
                        }),

                    EditAction::make()
                        ->visible(fn (WhatsAppTemplate $record) => ! $record->is_default && $record->gym_id === Filament::getTenant()?->id),

                    DeleteAction::make()
                        ->visible(fn (WhatsAppTemplate $record) => ! $record->is_default && $record->gym_id === Filament::getTenant()?->id),

                    RestoreAction::make()
                        ->visible(fn (WhatsAppTemplate $record) => ! $record->is_default && $record->trashed() && $record->gym_id === Filament::getTenant()?->id),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Templates Yet')
            ->emptyStateDescription('Create your first WhatsApp template to start sending messages to your members.')
            ->emptyStateIcon('phosphor-chat-text');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppTemplates::route('/'),
            'create' => CreateWhatsAppTemplate::route('/create'),
            'edit' => EditWhatsAppTemplate::route('/{record}/edit'),
        ];
    }

    protected static function getDefaultTemplateOptions(): array
    {
        $templates = static::getDefaultTemplatesForForm();
        $options = [];

        foreach ($templates as $template) {
            $options[$template['name']] = $template['display_name'] ?? ucwords(str_replace('_', ' ', $template['name']));
        }

        return $options;
    }

    public static function getTemplateProvider(?int $gymId = null): TemplateManagementInterface
    {
        $gymId ??= Filament::getTenant()?->id;

        throw_unless($gymId, RuntimeException::class, 'No tenant context available for provider resolution');

        return WhatsAppProviderFactory::create($gymId);
    }

    /**
     * Normalize template data before saving, filling in null/missing values
     * that Filament's default() and placeholder() don't submit.
     *
     * @param  array<string, mixed>  $data  Form data from create/edit
     * @return array<string, mixed> Normalized data with variables and variable_placeholder fully populated
     */
    public static function normalizeTemplateData(array $data): array
    {
        $body = $data['body'] ?? '';
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
        $uniqueNumbers = array_unique($matches[1] ?? []);
        sort($uniqueNumbers);

        $data['variable_count'] = count($uniqueNumbers);

        $defaultData = TemplateVariableHelper::getDefaultVariableData();

        $sources = WhatsAppVariableResolver::getAvailableDataSources();
        $variablePlaceholder = $data['variable_placeholder'] ?? [];
        $normalizedPlaceholder = [];
        $variables = [];

        foreach ($uniqueNumbers as $num) {
            $existing = $variablePlaceholder[$num] ?? [];
            $defaults = $defaultData[$num] ?? [
                'placeholder' => 'Variable ' . $num,
                'name' => 'variable_' . $num,
                'example' => 'Example ' . $num,
                'type' => 'text',
                'source' => 'custom',
            ];

            // Resolve source label for placeholder fallback
            $source = $existing['source'] ?? $defaults['source'];
            $sourceLabel = isset($sources[$source]) ? $sources[$source]['label'] : null;
            $sourceExample = isset($sources[$source]) ? $sources[$source]['example'] : null;

            // Fill in every field — never store null
            $placeholder = filled($existing['placeholder']) ? $existing['placeholder'] : ($sourceLabel ?? $defaults['placeholder']);
            $example = filled($existing['example']) ? $existing['example'] : ($sourceExample ?? $defaults['example']);
            $type = $existing['type'] ?? $defaults['type'];
            $name = filled($existing['name']) ? $existing['name'] : $defaults['name'];

            $normalizedPlaceholder[$num] = [
                'variable' => '{{' . $num . '}}',
                'source' => $source,
                'placeholder' => $placeholder,
                'example' => $example,
                'type' => $type,
                'name' => $name,
            ];

            $variables[] = [
                'name' => $name,
                'format' => $type,
                'example' => $example,
            ];
        }

        $data['variable_placeholder'] = $normalizedPlaceholder;
        $data['variables'] = $variables;

        return $data;
    }

    public static function getDefaultTemplatesForForm(): array
    {
        return WhatsAppTemplate::getDefaults()
            ->mapWithKeys(fn ($template) => [
                $template->name => [
                    'name' => $template->name,
                    'display_name' => $template->display_name,
                    'body' => $template->body,
                    'category' => $template->category,
                    'variable_placeholder' => $template->variable_placeholder,
                    'variables' => $template->variables,
                    'variable_count' => $template->variable_count,
                ],
            ])
            ->all();
    }
}
