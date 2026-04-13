<?php

namespace App\Filament\Admin\Pages;

use App\Models\Gym;
use App\Traits\HasTenantAccess;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use LaraZeus\Qr\Facades\Qr;

class QRCodeManager extends Page implements HasActions, HasForms
{
    use HasTenantAccess, InteractsWithActions, InteractsWithForms;

    protected static string $view = 'filament.admin.pages.qr-code-manager';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'QR Code';

    protected static ?string $slug = 'qr-code';

    protected ?string $heading = 'QR Code Attendance';

    public ?array $data = [];

    public Gym $gym;

    public bool $hasQrCode = false;

    public function mount(): void
    {
        $this->gym = $this->getGym();
        $this->hasQrCode = ! empty($this->gym->qr_code_token);
    }

    public function generateQrCode(): void
    {
        $this->gym->generateQrCodeToken();
        $this->hasQrCode = true;
        $this->data = [
            'qr_code_url' => $this->gym->getQrCodeUrl(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Attendance QR Code')
                ->description('QR code for member check-in. Members can scan this to check in to your gym.')
                ->schema([
                    // Show message when QR not generated
                    Forms\Components\Placeholder::make('no_qr_message')
                        ->label('')
                        ->hidden(fn () => $this->hasQrCode)
                        ->content(function () {
                            return new HtmlString('
                                <div class="flex flex-col items-center gap-4 py-8">
                                    <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center">
                                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-lg font-medium text-gray-900">QR Code Not Generated</p>
                                        <p class="text-sm text-gray-500 mt-1">Generate a QR code to allow members to check in by scanning.</p>
                                    </div>
                                </div>
                            ');
                        }),
                    // Generate button
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('generate')
                            ->label('Generate QR Code')
                            ->color('primary')
                            ->icon('heroicon-o-qr-code')
                            ->hidden(fn () => $this->hasQrCode)
                            ->action(function () {
                                $this->generateQrCode();
                            }),
                    ])
                        ->alignCenter(),
                    // QR Code display (when generated)
                    Forms\Components\Placeholder::make('qr_display')
                        ->label('')
                        ->hidden(fn () => ! $this->hasQrCode)
                        ->content(function () {
                            if (! $this->hasQrCode || empty($this->gym->qr_code_token)) {
                                return new HtmlString('<p class="text-gray-500">No QR code generated yet.</p>');
                            }

                            $url = $this->gym->getQrCodeUrl();

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
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('regenerate')
                            ->label('Regenerate QR Code')
                            ->color('warning')
                            ->icon('heroicon-o-arrow-path')
                            ->hidden(fn () => ! $this->hasQrCode)
                            ->requiresConfirmation()
                            ->modalHeading('Regenerate QR Code?')
                            ->modalDescription('This will generate a new QR code. The old URL will no longer work.')
                            ->action(function () {
                                $this->gym->qr_code_token = null;
                                $this->gym->generateQrCodeToken();
                                $this->hasQrCode = true;
                                $this->data = [
                                    'qr_code_url' => $this->gym->getQrCodeUrl(),
                                ];
                            }),
                    ])
                        ->alignCenter(),
                ]),
        ]);
    }
}
