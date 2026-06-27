<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Clusters\GymManagement\Resources;

use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Filament\SuperAdmin\Clusters\GymManagement;
use App\Filament\SuperAdmin\Clusters\GymManagement\Resources\BusinessTypeResource\Pages\ListBusinessTypes;
use App\Models\BusinessType;
use Filament\Forms\Components\Section;
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
use Illuminate\Support\Str;

class BusinessTypeResource extends BaseResource
{
    protected static ?string $model = BusinessType::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $cluster = GymManagement::class;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
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

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., fitness-center')
                            ->hint('Used for URL routing. Auto-generated from name.')
                            ->hintColor('secondary')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(['lg' => 2]),

                        Toggle::make('is_active')
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
                TextColumn::make('name')
                    ->sortable()->searchable(),

                ToggleColumn::make('is_active')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListBusinessTypes::route('/'),
            // 'create' => Pages\CreateBusinessType::route('/create'),
            // 'edit' => Pages\EditBusinessType::route('/{record}/edit'),
        ];
    }
}
