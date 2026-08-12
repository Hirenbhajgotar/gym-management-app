---
name: laravel-code-extraction
description: >
  Code extraction and Laravel best practices skill.
  Identifies what should be extracted from monolithic files into
  Services, Actions, DTOs, Traits, Enums, Value Objects, Repositories.
  Enforces Laravel best practices: DB transactions, Cache::lock,
  Form Requests, Policies, Eloquent patterns, Queue patterns.
license: MIT
compatibility: opencode, laravel-boost, oh-my-opencode
metadata:
  author: opencode-community
  version: "1.0"
  language: php
  framework: laravel
  categories: [extraction, services, actions, dtos, traits, best-practices]
---

# Laravel Code Extraction & Best Practices Skill

## Philosophy

> **A file doing everything is a file doing nothing well.**
> **Extract early, extract often. Single Responsibility is not optional.**

This skill identifies extraction opportunities and enforces Laravel best practices. It works alongside the 8-dimension review to ensure extracted code follows Laravel conventions.

---

## 9th Dimension: CODE EXTRACTION

### When to Extract

| Smell | Extract To | Example |
|-------|-----------|---------|
| File > 300 lines | Multiple classes | Controller with 500 lines -> Service + Action + DTO |
| Business logic in Controller/Job | `App\Services\*` | `processPayment()` in Controller |
| Single-purpose operation | `App\Actions\*` | `ImportWhatsAppTemplatesAction` |
| Complex data passing | `App\DTOs\*` | `CreateOrderDTO` with 10+ fields |
| Domain concept with validation | `App\ValueObjects\*` | `PhoneNumber`, `EmailAddress`, `Money` |
| Shared logic across classes | `App\Traits\*` | `HasUuid`, `HasTenant`, `LogsActivity` |
| Complex query logic | `App\Repositories\*` | `UserRepository::findActiveWithOrders()` |
| Related constants | `App\Enums\*` | `OrderStatusEnum`, `PaymentMethodEnum` |
| Form validation rules | `App\Http\Requests\*` | `StoreOrderRequest`, `UpdateProfileRequest` |
| Authorization logic | `App\Policies\*` | `OrderPolicy::update()`, `PostPolicy::delete()` |
| Event handling | `App\Listeners\*` | `SendOrderConfirmationEmail` |
| Notification logic | `App\Notifications\*` | `OrderShippedNotification` |

### Extraction Decision Tree

```
Is this logic used in multiple places?
  YES -> Extract to Service or Trait
  NO -> Is it a single-purpose operation?
    YES -> Extract to Action class
    NO -> Is it complex data structure?
      YES -> Extract to DTO
      NO -> Is it a domain concept with rules?
        YES -> Extract to Value Object
        NO -> Keep inline (but consider method extraction)
```

### Extraction Templates

#### A. Service Extraction
**When:** Business logic in Controller/Job/Command, or logic used in multiple places

```php
<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\DTOs\WhatsAppTemplateDTO;
use Illuminate\Support\Collection;

class WhatsAppTemplateService
{
    public function __construct(
        private readonly MetaTemplateService $metaService,
        private readonly TwilioTemplateService $twilioService,
    ) {}

    /**
     * Import templates for a given account.
     *
     * @param WhatsAppAccount $account
     * @return Collection<WhatsAppTemplateDTO>
     * @throws \RuntimeException
     */
    public function importForAccount(WhatsAppAccount $account): Collection
    {
        return match ($account->provider) {
            WhatsAppProviderTypeEnum::META => $this->metaService->fetchTemplates($account),
            WhatsAppProviderTypeEnum::TWILIO => $this->twilioService->fetchTemplates($account),
            default => throw new \InvalidArgumentException("Unknown provider: {$account->provider->value}"),
        };
    }

    /**
     * Sync fetched templates with database.
     *
     * @param WhatsAppAccount $account
     * @param Collection $templates
     * @return void
     */
    public function syncTemplates(WhatsAppAccount $account, Collection $templates): void
    {
        DB::transaction(function () use ($account, $templates) {
            $existingNames = WhatsAppTemplate::where('account_id', $account->id)
                ->pluck('id', 'name')
                ->toArray();

            foreach ($templates as $template) {
                WhatsAppTemplate::updateOrCreate(
                    ['account_id' => $account->id, 'name' => $template->name],
                    [
                        'language' => $template->language,
                        'status' => $template->status,
                        'components' => $template->components,
                    ]
                );
            }

            // Remove templates that no longer exist
            $fetchedNames = $templates->pluck('name')->toArray();
            $toDelete = array_diff(array_keys($existingNames), $fetchedNames);

            if (!empty($toDelete)) {
                WhatsAppTemplate::whereIn('id', $toDelete)->delete();
            }
        });
    }
}
```

