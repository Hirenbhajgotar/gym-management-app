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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Error Details')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(collect(ErrorStatus::cases())->mapWithKeys(
                                fn($method) => [$method->value => $method->getLabel()]
                            ))
                            ->required(),

                        Forms\Components\Select::make('priority')
                            ->options(collect(ErrorPriority::cases())->mapWithKeys(
                                fn($method) => [$method->value => $method->getLabel()]
                            ))
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
                        ->action(fn(Collection $records) => $records->each->update(['status' => ErrorStatus::RESOLVED->value]))
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

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', ErrorStatus::PENDING->value)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', ErrorStatus::PENDING->value)->where('priority', ErrorPriority::CRITICAL->value)->exists()
            ? 'danger'
            : 'warning';
    }
}
