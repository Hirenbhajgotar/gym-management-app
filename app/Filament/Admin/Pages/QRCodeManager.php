<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use App\Enums\DefaultRole;
use App\Filament\BasePages\BasePage;
use App\Models\Gym;
use App\Traits\HasTenantAccess;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use LaraZeus\Qr\Facades\Qr;

class QRCodeManager extends BasePage implements HasActions, HasForms
{
    use HasTenantAccess, InteractsWithActions, InteractsWithForms;

    protected static string $view = 'filament.admin.pages.qr-code-manager';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'QR Code';

    protected static ?string $slug = 'qr-code';

    protected ?string $heading = 'QR Code Attendance';

    public ?array $data = [];

    public Gym $gym;

    public bool $hasCheckInQrCode = false;

    public bool $hasCheckoutQrCode = false;

    public function defaultRolePermissions(): array
    {
        return [
            DefaultRole::MANAGER->value => ['page'],
            DefaultRole::RECEPTIONIST->value => ['page'],
            DefaultRole::TRAINER->value => [],
        ];
    }

    public function mount(): void
    {
        $this->gym = $this->getGym();
        $this->hasCheckInQrCode = filled($this->gym->qr_code_token);
        $this->hasCheckoutQrCode = filled($this->gym->qr_code_checkout_token);
    }

    public function generateCheckInQrCode(): void
    {
        $this->gym->generateCheckInQrCodeToken();
        $this->hasCheckInQrCode = true;
        $this->data = [
            'qr_code_url' => $this->gym->getCheckInQrCodeUrl(),
        ];
    }

