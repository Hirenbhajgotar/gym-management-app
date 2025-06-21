<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\StaffMemberResource\Pages;
use App\Filament\Admin\Resources\StaffMemberResource\RelationManagers;
use App\Models\StaffMember;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class StaffMemberResource extends Resource
{
    protected static ?string $model = StaffMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static bool $isScopedToTenant = false;


    public static function getLabel(): string
    {
        return 'Staff Member'; // Temporary label, will suggest alternatives below
    }

    public static function getPluralLabel(): string
    {
        return 'Staff Members';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Staff Member Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., John Doe'),
                        TextInput::make('email')
                            ->required()
                            ->email()
                            ->unique(table: 'staff_members', ignoreRecord: true)
                            ->placeholder('e.g., john@example.com'),
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
                        Select::make('gyms')
                            ->multiple()
                            ->relationship('gyms', 'name')
                            ->options(function ($record) {
                                // Only show gyms owned by the current user (gym owner)
                                $user = auth()->user();
                                return $user->gyms()->pluck('name', 'id');
                            })
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Set the first gym as active by default
                                if (!empty($state)) {
                                    $set('gyms', $state); // Ensure the pivot is updated
                                }
                            })
                            ->helperText('Select the gyms this staff member can access.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gyms.name')
                    ->label('Assigned Gyms')
                    ->listWithLineBreaks()
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffMembers::route('/'),
            'create' => Pages\CreateStaffMember::route('/create'),
            'edit' => Pages\EditStaffMember::route('/{record}/edit'),
        ];
    }
}
