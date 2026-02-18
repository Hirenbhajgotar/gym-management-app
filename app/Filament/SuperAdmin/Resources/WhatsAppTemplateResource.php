<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\SuperAdmin\Resources\WhatsAppTemplateResource\Pages;
use App\Jobs\PushWhatsAppTemplateToGymJob;
use App\Models\Gym;
use App\Models\WhatsappTemplate;
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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'WhatsApp Templates';

    public static function getNavigationGroup(): string
    {
        return 'WhatsApp';
    }

    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(WhatsAppTemplate::SUPER_ADMIN_NAVIGATION_BADGE_CACHE_KEY, [now()->addMinutes(30), now()->addHour()], function () {
            $count = static::getModel()::whereNull('gym_id')->isDefault()->count();

            return $count > 0 ? (string) $count : null;
        });
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
                Forms\Components\Section::make('Template Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->regex('/^[a-z][a-z0-9_]*$/')
                            ->placeholder('e.g., payment_reminder')
                            ->helperText('Lowercase, underscores only. This is the internal name used by WhatsApp API.'),

                        Forms\Components\TextInput::make('display_name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Payment Reminder')
                            ->helperText('A friendly name for easy identification.'),

                        Forms\Components\Select::make('category')
                            ->options([
                                'utility' => '🔧 Utility — Transactional messages',
                                'marketing' => '📢 Marketing — Promotional messages',
                            ])
                            ->required()
                            ->default('utility')
                            ->helperText('Utility for transactional, Marketing for promotional content.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Template Body')
                    ->description('Write your message template. Use {{1}}, {{2}}, etc. for dynamic variables.')
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
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{1}}</code> — Variable 1</span>
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{2}}</code> — Variable 2</span>
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{3}}</code> — Variable 3</span>
                                        <span><code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{4}}</code> — Variable 4</span>
                                    </div>
                                    <div class="text-gray-400 dark:text-gray-500 mt-1">Variables must be sequential: {{1}}, {{2}}, {{3}}. Don\'t skip numbers.</div>
                                </div>
                            ')),

                        Forms\Components\Placeholder::make('message_preview')
                            ->label('📱 Message Preview')
                            ->content(function (Forms\Get $get) {
                                $body = $get('body') ?? '';

                                if (empty($body)) {
                                    return new HtmlString('<div class="text-gray-400 italic text-sm">Start typing your message to see a preview...</div>');
                                }

                                $sampleValues = [
                                    '{{1}}' => 'John Doe',
                                    '{{2}}' => 'FitZone Gym',
                                    '{{3}}' => 'March 15, 2026',
                                    '{{4}}' => '₹1,499',
                                ];
                                $preview = str_replace(array_keys($sampleValues), array_values($sampleValues), e($body));
                                $preview = nl2br($preview);

                                return new HtmlString('
                                    <div class="max-w-sm">
                                        <div class="bg-[#e7fedd] dark:bg-emerald-900/40 rounded-lg rounded-tr-none p-3 shadow-sm border border-emerald-200/50 dark:border-emerald-800/50">
                                            <div class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">' . $preview . '</div>
                                        </div>
                                    </div>
                                ');
                            })
                            ->visible(fn (Forms\Get $get) => ! empty($get('body'))),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
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
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('variable_count')
                    ->label('Variables')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('body')
                    ->label('Preview')
                    ->limit(50)
                    ->tooltip(fn (WhatsAppTemplate $record) => $record->body),

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
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\Action::make('push')
                        ->label('Push to All Gyms')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Push Template to All Gyms')
                        ->modalDescription(fn (WhatsappTemplate $record) => "This will copy \"{$record->display_name}\" to all gyms with active users that don't already have a template with the same name. Existing templates with the same name will NOT be overwritten.")
                        ->before(function (WhatsappTemplate $record) {
                            $gymCount = Gym::whereHas('users', function ($query) {
                                $query->where('users.is_active', true);
                            })->count();

                            PushWhatsAppTemplateToGymJob::dispatch($record->id);

                            Notification::make()
                                ->success()
                                ->title('Template Push Started')
                                ->body("Template is being pushed to {$gymCount} active gyms in the background.")
                                ->send();
                        }),

                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('push_bulk')
                        ->label('Push Selected to All Gyms')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->requiresConfirmation()
                        ->modalHeading('Push Templates to All Gyms')
                        ->modalDescription('This will copy selected templates to all gyms with active users that don\'t already have templates with the same names.')
                        ->before(function ($records) {
                            $gymCount = Gym::whereHas('users', function ($query) {
                                $query->where('users.is_active', true);
                            })->count();
                            $totalJobs = $gymCount * $records->count();

                            foreach ($records as $record) {
                                PushWhatsAppTemplateToGymJob::dispatch($record->id);
                            }

                            Notification::make()
                                ->success()
                                ->title('Templates Push Started')
                                ->body("{$totalJobs} template-gym jobs have been dispatched to the queue.")
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListWhatsAppTemplates::route('/'),
            'create' => Pages\CreateWhatsAppTemplate::route('/create'),
            'edit' => Pages\EditWhatsAppTemplate::route('/{record}/edit'),
        ];
    }
}