    public function generateCheckoutQrCode(): void
    {
        $this->gym->generateCheckoutQrCodeToken();
        $this->hasCheckoutQrCode = true;
        $this->data = [
            'qr_code_checkout_url' => $this->gym->getCheckoutQrCodeUrl(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Grid::make(2)
                ->schema([
                    // Check-in QR Code Section
                    Section::make('Check-In QR Code')
                        ->description('QR code for member check-in.')
                        ->schema([
                            // Show message when QR not generated
                            Placeholder::make('no_qr_message')
                                ->label('')
                                ->hidden(fn () => $this->hasCheckInQrCode)
                                ->content(function () {
                                    return new HtmlString('
                                        <div class="flex flex-col items-center gap-4 py-8">
                                            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center">
                                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                </svg>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-lg font-medium text-gray-900">Check-In QR Code Not Generated</p>
                                                <p class="text-sm text-gray-500 mt-1">Generate a QR code to allow members to check in by scanning.</p>
                                            </div>
                                        </div>
                                    ');
                                }),
                            // Generate button
                            Actions::make([
                                Action::make('generate')
                                    ->label('Generate Check-In QR Code')
                                    ->icon('heroicon-o-qr-code')
                                    ->hidden(fn () => $this->hasCheckInQrCode)
                                    ->action(function () {
                                        $this->generateCheckInQrCode();
                                    }),
                            ])
                                ->alignCenter(),
                            // QR Code display (when generated)
                            Placeholder::make('qr_display')
                                ->label('')
                                ->hidden(fn () => ! $this->hasCheckInQrCode)
                                ->content(function () {
                                    if (! $this->hasCheckInQrCode || blank($this->gym->qr_code_token)) {
                                        return new HtmlString('<p class="text-gray-500">No QR code generated yet.</p>');
                                    }

                                    $url = $this->gym->getCheckInQrCodeUrl();

                                    // Use larazeus/qr to render with download option
                                    $qrHtml = Qr::render(
                                        data: $url,
                                        statePath: 'check_in_qr_code',
                                        downloadable: true
                                    );

                                    return new HtmlString('
                                        <div class="flex flex-col items-center gap-4">
                                            <div class="bg-white p-4 rounded-lg border">
                                                ' . $qrHtml . '
                                            </div>
                                            <p class="text-sm text-gray-500">Scan this code with your phone camera to check in</p>
                                        </div>
                                    ');
                                }),
                            // Actions when QR exists
                            Actions::make([
                                Action::make('regenerate')
                                    ->label('Regenerate')
                                    ->color('primary')
                                    ->icon('heroicon-o-arrow-path')
                                    ->hidden(fn () => ! $this->hasCheckInQrCode)
                                    ->requiresConfirmation()
                                    ->modalHeading('Regenerate Check-In QR Code?')
                                    ->modalDescription('This will generate a new QR code. The old URL will no longer work.')
                                    ->action(function () {
                                        $this->gym->qr_code_token = null;
                                        $this->gym->generateCheckInQrCodeToken();
                                        $this->hasCheckInQrCode = true;
                                        $this->data = [
                                            'qr_code_url' => $this->gym->getCheckInQrCodeUrl(),
                                        ];
                                    }),
                            ])
                                ->alignCenter(),
                        ])
                        ->columnSpan(1),

                    // Check-out QR Code Section
                    Section::make('Check-Out QR Code')
                        ->description('QR code for member check-out.')
                        ->schema([
                            // Show message when QR not generated
                            Placeholder::make('no_checkout_qr_message')
                                ->label('')
                                ->hidden(fn () => $this->hasCheckoutQrCode)
                                ->content(function () {
                                    return new HtmlString('
                                        <div class="flex flex-col items-center gap-4 py-8">
                                            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center">
                                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                </svg>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-lg font-medium text-gray-900">Check-Out QR Code Not Generated</p>
                                                <p class="text-sm text-gray-500 mt-1">Generate a QR code to allow members to check out by scanning.</p>
                                            </div>
                                        </div>
                                    ');
                                }),
                            // Generate button
                            Actions::make([
                                Action::make('generate_checkout')
                                    ->label('Generate Check-Out QR Code')
                                    ->icon('heroicon-o-qr-code')
                                    ->hidden(fn () => $this->hasCheckoutQrCode)
                                    ->action(function () {
                                        $this->generateCheckoutQrCode();
                                    }),
                            ])
                                ->alignCenter(),
                            // QR Code display (when generated)
                            Placeholder::make('checkout_qr_display')
                                ->label('')
                                ->hidden(fn () => ! $this->hasCheckoutQrCode)
                                ->content(function () {
                                    if (! $this->hasCheckoutQrCode || blank($this->gym->qr_code_checkout_token)) {
                                        return new HtmlString('<p class="text-gray-500">No QR code generated yet.</p>');
                                    }

                                    $url = $this->gym->getCheckoutQrCodeUrl();

                                    // Use larazeus/qr to render with download option
                                    $qrHtml = Qr::render(
                                        data: $url,
                                        statePath: 'check_out_qr_code',
                                        downloadable: true
                                    );

                                    return new HtmlString('
                                        <div class="flex flex-col items-center gap-4">
                                            <div class="bg-white p-4 rounded-lg border">
                                                ' . $qrHtml . '
                                            </div>
                                            <p class="text-sm text-gray-500">Scan this code with your phone camera to check out</p>
                                        </div>
                                    ');
                                }),
                            // Actions when QR exists
                            Actions::make([
                                Action::make('regenerate_checkout')
                                    ->label('Regenerate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->hidden(fn () => ! $this->hasCheckoutQrCode)
                                    ->requiresConfirmation()
                                    ->modalHeading('Regenerate Check-Out QR Code?')
                                    ->modalDescription('This will generate a new QR code. The old URL will no longer work.')
                                    ->action(function () {
                                        $this->gym->qr_code_checkout_token = null;
                                        $this->gym->generateCheckoutQrCodeToken();
                                        $this->hasCheckoutQrCode = true;
                                        $this->data = [
                                            'qr_code_checkout_url' => $this->gym->getCheckoutQrCodeUrl(),
                                        ];
                                    }),
                            ])
                                ->alignCenter(),
                        ])
                        ->columnSpan(1),
                ]),
        ]);
    }
}
