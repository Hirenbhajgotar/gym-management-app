<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\WhatsApp;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\BasePages\BasePage;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppTemplate;
use App\Providers\WhatsAppProviderFactory;
use App\Services\WhatsApp\BroadcastOrchestrator;
use App\Services\WhatsApp\BroadcastRateLimiter;
use App\Services\WhatsAppVariableResolver;
use Exception;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class Broadcast extends BasePage implements HasActions, HasForms, HasTable
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

    protected BroadcastOrchestrator $broadcastOrchestrator;

    public function mount(): void
    {
        $this->broadcastOrchestrator = resolve(BroadcastOrchestrator::class);
        $this->loadTemplates();
        $this->initializeForm();
    }

    public function defaultRolePermissions(): array
    {
        return [];
    }

    private function loadTemplates(): void
    {
        $query = WhatsAppTemplate::query()
            ->where('status', WhatsAppTemplateStatusEnum::APPROVED)
            ->isActive()
            ->where(function ($query) {
                $query->where('gym_id', $this->getGymId());

                if (config('services.twilio.use_sandbox')) {
                    $query->orWhereNull('gym_id');
                }
            });

        $this->templates = $query->get()->toArray();
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

        $this->availablePlans = MembershipPlan::query()->where('gym_id', $gymId)
            ->isActive()
            ->get()
            ->toArray();

        $this->availableTrainers = Trainer::query()->where('gym_id', $gymId)
            ->get()
            ->toArray();
    }

    private function getFilteredMembers(): Builder
    {
        $query = GymMember::query()->whereNotNull('phone')->where('phone', '!=', '');

        $data = $this->data;

        if (! isset($data['filters']) || blank($data['filters'])) {
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
        return GymMember::query()->where('gym_id', $this->getGymId())->count();
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
        // $this->selectedMemberIds = $this->getFilteredMembers()->pluck('id')->toArray();
        $this->selectedMemberIds = [];
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
                Split::make([
                    Section::make([
                        Select::make('template_id')
                            ->label('Choose Template')
                            ->options(fn () => collect($this->templates)->pluck('display_name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->updateTemplatePreview()),

                        // Variable Inputs (only for custom source variables)
                        Section::make('Message Variables')
                            ->description('Some variables are auto-filled from member data. Only custom variables need your input.')
                            ->schema(function () {
                                $templateId = $this->data['template_id'] ?? null;

                                if (! $templateId) {
                                    return [];
                                }

                                $template = WhatsAppTemplate::query()->find($templateId);

                                if (! $template) {
                                    return [];
                                }

                                $placeholders = $template->getVariablePlaceholders();

                                if (blank($placeholders)) {
                                    return [];
                                }

                                $fields = [];
                                $hasAutoResolved = false;
                                $autoResolvedLabels = [];

                                foreach ($placeholders as $key => $placeholder) {
                                    $source = $placeholder['source'] ?? 'custom';
                                    $label = $placeholder['placeholder'] ?? 'Variable ' . $key;

                                    if ($source !== 'custom') {
                                        $hasAutoResolved = true;
                                        $sources = WhatsAppVariableResolver::getAvailableDataSources();
                                        $sourceLabel = $sources[$source]['label'] ?? $source;
                                        $autoResolvedLabels[] = '{{' . $key . '}} ' . $label . ' — ' . $sourceLabel;

                                        continue;
                                    }

                                    $fields[] = TextInput::make('variables.' . $key)
                                        ->label('{{' . $key . '}} — ' . $label)
                                        ->placeholder($placeholder['example'] ?? '')
                                        ->required()
                                        ->helperText('This value will be the same for all recipients.');
                                }

                                if ($hasAutoResolved) {
                                    $autoList = collect($autoResolvedLabels)
                                        ->map(fn ($item) => '<li>' . e($item) . '</li>')
                                        ->implode('');

                                    array_unshift($fields, Placeholder::make('auto_resolved_info')
                                        ->label('')
                                        ->content(new HtmlString('
                                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/50 p-3 text-xs space-y-1">
                                            <div class="font-semibold text-emerald-700 dark:text-emerald-400">Auto-filled variables (from member data):</div>
                                            <ul class="text-emerald-600 dark:text-emerald-400 space-y-0.5 list-disc list-inside">' . $autoList . '</ul>
                                        </div>
                                    ')));
                                }

                                if (blank($fields)) {
                                    $fields[] = Placeholder::make('all_auto_resolved')
                                        ->label('')
                                        ->content(new HtmlString('
                                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/50 p-3 text-sm text-emerald-700 dark:text-emerald-400">
                                            All variables in this template are auto-filled from member data. No manual input needed.
                                        </div>
                                    '));
                                }

                                return $fields;
                            })
                            ->visible(fn () => filled($this->data['template_id']) && filled($this->selectedTemplate)),

                        Section::make('Recipients')
                            ->schema([
                                ToggleButtons::make('recipient_type')
                                    ->label('Send to')
                                    ->options([
                                        'all' => 'All Members (' . $this->targetCount . ')',
                                        'filtered' => 'Filter & Select Members',
                                    ])
                                    ->colors([
                                        'all' => 'info',
                                        'filtered' => 'info',
                                    ])
                                    // ->default('all')
                                    ->inline()
                                    ->inlineLabel(false)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->updatedRecipientType()),

                                Group::make()
                                    ->visible(fn () => ($this->data['recipient_type'] ?? 'all') === 'filtered')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('filters.plans')
                                                    ->label('Membership Plan')
                                                    ->options(fn () => collect($this->availablePlans)->pluck('name', 'id'))
                                                    ->placeholder('All Plans')
                                                    ->live(),

                                                Select::make('filters.trainers')
                                                    ->label('Trainer')
                                                    ->options(fn () => collect($this->availableTrainers)->pluck('name', 'id'))
                                                    ->placeholder('All Trainers')
                                                    ->live(),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                Select::make('filters.membership_type')
                                                    ->label('Active / Inactive')
                                                    ->options([
                                                        'active' => 'Active (Membership Valid)',
                                                        'inactive' => 'Inactive (Membership Expired)',
                                                    ])
                                                    ->placeholder('All Members')
                                                    ->live(),

                                                Select::make('filters.payment_status')
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

                                        Grid::make(2)
                                            ->schema([
                                                Actions::make([
                                                    Action::make('clearFilters')
                                                        ->label('Clear Filters')
                                                        ->color('gray')
                                                        ->icon('heroicon-o-x-mark')
                                                        ->action(fn () => $this->clearFilters()),

                                                    Action::make('updateFilters')
                                                        ->label('Apply Filters')
                                                        ->icon('heroicon-o-funnel')
                                                        ->action(fn () => $this->updatedFilters()),
                                                ]),
                                            ]),

                                        ViewField::make('results_table')
                                            ->view('filament.tenant.pages.components.member-checklist')
                                            ->visible(fn () => $this->filtersApplied),
                                    ]),

                                Placeholder::make('target_count')
                                    ->label('Estimated Recipients')
                                    ->visible(fn () => ($this->data['recipient_type'] ?? 'all') === 'all')
                                    ->content(fn () => new HtmlString('<strong>' . number_format($this->targetCount) . '</strong> members')),
                            ]),

                        Section::make('Schedule')
                            ->schema([
                                Radio::make('schedule_type')
                                    ->label('When to send')
                                    ->options([
                                        'now' => 'Send Now',
                                        'later' => 'Schedule for Later',
                                    ])
                                    ->default('now')
                                    ->inline()
                                    ->inlineLabel(false)
                                    ->live(),

                                DateTimePicker::make('scheduled_at')
                                    ->label('Schedule Date & Time')
                                    ->visible(fn (callable $get) => $get('schedule_type') === 'later')
                                    ->minDate(now())
                                    ->required(fn (callable $get) => $get('schedule_type') === 'later'),
                            ]),
                        Section::make('Confirm')
                            ->schema([
                                Placeholder::make('credits_info')
                                    ->label('Credits Required')
                                    ->content(fn () => new HtmlString('<strong>' . number_format($this->targetCount) . '</strong> credits will be deducted from your balance.')),

                                Actions::make([
                                    Action::make('sendTestMessage')
                                        ->label('Send Message')
                                        ->color('info')
                                        ->icon('heroicon-o-phone')
                                        ->visible(fn () => config('services.twilio.use_sandbox'))
                                        ->action(function () {
                                            $this->sendTestMessage();
                                        }),
                                    Action::make('sendBroadcast')
                                        ->label('Send Broadcast')
                                        ->visible(fn () => ! config('services.twilio.use_sandbox'))
                                        ->action(function () {
                                            $this->form->validate();
                                            $this->sendBroadcast();
                                        }),
                                ]),
                            ]),
                    ]),
                    Section::make([
                        // Rate Limit Quota Display
                        Section::make('Broadcast Quota')
                            ->schema([
                                Placeholder::make('quota_info')
                                    ->label('')
                                    ->content(function () {
                                        $gymId = $this->getGymId();

                                        $rateLimiter = resolve(BroadcastRateLimiter::class);

                                        $quota = $rateLimiter->getQuota($gymId);

                                        $remaining = $quota['remaining'];

                                        $lastBroadcast = WhatsAppBroadcast::query()->where('gym_id', $gymId)
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
                    ])->grow(false),
                ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getFilteredMembers())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('membership_type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'premium' => 'warning',
                        'vip' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
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
            $template = WhatsAppTemplate::query()->find($templateId);
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
        try {
            $this->form->validate();

            $data = [
                'template_id' => $this->data['template_id'],
                'variables' => $this->data['variables'] ?? [],
                'recipient_type' => $this->data['recipient_type'] ?? 'all',
                'schedule_type' => $this->data['schedule_type'] ?? 'now',
                'scheduled_at' => $this->data['scheduled_at'] ?? null,
                'filters' => $this->data['filters'] ?? [],
            ];

            $scheduleType = $this->data['schedule_type'] ?? 'now';

            $broadcast = $this->broadcastOrchestrator->createAndDispatch(
                $data,
                $this->getGymId(),
                $this->selectedMemberIds,
            );

            Notification::make()
                ->title($scheduleType === 'now' ? 'Broadcast Started!' : 'Broadcast Scheduled!')
                ->body($scheduleType === 'now'
                    ? 'Sending messages to ' . number_format($broadcast->target_count) . ' members.'
                    : 'Scheduled for ' . ($broadcast->scheduled_at?->format('M d, Y g:i A') ?? ''))
                ->success()
                ->send();

            $this->initializeForm();
            $this->loadTemplates();

        } catch (Exception $e) {
            Notification::make()
                ->title('Broadcast Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function sendTestMessage(): void
    {
        try {
            throw_unless(config('services.twilio.use_sandbox', false), Exception::class, 'Sandbox mode is not enabled. Please enable it in services.php configuration.');

            $testPhoneNumber = config('services.twilio.sandbox_recipient_number');

            throw_unless($testPhoneNumber, Exception::class, 'No test phone number configured for this gym');

            $provider = WhatsAppProviderFactory::create($this->getGymId());

            $result = $provider->sendTestWhatsAppMessage(
                $this->getGymId(),
                $testPhoneNumber,
            );

            if ($result['success']) {
                Notification::make()
                    ->title('Test Message Sent!')
                    ->body('Test message sent successfully to ' . $testPhoneNumber)
                    ->success()
                    ->send();
            } else {
                throw new Exception($result['error'] ?? 'Failed to send test message');
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Test Message Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
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
