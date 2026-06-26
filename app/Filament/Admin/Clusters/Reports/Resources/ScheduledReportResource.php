<?php

namespace App\Filament\Admin\Clusters\Reports\Resources;

use App\Enums\DefaultRole;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource\Pages;
use App\Filament\BaseResources\BaseResource;
use App\Models\ScheduledReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class ScheduledReportResource extends BaseResource
{
    protected static ?string $model = ScheduledReport::class;

    protected static ?string $navigationIcon = 'phosphor-clock';

    protected static ?string $cluster = Reports::class;

    protected static ?int $navigationSort = 10;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
            ],

            DefaultRole::RECEPTIONIST->value => [],
            DefaultRole::TRAINER->value => [],
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('report_type')
                            ->options([
                                \App\Filament\Admin\Clusters\Reports\Pages\ReportsDashboard::class => 'Reports Dashboard',
                                \App\Filament\Admin\Clusters\Reports\Pages\RevenueReports::class => 'Revenue Reports',
                                \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class => 'Member Reports',
                                // Add other report classes here
                            ])
                            ->required(),

                        Forms\Components\Select::make('frequency')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                            ])
                            ->required(),

                        Forms\Components\TimePicker::make('scheduled_time')
                            ->required()
                            ->default('09:00:00'),

                        Forms\Components\TagsInput::make('recipients')
                            ->placeholder('Enter email addresses')
                            ->helperText('Press Enter after each email')
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('report_type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        \App\Filament\Admin\Clusters\Reports\Pages\ReportsDashboard::class => 'Reports Dashboard',
                        \App\Filament\Admin\Clusters\Reports\Pages\RevenueReports::class => 'Revenue Reports',
                        \App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class => 'Member Reports',
                        default => class_basename($state),
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('frequency')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'daily' => 'info',
                        'weekly' => 'warning',
                        'monthly' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('scheduled_time')
                    ->time(),
                Tables\Columns\ToggleColumn::make('is_active'),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->dateTime()
                    ->placeholder('Never'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('send_now')
                    ->icon('phosphor-paper-plane-right')
                    ->color('success')
                    ->action(function (ScheduledReport $record) {
                        \App\Jobs\ScheduledReportJob::dispatch($record);
                        \Filament\Notifications\Notification::make()
                            ->title('Report Queued')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScheduledReports::route('/'),
            'create' => Pages\CreateScheduledReport::route('/create'),
            'edit' => Pages\EditScheduledReport::route('/{record}/edit'),
        ];
    }
}
