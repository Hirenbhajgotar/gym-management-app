<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\ErrorPriority;
use App\Enums\ErrorStatus;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\Pages;
use App\Filament\SuperAdmin\Resources\ErrorReportResource\RelationManagers;
use App\Models\ErrorReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('markResolved')
                        ->label('Mark as Resolved')
                        ->icon('heroicon-m-check-circle')
                        ->action(fn(Collection $records) => static::getModel()::whereIn('id', $records->pluck('id'))->update(['status' => ErrorStatus::RESOLVED->value]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('occurred_at', 'desc');
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
        $count = self::getBadgeData()['count'];
        return $count > 0 ? (string) $count : null;
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
