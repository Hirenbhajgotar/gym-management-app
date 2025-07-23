<?php

namespace App\Filament\Admin\Resources;

use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\ReceptionistResource\Pages;
use App\Filament\Admin\Resources\ReceptionistResource\RelationManagers;
use App\Models\Receptionist;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class ReceptionistResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Users';

    protected static ?string $tenantOwnershipRelationshipName = 'gyms';

    public static function getLabel(): string
    {
        return 'Receptionist';
    }

    public static function getPluralLabel(): string
    {
        return 'Receptionists';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['gym_owner']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Receptionist Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Jane Doe'),
                        TextInput::make('email')
                            ->required()
                            ->email()
                            ->unique(table: 'users', ignoreRecord: true)
                            ->placeholder('e.g., jane@example.com'),
                        TextInput::make('password')
                            ->revealable()
                            ->password()
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->rules([
                                fn($state) => filled($state) ? 'regex:/[A-Z]/' : '',
                                fn($state) => filled($state) ? 'regex:/[a-z]/' : '',
                                fn($state) => filled($state) ? 'regex:/[0-9]/' : '',
                                fn($state) => filled($state) ? 'regex:/[!@#$%^&*(),.?":{}|<>]/' : '',
                            ])
                            ->validationMessages([
                                'regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
                            ])
                            ->hint('Need a strong password?')
                            ->hintAction(
                                Action::make('generatePassword')
                                    ->label('Generate')
                                    ->icon('heroicon-o-key')
                                    ->action(function (Set $set) {
                                        $password = Str::password(12);
                                        $set('password', $password);
                                    })
                            ),
                        // Select::make('gyms')
                        //     ->multiple()
                        //     ->relationship('gyms', 'name')
                        //     ->options(function () {
                        //         $user = auth()->user();
                        //         // return $user->gyms()->wherePivot('role', 'gym_owner')->first()?->gyms()->get()->pluck('name', 'id') ?? [];
                        //         return $user->gyms()->get()->pluck('name', 'id') ?? [];
                        //     })
                        //     ->required()
                        //     ->saveRelationshipsUsing(function (Select $component, Model $record, ?array $state) {
                        //         // This ensures the gym data is properly handled
                        //         if (!empty($state)) {
                        //             $pivotData = [];
                        //             foreach ($state as $gymId) {
                        //                 // Check if this gym was already assigned to preserve its active status
                        //                 $existingPivot = $record->gyms()->where('gym_id', $gymId)->first()?->pivot;

                        //                 $pivotData[$gymId] = [
                        //                     'role' => 'receptionist',
                        //                     'active' => $existingPivot ? $existingPivot->active : true,
                        //                 ];
                        //             }
                        //             $record->gyms()->sync($pivotData);
                        //         } else {
                        //             $record->gyms()->detach();
                        //         }
                        //     })
                        //     ->helperText('Select the gyms this receptionist can access.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('gyms.name')
                    ->label('Assigned Gyms')
                    ->listWithLineBreaks()
                    ->badge()
                    ->searchable(),
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
            'index' => Pages\ListReceptionists::route('/'),
            'create' => Pages\CreateReceptionist::route('/create'),
            'edit' => Pages\EditReceptionist::route('/{record}/edit'),
        ];
    }
}
