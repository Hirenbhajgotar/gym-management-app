<?php

namespace App\Filament\Admin\Pages\WhatsApp;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use App\Services\TwilioWhatsAppService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class Broadcast extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions, InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Bulk SMS';

    protected static ?string $title = 'Bulk SMS';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static string $view = 'filament.tenant.pages.whatsapp-broadcast';

    protected static ?int $navigationSort = 5;

    public bool $filtersApplied = false;

    public array $data = [];

    public array $templates = [];

    public array $selectedTemplate = [];

    public array $targetMembers = [];

    public int $targetCount = 0;

    public array $messagePreview = [];

    public ?int $scheduledHour = null;

    public array $filters = [
        'plans' => [],
        'trainers' => [],
        'membership_type' => [],
        'payment_status' => [],
    ];

    public array $selectedMemberIds = [];

    public array $availablePlans = [];

    public array $availableTrainers = [];

    public function mount(): void
    {
        $this->loadTemplates();
        $this->initializeForm();
    }

    private function loadTemplates(): void
    {
        $this->templates = WhatsAppTemplate::where('gym_id', $this->getGymId())
            ->where('status', WhatsAppTemplateStatusEnum::APPROVED)
            ->isActive()
            ->get()
            ->toArray();
    }

    private function initializeForm(): void
    {
        $this->loadFilters();

        $this->data = [
            'template_id' => '',
            'variables' => [],
            'recipient_type' => 'all',
        ];

        $this->targetCount = $this->getAllMemberCount();
    }

    private function loadFilters(): void
    {
        $gymId = $this->getGymId();

        $this->availablePlans = MembershipPlan::where('gym_id', $gymId)
            ->isActive()
            ->get()
            ->toArray();

        $this->availableTrainers = Trainer::where('gym_id', $gymId)
            ->get()
            ->toArray();
    }

    private function getFilteredMembers(): Builder
    {
        $query = GymMember::query();

        $data = $this->data;

        if (! isset($data['filters']) || empty($data['filters'])) {
            return $query;
        }

        $filters = $data['filters'];

        if (isset($filters['membership_type']) && $filters['membership_type']) {
            if ($filters['membership_type'] === 'active') {
                $query->membershipActive();
            }
            if ($filters['membership_type'] === 'inactive') {
                $query->membershipExpired();
            }
        }

        if (isset($filters['plans']) && $filters['plans']) {
            $query->where('gym_membership_plan_id', $filters['plans']);
        }

        if (isset($filters['payment_status']) && $filters['payment_status']) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['trainers']) && $filters['trainers']) {
            $query->where('trainer_id', $filters['trainers']);
        }

        return $query;
    }

    private function getAllMemberCount(): int
    {
        return GymMember::where('gym_id', $this->getGymId())->count();
    }

    private function getFilteredMemberCount(): int
    {
        return $this->getFilteredMembers()->count();
    }

    public function updatedFilters(): void
    {
        $this->filtersApplied = true;
        $this->targetCount = $this->getFilteredMemberCount();
        $this->selectedMemberIds = $this->getFilteredMembers()->pluck('id')->toArray();
    }

    public function clearFilters(): void
    {
        $this->data['filters'] = [
            'plans' => null,
            'trainers' => null,
            'membership_type' => null,
            'payment_status' => null,
        ];

        $this->filtersApplied = false;
        $this->form->fill($this->data);
        $this->resetTableSearch();
        $this->resetTable();
        $this->targetCount = $this->getAllMemberCount();
        $this->selectedMemberIds = $this->getFilteredMembers()->pluck('id')->toArray();
    }

    public function updatedRecipientType(): void
    {
        $recipientType = $this->data['recipient_type'] ?? 'all';

        $this->selectedMemberIds = $this->getFilteredMembers()->pluck('id')->toArray();
        if ($recipientType === 'all') {
            $this->clearFilters();
            $this->targetCount = $this->getAllMemberCount();
        } else {
            $this->targetCount = $this->getFilteredMemberCount();
        }
    }

    public function updatedSelectedMemberIds(): void
    {
        $this->targetCount = count($this->selectedMemberIds);
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

                Forms\Components\Section::make('Recipients')
                    ->schema([
                        Forms\Components\Radio::make('recipient_type')
                            ->label('Send to')
                            ->options([
                                'all' => 'All Members (' . $this->getAllMemberCount() . ')',
                                'filtered' => 'Filter & Select Members',
                            ])
                            ->default('all')
                            ->inline()
                            ->inlineLabel(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->updatedRecipientType()),

                        Forms\Components\Group::make()
                            ->visible(fn () => ($this->data['recipient_type'] ?? 'all') === 'filtered')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('filters.plans')
                                            ->label('Membership Plan')
                                            ->options(fn () => collect($this->availablePlans)->pluck('name', 'id'))
                                            ->placeholder('All Plans')
                                            ->live(),

                                        Forms\Components\Select::make('filters.trainers')
                                            ->label('Trainer')
                                            ->options(fn () => collect($this->availableTrainers)->pluck('name', 'id'))
                                            ->placeholder('All Trainers')
                                            ->live(),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('filters.membership_type')
                                            ->label('Active / Inactive')
                                            ->options([
                                                'active' => 'Active (Membership Valid)',
                                                'inactive' => 'Inactive (Membership Expired)',
                                            ])
                                            ->placeholder('All Members')
                                            ->live(),

                                        Forms\Components\Select::make('filters.payment_status')
                                            ->label('Payment Status')
                                            ->options([
                                                'pending' => 'Pending',
                                                'completed' => 'Completed',
                                                'failed' => 'Failed',
                                                'expired' => 'Expired',
                                            ])
                                            ->placeholder('All Payment Statuses')
                                            ->live(),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('clearFilters')
                                                ->label('Clear Filters')
                                                ->color('gray')
                                                ->icon('heroicon-o-x-mark')
                                                ->action(fn () => $this->clearFilters()),

                                            Forms\Components\Actions\Action::make('updateFilters')
                                                ->label('Apply Filters')
                                                ->icon('heroicon-o-funnel')
                                                ->action(fn () => $this->updatedFilters()),
                                        ]),
                                    ]),

                                ViewField::make('results_table')
                                    ->view('filament.tenant.pages.components.member-checklist')
                                    ->visible(fn () => $this->filtersApplied),
                            ]),

                        Forms\Components\Placeholder::make('target_count')
                            ->label('Estimated Recipients')
                            ->visible(fn () => ($this->data['recipient_type'] ?? 'all') === 'all')
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
                            ->inline()
                            ->inlineLabel(false)
                            ->live(),

                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Schedule Date & Time')
                            ->visible(fn (callable $get) => $get('schedule_type') === 'later')
                            ->minDate(now())
                            ->required(fn (callable $get) => $get('schedule_type') === 'later'),
                    ]),

                Forms\Components\Section::make('Confirm')
                    ->schema([
                        Forms\Components\Placeholder::make('credits_info')
                            ->label('Credits Required')
                            ->content(fn () => new HtmlString('<strong>' . number_format($this->targetCount) . '</strong> credits will be deducted from your balance.')),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('sendBroadcast')
                                ->label('Send Broadcast')
                                ->action(function () {
                                    $this->form->validate();
                                    $this->sendBroadcast();
                                }),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getFilteredMembers())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('membership_type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'premium' => 'warning',
                        'vip' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->date()
                    ->sortable(),
            ])
            ->emptyStateHeading('No members found')
            ->emptyStateDescription('Try adjusting your filters.');
    }

    private function updateTemplatePreview(): void
    {
        $templateId = $this->data['template_id'] ?? null;

        if ($templateId) {
            $template = WhatsAppTemplate::find($templateId);
            $templateArray = $template?->toArray() ?? [];

            $templateArray['formatted_body'] = $template?->formatted_body ?? $template?->body;

            $this->selectedTemplate = $templateArray;
        } else {
            $this->selectedTemplate = [];
            $this->targetCount = 0;
        }
    }

    private function sendBroadcast(): void
    {
        dd($this->data);
        try {
            $templateId = $this->data['template_id'] ?? null;
            $variables = $this->data['variables'] ?? [];
            $scheduleType = $this->data['schedule_type'] ?? 'now';
            $scheduledAt = $this->data['scheduled_at'] ?? null;

            if (! $templateId) {
                throw new \Exception('Please select a template');
            }

            if ($this->targetCount === 0) {
                throw new \Exception('No recipients found. Please select members or change recipient type.');
            }

            if (($this->data['recipient_type'] ?? 'all') === 'filtered' && empty($this->selectedMemberIds)) {
                throw new \Exception('Please select at least one member from the filtered results.');
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
                'target_filters' => $this->data['recipient_type'] === 'filtered'
                    ? $this->filters
                    : ['type' => 'all'],
                'target_count' => $this->targetCount,
                'status' => $scheduleType === 'now' ? 'sending' : 'scheduled',
                'scheduled_at' => $scheduleType === 'later' ? $scheduledAt : null,
                'message_variables' => $variables,
            ]);

            $recipientType = $this->data['recipient_type'] ?? 'all';

            if ($recipientType === 'all') {
                $members = GymMember::where('gym_id', $this->getGymId())->get();
            } else {
                $members = GymMember::whereIn('id', $this->selectedMemberIds)->get();
            }

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
