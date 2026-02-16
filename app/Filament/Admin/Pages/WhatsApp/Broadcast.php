<?php

namespace App\Filament\Admin\Pages\WhatsApp;

use App\Models\GymMember;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use App\Services\TwilioWhatsAppService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class Broadcast extends Page implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Broadcast';

    protected static ?string $navigationGroup = 'Communication';

    protected static string $view = 'filament.tenant.pages.whatsapp-broadcast';

    public array $data = [];

    public array $templates = [];

    public array $selectedTemplate = [];

    public array $targetMembers = [];

    public int $targetCount = 0;

    public array $messagePreview = [];

    public ?int $scheduledHour = null;

    public function mount(): void
    {
        $this->loadTemplates();
        $this->initializeForm();
    }

    private function loadTemplates(): void
    {
        $this->templates = WhatsAppTemplate::where('gym_id', $this->getGymId())
            ->where('status', 'approved')
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    private function initializeForm(): void
    {
        $this->data = [
            'template_id' => '',
            'variables' => [],
            'target_filter' => 'active',
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Rate Limit Quota Display
                Forms\Components\Section::make('Broadcast Quota')
                    ->schema([
                        Forms\Components\Placeholder::make('quota_info')
                            ->label('')
                            ->content(function () {
                                $gymId = $this->getGymId();
                                $todayCount = WhatsAppBroadcast::where('gym_id', $gymId)
                                    ->whereDate('created_at', today())
                                    ->count();
                                $remaining = max(0, 2 - $todayCount);

                                $lastBroadcast = WhatsAppBroadcast::where('gym_id', $gymId)
                                    ->latest()
                                    ->first();

                                $cooldownText = '';
                                if ($lastBroadcast && $lastBroadcast->created_at->gt(now()->subHour())) {
                                    $nextAllowed = $lastBroadcast->created_at->addHour();
                                    $minutesLeft = (int) now()->diffInMinutes($nextAllowed, false);
                                    $cooldownText = "<div class='text-amber-600 dark:text-amber-400 text-xs mt-1'>⏱ Next broadcast available in {$minutesLeft} minute(s)</div>";
                                }

                                $statusColor = $remaining > 0 ? 'emerald' : 'red';

                                return new HtmlString("
                                    <div class='flex items-center gap-4'>
                                        <div class='flex items-center gap-2'>
                                            <span class='text-sm text-gray-600 dark:text-gray-400'>Today's Quota:</span>
                                            <span class='font-bold text-{$statusColor}-600 dark:text-{$statusColor}-400'>{$remaining}/2 remaining</span>
                                        </div>
                                    </div>
                                    {$cooldownText}
                                ");
                            }),
                    ])
                    ->compact(),

                Forms\Components\Section::make('Select Template')
                    ->schema([
                        Forms\Components\Select::make('template_id')
                            ->label('Choose Template')
                            ->options(fn () => collect($this->templates)->pluck('display_name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->updateTemplatePreview()),
                    ]),

                Forms\Components\Section::make('Template Variables')
                    ->visible(fn () => ! empty($this->selectedTemplate))
                    ->schema([
                        Forms\Components\Placeholder::make('template_body')
                            ->label('Template Preview')
                            ->content(new HtmlString('<pre class="whitespace-pre-wrap font-mono text-sm">' . ($this->selectedTemplate['body'] ?? '') . '</pre>')),

                        Forms\Components\Grid::make(2)->schema(
                            collect($this->selectedTemplate['variables'] ?? [])->map(function ($variable, $index) {
                                return Forms\Components\TextInput::make('variables.' . $variable['name'])
                                    ->label(($variable['example'] ?? 'Variable ' . ($index + 1)))
                                    ->required();
                            })->toArray()
                        ),
                    ]),

                Forms\Components\Section::make('Recipients')
                    ->schema([
                        Forms\Components\Radio::make('target_filter')
                            ->label('Send to')
                            ->options([
                                'active' => 'All Active Members (' . $this->getActiveMemberCount() . ')',
                            ])
                            ->default('active')
                            ->live(),

                        Forms\Components\Placeholder::make('target_count')
                            ->label('Estimated Recipients')
                            ->content(fn () => new HtmlString('<strong>' . number_format($this->targetCount) . '</strong> members')),
                    ]),

                Forms\Components\Section::make('Schedule')
                    ->schema([
                        Forms\Components\Radio::make('schedule_type')
                            ->label('When to send')
                            ->options([
                                'now' => 'Send Now',
                                'later' => 'Schedule for Later',
                            ])
                            ->default('now')
                            ->live(),

                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Schedule Date & Time')
                            ->visible(fn () => ($this->data['schedule_type'] ?? 'now') === 'later')
                            ->minDate(now())
                            ->required(),
                    ]),

                Forms\Components\Section::make('Confirm')
                    ->schema([
                        Forms\Components\Placeholder::make('credits_info')
                            ->label('Credits Required')
                            ->content(new HtmlString('<strong>' . number_format($this->targetCount) . '</strong> credits will be deducted from your balance.')),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('sendBroadcast')
                                ->label('Send Broadcast')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalHeading('Confirm Broadcast')
                                ->modalDescription(fn () => new HtmlString('
                                    <p>This will send a message to <strong>' . number_format($this->targetCount) . '</strong> members.</p>
                                    <p class="mt-2"><strong>' . number_format($this->targetCount) . '</strong> credits will be deducted.</p>
                                '))
                                ->action(fn () => $this->sendBroadcast()),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    private function updateTemplatePreview(): void
    {
        $templateId = $this->data['template_id'] ?? null;

        if ($templateId) {
            $template = WhatsAppTemplate::find($templateId);
            $this->selectedTemplate = $template?->toArray() ?? [];
            $this->targetCount = $this->getActiveMemberCount();
        } else {
            $this->selectedTemplate = [];
            $this->targetCount = 0;
        }
    }

    private function getActiveMemberCount(): int
    {
        return GymMember::where('gym_id', $this->getGymId())
            // ->where('status', 'active')
            ->count();
    }

    private function getActiveMembers(): \Illuminate\Database\Eloquent\Collection
    {
        return GymMember::where('gym_id', $this->getGymId())
            // ->where('status', 'active')
            ->get();
    }

    private function sendBroadcast(): void
    {
        try {
            $templateId = $this->data['template_id'] ?? null;
            $variables = $this->data['variables'] ?? [];
            $scheduleType = $this->data['schedule_type'] ?? 'now';
            $scheduledAt = $this->data['scheduled_at'] ?? null;

            if (! $templateId) {
                throw new \Exception('Please select a template');
            }

            if ($this->targetCount === 0) {
                throw new \Exception('No recipients found');
            }

            // ── Rate Limiting ──────────────────────────────────
            $gymId = $this->getGymId();
            $todayCount = WhatsAppBroadcast::where('gym_id', $gymId)
                ->whereDate('created_at', today())
                ->count();

            if ($todayCount >= 2) {
                throw new \Exception('Daily broadcast limit reached. You can send a maximum of 2 broadcasts per day. Try again tomorrow.');
            }

            $lastBroadcast = WhatsAppBroadcast::where('gym_id', $gymId)
                ->latest()
                ->first();

            if ($lastBroadcast && $lastBroadcast->created_at->gt(now()->subHour())) {
                $nextAllowed = $lastBroadcast->created_at->addHour();
                $minutesLeft = (int) now()->diffInMinutes($nextAllowed, false);
                throw new \Exception("Please wait {$minutesLeft} more minute(s) before sending another broadcast. Minimum gap is 1 hour between broadcasts.");
            }
            // ── End Rate Limiting ──────────────────────────────

            $creditBalance = WhatsAppMessageCredit::where('gym_id', $this->getGymId())->first();

            if (! $creditBalance || ! $creditBalance->canSendMessage($this->targetCount)) {
                throw new \Exception('Insufficient credits. You need ' . number_format($this->targetCount) . ' credits.');
            }

            $template = WhatsAppTemplate::findOrFail($templateId);

            DB::beginTransaction();

            $broadcast = WhatsAppBroadcast::create([
                'gym_id' => $this->getGymId(),
                'whatsapp_template_id' => $templateId,
                'title' => 'Broadcast: ' . $template->display_name,
                'target_filters' => ['status' => 'active'],
                'target_count' => $this->targetCount,
                'status' => $scheduleType === 'now' ? 'sending' : 'scheduled',
                'scheduled_at' => $scheduleType === 'later' ? $scheduledAt : null,
                'message_variables' => $variables,
            ]);

            $members = $this->getActiveMembers();

            foreach ($members as $member) {
                WhatsAppBroadcastRecipient::create([
                    'whatsapp_broadcast_id' => $broadcast->id,
                    'member_id' => $member->id,
                    'phone_number' => $member->phone ?? '',
                    'member_name' => $member->name ?? 'Member',
                    'status' => 'pending',
                ]);
            }

            if ($scheduleType === 'now') {
                $this->processBroadcast($broadcast);
            }

            DB::commit();

            Notification::make()
                ->title($scheduleType === 'now' ? 'Broadcast Started!' : 'Broadcast Scheduled!')
                ->body($scheduleType === 'now'
                    ? 'Sending messages to ' . number_format($this->targetCount) . ' members.'
                    : 'Scheduled for ' . $scheduledAt->format('M d, Y g:i A'))
                ->success()
                ->send();

            $this->initializeForm();
            $this->loadTemplates();

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('Broadcast Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function processBroadcast(WhatsAppBroadcast $broadcast): void
    {
        $service = new TwilioWhatsAppService((string) $this->getGymId());
        $service->sendBroadcast($broadcast->id);
    }

    public function refresh(): void
    {
        $this->loadTemplates();
        $this->updateTemplatePreview();
    }

    protected function getGymId(): int
    {
        return Filament::getTenant()->id;
    }

    protected function getGym()
    {
        return Filament::getTenant();
    }
}
