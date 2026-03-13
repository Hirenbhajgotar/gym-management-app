<?php

namespace App\Filament\Admin\Pages\WhatsApp;

use App\Enums\WabaMigrationStatusEnum;
use App\Models\WabaMigration;
use App\Models\WhatsAppAccount;
use App\Services\WabaMigrationService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class WabaMigrationPage extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Migrate to Individual WABA';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static string $view = 'filament.admin.pages.whatsapp.waba-migration';

    public bool $useIndividualWaba = false;

    public ?WhatsAppAccount $whatsappAccount = null;

    public ?WabaMigration $pendingMigration = null;

    public array $migrationStats = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false; // This will hide it from the menu
    }

    public function mount(): void
    {
        $this->useIndividualWaba = config('services.twilio.use_individual_waba', false);

        $gymId = Filament::getTenant()?->id;

        if ($gymId) {
            $this->whatsappAccount = WhatsAppAccount::where('gym_id', $gymId)->first();
            $this->pendingMigration = WabaMigration::where('gym_id', $gymId)
                ->whereIn('status', [
                    WabaMigrationStatusEnum::PENDING,
                    WabaMigrationStatusEnum::IN_PROGRESS,
                ])
                ->latest()
                ->first();

            $this->migrationStats = [
                'total' => WabaMigration::where('gym_id', $gymId)->count(),
                'completed' => WabaMigration::where('gym_id', $gymId)
                    ->where('status', WabaMigrationStatusEnum::COMPLETED)
                    ->count(),
                'pending' => WabaMigration::where('gym_id', $gymId)
                    ->where('status', WabaMigrationStatusEnum::PENDING)
                    ->count(),
                'failed' => WabaMigration::where('gym_id', $gymId)
                    ->where('status', WabaMigrationStatusEnum::FAILED)
                    ->count(),
            ];
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WabaMigration::query()
                    ->where('gym_id', Filament::getTenant()?->id)
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (WabaMigrationStatusEnum $state): string => match ($state) {
                        WabaMigrationStatusEnum::PENDING => 'warning',
                        WabaMigrationStatusEnum::IN_PROGRESS => 'info',
                        WabaMigrationStatusEnum::COMPLETED => 'success',
                        WabaMigrationStatusEnum::FAILED => 'danger',
                    }),

                Tables\Columns\TextColumn::make('old_waba_mode')
                    ->label('From')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'N/A'),

                Tables\Columns\TextColumn::make('new_waba_mode')
                    ->label('To')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '-'),

                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('migration_notes')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (WabaMigration $record): bool => $record->isFailed())
                    ->action(fn (WabaMigration $record, WabaMigrationService $service) => $this->retryMigration($record, $service)),
            ]);
    }

    public function startMigration(WabaMigrationService $migrationService): void
    {
        if (! $this->useIndividualWaba) {
            Notification::make()
                ->title('Feature Not Enabled')
                ->body('Individual WABA mode is not enabled. Please enable it in configuration.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->whatsappAccount) {
            Notification::make()
                ->title('No Account Found')
                ->body('Please set up your WhatsApp account first.')
                ->danger()
                ->send();

            return;
        }

        if ($this->whatsappAccount->hasIndividualWaba()) {
            Notification::make()
                ->title('Already Migrated')
                ->body('Your account is already using individual WABA.')
                ->info()
                ->send();

            return;
        }

        if ($this->pendingMigration) {
            Notification::make()
                ->title('Migration In Progress')
                ->body('You already have a pending migration.')
                ->warning()
                ->send();

            return;
        }

        $gymId = Filament::getTenant()?->id;

        $migration = $migrationService->createMigrationForGym($gymId);

        if ($migration) {
            Notification::make()
                ->title('Migration Started')
                ->body('Your WABA migration has been initiated. You will be prompted to complete the Embedded Signup.')
                ->success()
                ->send();

            $this->mount();
        } else {
            Notification::make()
                ->title('Migration Failed')
                ->body('Could not start migration. Please try again.')
                ->danger()
                ->send();
        }
    }

    public function retryMigration(WabaMigration $migration, WabaMigrationService $migrationService): void
    {
        $result = $migrationService->startMigration($migration->gym_id);

        if ($result['success']) {
            Notification::make()
                ->title('Migration Retried')
                ->success()
                ->send();

            $this->mount();
        } else {
            Notification::make()
                ->title('Retry Failed')
                ->body($result['error'] ?? 'Unknown error')
                ->danger()
                ->send();
        }
    }

    public function getGymId(): int|string|null
    {
        return Filament::getTenant()?->id;
    }
}
