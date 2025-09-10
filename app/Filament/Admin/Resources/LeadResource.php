<?php

namespace App\Filament\Admin\Resources;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTag;
use App\Filament\Admin\Resources\LeadResource\Pages;
use App\Filament\Admin\Resources\LeadResource\RelationManagers\FollowUpsRelationManager;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\MembershipPlan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'phosphor-circles-three-plus';

    protected static ?string $navigationGroup = 'CRM';

    public static function getNavigationBadge(): ?string
    {
        $modelClass = static::$model;
        $query = $modelClass::query()->where('status', LeadStatus::NEW);

        return $query->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Lead Details')->schema([
                Grid::make(3)->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('email')->email()->required(),
                    TextInput::make('phone')->tel()->required(),
                ]),

                Grid::make(2)->schema([
                    Select::make('membership_plan_id')
                        ->label('Membership Plan')
                        ->options(MembershipPlan::pluck('name', 'id'))
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->options(LeadStatus::class)
                        ->required(),

                    Forms\Components\Select::make('source')
                        ->options(LeadSource::class)
                        ->required(),

                    Forms\Components\Select::make('created_by')
                        ->label('Created By')
                        ->options(fn() => Gym::getCurrentTenantUsers()->pluck('users.name', 'users.id'))
                        ->searchable()
                        ->default(auth()->id()),

                    Forms\Components\TagsInput::make('interest')
                        ->placeholder('Add interests (e.g., Yoga, Weight Loss)'),

                    Forms\Components\TagsInput::make('tags')
                        ->placeholder('Add tags (e.g., High Priority, Referral)')
                        ->splitKeys(['Tab', ','])
                        ->suggestions(collect(LeadTag::cases())->map(fn(LeadTag $tag) => $tag->getLabel())->toArray()),
                ]),
                Forms\Components\RichEditor::make('notes')
                    ->label('Conversion Notes')
                    ->placeholder('Add any relevant notes about the conversion...')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('source')->sortable(),
                TextColumn::make('creator.name')->label('Created By')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(LeadStatus::class),
                Tables\Filters\SelectFilter::make('source')
                    ->options(LeadSource::class),
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
        return [
            FollowUpsRelationManager::class,
        ];
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
            Forms\Components\Section::make('Convert to Member')
                ->description('Convert this lead into a new gym member and assign a membership plan.')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->options(fn() => MembershipPlan::pluck('name', 'id'))
                            ->default(fn(?Lead $record) => $record?->membership_plan_id)
                            ->searchable()
                            ->required()
                            ->placeholder('Select a membership plan')
                            ->prefixIcon('phosphor-crown'),

                        Forms\Components\DatePicker::make('conversion_date')
                            ->label('Conversion Date')
                            ->default(now())
                            ->required()
                            ->prefixIcon('phosphor-calendar'),
                    ]),

                    Forms\Components\RichEditor::make('notes')
                        ->label('Conversion Notes')
                        ->placeholder('Add any relevant notes about the conversion...')
                        ->columnSpanFull(),
                ]),
        ];
    }
}
