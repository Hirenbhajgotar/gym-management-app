<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources;

use App\Contracts\HasFilamentPermissions;
use App\Filament\SuperAdmin\Resources\RequestContactResource\Pages;
use App\Models\RequestContact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class RequestContactResource extends Resource implements HasFilamentPermissions
{
    protected static ?string $model = RequestContact::class;

    protected static ?string $navigationIcon = 'phosphor-phone';

    protected static ?int $navigationSort = 3;

    private static ?array $badgeData = null;

    /**
     * Define the form schema for creating/editing request contacts
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            self::getContactInformationSection(),
            self::getRequestDetailsSection(),
            self::getStatusSection(),
            self::getTechnicalInformationSection(),
        ]);
    }

    /**
     * Define the table schema for listing request contacts
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns(self::getTableColumns())
            ->filters(self::getTableFilters())
            ->actions(self::getTableActions())
            ->bulkActions(self::getTableBulkActions())
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Define the infolist schema for viewing request contacts
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            self::getInfolistContactSection(),
            self::getInfolistRequestDetailsSection(),
            self::getInfolistContactStatusSection(),
            self::getInfolistMetadataSection(),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRequestContacts::route('/'),
            'create' => Pages\CreateRequestContact::route('/create'),
            'edit' => Pages\EditRequestContact::route('/{record}/edit'),
            'view' => Pages\ViewRequestContact::route('/{record}'),
        ];
    }

    /**
     * Get the navigation badge to display uncontacted requests count
     */
    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(RequestContact::NAVIGATION_BADGE_CACHE_KEY, [21600, 604800], function () {
            return (string) self::getBadgeData()['count'];
        }) ?: null;
    }

    /**
     * Get the navigation badge color
     */
    public static function getNavigationBadgeColor(): ?string
    {
        $count = self::getBadgeData()['count'];

        return $count > 0 ? 'warning' : null;
    }

    /**
     * Get contact information form section
     */
    private static function getContactInformationSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Contact Information')
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),

                Forms\Components\TextInput::make('company_name')
                    ->maxLength(255),

                Forms\Components\TextInput::make('gym_size')
                    ->numeric()
                    ->suffix('members'),
            ])
            ->columns(2);
    }

    /**
     * Get request details form section
     */
    private static function getRequestDetailsSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Request Details')
            ->schema([
                Forms\Components\Textarea::make('message')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Get status form section
     */
    private static function getStatusSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Status')
            ->schema([
                Forms\Components\Toggle::make('is_contacted')
                    ->label('Contacted')
                    ->inline(false),

                Forms\Components\DateTimePicker::make('contacted_at')
                    ->native(false)
                    ->closeOnDateSelection(true)
                    ->label('Contacted At'),

                Forms\Components\Textarea::make('admin_notes')
                    ->label('Admin Notes')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Get technical information form section
     */
    private static function getTechnicalInformationSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Technical Information')
            ->schema([
                Forms\Components\TextInput::make('ip_address')
                    ->disabled(),

                Forms\Components\Textarea::make('user_agent')
                    ->disabled()
                    ->rows(2),
            ])
            ->columns(1)
            ->collapsible()
            ->collapsed();
    }

    /**
     * Get table columns configuration
     *
     * @return array<Tables\Columns\Column>
     */
    private static function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('id')
                ->label('ID')
                ->sortable(),

            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('email')
                ->searchable()
                ->sortable()
                ->copyable(),

            Tables\Columns\TextColumn::make('phone')
                ->searchable()
                ->copyable(),

            Tables\Columns\TextColumn::make('company_name')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\IconColumn::make('is_contacted')
                ->label('Contacted')
                ->boolean()
                ->sortable(),

            Tables\Columns\TextColumn::make('preferred_contact_method')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'email' => 'info',
                    'phone' => 'success',
                    'whatsapp' => 'warning',
                    default => 'gray',
                }),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Submitted')
                ->dateTime()
                ->sortable()
                ->toggleable(),

            Tables\Columns\TextColumn::make('contacted_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * Get table filters configuration
     *
     * @return array<Tables\Filters\BaseFilter>
     */
    private static function getTableFilters(): array
    {
        return [
            Tables\Filters\TernaryFilter::make('is_contacted')
                ->label('Contact Status')
                ->placeholder('All requests')
                ->trueLabel('Contacted')
                ->falseLabel('Not contacted'),

            Tables\Filters\Filter::make('created_at')
                ->form([
                    Forms\Components\DatePicker::make('created_from')
                        ->label('Submitted from')
                        ->native(false)
                        ->closeOnDateSelection(true),
                    Forms\Components\DatePicker::make('created_until')
                        ->label('Submitted until')
                        ->native(false)
                        ->closeOnDateSelection(true),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate(
                                'created_at',
                                '>=',
                                $date,
                            ),
                        )
                        ->when(
                            $data['created_until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate(
                                'created_at',
                                '<=',
                                $date,
                            ),
                        );
                }),
        ];
    }

    /**
     * Get table actions configuration
     *
     * @return array<Tables\Actions\Action>
     */
    private static function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('markAsContacted')
                ->label('Mark Contacted')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn (RequestContact $record): bool => ! $record->is_contacted)
                ->form([
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Notes (optional)')
                        ->rows(3),
                ])
                ->action(function (RequestContact $record, array $data): void {
                    $record->markAsContacted($data['admin_notes'] ?? null);
                })
                ->successNotificationTitle('Marked as contacted'),

            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ];
    }

    /**
     * Get table bulk actions configuration
     *
     * @return array<Tables\Actions\BulkActionGroup>
     */
    private static function getTableBulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\BulkAction::make('markAsContacted')
                    ->label('Mark as Contacted')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->action(function (Collection $records): void {
                        /** @var Collection<int, RequestContact> $records */
                        $records->each(fn (RequestContact $record): mixed => $record->markAsContacted());
                    })
                    ->deselectRecordsAfterCompletion()
                    ->successNotificationTitle('Marked as contacted'),
            ]),
        ];
    }

    /**
     * Get contact information infolist section
     */
    private static function getInfolistContactSection(): Components\Section
    {
        return Components\Section::make('Contact Information')
            ->schema([
                Components\Split::make([
                    Components\Grid::make(2)->schema([
                        Components\Group::make([
                            Components\TextEntry::make('name')
                                ->icon('heroicon-m-user'),
                            Components\TextEntry::make('email')
                                ->icon('heroicon-m-envelope')
                                ->copyable(),
                            Components\TextEntry::make('phone')
                                ->icon('heroicon-m-phone')
                                ->copyable(),
                        ]),
                        Components\Group::make([
                            Components\TextEntry::make('company_name')
                                ->icon('heroicon-m-building-office')
                                ->placeholder('N/A'),
                            Components\TextEntry::make('gym_size')
                                ->suffix(' members')
                                ->icon('heroicon-m-users')
                                ->placeholder('N/A'),
                        ]),
                    ]),
                ])->from('lg'),
            ]);
    }

    /**
     * Get request details infolist section
     */
    private static function getInfolistRequestDetailsSection(): Components\Section
    {
        return Components\Section::make('Request Details')
            ->schema([
                Components\TextEntry::make('message')
                    ->prose()
                    ->markdown()
                    ->columnSpanFull()
                    ->placeholder('No message provided'),
            ]);
    }

    /**
     * Get contact status infolist section
     */
    private static function getInfolistContactStatusSection(): Components\Section
    {
        return Components\Section::make('Contact Status')
            ->schema([
                Components\Grid::make(2)->schema([
                    Components\TextEntry::make('is_contacted')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Contacted' : 'Not Contacted')
                        ->color(fn (bool $state): string => $state ? 'success' : 'warning'),

                    Components\TextEntry::make('contacted_at')
                        ->dateTime()
                        ->placeholder('Not contacted yet'),
                ]),

                Components\TextEntry::make('admin_notes')
                    ->prose()
                    ->markdown()
                    ->columnSpanFull()
                    ->placeholder('No notes'),
            ])
            ->collapsible();
    }

    /**
     * Get metadata infolist section
     */
    private static function getInfolistMetadataSection(): Components\Section
    {
        return Components\Section::make('Metadata')
            ->schema([
                Components\Grid::make(2)->schema([
                    Components\TextEntry::make('created_at')
                        ->label('Submitted At')
                        ->dateTime()
                        ->badge()
                        ->color('info'),

                    Components\TextEntry::make('updated_at')
                        ->dateTime()
                        ->badge()
                        ->color('gray'),
                ]),

                Components\TextEntry::make('ip_address')
                    ->copyable()
                    ->icon('heroicon-m-computer-desktop'),

                Components\TextEntry::make('user_agent')
                    ->copyable()
                    ->icon('heroicon-m-window')
                    ->columnSpanFull(),
            ])
            ->collapsible()
            ->collapsed();
    }

    /**
     * Get cached badge data for navigation
     *
     * @return array{count: int}
     */
    private static function getBadgeData(): array
    {
        if (self::$badgeData === null) {
            /** @var class-string<RequestContact> $modelClass */
            $modelClass = static::getModel();
            $count = $modelClass::where('is_contacted', false)->count();

            self::$badgeData = [
                'count' => (int) $count,
            ];
        }

        return self::$badgeData;
    }
}
