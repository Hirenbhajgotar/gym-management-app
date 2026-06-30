<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Resources\RequestContactResource\Pages\CreateRequestContact;
use App\Filament\SuperAdmin\Resources\RequestContactResource\Pages\EditRequestContact;
use App\Filament\SuperAdmin\Resources\RequestContactResource\Pages\ListRequestContacts;
use App\Filament\SuperAdmin\Resources\RequestContactResource\Pages\ViewRequestContact;
use App\Models\RequestContact;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class RequestContactResource extends BaseResource
{
    protected static ?string $model = RequestContact::class;

    protected static ?string $navigationIcon = 'phosphor-phone';

    protected static ?int $navigationSort = 3;

    private static ?array $badgeData = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

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
            'index' => ListRequestContacts::route('/'),
            'create' => CreateRequestContact::route('/create'),
            'edit' => EditRequestContact::route('/{record}/edit'),
            'view' => ViewRequestContact::route('/{record}'),
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
    private static function getContactInformationSection(): Section
    {
        return Section::make('Contact Information')
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),

                TextInput::make('company_name')
                    ->maxLength(255),

                TextInput::make('gym_size')
                    ->numeric()
                    ->suffix('members'),
            ])
            ->columns(2);
    }

    /**
     * Get request details form section
     */
    private static function getRequestDetailsSection(): Section
    {
        return Section::make('Request Details')
            ->schema([
                Textarea::make('message')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Get status form section
     */
    private static function getStatusSection(): Section
    {
        return Section::make('Status')
            ->schema([
                Toggle::make('is_contacted')
                    ->label('Contacted')
                    ->inline(false),

                DateTimePicker::make('contacted_at')
                    ->native(false)
                    ->closeOnDateSelection(true)
                    ->label('Contacted At'),

                Textarea::make('admin_notes')
                    ->label('Admin Notes')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Get technical information form section
     */
    private static function getTechnicalInformationSection(): Section
    {
        return Section::make('Technical Information')
            ->schema([
                TextInput::make('ip_address')
                    ->disabled(),

                Textarea::make('user_agent')
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
            TextColumn::make('id')
                ->label('ID')
                ->sortable(),

            TextColumn::make('name')
                ->searchable()
                ->sortable(),

            TextColumn::make('email')
                ->searchable()
                ->sortable()
                ->copyable(),

            TextColumn::make('phone')
                ->searchable()
                ->copyable(),

            TextColumn::make('company_name')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            IconColumn::make('is_contacted')
                ->label('Contacted')
                ->boolean()
                ->sortable(),

            TextColumn::make('preferred_contact_method')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'email' => 'info',
                    'phone' => 'success',
                    'whatsapp' => 'warning',
                    default => 'gray',
                }),

            TextColumn::make('created_at')
                ->label('Submitted')
                ->dateTime()
                ->sortable()
                ->toggleable(),

            TextColumn::make('contacted_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * Get table filters configuration
     *
     * @return array<BaseFilter>
     */
    private static function getTableFilters(): array
    {
        return [
            TernaryFilter::make('is_contacted')
                ->label('Contact Status')
                ->placeholder('All requests')
                ->trueLabel('Contacted')
                ->falseLabel('Not contacted'),

            Filter::make('created_at')
                ->form([
                    DatePicker::make('created_from')
                        ->label('Submitted from')
                        ->native(false)
                        ->closeOnDateSelection(true),
                    DatePicker::make('created_until')
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
            Action::make('markAsContacted')
                ->label('Mark Contacted')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn (RequestContact $record): bool => ! $record->is_contacted)
                ->form([
                    Textarea::make('admin_notes')
                        ->label('Notes (optional)')
                        ->rows(3),
                ])
                ->action(function (RequestContact $record, array $data): void {
                    $record->markAsContacted($data['admin_notes'] ?? null);
                })
                ->successNotificationTitle('Marked as contacted'),

            ViewAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Get table bulk actions configuration
     *
     * @return array<BulkActionGroup>
     */
    private static function getTableBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                BulkAction::make('markAsContacted')
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
                Split::make([
                    Grid::make(2)->schema([
                        Group::make([
                            TextEntry::make('name')
                                ->icon('heroicon-m-user'),
                            TextEntry::make('email')
                                ->icon('heroicon-m-envelope')
                                ->copyable(),
                            TextEntry::make('phone')
                                ->icon('heroicon-m-phone')
                                ->copyable(),
                        ]),
                        Group::make([
                            TextEntry::make('company_name')
                                ->icon('heroicon-m-building-office')
                                ->placeholder('N/A'),
                            TextEntry::make('gym_size')
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
                TextEntry::make('message')
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
                Grid::make(2)->schema([
                    TextEntry::make('is_contacted')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Contacted' : 'Not Contacted')
                        ->color(fn (bool $state): string => $state ? 'success' : 'warning'),

                    TextEntry::make('contacted_at')
                        ->dateTime()
                        ->placeholder('Not contacted yet'),
                ]),

                TextEntry::make('admin_notes')
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
                Grid::make(2)->schema([
                    TextEntry::make('created_at')
                        ->label('Submitted At')
                        ->dateTime()
                        ->badge()
                        ->color('info'),

                    TextEntry::make('updated_at')
                        ->dateTime()
                        ->badge()
                        ->color('gray'),
                ]),

                TextEntry::make('ip_address')
                    ->copyable()
                    ->icon('heroicon-m-computer-desktop'),

                TextEntry::make('user_agent')
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
            $count = $modelClass::query()->where('is_contacted', false)->count();

            self::$badgeData = [
                'count' => (int) $count,
            ];
        }

        return self::$badgeData;
    }
}
