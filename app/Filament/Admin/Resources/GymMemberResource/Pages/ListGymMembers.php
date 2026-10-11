<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Admin\Actions\RequestBiometricConsentAction;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\Exports\GymMemberExporter;
use App\Filament\Imports\GymMemberImporter;
use App\Imports\GymMemberImport;
use App\Jobs\FanOutBiometricConsentRequestsJob;
use App\Models\GymMember;
use App\Models\User;
use App\Services\Biometric\BiometricConsentDispatchReport;
use App\Services\Biometric\BiometricConsentEligibility;
use App\Traits\HasTenantAccess;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction as ActionsImportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListGymMembers extends ListRecords
{
    use HasTenantAccess;

    protected static string $resource = GymMemberResource::class;

    private ?int $eligibleConsentCount = null;

    private ?int $eligibleConsentGymId = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make(RequestBiometricConsentAction::HEADER_ACTION)
                ->label('Request Biometric Consent')
                ->tooltip('Send biometric consent notification to all active unconsented members')
                ->icon('heroicon-o-finger-print')
                ->color('primary')
                ->modalHeading('Send Biometric Consent Request')
                ->modalDescription(fn (): string => RequestBiometricConsentAction::headerDescription(
                    (int) $this->getGymId(),
                    $this->eligibleConsentCount(),
                ))
                ->modalSubmitActionLabel('Send Consent Requests')
                ->authorize(fn (): bool => RequestBiometricConsentAction::canAuthorize())
                ->form(RequestBiometricConsentAction::formSchema())
                ->action(function (array $data): void {
                    // Defence in depth. ->authorize() feeds Filament's isHidden(),
                    // which isDisabled() folds in, and both mountAction() and
                    // callMountedAction() refuse a disabled action — so an
                    // unauthorized operator cannot reach this handler at all.
                    // Repeating the check costs one policy evaluation and keeps the
                    // guard in the same place as the one in dispatch().
                    throw_unless(
                        RequestBiometricConsentAction::canAuthorize(),
                        AuthorizationException::class,
                        'Unauthorized biometric consent request.'
                    );

                    ['sendEmail' => $sendEmail, 'sendWhatsApp' => $sendWhatsApp] =
                        RequestBiometricConsentAction::channelFlags($data);

                    if (! $sendEmail && ! $sendWhatsApp) {
                        RequestBiometricConsentAction::notifyNoChannelSelected();

                        return;
                    }

                    $gymId = (int) $this->getGymId();

                    if (! RequestBiometricConsentAction::throttle($gymId)) {
                        return;
                    }

                    // Re-taken at submit time rather than reused from the modal
                    // description: the membership set can change between the two
                    // requests, and this count is a gate, not copy.
                    $eligible = $this->eligibleConsentCount();

                    if ($eligible === 0) {
                        Notification::make()
                            ->title('No eligible members')
                            ->body('No active members currently require biometric consent.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $broadcastId = RequestBiometricConsentAction::newBroadcastId();

                    // One queue push, not one per member: the fan-out runs in the
                    // worker so the HTTP thread never loops the whole gym (C4).
                    dispatch(new FanOutBiometricConsentRequestsJob(
                        gymId: $gymId,
                        actorId: Auth::id() === null ? null : (int) Auth::id(),
                        sendEmail: $sendEmail,
                        sendWhatsApp: $sendWhatsApp,
                        broadcastId: $broadcastId,
                    ));

                    // This is the larger broadcast, so it is audited here rather
                    // than left to the worker's completion log alone. The
                    // dispatch count is unknown until the worker runs, so the
                    // eligible count stands in and the job writes the outcome
                    // against the same broadcast id.
                    RequestBiometricConsentAction::audit(
                        RequestBiometricConsentAction::ACTION_FAN_OUT,
                        $sendEmail,
                        $sendWhatsApp,
                        new BiometricConsentDispatchReport(
                            selected: $eligible,
                            dispatched: 0,
                            ineligible: 0,
                            unauthorized: 0,
                        ),
                        $broadcastId,
                    );

                    RequestBiometricConsentAction::notifyFanOutQueued($eligible);
                }),

            ExportAction::make()
                ->label('Export Members')
                ->exporter(GymMemberExporter::class)
                ->formats([ExportFormat::Xlsx]),

            ImportAction::make()
                ->label('Import Members')
                ->importer(GymMemberImport::class)
                ->use(GymMember::class)
                ->modalHeading('Import Members')
                ->sampleFile(
                    urlOrData: url('import/templates/xls/member-sample.xlsx'),
                    fileName: 'member-sample.xlsx',
                    sampleButtonLabel: 'Download Sample File'
                )
                ->visible(config()->boolean('app.custom_import_action')),

            ActionsImportAction::make()
                ->importer(GymMemberImporter::class)
                ->label('Import Members')
                ->icon('heroicon-o-arrow-down-tray')
                ->options([
                    'tenantId' => $this->getGymId(),
                ])
                ->visible(! config()->boolean('app.custom_import_action')),

            CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->color('primary'),
        ];
    }

    public function getListeners(): array
    {
        return [
            "echo-private:App.Models.User.{$this->getUser()->id},.ImportCompleted" => '$refresh',
        ];
    }

    /**
     * Members eligible for a consent request, counted at most once per render.
     *
     * Filament re-evaluates the header action's modal description on every
     * re-render of the list page — every filter change, every table sort, every
     * wire round trip while the modal is open — and each evaluation used to run
     * an eligibility COUNT(*) across the whole gym. A Livewire component is
     * rebuilt per request, so this memo lives exactly as long as the request that
     * can change the membership set underneath it, and the submit path takes a
     * fresh one rather than trusting the copy on the modal.
     */
    private function eligibleConsentCount(): int
    {
        $gymId = (int) $this->getGymId();

        if ($this->eligibleConsentCount === null || $this->eligibleConsentGymId !== $gymId) {
            $this->eligibleConsentCount = BiometricConsentEligibility::constrain(GymMember::query(), $gymId)->count();
            $this->eligibleConsentGymId = $gymId;
        }

        return $this->eligibleConsentCount;
    }

    public function getUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->membershipActive();
                    $query->notArchived();
                }),
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->membershipExpired();
                    $query->notArchived();
                }),
            'archived' => Tab::make('Archived')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->archived();
                }),
        ];
    }
}
