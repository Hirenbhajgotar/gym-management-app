<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\SubscriptionStatus;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\CreateUser;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\EditUser;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\ListUsers;
use App\Filament\SuperAdmin\Resources\UserResource\RelationManagers;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\SubscriptionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    // Restrict to admins or gym owners with gyms
    // public static function canAccess(array $parameters = []): bool
    // {
    //     $user = auth()->user();

    //     return $user->hasRole('super_admin');
    // }

    public static function getMiddleware(): array
    {
        return [PermissionMiddleware::using('manage users')];
    }

    public static function getNavigationBadge(): ?string
    {
        return cache()->flexible('users_navigation_badge_count_' . auth()->id(), [20, 30],
            fn () => static::getModel()::query()
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
                ->count(),
        );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('User Creation')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('User Information')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            Forms\Components\Select::make('role')
                                ->label('Role')
                                ->options([
                                    \App\Enums\DefaultRole::GYM_OWNER->value => \App\Enums\DefaultRole::GYM_OWNER->getLabel(),
                                ])
                                ->default(\App\Enums\DefaultRole::GYM_OWNER->value)
                                ->required()
                                ->visible(fn (string $context): bool => $context === 'create')
                                ->helperText('Currently only Gym Owner role can be created from SuperAdmin panel'),
                            Forms\Components\DateTimePicker::make('email_verified_at')
                                ->native(false)
                                ->closeOnDateSelection(true)
                                ->default(now()),
                            Forms\Components\TextInput::make('password')
                                ->password()
                                ->revealable()
                                ->maxLength(255)
                                ->dehydrated(fn ($state) => filled($state))
                                ->required(fn (string $context): bool => $context === 'create')
                                ->helperText(fn (string $context): string => $context === 'create'
                                    ? 'User will receive this password in their welcome email'
                                    : 'Leave empty to keep current password'),
                            Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->columnSpanFull(),

                            Forms\Components\Section::make('Plan Assignment')->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('activeSubscription.plan_id')
                                        ->label('Assign Plan')
                                        ->options(Plan::active()->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a plan to assign')
                                        ->reactive(),
                                    Forms\Components\Select::make('activeSubscription.billing_cycle')
                                        ->label('Billing Cycle')
                                        ->options([
                                            'monthly' => 'Monthly',
                                            'yearly' => 'Yearly',
                                        ])
                                        ->default('monthly')
                                        ->required(fn (callable $get): bool => ! empty($get('activeSubscription.plan_id')))
                                        ->visible(fn (callable $get): bool => ! empty($get('activeSubscription.plan_id'))),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\DateTimePicker::make('activeSubscription.trial_ends_at')
                                        ->label('Trial End Date')
                                        ->native(false)
                                        ->closeOnDateSelection(true)
                                        ->placeholder('Optional trial end date')
                                        ->visible(fn (callable $get): bool => ! empty($get('activeSubscription.plan_id'))),
                                    Forms\Components\Select::make('activeSubscription.status')
                                        ->label('Subscription Status')
                                        ->options(SubscriptionStatus::class)
                                        ->default(SubscriptionStatus::ACTIVE)
                                        ->required(fn (callable $get): bool => ! empty($get('activeSubscription.plan_id')))
                                        ->visible(fn (callable $get): bool => ! empty($get('activeSubscription.plan_id'))),
                                ]),
                                Forms\Components\RichEditor::make('activeSubscription.notes')
                                    ->label('Assignment Notes')
                                    ->placeholder('Notes about this plan assignment (e.g., offline purchase, manual assignment, etc.)')
                                    ->visible(fn (callable $get): bool => ! empty($get('activeSubscription.plan_id'))),
                            ])->visible(fn (string $context): bool => $context === 'edit'),
                        ])->columns(2),

                    Forms\Components\Tabs\Tab::make('Gym Information')
                        ->visible(fn (string $context): bool => $context === 'create')
                        ->schema([
                            Forms\Components\TextInput::make('gym_name')
                                ->label('Gym Name')
                                ->required()
                                ->maxLength(255),
                            \App\Forms\Components\AddressForm::make('address')
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
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('gyms.name')
                    ->label('Gyms')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('activeSubscription.plan.name')
                    ->label('Current Plan')
                    ->badge()
                    ->color(fn ($record): string => $record->activeSubscription ? 'primary' : 'gray')
                    ->placeholder('No plan assigned'),
                Tables\Columns\TextColumn::make('activeSubscription.status')
                    ->label('Subscription Status')
                    ->badge()
                    ->color(fn ($record): string => $record->activeSubscription?->status->getColor() ?? 'gray'),
                Tables\Columns\TextColumn::make('activeSubscription.current_period_end')
                    ->label('Expires')
                    ->dateTime()
                    ->formatStateUsing(fn ($state): ?string => $state?->format('M j, Y') ?? null)
                    ->placeholder('N/A'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Active'),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan')
                    ->relationship('activeSubscription.plan', 'name')
                    ->label('Filter by Plan'),

                Tables\Filters\SelectFilter::make('subscription_status')
                    ->options(SubscriptionStatus::class)
                    ->label('Filter by Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('assign_plan')
                    ->label('Assign Plan')
                    ->icon('heroicon-o-credit-card')
                    ->form([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Select::make('plan_id')
                                    ->label('Plan')
                                    ->options(Plan::active()->pluck('name', 'id'))
                                    ->default(fn ($record) => $record?->activeSubscription?->plan_id)
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('billing_cycle')
                                    ->label('Billing Cycle')
                                    ->options([
                                        'monthly' => 'Monthly',
                                        'yearly' => 'Yearly',
                                    ])
                                    ->default(fn ($record) => $record?->activeSubscription?->billing_cycle ?? 'monthly')
                                    ->required(),

                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options(SubscriptionStatus::class)
                                    ->default(fn ($record) => $record?->activeSubscription?->status ?? SubscriptionStatus::ACTIVE)
                                    ->required(),

                                Forms\Components\DateTimePicker::make('trial_ends_at')
                                    ->label('Trial End Date')
                                    ->native(false)
                                    ->closeOnDateSelection(true)
                                    ->helperText('Optional - for trial subscriptions'),

                                Forms\Components\RichEditor::make('notes')
                                    ->label('Notes')
                                    ->placeholder('Add notes about this assignment...')
                                    ->columnSpanFull(),

                                Forms\Components\Toggle::make('generate_invoice')
                                    ->label('Generate Invoice')
                                    ->default(true)
                                    ->columnSpanFull(),
                            ])->columns(2),

                    ])
                    ->action(function (User $record, array $data): void {
                        $subscriptionService = app(SubscriptionService::class);

                        DB::transaction(function () use ($record, $data, $subscriptionService) {
                            $plan = Plan::findOrFail($data['plan_id']);
                            $subscriptionService->assignPlanToUser($record, $plan, [
                                'billing_cycle' => $data['billing_cycle'],
                                'status' => $data['status'], // Pass the selected status
                                'trial_ends_at' => $data['trial_ends_at'] ?? null,
                                'assigned_by' => auth()->id(),
                                'notes' => $data['notes'] ?? null,
                            ]);

                            if (! empty($data['generate_invoice'])) {
                                $amount = $data['billing_cycle'] === 'monthly' ? $plan->price_monthly : $plan->price_yearly;

                                $payment = Payment::create([
                                    'user_id' => $record->id,
                                    'amount' => $amount,
                                    'currency' => $plan->currency,
                                    'status' => \App\Enums\PaymentStatus::COMPLETED,
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

                                app(\App\Services\InvoiceService::class)->generateInvoice($payment);
                            }
                        });
                    })
                    ->successNotificationTitle('Plan assigned successfully')
                    ->color('primary'),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('bulk_assign_plan')
                        ->label('Bulk Assign Plan')
                        ->icon('heroicon-o-credit-card')
                        ->form([
                            Forms\Components\Select::make('plan_id')
                                ->label('Plan')
                                ->options(Plan::active()->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required(),
                            Forms\Components\Select::make('billing_cycle')
                                ->label('Billing Cycle')
                                ->options([
                                    'monthly' => 'Monthly',
                                    'yearly' => 'Yearly',
                                ])
                                ->default('monthly')
                                ->required(),
                            Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options(SubscriptionStatus::class)
                                ->default(SubscriptionStatus::ACTIVE)
                                ->required(),
                            Forms\Components\DateTimePicker::make('trial_ends_at')
                                ->label('Trial End Date')
                                ->native(false)
                                ->closeOnDateSelection(true)
                                ->helperText('Optional - for trial subscriptions'),
                            Forms\Components\Textarea::make('notes')
                                ->label('Notes')
                                ->placeholder('Add notes about this assignment...')
                                ->rows(3),
                        ])
                        ->action(function (array $data, \Illuminate\Support\Collection $records): void {
                            $subscriptionService = app(SubscriptionService::class);
                            $plan = Plan::findOrFail($data['plan_id']);

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
            RelationManagers\SubscriptionRelationManager::class,
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
