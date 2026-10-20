<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\BaseWidgets\BaseTableWidget;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The aggregate discovery surface for an erasure the system cannot discharge by itself.
 *
 * The relation manager's "Needs Operator" filter is per-member; a row parked at the
 * terminal `DeletionUnresolvable` state, or a hardware row whose `user_delete` exhausted
 * its delivery budget, would otherwise only be found by opening the right member. The
 * candidates are selected in SQL and narrowed by the row's own
 * {@see BiometricTemplate::requiresOperatorAttention()} predicate in PHP, because the
 * delivery cap lives on the command log and the provider reference is encrypted — neither
 * is expressible in the template query.
 *
 * Registered by discovery in `AdminPanelProvider::discoverWidgets()`, so no manual
 * registration is needed.
 */
class BiometricOperatorAttentionWidget extends BaseTableWidget
{
    protected static ?string $heading = 'Biometric Erasures Requiring Operator Attention';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 7;

    /**
     * Receptionists are admitted because the `enrollBiometric` ability admits them: the widget
     * surfaces the rows the relation manager's erasure actions repair, so it must not be
     * visible only to the roles that can authorise the repair.
     *
     * The audience is the roles that can repair an erasure, OR anyone the base widget
     * permission gate admits.
     */
    public static function canView(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole([
            DefaultRole::GYM_OWNER->value,
            DefaultRole::MANAGER->value,
            DefaultRole::RECEPTIONIST->value,
        ]) || parent::canAccess();
    }

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::GYM_OWNER->value => ['widget'],
            DefaultRole::MANAGER->value => ['widget'],
            DefaultRole::RECEPTIONIST->value => ['widget'],
            DefaultRole::TRAINER->value => [],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->operatorAttentionQuery())
            ->columns([
                TextColumn::make('gymMember.name')
                    ->label('Member')
                    ->url(fn (BiometricTemplate $record): ?string => $record->gymMember !== null
                        ? GymMemberResource::getUrl('edit', ['record' => $record->gymMember])
                        : null),

                TextColumn::make('biometric_type')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('sync_status')
                    ->label('Sync Status')
                    ->badge()
                    ->formatStateUsing(fn ($state, BiometricTemplate $record): string => $record->getSyncStatusBadgeLabel())
                    ->color(fn ($state, BiometricTemplate $record): string => $record->getSyncStatusBadgeColor()),

                TextColumn::make('enrolledByDevice.device_name')
                    ->label('Device')
                    ->placeholder('Cloud Provider'),

                TextColumn::make('erasure_attempts')
                    ->label('Erasure Attempts')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->placeholder('0')
                    ->tooltip('Provider-side erase attempts recorded against this row.'),
            ])
            ->emptyStateHeading('No biometric erasures need an operator')
            ->emptyStateDescription('Every outstanding erasure is progressing through its provider or terminal.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }

    /**
     * Select the rows for which {@see BiometricTemplate::requiresOperatorAttention()} answers
     * true.
     *
     * Mirrors the relation manager's `needs_operator` filter within the current tenant: the
     * SQL narrows to {@see BiometricTemplateSyncStatusEnum::operatorAttentionCandidateStates()}
     * and PHP applies the row predicate, which the encrypted reference and the command-log
     * delivery cap make unexpressible in the template query. The candidates are fetched
     * without their relations — the predicate never reads them — and the exhausted-delete set
     * is read once for the whole tenant instead of one `EXISTS` per candidate row.
     *
     * @return Builder<BiometricTemplate>
     */
    protected function operatorAttentionQuery(): Builder
    {
        /** @var Gym|null $gym */
        $gym = Filament::getTenant();

        $exhaustedHardwareDeleteKeys = $gym === null
            ? []
            : BiometricTemplate::exhaustedHardwareDeleteKeys($gym->id);

        $candidates = BiometricTemplate::query()
            ->where('gym_id', $gym?->id)
            ->whereIn('sync_status', BiometricTemplateSyncStatusEnum::operatorAttentionCandidateStates())
            ->get();

        /** @var array<int, int> $ids */
        $ids = [];

        foreach ($candidates as $candidate) {
            if (! $candidate->requiresOperatorAttention($exhaustedHardwareDeleteKeys)) {
                continue;
            }

            $id = $candidate->getKey();

            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return BiometricTemplate::query()
            ->whereIn('id', $ids === [] ? [-1] : $ids)
            ->with(['enrolledByDevice', 'gymMember']);
    }
}
