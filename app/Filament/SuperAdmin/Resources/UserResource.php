<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\UserResource\Pages\CreateUser;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\EditUser;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = "heroicon-o-users";

    protected static ?int $navigationSort = 1;

    // Restrict to admins or gym owners with gyms
    // public static function canAccess(array $parameters = []): bool
    // {
    //     $user = auth()->user();

    //     return $user->hasRole('super_admin');
    // }

    public static function getMiddleware(): array
    {
        return [PermissionMiddleware::using("manage users")];
    }

    public static function getNavigationBadge(): ?string
    {
        return cache()->remember(
            "users_navigation_badge_count_" . auth()->id(),
            now()->addMinutes(15),
            fn() => static::getModel()::count(),
        );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make("User Information")->schema([
                Forms\Components\TextInput::make("name")
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make("email")
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make("email_verified_at"),
                Forms\Components\TextInput::make("password")
                    ->password()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make("is_active")
                    ->label("Active")
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->whereExists(function ($query) {
                        $query
                            ->select(DB::raw(1))
                            ->from("model_has_roles")
                            ->join(
                                "roles",
                                "roles.id",
                                "=",
                                "model_has_roles.role_id",
                            )
                            ->whereColumn(
                                "users.id",
                                "=",
                                "model_has_roles.model_id",
                            )
                            ->where(
                                "model_has_roles.model_type",
                                "=",
                                "App\\Models\\User",
                            )
                            ->where("roles.name", "=", "gym_owner");
                    })
                    ->with("gyms")
                    ->latest(),
            )
            ->columns([
                Tables\Columns\TextColumn::make("name")->searchable(),
                Tables\Columns\TextColumn::make("email")->searchable(),
                Tables\Columns\TextColumn::make("gyms.name")
                    ->label("Gyms")
                    ->badge()
                    ->separator(","),
                Tables\Columns\ToggleColumn::make("is_active")->label("Active"),
                Tables\Columns\TextColumn::make("email_verified_at")
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make("created_at")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make("updated_at")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            "index" => ListUsers::route("/"),
            "create" => CreateUser::route("/create"),
            "edit" => EditUser::route("/{record}/edit"),
        ];
    }
}
