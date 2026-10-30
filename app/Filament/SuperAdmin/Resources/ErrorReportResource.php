<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources;

use App\Biometrics\Services\BiometricLogRedactor;
use App\Enums\DefaultRole;
use App\Enums\ErrorPriority;
use App\Enums\ErrorStatus;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages\CreateErrorReport;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages\EditErrorReport;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages\ListErrorReports;
use App\Helpers\ClipboardHelper;
use App\Helpers\ParserHelper;
use App\Models\ErrorReport;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class ErrorReportResource extends BaseResource
{
    protected static ?string $model = ErrorReport::class;

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 2;

    private static ?array $badgeData = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Error Details')
                    ->schema([
                        Select::make('status')
                            ->options(ErrorStatus::class)
                            ->default(ErrorStatus::PENDING)
                            ->required(),

                        Select::make('priority')
                            ->options(ErrorPriority::class)
                            ->default(ErrorPriority::MEDIUM)
                            ->required(),

                        Select::make('error_type')
                            ->label('Type')
                            ->options(self::errorTypeOptions())
                            ->default('user_reported')
                            ->required(),

                        Textarea::make('error_message')
                            ->rows(4)
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->required(fn (Get $get): bool => blank($get('user_description')))
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state === null
                                ? null
                                : BiometricLogRedactor::redactText($state))
                            ->columnSpanFull(),

                        Placeholder::make('error_message_view')
                            ->label('Error Message')
                            ->content(fn (?ErrorReport $record): HtmlString => self::preformatted(
                                BiometricLogRedactor::redactText($record?->error_message)
                            ))
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->columnSpanFull(),

                        Textarea::make('user_description')
                            ->label('User Description')
                            ->rows(4)
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->required(fn (Get $get): bool => blank($get('error_message')))
                            ->helperText('Describe the problem in your own words. This text cannot be edited after the report is created.')
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state === null
                                ? null
                                : BiometricLogRedactor::redactText($state))
                            ->columnSpanFull(),

                        // Read-only on edit: Filament's formatStateUsing() mutates
                        // the hydrated state, so a writable redacted field would
                        // be saved back and corrupt the stored evidence.
                        Placeholder::make('user_description_view')
                            ->label('User Description')
                            ->content(fn (?ErrorReport $record): HtmlString => self::preformatted(
                                BiometricLogRedactor::redactText($record?->user_description)
                            ))
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Technical Details')
                    ->description('Read-only. These fields are redacted for display and are never written back to the stored report.')
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->schema([
                        Placeholder::make('origin_url_view')
                            ->label('Origin URL')
                            ->content(fn (?ErrorReport $record): string => $record?->redactedOriginUrl() ?? '')
                            ->columnSpanFull(),

                        Placeholder::make('livewire_component_view')
                            ->label('Livewire/Filament Component')
                            ->content(fn (?ErrorReport $record): string => (string) $record?->livewire_component),

                        Placeholder::make('php_version_view')
                            ->label('PHP Version')
                            ->content(fn (?ErrorReport $record): string => (string) $record?->php_version),

                        Placeholder::make('laravel_version_view')
                            ->label('Laravel Version')
                            ->content(fn (?ErrorReport $record): string => (string) $record?->laravel_version),

                        Placeholder::make('markdown_report_view')
                            ->label('Full Markdown Report')
                            ->content(fn (?ErrorReport $record): HtmlString => self::preformatted(
                                $record?->toRedactedMarkdown() ?? ''
                            ))
                            ->columnSpanFull(),

                        Placeholder::make('stack_trace_view')
                            ->label('Raw Stack Trace')
                            ->content(fn (?ErrorReport $record): HtmlString => self::preformatted(
                                BiometricLogRedactor::redactText($record?->stack_trace)
                            ))
                            ->columnSpanFull(),

                        Placeholder::make('context_view')
                            ->label('Context')
                            ->content(fn (?ErrorReport $record): HtmlString => self::preformatted(
                                (string) json_encode(
                                    $record?->redactedContextForDisplay() ?? [],
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                                )
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    private static function preformatted(string $value): HtmlString
    {
        return new HtmlString(
            '<pre class="whitespace-pre-wrap break-words text-sm">' . e($value) . '</pre>'
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Reporter')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('priority')
                    ->badge(),

                TextColumn::make('duplicate_count')
                    ->color('danger')
                    ->badge(),

                TextColumn::make('error_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BiometricLogRedactor::redactText($state)),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
                    ->formatStateUsing(fn (?string $state): string => BiometricLogRedactor::redactText($state))
                    ->searchable(),

                TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ErrorStatus::class),

                SelectFilter::make('priority')
                    ->options(ErrorPriority::class),

                SelectFilter::make('error_type')
                    ->options(self::errorTypeOptions()),
            ])
            ->actions([
                ActionGroup::make([
                    self::getCopyToMarkdownAction(),
                    Action::make('markResolved')
                        ->label('Mark as Resolved')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (ErrorReport $record) => $record->status !== ErrorStatus::RESOLVED)
                        ->action(function (ErrorReport $record) {
                            $record->update(['status' => ErrorStatus::RESOLVED]);

                            Notification::make()
                                ->title('Marked as resolved')
                                ->success()
                                ->send();
                        }),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('markResolved')
                        ->label('Mark as Resolved')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(function (Collection $records) {
                            // Use a single query for bulk updates to avoid N+1 performance issues.
                            static::getModel()::whereIn('id', $records->pluck('id'))->update(['status' => ErrorStatus::RESOLVED]);

                            // Manually clear caches since bulk update doesn't trigger model observers.
                            $keys = [
                                ErrorReport::NAVIGATION_BADGE_CACHE_KEY,
                                ErrorReport::ERROR_REPORT_ALL_COUNT_CACHE_KEY,
                                ErrorReport::ERROR_REPORT_PENDING_COUNT_CACHE_KEY,
                                ErrorReport::ERROR_REPORT_INVESTIGATING_COUNT_CACHE_KEY,
                                ErrorReport::ERROR_REPORT_RESOLVED_COUNT_CACHE_KEY,
                            ];
                            ParserHelper::clearCache($keys);

                            Notification::make()
                                ->title('Marked as resolved')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('occurred_at', 'desc');
    }

    public static function getCopyToMarkdownAction(): Action
    {
        return Action::make('copyMarkdown')
            ->label('Copy as Markdown')
            ->icon('heroicon-o-clipboard-document')
            ->action(function (ErrorReport $record, $livewire) {
                // Historical rows may predate write-time redaction. The model
                // owns the single redacted export used by both copy actions.
                $livewire->js(self::copyToClipboardScript($record->toRedactedMarkdown()));
            });
    }

    /**
     * Clipboard script with a secure-context fallback for HTTP/LAN panels.
     */
    public static function copyToClipboardScript(string $value): string
    {
        return ClipboardHelper::copyScript($value);
    }

    /**
     * @return array<string, string>
     */
    public static function errorTypeOptions(): array
    {
        return [
            'auto_reported' => 'Auto Reported',
            'user_reported' => 'User Reported',
            'exception' => 'Exception',
            'validation' => 'Validation',
        ];
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
            'index' => ListErrorReports::route('/'),
            'create' => CreateErrorReport::route('/create'),
            'edit' => EditErrorReport::route('/{record}/edit'),
        ];
    }

    private static function getBadgeData(): array
    {
        if (self::$badgeData === null) {
            $stats = static::getModel()::where('status', ErrorStatus::PENDING->value)
                ->selectRaw('count(*) as count, (SUM(CASE WHEN priority = ? THEN 1 ELSE 0 END) > 0) as has_critical', [ErrorPriority::CRITICAL->value])
                ->first();

            self::$badgeData = [
                'count' => (int) $stats->count,
                'has_critical' => (bool) $stats->has_critical,
            ];
        }

        return self::$badgeData;
    }

    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(ErrorReport::NAVIGATION_BADGE_CACHE_KEY, [21600, 604800], function () {
            return (string) self::getBadgeData()['count'];
        }) ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $data = self::getBadgeData();
        if ($data['count'] === 0) {
            // No badge is shown, so no color is needed.
            return null;
        }

        return $data['has_critical'] ? 'danger' : 'warning';
    }
}
