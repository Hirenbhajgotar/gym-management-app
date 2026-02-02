<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
use App\Enums\RecurringFrequency;
use App\Filament\Admin\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'phosphor-money';

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?string $navigationGroup = 'Expense Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $form
            ->schema([
                Forms\Components\Section::make('Expense Details')
                    ->icon('phosphor-money')
                    ->description('Record expense information')
                    ->schema([
                        Forms\Components\Hidden::make('gym_id')
                            ->default($gym->id),

                        Forms\Components\Hidden::make('user_id')
                            ->default($user->id),

                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Electricity Bill January')
                            ->autofocus()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('expense_category_id')
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
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Hidden::make('gym_id')
                                    ->default($gym->id),
                                Forms\Components\Hidden::make('is_system')
                                    ->default(false),
                            ])
                            ->createOptionUsing(function (array $data) use ($gym) {
                                return ExpenseCategory::create([
                                    'gym_id' => $gym->id,
                                    'name' => $data['name'],
                                    'is_active' => true,
                                    'is_system' => false,
                                ])->id;
                            }),

                        Forms\Components\DatePicker::make('expense_date')
                            ->label('Date')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->native(false),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->prefix(fn ($get) => Currency::tryFrom($get('currency') ?? 'INR')?->getSymbol() ?? '₹')
                                    ->placeholder('0.00'),

                                Forms\Components\Select::make('currency')
                                    ->options(Currency::toSelectOptions())
                                    ->default('INR')
                                    ->required()
                                    ->reactive(),

                                Forms\Components\Select::make('payment_method')
                                    ->options(ExpensePaymentMethod::toSelectOptions())
                                    ->default(ExpensePaymentMethod::CASH->value)
                                    ->required(),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->placeholder('Additional details about this expense...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Vendor Information')
                    ->icon('phosphor-storefront')
                    ->description('Optional vendor/supplier details')
                    ->schema([
                        Forms\Components\TextInput::make('vendor_name')
                            ->label('Vendor Name')
                            ->maxLength(255)
                            ->placeholder('e.g., ABC Suppliers'),

                        Forms\Components\TextInput::make('vendor_contact')
                            ->label('Contact')
                            ->maxLength(255)
                            ->placeholder('Phone or email'),

                        Forms\Components\TextInput::make('reference_number')
                            ->label('Invoice/Receipt Number')
                            ->maxLength(255)
                            ->placeholder('e.g., INV-2026-001'),
                    ])
                    ->columns(3)
                    ->collapsed(),

                Forms\Components\Section::make('Attachments')
                    ->icon('phosphor-paperclip')
                    ->description('Upload receipts or invoices')
                    ->schema([
                        Forms\Components\FileUpload::make('attachments')
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

                Forms\Components\Section::make('Recurring Settings')
                    ->icon('phosphor-repeat')
                    ->description('Set up recurring expense')
                    ->schema([
                        Forms\Components\Toggle::make('is_recurring')
                            ->label('This is a recurring expense')
                            ->reactive()
                            ->default(false),

                        Forms\Components\Select::make('recurring_frequency')
                            ->label('Frequency')
                            ->options(RecurringFrequency::toSelectOptions())
                            ->visible(fn ($get) => $get('is_recurring'))
                            ->required(fn ($get) => $get('is_recurring')),

                        Forms\Components\DatePicker::make('next_due_date')
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
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(40)
                    ->description(fn ($record) => $record->vendor_name),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color ?? 'gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency ?? 'INR')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Submitted By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_recurring')
                    ->label('Recurring')
                    ->boolean()
                    ->trueIcon('phosphor-repeat')
                    ->falseIcon('')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('attachments')
                    ->label('')
                    ->icon('phosphor-paperclip')
                    ->visible(fn ($record) => ! empty($record->attachments))
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ExpenseStatus::toSelectOptions())
                    ->multiple(),

                Tables\Filters\SelectFilter::make('expense_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->options(ExpensePaymentMethod::toSelectOptions()),

                DateRangeFilter::make('expense_date')
                    ->label('Date Range'),

                Tables\Filters\TernaryFilter::make('is_recurring')
                    ->label('Recurring')
                    ->placeholder('All')
                    ->trueLabel('Recurring only')
                    ->falseLabel('One-time only'),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Submitted By')
                    ->relationship('user', 'name')
                    ->visible(fn () => $user->hasAnyRole([
                        DefaultRole::GYM_OWNER->value,
                        DefaultRole::MANAGER->value,
                    ])),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn ($record) => $record->canEdit()),

                    // Approval Actions
                    Tables\Actions\Action::make('submit')
                        ->label('Submit for Approval')
                        ->icon('phosphor-paper-plane-tilt')
                        ->color('info')
                        ->visible(fn ($record) => $record->canSubmit())
                        ->requiresConfirmation()
                        ->modalHeading('Submit for Approval')
                        ->modalDescription('Are you sure you want to submit this expense for approval?')
                        ->action(fn ($record) => $record->submit())
                        ->successNotificationTitle('Expense submitted for approval'),

                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('phosphor-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->canApprove() && $user->can('approve', $record))
                        ->requiresConfirmation()
                        ->modalHeading('Approve Expense')
                        ->modalDescription(fn ($record) => "Approve expense: {$record->title} ({$record->formatted_amount})?")
                        ->action(fn ($record) => $record->approve($user))
                        ->successNotificationTitle('Expense approved'),

                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('phosphor-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->canApprove() && $user->can('reject', $record))
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Reason for Rejection')
                                ->required()
                                ->maxLength(500)
                                ->rows(3),
                        ])
                        ->action(fn ($record, array $data) => $record->reject($user, $data['rejection_reason']))
                        ->successNotificationTitle('Expense rejected'),

                    Tables\Actions\Action::make('mark_paid')
                        ->label('Mark as Paid')
                        ->icon('phosphor-coins')
                        ->color('info')
                        ->visible(fn ($record) => $record->isApproved())
                        ->requiresConfirmation()
                        ->action(fn ($record) => $record->markAsPaid())
                        ->successNotificationTitle('Expense marked as paid'),

                    Tables\Actions\DeleteAction::make()
                        ->visible(fn ($record) => $record->isDraft()),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('phosphor-check-circle')
                        ->color('success')
                        ->visible(fn () => $user->can('approve_expense'))
                        ->requiresConfirmation()
                        ->action(function ($records) use ($user) {
                            $records->each(fn ($record) => $record->canApprove() && $record->approve($user));
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make()
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
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        /** @var \App\Models\User $user */
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
        /** @var \App\Models\Gym $gym */
        $gym = Filament::getTenant();

        return Cache::flexible(Expense::NAVIGATION_BADGE_CACHE_KEY . $gym?->id, [now()->addHour(), now()->addDay()], function () use ($gym) {
            return (string) static::getModel()::where('gym_id', $gym?->id)
                ->where('status', ExpenseStatus::PENDING)
                ->count() ?: null;
        });
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
