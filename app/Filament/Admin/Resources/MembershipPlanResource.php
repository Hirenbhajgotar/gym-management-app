<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Currency;
use App\Enums\DurationMonth;
use App\Filament\Admin\Resources\MembershipPlanResource\Pages;
use App\Models\MembershipPlan;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MembershipPlanResource extends Resource
{
    protected static ?string $model = MembershipPlan::class;

    protected static ?string $navigationLabel = 'Membership Plans';

    protected static ?string $navigationGroup = 'Gym Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plan Details')
                    ->icon('heroicon-o-ticket')
                    ->description('Create a membership plan for your gym')
                    ->schema(self::planFormSchema())
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger'),

                Tables\Columns\TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->successNotification(null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
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

    public static function planFormSchema(): array
    {
        return [
            Forms\Components\Hidden::make('gym_id')->default(
                Filament::getTenant()->id,
            ),

            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g., Gold Membership')
                ->autofocus()
                ->extraInputAttributes([
                    'class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                ]),

            Forms\Components\TextInput::make('price')
                ->numeric()
                ->required()
                ->prefix(function ($get) {
                    $currency = Currency::tryFrom(
                        $get('currency') ?? Currency::INR->value,
                    );

                    return $currency?->getSymbol() ??
                        Currency::INR->getSymbol();
                })
                ->minValue(0)
                ->step(0.01)
                ->placeholder('0.00')
                ->extraInputAttributes([
                    'class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                ]),
            Forms\Components\Select::make('currency')
                ->options(
                    collect(Currency::cases())->mapWithKeys(
                        fn (Currency $currency) => [
                            $currency->value => $currency->getLabel(),
                        ],
                    ),
                )
                ->required()
                ->reactive()
                ->default(Currency::INR->value),
            Forms\Components\Select::make('duration')
                ->label('Duration (Months)')
                ->options(
                    collect(DurationMonth::cases())->mapWithKeys(
                        fn (DurationMonth $duration) => [
                            $duration->value => $duration->getLabel(),
                        ],
                    ),
                )
                ->required()
                ->default(DurationMonth::THREE_MONTHS->value),
            Forms\Components\Textarea::make('description')
                ->maxLength(65535)
                ->placeholder(
                    'e.g., Includes unlimited classes and personal training',
                )
                ->rows(4)
                ->extraAttributes([
                    'class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                ])
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_active')
                ->default(true)
                ->label('Active')
                ->onColor('success')
                ->offColor('danger'),
        ];
    }
}
