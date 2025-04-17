<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GymMemberResource\Pages;
use App\Models\GymMember;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GymMemberResource extends Resource
{
    protected static ?string $model = GymMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    // Restrict to admins or gym owners with gyms
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        return $user->hasRole('admin') || ($user->hasRole('gym_owner') && $user->gyms()->exists());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('gym_id')
                    ->relationship('gym', 'name')
                    ->required()
                    ->visible(fn() => auth()->user()->hasRole('admin')) // Only admins can choose gym
                    ->default(fn() => auth()->user()->hasRole('gym_owner') ? auth()->user()->gyms()->first()->id ?? null : null)
                    ->disabled(fn() => auth()->user()->hasRole('gym_owner'))
                    ->placeholder('Select a gym'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(GymMember::class, 'email', ignoreRecord: true),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\Textarea::make('address')
                    ->maxLength(65535),
                Forms\Components\DatePicker::make('dob')
                    ->label('Date of Birth'),
                Forms\Components\DatePicker::make('membership_start'),
                Forms\Components\DatePicker::make('membership_end'),
                // Forms\Components\Select::make('plan_id')
                //     ->relationship('plan', 'name')
                //     ->required()
                //     ->placeholder('Select a plan'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('gym.name')->label('Gym'),
                Tables\Columns\TextColumn::make('plan.name')->label('Plan'),
                Tables\Columns\TextColumn::make('membership_start')->date(),
                Tables\Columns\TextColumn::make('membership_end')->date(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gym_id')
                    ->relationship('gym', 'name')
                    ->visible(fn() => auth()->user()->hasRole('admin')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();
                if ($user->hasRole('gym_owner')) {
                    $query->whereIn('gym_id', $user->gyms()->pluck('id'));
                }
                return $query;
            });
    }

    public static function getRelations(): array
    {
        return [
            // Add payment relation manager later
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGymMembers::route('/'),
            'create' => Pages\CreateGymMember::route('/create'),
            'edit' => Pages\EditGymMember::route('/{record}/edit'),
        ];
    }
}