#### B. Action Class Extraction
**When:** Single-purpose, stateless operation

```php
<?php

namespace App\Actions;

use App\Models\WhatsAppAccount;
use App\Services\WhatsAppTemplateService;
use App\DTOs\ImportResultDTO;

class ImportWhatsAppTemplatesAction
{
    public function __construct(
        private readonly WhatsAppTemplateService $templateService,
    ) {}

    public function __invoke(WhatsAppAccount $account): ImportResultDTO
    {
        $templates = $this->templateService->importForAccount($account);
        $this->templateService->syncTemplates($account, $templates);

        return new ImportResultDTO(
            imported: $templates->count(),
            accountId: $account->id,
            provider: $account->provider,
        );
    }
}
```

#### C. DTO Extraction
**When:** Complex data passing, API responses, form data

```php
<?php

namespace App\DTOs;

readonly class WhatsAppTemplateDTO
{
    public function __construct(
        public string $name,
        public string $language,
        public WhatsAppTemplateStatusEnum $status,
        public array $components,
        public ?string $category = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            language: $data['language'],
            status: WhatsAppTemplateStatusEnum::from($data['status']),
            components: $data['components'] ?? [],
            category: $data['category'] ?? null,
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'language' => $this->language,
            'status' => $this->status->value,
            'components' => $this->components,
            'category' => $this->category,
        ];
    }
}
```

#### D. Value Object Extraction
**When:** Domain concept with built-in validation

```php
<?php

namespace App\ValueObjects;

final class TemplateLanguage
{
    private function __construct(
        private readonly string $code,
    ) {
        if (!preg_match('/^[a-z]{2}_[A-Z]{2}$/', $code)) {
            throw new \InvalidArgumentException(
                "Invalid language code '{$code}'. Expected format: en_US, hi_IN"
            );
        }
    }

    public static function from(string $code): self
    {
        return new self($code);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
```

#### E. Trait Extraction
**When:** Shared logic across multiple classes

```php
<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasTenantContext
{
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function belongsToTenant(): bool
    {
        return $this->tenant_id !== null;
    }
}
```

#### F. Repository Extraction
**When:** Complex query logic, query reuse

```php
<?php

namespace App\Repositories;

use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;

class WhatsAppTemplateRepository
{
    public function findActiveByAccount(int $accountId): Collection
    {
        return WhatsAppTemplate::where('account_id', $accountId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function findByNameAndAccount(string $name, int $accountId): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::where('account_id', $accountId)
            ->where('name', $name)
            ->first();
    }
}
```

#### G. Enum Extraction
**When:** Related constants, state definitions

```php
<?php

namespace App\Enums;

enum WhatsAppTemplateStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case ACTIVE = 'active';
    case PAUSED = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
```

#### H. Form Request Extraction
**When:** Complex validation rules

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportTemplatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', WhatsAppAccount::class);
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'exists:whatsapp_accounts,id'],
            'force_refresh' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'force_refresh' => $this->boolean('force_refresh', false),
        ]);
    }
}
```

#### I. Policy Extraction
**When:** Authorization logic

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppAccount;

class WhatsAppAccountPolicy
{
    public function view(User $user, WhatsAppAccount $account): bool
    {
        return $user->tenant_id === $account->tenant_id;
    }

    public function import(User $user, WhatsAppAccount $account): bool
    {
        return $this->view($user, $account) 
            && $user->hasPermission('whatsapp.templates.import');
    }
}
```

---

## Laravel Best Practices (Cross-Cutting)

### 1. Database Transactions

**ALWAYS** wrap multi-step DB operations:

