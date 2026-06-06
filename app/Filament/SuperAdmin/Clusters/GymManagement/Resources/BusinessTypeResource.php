<?php

namespace App\Filament\SuperAdmin\Clusters\GymManagement\Resources;

use App\Contracts\HasFilamentPermissions;
use App\Filament\SuperAdmin\Clusters\GymManagement;
use App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource\Pages;
use App\Models\BusinessType;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BusinessTypeResource extends Resource implements HasFilamentPermissions
{
    protected static ?string $model = BusinessType::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $cluster = GymManagement::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Business Type Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Fitness Center')
                            ->hint('Enter a unique name for the business type.')
                            ->hintColor('primary')
                            ->columnSpan(['lg' => 2])
                            ->autocomplete('off')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('slug', Str::slug($state));
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., fitness-center')
                            ->hint('Used for URL routing. Auto-generated from name.')
                            ->hintColor('secondary')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(['lg' => 2]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Status')
                            ->required()
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->onIcon('heroicon-o-check-circle')
                            ->offIcon('heroicon-o-x-circle')
                            ->inline(false)
                            ->helperText('Toggle to activate or deactivate this business type.')
                            ->columnSpan(['lg' => 1]),
                    ])
                    ->columns(3)
                    ->columnSpan(['lg' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()->searchable(),

                ToggleColumn::make('is_active')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBusinessTypes::route('/'),
            // 'create' => Pages\CreateBusinessType::route('/create'),
            // 'edit' => Pages\EditBusinessType::route('/{record}/edit'),
        ];
    }
}
