<?php

namespace App\Filament\Resources;

use App\Enums\Currency;
use App\Enums\DurationMonth;
use App\Filament\Resources\MembershipPlanResource\Pages;
use App\Models\MembershipPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MembershipPlanResource extends Resource
{
    protected static ?string $model = MembershipPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Membership Plans';

    protected static ?string $navigationGroup = 'Gym Management';

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user->hasRole('super_admin') || ($user->hasRole('gym_owner') && $user->gyms()->exists());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plan Details')
                    ->icon('heroicon-o-ticket')
                    ->description('Create a membership plan for your gym')
                    ->schema([
                        Forms\Components\Select::make('gym_id')
                            ->relationship('gym', 'name', function ($query) {
                                if (auth()->user()->hasRole('gym_owner')) {
                                    return $query->whereIn('id', auth()->user()->gyms()->pluck('id'));
                                }

                                return $query;
                            })
                            ->required()
                            // ->default(fn() => auth()->user()->hasRole('gym_owner') ? auth()->user()->gyms()->first()->id ?? null : null)
                            ->placeholder('Select a gym'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Gold Membership')
                            ->autofocus()
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),

                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->required()
                            ->prefix(function ($get) {
                                $currency = Currency::tryFrom($get('currency') ?? Currency::INR->value);

                                return $currency?->getSymbol() ?? Currency::INR->getSymbol();
                            })
                            ->minValue(0)
                            ->step(0.01)
                            ->placeholder('0.00')
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),
                        Forms\Components\Select::make('currency')
                            ->options(collect(Currency::cases())->mapWithKeys(
                                fn(Currency $currency) => [$currency->value => $currency->getLabel()]
                            ))
                            ->required()
                            ->reactive()
                            ->default(Currency::INR->value),
                        Forms\Components\Select::make('duration')
                            ->label('Duration (Months)')
                            ->options(collect(DurationMonth::cases())->mapWithKeys(
                                fn(DurationMonth $duration) => [$duration->value => $duration->getLabel()]
                            ))
                            ->required()
                            ->default(DurationMonth::THREE_MONTHS->value),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->placeholder('e.g., Includes unlimited classes and personal training')
                            ->rows(4)
                            ->extraAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500'])
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active')
                            ->onColor('success')
                            ->offColor('danger'),
                    ])
                    ->collapsible()
                    ->columns(2),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn($state) => "<div class='flex items-center gap-2'>
                        <span class='inline-flex items-center justify-center w-8 h-8 bg-purple-100 text-purple-800 rounded-full'>
                            " . strtoupper(substr($state, 0, 1)) . "
                        </span>
                        <span class='font-semibold'>$state</span>
                    </div>")
                    ->html(),
                Tables\Columns\TextColumn::make('gym.name')
                    ->label('Gym')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('price')
                    ->money(fn($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gym_id')
                    ->relationship('gym', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->successNotification(null),
            ])
            // ->bulkActions([
            //     Tables\Actions\BulkActionGroup::make([
            //         Tables\Actions\DeleteBulkAction::make(),
            //     ]),
            // ])
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();
                if ($user->hasRole('gym_owner')) {
                    $query->whereIn('gym_id', $user->gyms()->pluck('id'));
                }

                return $query;
            })
            ->defaultSort('name')
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
            'index' => Pages\ListMembershipPlans::route('/'),
            'create' => Pages\CreateMembershipPlan::route('/create'),
            'edit' => Pages\EditMembershipPlan::route('/{record}/edit'),
        ];
    }
}
