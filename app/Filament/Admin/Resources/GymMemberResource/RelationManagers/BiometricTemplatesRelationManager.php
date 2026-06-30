<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\RelationManagers;

use App\Enums\BiometricTypeEnum;
use App\Models\BiometricTemplate;
use App\Traits\HasTenantAccess;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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
                Hidden::make('gym_id')
                    ->default(fn () => $this->getGymId()),

                Section::make('Biometric Template')
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

                        Toggle::make('is_active')
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
                Filter::make('active_only')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->where('is_active', true)),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->action(function (BiometricTemplate $record) {
                        $record->update(['is_active' => false]);
                        Notification::make()
                            ->title('Biometric Template Deactivated')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Biometric Template')
                    ->icon('heroicon-o-plus-circle')
                    ->form($this->form(new Form($this))->getComponents())
                    ->action(fn (array $data) => $this->handleCreateBiometricTemplate($data)),
            ]);
    }

    protected function getGymId(): int
    {
        $tenant = Filament::getTenant();

        return $tenant?->id ?? $this->ownerRecord->gym_id;
    }

    protected function handleCreateBiometricTemplate(array $data): void
    {
        // Convert template_data to template_ref JSON format
        if (isset($data['template_data'])) {
            $data['template_ref'] = json_encode([
                'data' => $data['template_data'],
                'created_at' => now()->toISOString(),
            ]);
            unset($data['template_data']);
        }

        // Set provider_type to custom by default (manual enrollment via admin)
        $data['provider_type'] = 'custom';
        $data['enrolled_at'] = now();

        // enrolled_by_device_id is null for manual enrollment via admin panel
        // For device-based enrollment, this is set via the BiometricEnrollController API
        $data['enrolled_by_device_id'] = null;

        // Set gym_id if not already set via hidden field
        if (! isset($data['gym_id'])) {
            $data['gym_id'] = $this->getGymId();
        }

        $this->getRelationship()->create($data);

        Notification::make()
            ->title('Biometric Template Created')
            ->success()
            ->send();
    }
}