```php
// BAD: No transaction - partial failure leaves DB inconsistent
$order = Order::create($data);
$order->items()->createMany($items);
$order->update(['total' => $total]);

// GOOD: Atomic operation
DB::transaction(function () use ($data, $items, $total) {
    $order = Order::create($data);
    $order->items()->createMany($items);
    $order->update(['total' => $total]);
    $order->user->notify(new OrderCreated($order));
});

// BETTER: With retry for deadlock
DB::transaction(function () {
    // operations
}, 5); // 5 retry attempts
```

### 2. Cache Locks for Race Conditions

```php
// BAD: Check-then-update race condition
if (Cache::get('import_running')) {
    return;
}
Cache::put('import_running', true, 300);

// GOOD: Atomic lock
$lock = Cache::lock('whatsapp-import-' . $accountId, 300);

if ($lock->get()) {
    try {
        $this->performImport($account);
    } finally {
        $lock->release();
    }
} else {
    Log::info('Import already running for account', ['account_id' => $accountId]);
    return;
}

// BETTER: Block until lock available
$lock = Cache::lock('whatsapp-import-' . $accountId, 300);

if ($lock->block(10)) { // Wait up to 10 seconds
    try {
        $this->performImport($account);
    } finally {
        $lock->release();
    }
}
```

### 3. Queue Job Patterns

```php
// Unique job - prevents duplicate processing
class ImportWhatsAppTemplatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $uniqueFor = 3600; // 1 hour

    public function uniqueId(): string
    {
        return 'import-whatsapp-templates-' . $this->accountId;
    }

    // OR use WithoutOverlapping middleware
    public function middleware(): array
    {
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('import-' . $this->accountId))->releaseAfter(60)];
    }
}
```

### 4. Eloquent Patterns

```php
// BAD: N+1 query
$accounts = WhatsAppAccount::all();
foreach ($accounts as $account) {
    echo $account->gym->name; // Query per account
}

// GOOD: Eager load
$accounts = WhatsAppAccount::with('gym')->get();

// BAD: Loading all records
$templates = WhatsAppTemplate::all(); // Memory explosion

// GOOD: Chunked processing
WhatsAppTemplate::chunk(100, function ($templates) {
    foreach ($templates as $template) {
        // process
    }
});

// GOOD: Cursor for memory efficiency
foreach (WhatsAppTemplate::where('status', 'pending')->cursor() as $template) {
    // process
}

// BAD: Query in loop
foreach ($names as $name) {
    Template::where('name', $name)->first(); // N queries
}

// GOOD: Single query
Template::whereIn('name', $names)->get()->keyBy('name');
```

### 5. API Error Handling

```php
// BAD: Raw exception exposed
public function fetchTemplates(): array
{
    $response = Http::get($url); // May throw
    return $response->json();
}

// GOOD: Structured error handling
public function fetchTemplates(): array
{
    try {
        $response = Http::retry(3, 100)
            ->timeout(30)
            ->withToken($this->apiKey)
            ->get($this->baseUrl . '/templates');

        if ($response->failed()) {
            throw new \RuntimeException(
                "API returned {$response->status()}: {$response->body()}"
            );
        }

        return $response->json('data', []);
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        throw new \RuntimeException('Failed to connect to API: ' . $e->getMessage());
    } catch (\Exception $e) {
        report($e);
        throw new \RuntimeException('Template fetch failed: ' . $e->getMessage());
    }
}
```

### 6. Form Request + Policy Pattern

```php
// Controller
public function store(StoreOrderRequest $request)
{
    $this->authorize('create', Order::class);

    $dto = CreateOrderDTO::fromRequest($request);
    $order = $this->orderService->create($dto);

    return new OrderResource($order);
}

// Form Request handles validation
// Policy handles authorization
// Service handles business logic
// DTO handles data transfer
// Resource handles response formatting
```

### 7. Event-Driven Architecture

```php
// Dispatch event instead of side effects
class OrderService
{
    public function create(CreateOrderDTO $dto): Order
    {
        $order = DB::transaction(function () use ($dto) {
            $order = Order::create($dto->toArray());
            $order->items()->createMany($dto->items);
            return $order;
        });

        // Dispatch event - listeners handle side effects
        OrderCreated::dispatch($order);

        return $order;
    }
}

// Listener
class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        $event->order->user->notify(new OrderConfirmation($event->order));
    }
}
```

