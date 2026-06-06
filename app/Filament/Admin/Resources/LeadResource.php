<?php

namespace App\Filament\Admin\Resources;

use App\Contracts\HasFilamentPermissions;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTag;
use App\Filament\Admin\Resources\LeadResource\Pages;
use App\Filament\Admin\Resources\LeadResource\RelationManagers\FollowUpsRelationManager;
use App\Models\Gym;
use App\Models\Lead;
use App\Rules\ValidEmailDomain;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use libphonenumber\PhoneNumberType;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class LeadResource extends Resource implements HasFilamentPermissions
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationGroup = 'CRM';

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        return Cache::flexible(Lead::NAVIGATION_BADGE_CACHE_KEY . $tenant->id, [21600, 604800], function () {
            return (string) (static::$model)::query()->where('status', LeadStatus::NEW)->count();
        }) ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Lead Details')->schema([
                Grid::make(3)->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('email')->email()
                        ->rule(new ValidEmailDomain)
                        ->required(),
                    PhoneInput::make('phone')
                        ->required()
                        ->defaultCountry('IN')
                        ->validateFor(
                            type: [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE], // default: null
                        ),
                ]),

                Grid::make(2)->schema([
                    Forms\Components\Hidden::make('status')
                        ->default(LeadStatus::NEW)
                        ->visibleOn('create'),

                    Forms\Components\Select::make('status')
                        ->options(LeadStatus::class)
                        ->visibleOn('edit')
                        ->required(),

                    Forms\Components\Select::make('source')
                        ->options(LeadSource::class)
                        ->required(),

                    Forms\Components\Select::make('created_by')
                        ->label('Created By')
                        ->options(
                            fn () => Gym::getCurrentTenantUsers()->pluck(
                                'users.name',
                                'users.id',
                            ),
                        )
                        ->searchable()
                        ->default(auth()->id()),

                    Forms\Components\TagsInput::make('interest')->placeholder(
                        'Add interests (e.g., Yoga, Weight Loss)',
                    ),

                    Forms\Components\TagsInput::make('tags')
                        ->placeholder(
                            'Add tags (e.g., High Priority, Referral)',
                        )
                        ->splitKeys(['Tab', ','])
                        ->suggestions(
                            collect(LeadTag::cases())
                                ->map(fn (LeadTag $tag) => $tag->getLabel())
                                ->toArray(),
                        ),
                ]),
                Forms\Components\Textarea::make('notes')
                    ->label('Conversion Notes')
                    ->rows(6)
                    ->placeholder(
                        'Add any relevant notes about the conversion...',
                    )
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('source')->sortable(),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(
                    LeadStatus::class,
                ),
                Tables\Filters\SelectFilter::make('source')->options(
                    LeadSource::class,
                ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [FollowUpsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }

    public static function getConvertToMemberFormSchema(): array
    {
        return [
            Forms\Components\Group::make()
                ->schema([
                    Forms\Components\Section::make('Membership Details')
                        ->icon('heroicon-o-ticket')
                        ->description('Configure membership settings')
                        ->schema([
                            Forms\Components\Hidden::make('gym_id')
                                ->default(fn () => Filament::getTenant()?->id ?? null),

                            Forms\Components\Select::make('trainer_id')
                                ->label('Assign Trainer')
                                ->options(function (callable $get) {
                                    $gymId = Filament::getTenant()->id ?? null;
                                    if (! $gymId) {
                                        return [];
                                    }

                                    return \App\Models\Trainer::where('gym_id', $gymId)
                                        ->available()
                                        ->pluck('name', 'id')
                                        ->prepend('None', null);
                                })
                                ->reactive()
                                ->nullable()
                                ->helperText('Leave as "None" if the member does not want a personal trainer.'),

                            ...GymMemberResource::getMembershipPlanFormSchema(function () {
                                return Filament::getTenant()->id ?? null;
                            }),
                        ])
                        ->columns(2),

                    ...GymMemberResource::getPaymentFormSchema(),

                    Forms\Components\RichEditor::make('conversion_notes')
                        ->label('Conversion Notes')
                        ->placeholder(
                            'Add any relevant notes about the conversion...',
                        )
                        ->columnSpanFull(),
                ]),
        ];
    }
}
