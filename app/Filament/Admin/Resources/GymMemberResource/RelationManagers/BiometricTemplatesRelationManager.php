<?php

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use App\Enums\BiometricTypeEnum;
use App\Models\BiometricTemplate;
use App\Traits\HasTenantAccess;
use Filament\Forms\Components;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BiometricTemplatesRelationManager extends RelationManager
{
    use HasTenantAccess;

    protected static string $relationship = 'biometricTemplates';

    protected static ?string $icon = 'heroicon-o-finger-print';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Section::make('Biometric Template')
                    ->schema([
                        Select::make('biometric_type')
                            ->label('Biometric Type')
                            ->options(BiometricTypeEnum::class)
                            ->required(),

                        Textarea::make('template_data')
                            ->label('Template Data')
                            ->rows(3)
                            ->required()
                            ->helperText('Base64 encoded biometric template'),

                        Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('biometric_type')
                    ->label('Type')
                    ->badge()
                    ->color('info'),

                TextColumn::make('template_id')
                    ->label('Template ID')
                    ->searchable(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ])
                    ->formatStateUsing(fn (bool $state) => $state ? 'Active' : 'Inactive'),

                TextColumn::make('created_at')
                    ->label('Enrolled')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\Filter::make('active_only')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->where('is_active', true)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->action(function (BiometricTemplate $record) {
                        $record->update(['is_active' => false]);
                        Notification::make()
                            ->title('Biometric Template Deactivated')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }
}