### 8. Configuration & Environment

```php
// BAD: env() outside config
$apiKey = env('META_API_KEY'); // Returns null if cached

// GOOD: config file
// config/services.php
return [
    'meta' => [
        'api_key' => env('META_API_KEY'),
        'base_url' => env('META_BASE_URL', 'https://graph.facebook.com/v18.0'),
    ],
];

// Usage
$config = config('services.meta');
$apiKey = $config['api_key'];
```

### 9. Logging & Monitoring

```php
// BAD: No context
Log::error('Import failed');

// GOOD: Structured logging
Log::error('WhatsApp template import failed', [
    'account_id' => $account->id,
    'provider' => $account->provider->value,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
    'duration_ms' => $startTime->diffInMilliseconds(now()),
]);

// GOOD: Contextual logging
Log::withContext([
    'tenant_id' => $tenantId,
    'user_id' => auth()->id(),
    'request_id' => request()->header('X-Request-ID'),
]);
```

### 10. Type Safety

```php
// BAD: No types
function process($data) {
    return $data;
}

// GOOD: Full type declarations
/**
 * @param array<int, WhatsAppTemplateDTO> $templates
 * @return Collection<int, WhatsAppTemplate>
 */
function processTemplates(array $templates): Collection
{
    return collect($templates)->map(
        fn (WhatsAppTemplateDTO $dto) => WhatsAppTemplate::fromDTO($dto)
    );
}

// Use PHP 8.1+ features
readonly class ConfigDTO
{
    public function __construct(
        public string $key,
        public mixed $value,
        public ?string $description = null,
    ) {}
}
```

---

## Extraction Checklist

For each file under review:

```markdown
## Extraction Review: `path/to/file.php`

### Current Structure
- Lines: [count]
- Classes: [count]
- Methods: [count]
- Responsibilities: [list]

### Extraction Opportunities
- [ ] **Service**: [logic description] -> `App\Services\[Name]Service`
- [ ] **Action**: [operation description] -> `App\Actions\[Name]Action`
- [ ] **DTO**: [data structure] -> `App\DTOs\[Name]DTO`
- [ ] **Value Object**: [domain concept] -> `App\ValueObjects\[Name]`
- [ ] **Trait**: [shared logic] -> `App\Traits\Has[Name]`
- [ ] **Repository**: [query logic] -> `App\Repositories\[Name]Repository`
- [ ] **Enum**: [constants] -> `App\Enums\[Name]Enum`
- [ ] **Form Request**: [validation] -> `App\Http\Requests\[Name]Request`
- [ ] **Policy**: [authorization] -> `App\Policies\[Name]Policy`
- [ ] **Event**: [domain event] -> `App\Events\[Name]Event`
- [ ] **Listener**: [side effect] -> `App\Listeners\[Name]Listener`
- [ ] **Notification**: [notification] -> `App\Notifications\[Name]Notification`

### Laravel Best Practices
- [ ] DB transactions for multi-step operations
- [ ] Cache::lock() for race condition prevention
- [ ] Form Request for validation
- [ ] Policy for authorization
- [ ] Eager loading for relationships
- [ ] Chunked/cursor queries for large datasets
- [ ] Structured logging with context
- [ ] env() only in config files
- [ ] Type declarations on all methods
- [ ] DocBlocks for complex methods

### Recommended Refactoring Plan
1. Extract [X] to [location]
2. Extract [Y] to [location]
3. Apply [best practice]
4. ...
```

---

## Integration with Refactor Agent

The refactor agent uses this skill to:

1. **Pre-refactor audit**: Identify extraction opportunities before touching code
2. **During refactor**: Apply extraction patterns as part of refactoring steps
3. **Post-refactor validation**: Verify extracted classes follow Laravel best practices

### Example Integration

```
Phase 4 (Plan Generation):
- Identify extraction opportunities using laravel-code-extraction skill
- Plan atomic extraction steps
- Ensure each extracted class follows Laravel best practices

Phase 5 (Execute):
- Extract Service -> Verify: constructor injection, single responsibility
- Extract Action -> Verify: __invoke(), stateless, returns DTO
- Extract DTO -> Verify: readonly, fromArray(), fromRequest(), toArray()
- Apply best practices -> Verify: transactions, locks, eager loading
```
