<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages\CreateWhatsAppTemplate;
use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages\EditWhatsAppTemplate;
use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages\ListWhatsAppTemplates;
use App\Jobs\PushWhatsAppTemplateToGymJob;
use App\Models\Gym;
use App\Models\WhatsAppTemplate;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class WhatsAppTemplateResource extends BaseResource
{
    protected static ?string $model = WhatsAppTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'WhatsApp Templates';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    public static function getNavigationGroup(): string
    {
        return 'WhatsApp';
    }

    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(WhatsAppTemplate::SUPER_ADMIN_NAVIGATION_BADGE_CACHE_KEY, [1800, 3600], function () {
            return (string) static::getModel()::whereNull('gym_id')->isDefault()->count();
        }) ?: null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('gym_id')
            ->isDefault();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Template Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->regex('/^[a-z][a-z0-9_]*$/')
                            ->placeholder('e.g., payment_reminder')
                            ->helperText('Lowercase, underscores only. This is the internal name used by WhatsApp API.'),

                        TextInput::make('display_name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Payment Reminder')
                            ->helperText('A friendly name for easy identification.'),

                        Select::make('category')
                            ->options([
                                'utility' => '🔧 Utility — Transactional messages',
                                'marketing' => '📢 Marketing — Promotional messages',
                            ])
                            ->required()
                            ->default('utility')
                            ->helperText('Utility for transactional, Marketing for promotional content.'),
                    ])
                    ->columns(2),

                Section::make('Template Body')
                    ->description('Write your message template. Use {{1}}, {{2}}, etc. for dynamic variables.')
                    ->schema([
                        Textarea::make('body')
                            ->required()
                            ->rows(6)
                            ->maxLength(1024)
                            ->live(debounce: 500)
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
                                    <div class="text-gray-500 dark:text-gray-400 grid grid-cols-2 gap-1">
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{1}}</code> — Variable 1</span>
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{2}}</code> — Variable 2</span>
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{3}}</code> — Variable 3</span>
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{4}}</code> — Variable 4</span>
                                    </div>
                                    <div class="text-gray-400 dark:text-gray-500 mt-1">Variables must be sequential: {{1}}, {{2}}, {{3}}. Don\'t skip numbers.</div>
                                </div>
                            ')),

                        // Dynamic Variable Placeholders
                        Section::make('Variable Placeholders')
                            ->description('Define what each variable represents to help users fill in the correct values.')
                            ->schema([
                                Placeholder::make('variable_placeholder_info')
                                    ->label('')
                                    ->content(new HtmlString('
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                            Define descriptive names for each variable used in your template. This helps users understand what value to enter for each {{1}}, {{2}}, etc.
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
                                    '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text'],
                                    '2' => ['placeholder' => 'Gym Name', 'name' => 'gym_name', 'example' => 'FitZone Gym', 'type' => 'text'],
                                    '3' => ['placeholder' => 'Date', 'name' => 'date', 'example' => 'January 15, 2026', 'type' => 'text'],
                                    '4' => ['placeholder' => 'Amount', 'name' => 'amount', 'example' => '₹1,499', 'type' => 'number'],
                                    '5' => ['placeholder' => 'Time', 'name' => 'time', 'example' => '7:00 AM', 'type' => 'text'],
                                    '6' => ['placeholder' => 'Plan Name', 'name' => 'plan_name', 'example' => 'Premium Plan', 'type' => 'text'],
                                ];

                                foreach ($variableNumbers as $num) {
                                    $defaults = $defaultData[$num] ?? ['placeholder' => 'Variable ' . $num, 'name' => 'variable_' . $num, 'example' => 'Example ' . $num, 'type' => 'text'];

                                    $fields[] = Grid::make(4)
                                        ->schema([
                                            TextInput::make('variable_placeholder.' . $num . '.variable')
                                                ->label('{{' . $num . '}}')
                                                ->readonly()
                                                ->default('{{' . $num . '}}')
                                                ->columnSpan(1),
                                            TextInput::make('variable_placeholder.' . $num . '.placeholder')
                                                ->label('Placeholder')
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
                                    '{{6}}' => 'Premium Plan',
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

                Section::make('Status')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'pending' => 'Pending Approval',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('approved')
                            ->required()
                            ->helperText('Set to approved for new gyms to use this template by default.'),
                    ])
                    ->collapsed(),
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
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('variable_count')
                    ->label('Variables')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('body')
                    ->label('Preview')
                    ->limit(50)
                    ->tooltip(fn (WhatsAppTemplate $record) => $record->body),

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
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('push')
                        ->label('Push to All Gyms')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Push Template to All Gyms')
                        ->modalDescription(fn (WhatsAppTemplate $record) => "This will copy \"{$record->display_name}\" to all gyms with active users that don't already have a template with the same name. Existing templates with the same name will NOT be overwritten.")
                        ->before(function (WhatsAppTemplate $record) {
                            $gymCount = Gym::whereHas('users', function (Builder $query) {
                                $query->where('users.is_active', true);
                            })->count();

                            dispatch(new PushWhatsAppTemplateToGymJob($record->id));

                            Notification::make()
                                ->success()
                                ->title('Template Push Started')
                                ->body("Template is being pushed to {$gymCount} active gyms in the background.")
                                ->send();
                        }),

                    EditAction::make(),

                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('push_bulk')
                        ->label('Push Selected to All Gyms')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->requiresConfirmation()
                        ->modalHeading('Push Templates to All Gyms')
                        ->modalDescription('This will copy selected templates to all gyms with active users that don\'t already have templates with the same names.')
                        ->before(function ($records) {
                            $gymCount = Gym::whereHas('users', function (Builder $query) {
                                $query->where('users.is_active', true);
                            })->count();
                            $totalJobs = $gymCount * $records->count();

                            foreach ($records as $record) {
                                dispatch(new PushWhatsAppTemplateToGymJob($record->id));
                            }

                            Notification::make()
                                ->success()
                                ->title('Templates Push Started')
                                ->body("{$totalJobs} template-gym jobs have been dispatched to the queue.")
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Global Templates Yet')
            ->emptyStateDescription('Create global WhatsApp templates that will be available to all gyms.')
            ->emptyStateIcon('phosphor-chat-text');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppTemplates::route('/'),
            'create' => CreateWhatsAppTemplate::route('/create'),
            'edit' => EditWhatsAppTemplate::route('/{record}/edit'),
        ];
    }
}
