<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\LeadResource\Pages;
use App\Filament\Admin\Resources\LeadResource\RelationManagers;
use App\Models\Lead;
use App\Models\MembershipPlan;
use App\Services\LeadConversionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    // protected static ?string $navigationIcon = 'heroicon-o-phone-incoming';

    protected static ?string $navigationGroup = 'CRM';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\Select::make('source')
                    ->options(\App\Enums\LeadSource::class)
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options(\App\Enums\LeadStatus::class)
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('source'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('convert')
                    ->label('Convert to Member')
                    ->action(function (Lead $record, array $data) {
                        $service = new LeadConversionService;
                        $service->convert($record, $data['membership_plan_id']);

                        Notification::make()
                            ->title('Lead Converted')
                            ->success()
                            ->send();
                    })
                    ->form([
                        Forms\Components\Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->options(MembershipPlan::query()->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Lead $record) => $record->status !== \App\Enums\LeadStatus::CONVERTED),
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
            RelationManagers\InteractionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
