<?php

declare(strict_types=1);

namespace App\Filament\Admin\Clusters\Reports\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Filament\Admin\Clusters\Reports\Pages\ReportsDashboard;
use App\Filament\Admin\Clusters\Reports\Pages\RevenueReports;
use App\Filament\Admin\Clusters\Reports\Pages\MemberReports;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;
use App\Jobs\ScheduledReportJob;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource\Pages\ListScheduledReports;
use App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource\Pages\CreateScheduledReport;
use App\Filament\Admin\Clusters\Reports\Resources\ScheduledReportResource\Pages\EditScheduledReport;
use App\Enums\DefaultRole;
use App\Filament\Admin\Clusters\Reports;
use App\Filament\BaseResources\BaseResource;
use App\Models\ScheduledReport;
use Filament\Forms\Form;
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
                Section::make('Report Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Select::make('report_type')
                            ->options([
                                ReportsDashboard::class => 'Reports Dashboard',
                                RevenueReports::class => 'Revenue Reports',
                                MemberReports::class => 'Member Reports',
                                // Add other report classes here
                            ])
                            ->required(),

                        Select::make('frequency')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                            ])
                            ->required(),

                        TimePicker::make('scheduled_time')
                            ->required()
                            ->default('09:00:00'),

                        TagsInput::make('recipients')
                            ->placeholder('Enter email addresses')
                            ->helperText('Press Enter after each email')
                            ->required(),

                        Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('report_type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ReportsDashboard::class => 'Reports Dashboard',
                        RevenueReports::class => 'Revenue Reports',
                        MemberReports::class => 'Member Reports',
                        default => class_basename($state),
                    })
                    ->badge(),
                TextColumn::make('frequency')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'daily' => 'info',
                        'weekly' => 'warning',
                        'monthly' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('scheduled_time')
                    ->time(),
                ToggleColumn::make('is_active'),
                TextColumn::make('last_run_at')
                    ->dateTime()
                    ->placeholder('Never'),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('send_now')
                    ->icon('phosphor-paper-plane-right')
                    ->color('success')
                    ->action(function (ScheduledReport $record) {
                        dispatch(new ScheduledReportJob($record));
                        Notification::make()
                            ->title('Report Queued')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduledReports::route('/'),
            'create' => CreateScheduledReport::route('/create'),
            'edit' => EditScheduledReport::route('/{record}/edit'),
        ];
    }
}
