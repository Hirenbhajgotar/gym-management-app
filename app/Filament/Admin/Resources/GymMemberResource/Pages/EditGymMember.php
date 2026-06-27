<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\GymMemberResource\Pages;

use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\Admin\Resources\GymMemberResource\Widgets\MemberProgressChart;
use App\Models\GymMember;
use App\Models\User;
use App\Notifications\MemberActionNotification;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditGymMember extends EditRecord
{
    protected static string $resource = GymMemberResource::class;

    protected function getHeaderActions(): array
    {

        return [
            Action::make('view_invoice')
                ->label('View Invoice')
                ->icon('heroicon-o-document-text')
                ->url(fn (GymMember $member) => $member->latestPayment ? route('member.invoice.show', $member->latestPayment) : null)
                ->openUrlInNewTab()
                ->visible(fn (GymMember $member) => $member->latestPayment()->exists() && $member->latestPayment->invoice_path),

            DeleteAction::make()
                ->after(function (GymMember $member) {
                    try {
                        // Notify the member if they have a user account
                        if ($member->user) {
                            /**
                             * @var User $user
                             */
                            $user = $member->user;
                            $user->notify(new MemberActionNotification('deleted', $member));
                        }

                        // Notify the gym owner
                        /**
                         * @var User|null $owner
                         */
                        $owner = $member->user;
                        if ($owner) {
                            $owner->notify(new MemberActionNotification('deleted', $member));
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to send member deletion notification: ' . $e->getMessage(), [
                            'member_id' => $member->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }),
        ];
    }

    public function getFooterWidgets(): array
    {
        return [
            MemberProgressChart::class,
        ];
    }
}
