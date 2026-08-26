<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\WhatsApp;

use App\Enums\TemplateCategoryEnum;
use App\Filament\Admin\Resources\WhatsAppTemplateResource;
use App\Filament\BasePages\BasePage;
use App\Forms\Components\SummaryStatCard;
use App\Forms\Components\TemplatePicker;
use App\Forms\Components\TemplatePreview;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\BroadcastMemberQuery;
use App\Services\WhatsApp\BroadcastOrchestrator;
use App\Services\WhatsApp\BroadcastRateLimiter;
use App\Services\WhatsAppBillingService;
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
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * @property Form $form
 * @property-read Collection<int, WhatsAppTemplate> $templates
 */
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

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed> */
    public array $selectedTemplate = [];

    /** @var array<int, mixed> */
    public array $targetMembers = [];

    public int $targetCount = 0;

    /** @var array<string, mixed> */
    public array $messagePreview = [];

    public ?int $scheduledHour = null;

    /** @var array<string, mixed> */
    public array $filters = [
        'plans' => [],
        'trainers' => [],
        'membership_type' => [],
        'payment_status' => [],
    ];

    /** @var array<int, int> */
    public array $selectedMemberIds = [];

    protected BroadcastOrchestrator $broadcastOrchestrator;

    public function boot(BroadcastOrchestrator $broadcastOrchestrator): void
    {
        $this->broadcastOrchestrator = $broadcastOrchestrator;
    }

    public function mount(): void
    {
        $this->initializeForm();
    }

    public function defaultRolePermissions(): array
    {
        return [];
    }

    /**
     * @return Collection<int, WhatsAppTemplate>
     */
    #[Computed]
    public function templates(): Collection
    {
        return WhatsAppTemplate::query()
            ->where(function ($query) {
                $query->where('gym_id', $this->getGymId());

                if (config('services.twilio.use_sandbox')) {
                    $query->orWhereNull('gym_id');
                }
            })
            ->approved()
            ->isActive()
            ->get();
    }

    private function initializeForm(): void
    {
        $this->data = [
            'template_id' => '',
            'variables' => [],
            'schedule_type' => 'now',
            'scheduled_at' => null,
            'filters' => [
                'plans' => null,
                'trainers' => null,
                'membership_type' => null,
                'payment_status' => null,
            ],
        ];

        $this->selectedTemplate = [];
        $this->selectedMemberIds = [];
        $this->targetCount = 0;
        $this->filtersApplied = false;

        /** @var Form $form */
        $form = $this->form;
        $form->fill($this->data);
        $this->resetValidation();
    }

    /**
     * @return Builder<GymMember>
     */
    private function getFilteredMembers(): Builder
    {
        /** @var array<string, mixed> $filters */
        $filters = (array) ($this->data['filters'] ?? []);

        return BroadcastMemberQuery::build($this->getGymId(), $filters);
    }

    public function getFilteredMemberCount(): int
    {
        return $this->getFilteredMembers()->count();
    }

    public function applyFilters(): void
    {
        $this->resetValidation();

        $filters = $this->data['filters'] ?? [];
        $activeFilters = array_filter((array) $filters, fn ($value) => filled($value));

        if (blank($activeFilters)) {
            throw ValidationException::withMessages([
                'data.filters.plans' => 'Audience Filtering required: Please select at least one filter before applying.',
            ]);
        }

        /** @var array<int, int> $ids */
        $ids = array_map(fn ($id) => $id, $this->getFilteredMembers()->pluck('id')->toArray());
        $this->selectedMemberIds = $ids;
        $this->targetCount = count($this->selectedMemberIds);

        if ($this->targetCount === 0) {
            throw ValidationException::withMessages([
                'data.filters.plans' => '0 members match the selected criteria. Please adjust your filters.',
            ]);
        }

        $this->filtersApplied = true;
    }

    public function updatedFilters(): void
    {
        $this->applyFilters();
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
        /** @var Form $form */
        $form = $this->form;
        $form->fill($this->data);
        // $this->resetTableSearch();
        // $this->resetTable();
        // $this->targetCount = $this->getAllMemberCount();
        $this->targetCount = 0;
        $this->selectedMemberIds = [];
        $this->resetValidation();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Split::make([
                    Section::make([
                        Section::make('Audience Filtering')
                            ->icon('heroicon-o-funnel')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('filters.plans')
                                                    ->label('Membership Plan')
                                                    ->options(fn () => MembershipPlan::query()->where('gym_id', $this->getGymId())->isActive()->pluck('name', 'id')),

                                                Select::make('filters.trainers')
                                                    ->label('Trainer')
                                                    ->options(fn () => Trainer::query()->where('gym_id', $this->getGymId())->pluck('name', 'id')),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                Select::make('filters.membership_type')
                                                    ->label('Active / Inactive')
                                                    ->options([
                                                        'active' => 'Active (Membership Valid)',
                                                        'inactive' => 'Inactive (Membership Expired)',
                                                    ]),

                                                Select::make('filters.payment_status')
                                                    ->label('Payment Status')
                                                    ->options([
                                                        'pending' => 'Pending',
                                                        'completed' => 'Completed',
                                                        'failed' => 'Failed',
                                                        'expired' => 'Expired',
                                                    ]),
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
                                    ]),
                            ]),

                        SummaryStatCard::make('')
                            ->icon('heroicon-o-user-group')
                            ->color('success')
                            ->stats(function (Get $get): string {
                                $count = count($this->selectedMemberIds);
                                $isSelfManaged = WhatsAppAccount::query()
                                    ->where('gym_id', $this->getGymId())
                                    ->selfManageBillingMode()
                                    ->exists();

                                if ($isSelfManaged) {
                                    $templateId = $get('template_id') ?? $this->data['template_id'] ?? null;
                                    $category = null;

                                    if (filled($templateId)) {
                                        /** @var TemplateCategoryEnum|null $category */
                                        $category = WhatsAppTemplate::query()->where('id', $templateId)->value('category');
                                    }

                                    $perMessageCost = WhatsAppBillingService::getCategoryPerMessageCost($category);
                                    $expectedCost = $count * $perMessageCost;

                                    $costFormatted = $expectedCost > 0 && $expectedCost < 0.01
                                        ? number_format($expectedCost, 3)
                                        : number_format($expectedCost, 2);

                                    return 'Total Filtered Members: ' . number_format($count) . ' | Expected Cost: $' . $costFormatted;
                                }

                                return 'Total Filtered Members: ' . number_format($count) . ' | Required Credits: ' . number_format($count);
                            }),

                        Section::make('Template Selection')
                            ->icon('heroicon-o-squares-2x2')
                            ->headerActions([
                                Action::make('manageTemplates')
                                    ->label('Manage Templates')
                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                    ->iconPosition(IconPosition::Before)
                                    ->color('success')
                                    ->link()
                                    ->url(WhatsAppTemplateResource::getUrl())
                                    ->openUrlInNewTab()
                                    ->extraAttributes([
                                        'onclick' => 'window.open(this.href, \'_blank\'); return false;',
                                    ]),
                            ])
                            ->schema(function () {
                                if (blank($this->templates)) {
                                    return [
                                        Placeholder::make('no_templates')
                                            ->hiddenLabel()
                                            ->content('No approved WhatsApp templates found.'),
                                    ];
                                }

                                return [
                                    TemplatePicker::make('template_id')
                                        ->options($this->templates)
                                        ->itemsPerPage(2)
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn () => $this->updateTemplatePreview()),
                                ];
                            }),

                        Radio::make('schedule_type')
                            ->label('')
                            ->required()
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

                        Actions::make([
                            Action::make('sendBroadcastAction')
                                ->label('Send Broadcast')
                                ->icon('heroicon-o-paper-airplane')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalHeading('Confirm Broadcast Dispatch')
                                ->modalDescription(fn (): string => 'Are you sure you want to send this WhatsApp broadcast to ' . number_format($this->targetCount) . ' member(s)? Message credits will be deducted.')
                                ->modalSubmitActionLabel('Yes, Send Broadcast')
                                ->visible(fn () => ! config('services.twilio.use_sandbox'))
                                ->action(function () {
                                    $this->sendBroadcast();
                                }),
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

                                        /** @var WhatsAppBroadcast|null $lastBroadcast */
                                        $lastBroadcast = WhatsAppBroadcast::query()->where('gym_id', $gymId)
                                            ->excludingTerminalStatuses()
                                            ->latest()
                                            ->first();

                                        $cooldownText = '';
                                        if ($lastBroadcast && $lastBroadcast->created_at?->gt(now()->subHour())) {
                                            $nextAllowed = $lastBroadcast->created_at->copy()->addHour();
                                            $minutesLeft = (int) now()->diffInMinutes($nextAllowed, false);
                                            $cooldownText = "<div class='text-amber-600 dark:text-amber-400 text-xs mt-1'>⏱ Next broadcast available in {$minutesLeft} minute(s)</div>";
                                        }

                                        $statusColor = (is_int($remaining) ? $remaining : 0) > 0 ? 'emerald' : 'red';
                                        $remainingText = is_int($remaining) ? $remaining : 0;

                                        return new HtmlString("
                                            <div class='flex items-center gap-4'>
                                                <div class='flex items-center gap-2'>
                                                    <span class='text-sm text-gray-600 dark:text-gray-400'>Today's Quota:</span>
                                                    <span class='font-bold text-{$statusColor}-600 dark:text-{$statusColor}-400'>{$remainingText}/2 remaining</span>
                                                </div>
                                            </div>
                                            {$cooldownText}
                                        ");
                                    }),
                            ])
                            ->compact(),

                        Section::make('Template Preview')
                            ->schema([
                                TemplatePreview::make('template_preview')
                                    ->template(fn () => $this->data['template_id'] ?? $this->selectedTemplate['id'] ?? null)
                                    ->live(),
                            ])
                            ->compact(),

                        Section::make('Template Variables')
                            ->visible(function (Get $get): bool {
                                $templateId = $get('template_id');
                                if (blank($templateId)) {
                                    return false;
                                }
                                /** @var WhatsAppTemplate|null $tpl */
                                $tpl = WhatsAppTemplate::query()->find($templateId);

                                return $tpl !== null && WhatsAppVariableResolver::hasCustomVariables($tpl);
                            })
                            ->schema(function (Get $get): array {
                                $templateId = $get('template_id');
                                if (blank($templateId)) {
                                    return [];
                                }
                                /** @var WhatsAppTemplate|null $template */
                                $template = WhatsAppTemplate::query()->find($templateId);
                                if (! $template) {
                                    return [];
                                }
                                $customVars = WhatsAppVariableResolver::getCustomVariables($template);
                                $fields = [];
                                foreach ($customVars as $varKey => $info) {
                                    $label = $info['placeholder'];
                                    $placeholder = $info['example'];
                                    $fields[] = TextInput::make("variables.{$varKey}")
                                        ->label($label)
                                        ->placeholder($placeholder)
                                        ->required();
                                }

                                return $fields;
                            })
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
            /** @var array<string, mixed> $templateArray */
            $templateArray = is_array($arr = $template?->toArray()) ? $arr : [];

            $templateArray['formatted_body'] = $template?->formatted_body ?? $template?->body;

            $this->selectedTemplate = $templateArray;
        } else {
            $this->selectedTemplate = [];
            $this->targetCount = 0;
        }
    }

    public function sendBroadcast(): void
    {
        if ($this->targetCount === 0) {
            Notification::make()
                ->title('No Recipients Selected')
                ->body('Please apply filters to select at least one member before sending a broadcast.')
                ->danger()
                ->send();

            $this->addError('data.filters.plans', 'Please select at least one member before sending a broadcast.');

            return;
        }

        try {
            /** @var Form $form */
            $form = $this->form;
            $form->validate();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Validation Error')
                ->body('Please select a WhatsApp template and fill in all required fields.')
                ->danger()
                ->send();

            $this->setErrorBag($e->validator->getMessageBag());

            return;
        }

        try {
            $data = [
                'template_id' => $this->data['template_id'],
                'variables' => $this->data['variables'] ?? [],
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

        } catch (Exception $e) {
            Notification::make()
                ->title('Broadcast Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refresh(): void
    {
        $this->updateTemplatePreview();
    }

    protected function getGymId(): int
    {
        /** @var Gym|null $tenant */
        $tenant = Filament::getTenant();

        return $tenant ? $tenant->id : 0;
    }

    protected function getGym(): ?Model
    {
        return Filament::getTenant();
    }
}
