<?php

namespace App\Filament\SuperAdmin\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\KeyValue;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\BulkAction;
use App\Helpers\ParserHelper;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages\ListErrorReports;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages\CreateErrorReport;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages\EditErrorReport;
use App\Enums\DefaultRole;
use App\Enums\ErrorPriority;
use App\Enums\ErrorStatus;
use App\Filament\BaseResources\BaseResource;
use App\Models\ErrorReport;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

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
                            ->required(),

                        Select::make('priority')
                            ->options(ErrorPriority::class)
                            ->required(),

                        TextInput::make('error_type'),

                        Textarea::make('error_message')
                            ->rows(4)
                            ->columnSpanFull(),

                        Textarea::make('user_description')
                            ->label('User Description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Technical Details')
                    ->schema([
                        Textarea::make('stack_trace')
                            ->label('Stack Trace')
                            ->disabled()
                            ->rows(15),

                        KeyValue::make('context')
                            ->disabled(),
                    ])
                    ->collapsible(),
            ]);
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
                    ->badge(),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
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
                    ->options([
                        'auto_reported' => 'Auto Reported',
                        'user_reported' => 'User Reported',
                        'exception' => 'Exception',
                        'validation' => 'Validation',
                    ]),
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
                // Use json_encode to safely escape the string for JavaScript
                $jsString = json_encode($record->toMarkdown());

                $livewire->js("
                    window.navigator.clipboard.writeText({$jsString});
                    new FilamentNotification()
                        .title('Copied to clipboard')
                        .success()
                        .send();
                ");
            });
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
