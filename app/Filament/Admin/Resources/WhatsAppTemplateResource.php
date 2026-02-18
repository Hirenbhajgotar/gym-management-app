<?php

namespace App\Filament\Admin\Resources;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class WhatsAppTemplateResource extends Resource
{
    protected static ?string $model = WhatsAppTemplate::class;

    protected static ?string $navigationIcon = 'phosphor-chat-text';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $modelLabel = 'Template';

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        return Cache::flexible(WhatsAppTemplate::NAVIGATION_BADGE_CACHE_KEY . $tenant->id, [now()->addMinutes(30), now()->addHour()], function () use ($tenant) {
            $count = static::getModel()::where('gym_id', $tenant->id)->count();

            return $count > 0 ? (string) $count : null;
        });
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()
            ->where('gym_id', $tenant?->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ──────────────────────────────────────────
                // Default Template Selection (only for create)
                // ──────────────────────────────────────────
                Forms\Components\Section::make('Start from Default Template')
                    ->description('Choose a pre-built template and customize it for your gym')
                    ->schema([
                        Forms\Components\Select::make('_default_template')
                            ->label('Select a Default Template')
                            ->placeholder('Choose a template to get started...')
                            ->options(static::getDefaultTemplateOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (empty($state)) {
                                    return;
                                }

                                $templates = static::getDefaultTemplatesForForm();
                                if (isset($templates[$state])) {
                                    $template = $templates[$state];
                                    $set('name', $template['name']);
                                    $set('display_name', $template['display_name'] ?? ucwords(str_replace('_', ' ', $template['name'])));
                                    $set('body', $template['body']);
                                    $set('category', $template['category'] ?? 'utility');
                                }
                            }),

                        Forms\Components\Placeholder::make('_template_preview')
                            ->label('')
                            ->content(function (Forms\Get $get) {
                                $selected = $get('_default_template');
                                if (empty($selected)) {
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
                            ->visible(fn (Forms\Get $get) => ! empty($get('_default_template'))),
                    ])
                    ->collapsed()
                    ->visible(fn ($record) => $record === null), // Only show on create

                // ──────────────────────────────────────────
                // Category Selection & Guidance
                // ──────────────────────────────────────────
                Forms\Components\Section::make('Template Category')
                    ->description('Choose the right category for your template. This affects Meta approval speed and how you can use the template.')
                    ->schema([
                        Forms\Components\Select::make('category')
                            ->options([
                                'utility' => '🔧 Utility — Transactional messages',
                                'marketing' => '📢 Marketing — Promotional messages',
                            ])
                            ->required()
                            ->default('utility')
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('_category_warning_shown', true))
                            ->helperText('Utility templates are for transactional messages (reminders, updates, confirmations). Marketing templates are for promotions and offers.'),

                        // Utility category guidance
                        Forms\Components\Placeholder::make('utility_guide')
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
                            ->visible(fn (Forms\Get $get) => $get('category') === 'utility'),

                        // Marketing category warning
                        Forms\Components\Placeholder::make('marketing_guide')
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
                            ->visible(fn (Forms\Get $get) => $get('category') === 'marketing'),
                    ])
                    ->columns(1)
                    ->collapsible(),

                // ──────────────────────────────────────────
                // Template Details
                // ──────────────────────────────────────────
                Forms\Components\Section::make('Template Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('gym_id', Filament::getTenant()?->id))
                            ->regex('/^[a-z][a-z0-9_]*$/')
                            ->placeholder('e.g., payment_reminder')
                            ->helperText('Lowercase, underscores only. e.g., "payment_reminder", "welcome_message". This is the internal name used by WhatsApp API.'),

                        Forms\Components\TextInput::make('display_name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Payment Reminder')
                            ->helperText('A friendly name for easy identification. This is what you\'ll see in the template picker.'),
                    ])
                    ->columns(2),

                // ──────────────────────────────────────────
                // Template Body Builder
                // ──────────────────────────────────────────
                Forms\Components\Section::make('Template Body')
                    ->description('Write your message template. Use {{1}}, {{2}}, etc. for dynamic variables like member name, amount, dates.')
                    ->schema([
                        Forms\Components\Textarea::make('body')
                            ->required()
                            ->rows(6)
                            ->maxLength(1024)
                            ->live(debounce: 500)
                            ->placeholder('Hi {{1}}, your membership at {{2}} is due for renewal on {{3}}. Please renew to continue enjoying our services.')
                            ->helperText(fn (Forms\Get $get) => new HtmlString(
                                '<span class="text-xs">'
                                . 'Characters: <strong>' . strlen($get('body') ?? '') . '/1024</strong>'
                                . ' | Variables detected: <strong>' . preg_match_all('/\{\{\d+\}\}/', $get('body') ?? '') . '</strong>'
                                . '</span>'
                            )),

                        Forms\Components\Placeholder::make('variable_helper')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800 p-3 text-xs space-y-2">
                                    <div class="font-semibold text-gray-600 dark:text-gray-300">💡 Variable Guide</div>
                                    <div class="text-gray-500 dark:text-gray-400 grid grid-cols-2 gap-1">
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{1}}</code> — Member name</span>
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{2}}</code> — Gym name</span>
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{3}}</code> — Date / Amount</span>
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{4}}</code> — Additional info</span>
                                    </div>
                                    <div class="text-gray-400 dark:text-gray-500 mt-1">Variables must be sequential: {{1}}, {{2}}, {{3}}. Don\'t skip numbers.</div>
                                </div>
                            ')),

                        // Dynamic Variable Placeholders
                        Forms\Components\Section::make('Variable Placeholders')
                            ->description('Map each dynamic placeholder to a descriptive label and example value to clarify the purpose of each variable during message composition.')
                            ->schema([
                                Forms\Components\Placeholder::make('variable_placeholder_info')
                                    ->label('')
                                    ->content(new HtmlString('
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                            Define descriptive names for each variable used in your template. This helps users understand what value to enter for each {{1}}, {{2}}, etc.
                                        </div>
                                    ')),
                            ])
                            ->schema(function (Forms\Get $get) {
                                $body = $get('body') ?? '';
                                preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
                                $variableNumbers = array_unique($matches[1] ?? []);

                                if (empty($variableNumbers)) {
                                    return [];
                                }

                                $fields = [];
                                $defaultData = [
                                    '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text'],
                                    '2' => ['placeholder' => 'Gym Name', 'name' => 'gym_name', 'example' => 'FitZone Gym', 'type' => 'text'],
                                    '3' => ['placeholder' => 'Date', 'name' => 'date', 'example' => 'January 15, 2026', 'type' => 'text'],
                                    '4' => ['placeholder' => 'Amount', 'name' => 'amount', 'example' => '₹1,499', 'type' => 'number'],
                                    '5' => ['placeholder' => 'Time', 'name' => 'time', 'example' => '7:00 AM', 'type' => 'text'],
                                    '6' => ['placeholder' => 'Plan Name', 'name' => 'plan_name', 'example' => 'Premium Plan', 'type' => 'text'],
                                ];

                                foreach ($variableNumbers as $num) {
                                    $defaults = $defaultData[$num] ?? ['placeholder' => 'Variable ' . $num, 'name' => 'variable_' . $num, 'example' => 'Example ' . $num, 'type' => 'text'];

                                    $fields[] = Forms\Components\Grid::make(4)
                                        ->schema([
                                            Forms\Components\TextInput::make('variable_placeholder.' . $num . '.variable')
                                                ->label('{{' . $num . '}}')
                                                ->readonly()
                                                ->default('{{' . $num . '}}')
                                                ->columnSpan(1),
                                            Forms\Components\TextInput::make('variable_placeholder.' . $num . '.placeholder')
                                                ->label('Placeholder')
                                                ->placeholder($defaults['placeholder'])
                                                ->live(debounce: 500)
                                                ->helperText('Display name')
                                                ->columnSpan(1),
                                            Forms\Components\TextInput::make('variable_placeholder.' . $num . '.example')
                                                ->label('Example')
                                                ->placeholder($defaults['example'])
                                                ->live(debounce: 500)
                                                ->helperText('Sample value')
                                                ->columnSpan(1),
                                            Forms\Components\Select::make('variable_placeholder.' . $num . '.type')
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
                            ->visible(fn (Forms\Get $get) => ! empty($get('body')) && preg_match_all('/\{\{(\d+)\}\}/', $get('body') ?? '', $matches) > 0),

                        // Live message preview
                        Forms\Components\Placeholder::make('message_preview')
                            ->label('📱 Message Preview')
                            ->content(function (Forms\Get $get) {
                                $body = $get('body') ?? '';
                                if (empty($body)) {
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
                                        if (isset($variablePlaceholder[$num]['example']) && ! empty($variablePlaceholder[$num]['example'])) {
                                            $sampleValues[$key] = $variablePlaceholder[$num]['example'];
                                        } elseif (isset($variablePlaceholder[$num]['placeholder']) && ! empty($variablePlaceholder[$num]['placeholder'])) {
                                            $sampleValues[$key] = $variablePlaceholder[$num]['placeholder'];
                                        } else {
                                            $sampleValues[$key] = $defaultSampleValues[$key] ?? 'Variable ' . $num;
                                        }
                                    }
                                }

                                $preview = str_replace(array_keys($sampleValues), array_values($sampleValues), e($body));
                                $preview = nl2br($preview);

                                $usedValues = array_values($sampleValues);

                                return new HtmlString('
                                    <div class="max-w-sm">
                                        <div class="bg-[#e7fedd] dark:bg-emerald-900/40 rounded-lg rounded-tr-none p-3 shadow-sm border border-emerald-200/50 dark:border-emerald-800/50">
                                            <div class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">' . $preview . '</div>
                                            <div class="text-[10px] text-gray-400 dark:text-gray-500 text-right mt-1">' . now()->format('g:i A') . ' ✓✓</div>
                                        </div>
                                        <div class="text-[10px] text-gray-400 mt-2 italic">
                                            Preview with: ' . implode(', ', $usedValues) . '
                                        </div>
                                    </div>
                                ');
                            })
                            ->visible(fn (Forms\Get $get) => ! empty($get('body'))),
                    ])
                    ->columns(1),

                // ──────────────────────────────────────────
                // Rejection Reason (shown only for rejected templates)
                // ──────────────────────────────────────────
                Forms\Components\Section::make('⚠️ Template Rejected')
                    ->schema([
                        Forms\Components\Placeholder::make('rejection_banner')
                            ->label('')
                            ->content(function ($record) {
                                if (! $record || ! $record->rejection_reason) {
                                    return '';
                                }

                                return new HtmlString('
                                    <div class="rounded-lg border-2 border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/50 p-4 space-y-3">
                                        <div class="font-semibold text-red-700 dark:text-red-400 flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            Meta Rejection Reason
                                        </div>
                                        <div class="text-red-600 dark:text-red-400 text-sm">' . e($record->rejection_reason) . '</div>
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
                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
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
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->description(fn (WhatsAppTemplate $record) => $record->name)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category')
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

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->limit(40)
                    ->tooltip(fn (WhatsAppTemplate $record) => $record->rejection_reason)
                    ->visible(fn () => WhatsAppTemplate::where('gym_id', Filament::getTenant()?->id)
                        ->where('status', WhatsAppTemplateStatusEnum::REJECTED)->exists())
                    ->color('danger'),

                Tables\Columns\TextColumn::make('variable_count')
                    ->label('Variables')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Used')
                    ->sortable()
                    ->alignCenter()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(WhatsAppTemplateStatusEnum::class),
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'utility' => 'Utility',
                        'marketing' => 'Marketing',
                    ]),
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Templates'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                ActionGroup::make([
                    // Preview action (WhatsApp-style message preview)
                    Tables\Actions\Action::make('preview')
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
                    Tables\Actions\Action::make('submit')
                        ->label('Submit for Approval')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Submit Template for Meta Approval')
                        ->modalDescription('Your template will be submitted to Meta (WhatsApp) for review. This typically takes a few minutes to a few hours. You\'ll be notified when it\'s approved or rejected.')
                        ->visible(fn (WhatsAppTemplate $record) => $record->status === WhatsAppTemplateStatusEnum::DRAFT)
                        ->action(function (WhatsAppTemplate $record) {
                            $service = app(WhatsAppTemplateService::class);
                            $result = $service->submitForVerification($record);

                            if ($result['success']) {
                                Notification::make()
                                    ->success()
                                    ->title('Template Submitted!')
                                    ->body('Your template has been submitted for Meta approval. You\'ll be notified of the result.')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Submission Failed')
                                    ->body($result['error'] ?? 'Unknown error occurred.')
                                    ->send();
                            }
                        }),

                    // Resubmit (for rejected templates)
                    Tables\Actions\Action::make('resubmit')
                        ->label('Resubmit')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Resubmit Template for Approval')
                        ->modalDescription(fn (WhatsAppTemplate $record) => 'Previous rejection reason: "' . ($record->rejection_reason ?? 'Unknown') . '". Please ensure you\'ve fixed the issue before resubmitting.')
                        ->visible(fn (WhatsAppTemplate $record) => $record->status === WhatsAppTemplateStatusEnum::REJECTED)
                        ->action(function (WhatsAppTemplate $record) {
                            // Reset status to draft, clear rejection, then resubmit
                            $record->update([
                                'status' => WhatsAppTemplateStatusEnum::DRAFT,
                                'rejection_reason' => null,
                            ]);

                            $service = app(WhatsAppTemplateService::class);
                            $result = $service->submitForVerification($record);

                            if ($result['success']) {
                                Notification::make()
                                    ->success()
                                    ->title('Template Resubmitted!')
                                    ->body('Your template has been resubmitted for approval.')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Resubmission Failed')
                                    ->body($result['error'] ?? 'Unknown error occurred.')
                                    ->send();
                            }
                        }),

                    // Sync status
                    Tables\Actions\Action::make('syncStatus')
                        ->label('Sync Status')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->visible(fn (WhatsAppTemplate $record) => ! empty($record->twilio_template_sid))
                        ->requiresConfirmation()
                        ->modalHeading('Sync Template Status')
                        ->modalDescription('This will fetch the latest status from Meta/WhatsApp. Use this if your template is stuck in "pending" status.')
                        ->action(function (WhatsAppTemplate $record) {
                            $service = app(WhatsAppTemplateService::class);
                            $service->syncTemplateStatus($record);
                            $record->refresh();

                            Notification::make()
                                ->info()
                                ->title('Status Synced')
                                ->body("Template status: {$record->status}")
                                ->send();
                        }),

                    Tables\Actions\EditAction::make()
                        ->visible(fn (WhatsAppTemplate $record) => ! $record->is_default),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (WhatsAppTemplate $record) => ! $record->is_default && ! $record->isApproved()),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListWhatsAppTemplates::route('/'),
            'create' => Pages\CreateWhatsAppTemplate::route('/create'),
            'edit' => Pages\EditWhatsAppTemplate::route('/{record}/edit'),
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

    public static function getDefaultTemplatesForForm(): array
    {
        $templates = WhatsAppTemplate::getDefaults()
            ->mapWithKeys(fn ($template) => [
                $template->name => [
                    'name' => $template->name,
                    'display_name' => $template->display_name,
                    'body' => $template->body,
                    'category' => $template->category,
                ],
            ])
            ->toArray();

        return $templates;
    }
}
