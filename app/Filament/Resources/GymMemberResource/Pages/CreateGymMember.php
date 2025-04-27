<?php

namespace App\Filament\Resources\GymMemberResource\Pages;

use App\Filament\Resources\GymMemberResource;
use App\Mail\WelcomeMemberMail;
use App\Notifications\MemberActionNotification;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateGymMember extends CreateRecord
{
    protected static string $resource = GymMemberResource::class;

    protected function getFormSchema(): array
    {
        $parentSchema = parent::getFormSchema();
        $parentSchema[] = Forms\Components\Section::make('Payment Option')
            ->icon('heroicon-o-currency-dollar')
            ->description('Optionally add an initial payment after creating the member')
            ->schema([
                Forms\Components\Checkbox::make('add_payment')
                    ->label('Add initial payment after creation')
                    ->default(true)
                    ->live(),
            ])
            ->collapsible();

        return $parentSchema;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Save Member'),
            Action::make('cancel')
                ->label('Cancel')
                ->action(fn () => $this->redirect(GymMemberResource::getUrl('index')))
                ->color('gray'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['add_payment'] = $data['add_payment'] ?? true;
        $this->addPayment = $data['add_payment'];
        unset($data['add_payment']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->user) {
            $this->record->user->notify(new MemberActionNotification('created', $this->record));
        } else {
            // Fallback: Notify current authenticated user
            auth()->user()->notify(new MemberActionNotification('created', $this->record));
        }

        // Send email to the gym member
        try {
            if (empty($this->record->email)) {
                Log::warning('No email provided for gym member', [
                    'member_id' => $this->record->id,
                ]);

                return;
            }

            // Ensure required member data is available
            if (! $this->record->membershipPlan || ! $this->record->membership_start || ! $this->record->membership_end) {
                Log::error('Missing required member data for email notification', [
                    'member_id' => $this->record->id,
                    'has_plan' => isset($this->record->membershipPlan),
                    'has_start_date' => isset($this->record->membership_start),
                    'has_end_date' => isset($this->record->membership_end),
                ]);

                return;
            }

            Mail::to($this->record->email)->send(new WelcomeMemberMail($this->record));

        } catch (\Exception $e) {
            Log::error('Failed to send welcome email to member', [
                'member_id' => $this->record->id,
                'email' => $this->record->email ?? 'not_set',
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->addPayment
            ? GymMemberResource::getUrl('edit', ['record' => $this->record->id]).'#relation-manager-payments'
            : GymMemberResource::getUrl('index');
    }
}
