<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Enums\Currency;
use App\Enums\DurationMonth;
use App\Filament\Admin\Resources\MembershipPlanResource\Pages\CreateMembershipPlan;
use App\Filament\Admin\Resources\MembershipPlanResource\Pages\EditMembershipPlan;
use App\Filament\Admin\Resources\MembershipPlanResource\Pages\ListMembershipPlans;
use App\Filament\BaseResources\BaseResource;
use App\Models\Gym;
use App\Models\MembershipPlan;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MembershipPlanResource extends BaseResource
{
    protected static ?string $model = MembershipPlan::class;

    protected static ?string $navigationLabel = 'Membership Plans';

    protected static ?string $navigationGroup = 'Gym Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Plan Details')
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger'),

                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
            ])
            ->filters([])
            ->actions([
                EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary'),
                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->successNotification(null),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListMembershipPlans::route('/'),
            'create' => CreateMembershipPlan::route('/create'),
            'edit' => EditMembershipPlan::route('/{record}/edit'),
        ];
    }

    public static function planFormSchema(): array
    {
        /**
         * @var Gym $gym
         */
        $gym = Filament::getTenant();

        return [
            Hidden::make('gym_id')->default($gym->id),

            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g., Gold Membership')
                ->autofocus()
                ->extraInputAttributes([
                    'class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                ]),

            TextInput::make('price')
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
            Select::make('currency')
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
            Select::make('duration')
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
            Textarea::make('description')
                ->maxLength(65535)
                ->placeholder(
                    'e.g., Includes unlimited classes and personal training',
                )
                ->rows(4)
                ->extraAttributes([
                    'class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500',
                ])
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->default(true)
                ->label('Active')
                ->onColor('success')
                ->offColor('danger'),
        ];
    }
}
