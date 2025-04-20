<?php

namespace App\Filament\Resources\GymMemberResource\Pages;

use App\Filament\Resources\GymMemberResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Log;

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
                ->action(fn() => $this->redirect(GymMemberResource::getUrl('index')))
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
        // \Log::info('After create, add_payment:', ['add_payment' => $this->addPayment]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->addPayment
            ? GymMemberResource::getUrl('edit', ['record' => $this->record->id]) . '#relation-manager-payments'
            : GymMemberResource::getUrl('index');
    }
}
