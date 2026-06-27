<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\ExpenseResource\Pages\ListExpenses;
use App\Filament\Admin\Resources\ExpenseResource\Pages\CreateExpense;
use App\Filament\Admin\Resources\ExpenseResource\Pages\ViewExpense;
use App\Filament\Admin\Resources\ExpenseResource\Pages\EditExpense;
use App\Models\Gym;
use App\Models\User;
use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
use App\Enums\RecurringFrequency;
use App\Filament\BaseResources\BaseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class ExpenseResource extends BaseResource
{
    protected static ?string $model = Expense::class;

    // protected static ?string $navigationIcon = 'phosphor-money';

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?string $navigationGroup = 'Expense Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => [
                'view',
                'view_any',
                'reorder',
            ],

            DefaultRole::RECEPTIONIST->value => [],
            DefaultRole::TRAINER->value => [],
        ];
    }

    public static function form(Form $form): Form
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        /** @var User $user */
        $user = auth()->user();

        return $form
            ->schema([
                Section::make('Expense Details')
                    ->icon('phosphor-money')
                    ->description('Record expense information')
                    ->schema([
                        Hidden::make('gym_id')
                            ->default($gym->id),

                        Hidden::make('user_id')
                            ->default($user->id),

                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Electricity Bill January')
                            ->autofocus()
                            ->columnSpanFull(),

                        Select::make('expense_category_id')
                            ->label('Category')
                            ->options(fn () => ExpenseCategory::query()
                                ->availableForGym($gym->id)
                                ->active()
                                ->orderByDesc('is_system')
                                ->orderBy('order')
                                ->get()
                                ->mapWithKeys(fn ($cat) => [
                                    $cat->id => $cat->is_system
                                        ? "★ {$cat->name}"
                                        : $cat->name,
                                ]))
                            ->searchable()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Hidden::make('gym_id')
                                    ->default($gym->id),
                                Hidden::make('is_system')
                                    ->default(false),
                            ])
                            ->createOptionUsing(function (array $data) use ($gym) {
                                return ExpenseCategory::query()->create([
                                    'gym_id' => $gym->id,
                                    'name' => $data['name'],
                                    'is_active' => true,
                                    'is_system' => false,
                                ])->id;
                            }),

                        DatePicker::make('expense_date')
                            ->label('Date')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->native(false),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->prefix(fn ($get) => Currency::tryFrom($get('currency') ?? 'INR')?->getSymbol() ?? '₹')
                                    ->placeholder('0.00'),

                                Select::make('currency')
                                    ->options(Currency::toSelectOptions())
                                    ->default('INR')
                                    ->required()
                                    ->reactive(),

                                Select::make('payment_method')
                                    ->options(ExpensePaymentMethod::toSelectOptions())
                                    ->default(ExpensePaymentMethod::CASH->value)
                                    ->required(),
                            ]),

                        Textarea::make('description')
                            ->maxLength(1000)
                            ->placeholder('Additional details about this expense...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Vendor Information')
                    ->icon('phosphor-storefront')
                    ->description('Optional vendor/supplier details')
                    ->schema([
                        TextInput::make('vendor_name')
                            ->label('Vendor Name')
                            ->maxLength(255)
                            ->placeholder('e.g., ABC Suppliers'),

                        TextInput::make('vendor_contact')
                            ->label('Contact')
                            ->maxLength(255)
                            ->placeholder('Phone or email'),

                        TextInput::make('reference_number')
                            ->label('Invoice/Receipt Number')
                            ->maxLength(255)
                            ->placeholder('e.g., INV-2026-001'),
                    ])
                    ->columns(3)
                    ->collapsed(),

                Section::make('Attachments')
                    ->icon('phosphor-paperclip')
                    ->description('Upload receipts or invoices')
                    ->schema([
                        FileUpload::make('attachments')
                            ->label('Receipt/Invoice')
                            ->multiple()
                            ->maxFiles(5)
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxSize(5120) // 5MB
                            ->directory('expenses/attachments')
                            ->visibility('private')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Section::make('Recurring Settings')
                    ->icon('phosphor-repeat')
                    ->description('Set up recurring expense')
                    ->schema([
                        Toggle::make('is_recurring')
                            ->label('This is a recurring expense')
                            ->reactive()
                            ->default(false),

                        Select::make('recurring_frequency')
                            ->label('Frequency')
                            ->options(RecurringFrequency::toSelectOptions())
                            ->visible(fn ($get) => $get('is_recurring'))
                            ->required(fn ($get) => $get('is_recurring')),

                        DatePicker::make('next_due_date')
                            ->label('Next Due Date')
                            ->visible(fn ($get) => $get('is_recurring'))
                            ->minDate(now()->addDay())
                            ->native(false),
                    ])
                    ->columns(3)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->columns([
                TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(40)
                    ->description(fn ($record) => $record->vendor_name),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color ?? 'gray')
                    ->searchable(),

                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency ?? 'INR')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Submitted By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_recurring')
                    ->label('Recurring')
                    ->boolean()
                    ->trueIcon('phosphor-repeat')
                    ->falseIcon('')
                    ->alignCenter(),

                IconColumn::make('attachments')
                    ->label('')
                    ->icon('phosphor-paperclip')
                    ->visible(fn ($record) => filled($record->attachments))
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ExpenseStatus::toSelectOptions())
                    ->multiple(),

                SelectFilter::make('expense_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload()
                    ->multiple(),

                SelectFilter::make('payment_method')
                    ->options(ExpensePaymentMethod::toSelectOptions()),

                DateRangeFilter::make('expense_date')
                    ->label('Date Range'),

                TernaryFilter::make('is_recurring')
                    ->label('Recurring')
                    ->placeholder('All')
                    ->trueLabel('Recurring only')
                    ->falseLabel('One-time only'),

                SelectFilter::make('user_id')
                    ->label('Submitted By')
                    ->relationship('user', 'name')
                    ->visible(fn () => $user->hasAnyRole([
                        DefaultRole::GYM_OWNER->value,
                        DefaultRole::MANAGER->value,
                    ])),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn ($record) => $record->canEdit()),

                    // Approval Actions
                    Action::make('submit')
                        ->label('Submit for Approval')
                        ->icon('phosphor-paper-plane-tilt')
                        ->color('info')
                        ->visible(fn ($record) => $record->canSubmit())
                        ->requiresConfirmation()
                        ->modalHeading('Submit for Approval')
                        ->modalDescription('Are you sure you want to submit this expense for approval?')
                        ->action(fn ($record) => $record->submit())
                        ->successNotificationTitle('Expense submitted for approval'),

                    Action::make('approve')
                        ->label('Approve')
                        ->icon('phosphor-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->canApprove() && $user->can('approve', $record))
                        ->requiresConfirmation()
                        ->modalHeading('Approve Expense')
                        ->modalDescription(fn ($record) => "Approve expense: {$record->title} ({$record->formatted_amount})?")
                        ->action(fn ($record) => $record->approve($user))
                        ->successNotificationTitle('Expense approved'),

                    Action::make('reject')
                        ->label('Reject')
                        ->icon('phosphor-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->canApprove() && $user->can('reject', $record))
                        ->form([
                            Textarea::make('rejection_reason')
                                ->label('Reason for Rejection')
                                ->required()
                                ->maxLength(500)
                                ->rows(3),
                        ])
                        ->action(fn ($record, array $data) => $record->reject($user, $data['rejection_reason']))
                        ->successNotificationTitle('Expense rejected'),

                    Action::make('mark_paid')
                        ->label('Mark as Paid')
                        ->icon('phosphor-coins')
                        ->color('info')
                        ->visible(fn ($record) => $record->isApproved())
                        ->requiresConfirmation()
                        ->action(fn ($record) => $record->markAsPaid())
                        ->successNotificationTitle('Expense marked as paid'),

                    DeleteAction::make()
                        ->visible(fn ($record) => $record->isDraft()),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('phosphor-check-circle')
                        ->color('success')
                        ->visible(fn () => $user->can('approve_expense'))
                        ->requiresConfirmation()
                        ->action(function ($records) use ($user) {
                            $records->each(fn ($record) => $record->canApprove() && $record->approve($user));
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()
                        ->visible(fn () => $user->hasRole(DefaultRole::GYM_OWNER->value)),
                ]),
            ])
            ->defaultSort('expense_date', 'desc')
            ->striped();
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
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'view' => ViewExpense::route('/{record}'),
            'edit' => EditExpense::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        /** @var User $user */
        $user = auth()->user();

        $query = parent::getEloquentQuery()->where('gym_id', $gym?->id);

        // Non-managers/owners can only see their own expenses
        if (! $user->hasAnyRole([DefaultRole::GYM_OWNER->value, DefaultRole::MANAGER->value])) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var Gym $gym */
        $gym = Filament::getTenant();

        return Cache::flexible(Expense::NAVIGATION_BADGE_CACHE_KEY . $gym?->id, [3600, 86400], function () use ($gym) {
            return (string) static::getModel()::where('gym_id', $gym?->id)
                ->where('status', ExpenseStatus::PENDING)
                ->count();
        }) ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'description', 'vendor_name', 'reference_number'];
    }
}
