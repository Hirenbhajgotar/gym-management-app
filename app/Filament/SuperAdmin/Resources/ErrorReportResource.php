<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\ErrorPriority;
use App\Enums\ErrorStatus;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages;
use App\Models\ErrorReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ErrorReportResource extends Resource
{
    protected static ?string $model = ErrorReport::class;

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 2;

    private static ?array $badgeData = null;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Error Details')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(ErrorStatus::class)
                            ->required(),

                        Forms\Components\Select::make('priority')
                            ->options(ErrorPriority::class)
                            ->required(),

                        Forms\Components\TextInput::make('error_type'),

                        Forms\Components\Textarea::make('error_message')
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('user_description')
                            ->label('User Description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Technical Details')
                    ->schema([
                        Forms\Components\Textarea::make('stack_trace')
                            ->label('Stack Trace')
                            ->disabled()
                            ->rows(15),

                        Forms\Components\KeyValue::make('context')
                            ->disabled(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Reporter')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge(),

                Tables\Columns\TextColumn::make('duplicate_count')
                    ->color('danger')
                    ->badge(),

                Tables\Columns\TextColumn::make('error_type')
                    ->label('Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ErrorStatus::class),

                Tables\Filters\SelectFilter::make('priority')
                    ->options(ErrorPriority::class),

                Tables\Filters\SelectFilter::make('error_type')
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
                    Tables\Actions\Action::make('markResolved')
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
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('markResolved')
                        ->label('Mark as Resolved')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(function (Collection $records) {
                            // Use a single query for bulk updates to avoid N+1 performance issues.
                            static::getModel()::whereIn('id', $records->pluck('id'))->update(['status' => ErrorStatus::RESOLVED]);

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

    public static function getCopyToMarkdownAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('copyMarkdown')
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
            'index' => Pages\ListErrorReports::route('/'),
            'create' => Pages\CreateErrorReport::route('/create'),
            'edit' => Pages\EditErrorReport::route('/{record}/edit'),
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
        return Cache::flexible(ErrorReport::NAVIGATION_BADGE_CACHE_KEY, [now()->addHours(6), now()->addWeek()], function () {
            $count = self::getBadgeData()['count'];

            return $count > 0 ? (string) $count : null;
        });
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
