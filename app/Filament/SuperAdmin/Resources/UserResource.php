<?php

namespace App\Filament\SuperAdmin\Resources;

use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use App\Forms\Components\AddressForm;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Toggle;
use App\Enums\PaymentStatus;
use App\Services\InvoiceService;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Collection;
use App\Filament\SuperAdmin\Resources\UserResource\RelationManagers\SubscriptionRelationManager;
use App\Enums\DefaultRole;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\CreateUser;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\EditUser;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\ListUsers;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Rules\ValidEmailDomain;
use App\Services\SubscriptionService;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use libphonenumber\PhoneNumberType;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    // Restrict to admins or gym owners with gyms
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    public static function getMiddleware(): array
    {
        return [PermissionMiddleware::using('manage users')];
    }

    public static function getNavigationBadge(): ?string
    {
        return cache()->flexible(User::NAVIGATION_BADGE_CACHE_KEY, [21600, 604800], function () {
            return (string) static::getModel()::query()
                ->whereExists(function ($query) {
                    $query
                        ->select(DB::raw(1))
                        ->from('model_has_roles')
                        ->join(
                            'roles',
                            'roles.id',
                            '=',
                            'model_has_roles.role_id',
                        )
                        ->whereColumn(
                            'users.id',
                            '=',
                            'model_has_roles.model_id',
                        )
                        ->where(
                            'model_has_roles.model_type',
                            '=',
                            'App\\Models\\User',
                        )
                        ->where('roles.name', '=', 'gym_owner');
                })
                ->count();
        }) ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('User Creation')
                ->tabs([
                    Tab::make('User Information')
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->email()
                                ->rule(new ValidEmailDomain)
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            PhoneInput::make('phone')
                                ->defaultCountry('IN')
                                ->initialCountry('IN')
                                ->label('Phone Number')
                                ->required()
                                ->validateFor(
                                    country: ['IN', 'PK', 'BD', 'US'],
                                    type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE],
                                    lenient: true,
                                ),
                            Select::make('role')
                                ->label('Role')
                                ->options([
                                    DefaultRole::GYM_OWNER->value => DefaultRole::GYM_OWNER->getLabel(),
                                ])
                                ->default(DefaultRole::GYM_OWNER->value)
                                ->required()
                                ->visible(fn (string $context): bool => $context === 'create')
                                ->helperText('Currently only Gym Owner role can be created from SuperAdmin panel'),
                            DateTimePicker::make('email_verified_at')
                                ->native(false)
                                ->closeOnDateSelection(true)
                                ->default(now()),
                            TextInput::make('password')
                                ->password()
                                ->revealable()
                                ->maxLength(255)
                                ->dehydrated(fn ($state) => filled($state))
                                ->required(false)
                                ->helperText(fn (string $context): string => $context === 'create'
                                    ? 'Leave empty to auto-generate and send a password setup link'
                                    : 'Leave empty to keep current password'),

                            ToggleButtons::make('status')
                                ->label('Status')
                                ->options(UserStatus::class)
                                ->inline()
                                ->required(),

                            Section::make('Plan Assignment')->schema([
                                Grid::make(2)->schema([
                                    Select::make('activeSubscription.plan_id')
                                        ->label('Assign Plan')
                                        ->options(Plan::active()->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a plan to assign')
                                        ->reactive(),
                                    Select::make('activeSubscription.billing_cycle')
                                        ->label('Billing Cycle')
                                        ->options([
                                            'monthly' => 'Monthly',
                                            'yearly' => 'Yearly',
                                        ])
                                        ->default('monthly')
                                        ->required(fn (callable $get): bool => filled($get('activeSubscription.plan_id')))
                                        ->visible(fn (callable $get): bool => filled($get('activeSubscription.plan_id'))),
                                ]),
                                Grid::make(2)->schema([
                                    DateTimePicker::make('activeSubscription.trial_ends_at')
                                        ->label('Trial End Date')
                                        ->native(false)
                                        ->closeOnDateSelection(true)
                                        ->placeholder('Optional trial end date')
                                        ->visible(fn (callable $get): bool => filled($get('activeSubscription.plan_id'))),
                                    Select::make('activeSubscription.status')
                                        ->label('Subscription Status')
                                        ->options(SubscriptionStatus::class)
                                        ->default(SubscriptionStatus::ACTIVE)
                                        ->required(fn (callable $get): bool => filled($get('activeSubscription.plan_id')))
                                        ->visible(fn (callable $get): bool => filled($get('activeSubscription.plan_id'))),
                                ]),
                                RichEditor::make('activeSubscription.notes')
                                    ->label('Assignment Notes')
                                    ->placeholder('Notes about this plan assignment (e.g., offline purchase, manual assignment, etc.)')
                                    ->visible(fn (callable $get): bool => filled($get('activeSubscription.plan_id'))),
                            ])->visible(fn (string $context): bool => $context === 'edit'),
                        ])->columns(2),

                    Tab::make('Gym Information')
                        ->visible(fn (string $context): bool => $context === 'create')
                        ->schema([
                            TextInput::make('gym_name')
                                ->label('Gym Name')
                                ->required()
                                ->maxLength(255),
                            AddressForm::make('address')
                                ->dehydrated(true),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->whereExists(function ($query) {
                        $query
                            ->select(DB::raw(1))
                            ->from('model_has_roles')
                            ->join(
                                'roles',
                                'roles.id',
                                '=',
                                'model_has_roles.role_id',
                            )
                            ->whereColumn(
                                'users.id',
                                '=',
                                'model_has_roles.model_id',
                            )
                            ->where(
                                'model_has_roles.model_type',
                                '=',
                                'App\\Models\\User',
                            )
                            ->where('roles.name', '=', 'gym_owner');
                    })
                    ->with(['gyms', 'activeSubscription.plan'])
                    ->latest(),
            )
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('gyms.name')
                    ->label('Gyms')
                    ->badge()
                    ->separator(','),
                TextColumn::make('activeSubscription.plan.name')
                    ->label('Current Plan')
                    ->badge()
                    ->color(fn ($record): string => $record->activeSubscription ? 'primary' : 'gray')
                    ->placeholder('No plan assigned'),
                TextColumn::make('activeSubscription.status')
                    ->label('Subscription Status')
                    ->badge()
                    ->color(fn ($record): string => $record->activeSubscription?->status->getColor() ?? 'gray'),
                TextColumn::make('activeSubscription.current_period_end')
                    ->label('Expires')
                    ->dateTime()
                    ->formatStateUsing(fn ($state): ?string => $state?->format('M j, Y') ?? null)
                    ->placeholder('N/A'),

                SelectColumn::make('status')
                    ->options(UserStatus::class),

                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plan')
                    ->relationship('activeSubscription.plan', 'name')
                    ->label('Filter by Plan'),

                SelectFilter::make('subscription_status')
                    ->options(SubscriptionStatus::class)
                    ->label('Filter by Status'),
            ])
            ->actions([
                EditAction::make(),

                Action::make('assign_plan')
                    ->label('Assign Plan')
                    ->icon('heroicon-o-credit-card')
                    ->form([
                        Section::make()
                            ->schema([
                                Select::make('plan_id')
                                    ->label('Plan')
                                    ->options(Plan::active()->pluck('name', 'id'))
                                    ->default(fn ($record) => $record?->activeSubscription?->plan_id)
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('billing_cycle')
                                    ->label('Billing Cycle')
                                    ->options([
                                        'monthly' => 'Monthly',
                                        'yearly' => 'Yearly',
                                    ])
                                    ->default(fn ($record) => $record?->activeSubscription?->billing_cycle ?? 'monthly')
                                    ->required(),

                                Select::make('status')
                                    ->label('Status')
                                    ->options(SubscriptionStatus::class)
                                    ->default(fn ($record) => $record?->activeSubscription?->status ?? SubscriptionStatus::ACTIVE)
                                    ->required(),

                                DateTimePicker::make('trial_ends_at')
                                    ->label('Trial End Date')
                                    ->native(false)
                                    ->closeOnDateSelection(true)
                                    ->helperText('Optional - for trial subscriptions'),

                                RichEditor::make('notes')
                                    ->label('Notes')
                                    ->placeholder('Add notes about this assignment...')
                                    ->columnSpanFull(),

                                Toggle::make('generate_invoice')
                                    ->label('Generate Invoice')
                                    ->default(true)
                                    ->columnSpanFull(),
                            ])->columns(2),

                    ])
                    ->action(function (User $record, array $data): void {
                        $subscriptionService = resolve(SubscriptionService::class);

                        DB::transaction(function () use ($record, $data, $subscriptionService) {
                            $plan = Plan::query()->findOrFail($data['plan_id']);
                            $subscriptionService->assignPlanToUser($record, $plan, [
                                'billing_cycle' => $data['billing_cycle'],
                                'status' => $data['status'], // Pass the selected status
                                'trial_ends_at' => $data['trial_ends_at'] ?? null,
                                'assigned_by' => auth()->id(),
                                'notes' => $data['notes'] ?? null,
                            ]);

                            if (filled($data['generate_invoice'])) {
                                $amount = $data['billing_cycle'] === 'monthly' ? $plan->price_monthly : $plan->price_yearly;

                                $payment = Payment::query()->create([
                                    'user_id' => $record->id,
                                    'amount' => $amount,
                                    'currency' => $plan->currency,
                                    'status' => PaymentStatus::COMPLETED,
                                    'payment_type' => 'manual_assignment',
                                    'razorpay_order_id' => 'MAN-ORD-' . Str::ulid(),
                                    'razorpay_payment_id' => 'MAN-PAY-' . Str::ulid(),
                                    'metadata' => [
                                        'plan_id' => $plan->id,
                                        'plan_name' => $plan->name,
                                        'billing_cycle' => $data['billing_cycle'],
                                        'assigned_by' => auth()->id(),
                                    ],
                                    'paid_at' => now(),
                                ]);

                                resolve(InvoiceService::class)->generateInvoice($payment);
                            }
                        });
                    })
                    ->successNotificationTitle('Plan assigned successfully')
                    ->color('primary'),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('bulk_assign_plan')
                        ->label('Bulk Assign Plan')
                        ->icon('heroicon-o-credit-card')
                        ->form([
                            Select::make('plan_id')
                                ->label('Plan')
                                ->options(Plan::active()->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('billing_cycle')
                                ->label('Billing Cycle')
                                ->options([
                                    'monthly' => 'Monthly',
                                    'yearly' => 'Yearly',
                                ])
                                ->default('monthly')
                                ->required(),
                            Select::make('status')
                                ->label('Status')
                                ->options(SubscriptionStatus::class)
                                ->default(SubscriptionStatus::ACTIVE)
                                ->required(),
                            DateTimePicker::make('trial_ends_at')
                                ->label('Trial End Date')
                                ->native(false)
                                ->closeOnDateSelection(true)
                                ->helperText('Optional - for trial subscriptions'),
                            Textarea::make('notes')
                                ->label('Notes')
                                ->placeholder('Add notes about this assignment...')
                                ->rows(3),
                        ])
                        ->action(function (array $data, Collection $records): void {
                            $subscriptionService = resolve(SubscriptionService::class);
                            $plan = Plan::query()->findOrFail($data['plan_id']);

                            $records->each(function (User $record) use ($data, $subscriptionService, $plan) {
                                $subscriptionService->assignPlanToUser($record, $plan, [
                                    'billing_cycle' => $data['billing_cycle'],
                                    'status' => $data['status'],
                                    'trial_ends_at' => $data['trial_ends_at'] ?? null,
                                    'assigned_by' => auth()->id(),
                                    'notes' => $data['notes'] ?? null,
                                ]);
                            });
                        })
                        ->successNotificationTitle('Plans assigned successfully')
                        ->color('primary'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SubscriptionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
