---
name: laravel-code-review
description: Deep Laravel code review skill focusing on business logic, race conditions, transactions, idempotency, runtime errors, and bugs. Every pattern includes BAD vs GOOD examples.
license: MIT
compatibility: opencode
metadata:
  audience: laravel-developers
  workflow: code-review
  version: 2.0
---

# Laravel Code Review Skill (Comprehensive)

## When to use this skill
Use this skill when performing code reviews on Laravel/PHP codebases. This skill activates deep analysis mode for:
- Business logic validation
- Race condition detection
- Database transaction auditing
- Idempotency verification
- Runtime error prevention
- Bug and anti-pattern identification

**AGENT INSTRUCTION: You MUST load this skill file at the start of every review. Read it completely before analyzing any code. Also load `.coderabbit.yaml` if it exists in the project root.**

---

## Table of Contents
1. [Business Logic Deep Dive](#1-business-logic-deep-dive)
2. [Race Conditions & Concurrency](#2-race-conditions--concurrency)
3. [Database Transactions](#3-database-transactions)
4. [Idempotency Patterns](#4-idempotency-patterns)
5. [Runtime Error Prevention](#5-runtime-error-prevention)
6. [Bugs & Anti-Patterns](#6-bugs--anti-patterns)
7. [Security Patterns](#7-security-patterns)
8. [Performance Patterns](#8-performance-patterns)
9. [Testing Patterns](#9-testing-patterns)
10. [Laravel-Specific Deep Patterns](#10-laravel-specific-deep-patterns)
11. [Review Checklist by File Type](#11-review-checklist-by-file-type)

---

## 1. Business Logic Deep Dive

### 1.1 Domain Rules Enforcement
Business rules must be enforced at the service/model level, NOT just in validation.

```php
// ❌ BAD: Rule only in Form Request, not enforced in service
class StoreOrderRequest extends FormRequest
{
    public function rules()
    {
        return ['quantity' => 'required|integer|min:1'];
    }
}

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request)
    {
        $product = Product::find($request->product_id);
        Order::create($request->validated());
    }
}

// ✅ GOOD: Rules enforced at multiple layers
class OrderService
{
    public function createOrder(array $data, User $user): Order
    {
        $product = Product::findOrFail($data['product_id']);

        if (!$product->is_active) {
            throw new InactiveProductException('Product is not available for purchase');
        }

        if (!$product->isAvailableInRegion($user->region)) {
            throw new RegionNotSupportedException('Product not available in your region');
        }

        $updated = Product::where('id', $product->id)
            ->where('stock', '>=', $data['quantity'])
            ->decrement('stock', $data['quantity']);

        if (!$updated) {
            throw new InsufficientStockException('Not enough stock available');
        }

        return DB::transaction(function () use ($data, $user, $product) {
            $order = Order::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => $data['quantity'],
                'status' => 'pending',
                'total' => $product->price * $data['quantity'],
            ]);

            OrderLog::create(['order_id' => $order->id, 'action' => 'created']);

            return $order;
        });
    }
}
```

### 1.2 State Machine Validity
Every entity with states must have valid transitions. Invalid transitions must be blocked.

```php
// ❌ BAD: Any state can transition to any state
class Order extends Model
{
    public function updateStatus(string $status)
    {
        $this->update(['status' => $status]);
    }
}

// ✅ GOOD: Validated state transitions
class Order extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    private const VALID_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_PAID => [self::STATUS_PROCESSING, self::STATUS_REFUNDED],
        self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_DELIVERED, self::STATUS_REFUNDED],
        self::STATUS_DELIVERED => [self::STATUS_REFUNDED],
        self::STATUS_CANCELLED => [],
        self::STATUS_REFUNDED => [],
    ];

    public function transitionTo(string $newStatus): void
    {
        $currentStatus = $this->status;

        if (!isset(self::VALID_TRANSITIONS[$currentStatus])) {
            throw new InvalidStateException("Unknown current status: {$currentStatus}");
        }

        if (!in_array($newStatus, self::VALID_TRANSITIONS[$currentStatus], true)) {
            throw new InvalidStateTransitionException(
                "Cannot transition from '{$currentStatus}' to '{$newStatus}'"
            );
        }

        $this->update(['status' => $newStatus]);
        OrderStatusChanged::dispatch($this, $currentStatus, $newStatus);
    }

    public function pay(): void { $this->transitionTo(self::STATUS_PAID); }
    public function ship(): void { $this->transitionTo(self::STATUS_SHIPPED); }
    public function cancel(): void { $this->transitionTo(self::STATUS_CANCELLED); }
}
```

### 1.3 Calculation Accuracy
Monetary calculations must use integer cents or Decimal, NEVER float.

```php
// ❌ BAD: Float arithmetic causes precision errors
$total = $price * $quantity;
$tax = $total * 0.18;

// ✅ GOOD: Use integer cents
class Money
{
    public function __construct(private int $amountInCents) {}

    public static function fromDecimal(float $amount): self
    {
        return new self((int) round($amount * 100));
    }

    public function add(Money $other): self
    {
        return new self($this->amountInCents + $other->amountInCents);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->amountInCents * $factor));
    }

    public function toDecimal(): float
    {
        return $this->amountInCents / 100;
    }

    public function format(): string
    {
        return number_format($this->amountInCents / 100, 2);
    }
}
```

### 1.4 Authorization Logic
Check both authentication AND authorization at every entry point.

```php
// ❌ BAD: Only checks if user is logged in
public function show(Order $order)
{
    return view('orders.show', compact('order'));
}

// ✅ GOOD: Policy-based authorization
public function show(Order $order)
{
    $this->authorize('view', $order);
    return view('orders.show', compact('order'));
}

// In OrderPolicy
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->isAdmin();
    }

    public function update(User $user, Order $order): bool
    {
        if (in_array($order->status, ['shipped', 'delivered'], true)) {
            return false;
        }
        return $user->id === $order->user_id;
    }

    public function delete(User $user, Order $order): bool
    {
        return $order->status === 'pending' && $user->id === $order->user_id;
    }
}
```

### 1.5 Data Integrity
Prevent orphan records and ensure referential integrity.

```php
// ❌ BAD: No foreign key
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->timestamps();
});

// ✅ GOOD: Foreign keys with proper cascade rules
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')
        ->constrained()
        ->onDelete('restrict');
    $table->foreignId('product_id')
        ->constrained()
        ->onDelete('restrict');
    $table->timestamps();
});

// For soft deletes
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->timestamp('deleted_at')->nullable();
    $table->timestamps();
    $table->unique(['email', 'deleted_at']);
});
```

### 1.6 Missing Scenarios (Edge Cases)
Always ask: "What can go wrong?"

```php
// ❌ BAD: Happy path only
public function processWebhook(Request $request)
{
    $payment = Payment::find($request->payment_id);
    $payment->update(['status' => 'completed']);
    $payment->order->update(['status' => 'paid']);
    event(new PaymentReceived($payment));
}

// ✅ GOOD: Handles all edge cases
public function processWebhook(Request $request)
{
    if (!$this->verifyWebhookSignature($request)) {
        Log::warning('Invalid webhook signature', ['payload' => $request->all()]);
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    $eventId = $request->header('X-Event-ID');
    if (WebhookLog::where('event_id', $eventId)->exists()) {
        return response()->json(['status' => 'already_processed']);
    }

    $payment = Payment::where('transaction_id', $request->transaction_id)
        ->lockForUpdate()
        ->first();

    if (!$payment) {
        return response()->json(['error' => 'Payment not found'], 404);
    }

    if ($payment->status === 'completed') {
        return response()->json(['status' => 'already_completed']);
    }

    if (!in_array($payment->status, ['pending', 'processing'], true)) {
        return response()->json(['error' => 'Invalid state'], 422);
    }

    DB::transaction(function () use ($payment, $request, $eventId) {
        $payment->update(['status' => 'completed']);
        $payment->order->transitionTo('paid');
        WebhookLog::create(['event_id' => $eventId, 'processed_at' => now()]);
    });

    event(new PaymentReceived($payment));
    return response()->json(['status' => 'success']);
}
```

### 1.7 Soft Deletes Handling

```php
// ❌ BAD: Soft deleted records included
$users = User::all();

// ✅ GOOD: Explicit handling
class User extends Model
{
    use SoftDeletes;

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }
}

$activeUsers = User::active()->get();
$allUsers = User::withTrashed()->get();

// Unique constraints with soft deletes
Schema::table('users', function (Blueprint $table) {
    $table->string('deleted_email')->nullable();
    $table->unique('email');
});

protected static function booted()
{
    static::deleting(function ($user) {
        $user->deleted_email = $user->email;
        $user->email = null;
        $user->save();
    });
}
```

### 1.8 Timezone & Locale

```php
// ❌ BAD: No timezone handling
$order->created_at->format('Y-m-d H:i:s');

// ✅ GOOD: Store UTC, display in user timezone
class Order extends Model
{
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];
}

public function show(Order $order)
{
    $userTimezone = auth()->user()->timezone ?? 'UTC';
    return response()->json([
        'created_at' => $order->created_at->timezone($userTimezone)->toDateTimeString(),
        'created_at_human' => $order->created_at->timezone($userTimezone)->diffForHumans(),
    ]);
}
```

---

## 2. Race Conditions & Concurrency

### 2.1 Read-Modify-Write (The #1 Race Condition)

```php
// ❌ BAD: Classic race condition
public function decrementStock(int $productId, int $quantity): void
{
    $product = Product::find($productId);
    $product->stock -= $quantity;
    $product->save();
}

// ✅ GOOD: Atomic operation
public function decrementStock(int $productId, int $quantity): bool
{
    $affected = Product::where('id', $productId)
        ->where('stock', '>=', $quantity)
        ->decrement('stock', $quantity);

    return $affected > 0;
}

// ✅ GOOD: Pessimistic locking
public function decrementStock(int $productId, int $quantity): bool
{
    return DB::transaction(function () use ($productId, $quantity) {
        $product = Product::where('id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$product || $product->stock < $quantity) {
            return false;
        }

        $product->decrement('stock', $quantity);
        StockLog::create([
            'product_id' => $productId,
            'quantity' => -$quantity,
            'reason' => 'order',
        ]);

        return true;
    }, 5);
}
```

### 2.2 Duplicate Creation Prevention

```php
// ❌ BAD: Can create duplicates
public function createSubscription(Request $request)
{
    $user = auth()->user();
    if ($user->subscriptions()->where('status', 'active')->exists()) {
        throw new Exception('Already has active subscription');
    }
    Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $request->plan_id,
        'status' => 'active',
    ]);
}

// ✅ GOOD: Unique constraint at database level
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('plan_id')->constrained();
    $table->string('status');
    $table->timestamps();
    $table->unique(['user_id', 'status'])->where('status', 'active');
});

public function createSubscription(Request $request)
{
    try {
        return DB::transaction(function () {
            return Subscription::create([
                'user_id' => auth()->id(),
                'plan_id' => $request->plan_id,
                'status' => 'active',
            ]);
        });
    } catch (QueryException $e) {
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            throw new DuplicateSubscriptionException('Active subscription already exists');
        }
        throw $e;
    }
}
```

### 2.3 Job Processing Duplicates

```php
// ❌ BAD: Job can be processed multiple times
class ProcessPaymentJob implements ShouldQueue
{
    public $tries = 3;

    public function handle()
    {
        $payment = Payment::find($this->paymentId);
        $payment->update(['status' => 'completed']);
    }
}

// ✅ GOOD: Idempotent job processing
class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'process-payment-' . $this->paymentId;
    }

    public function handle()
    {
        $payment = Payment::find($this->paymentId);

        if ($payment->status === 'completed') {
            Log::info('Payment already processed', ['payment_id' => $this->paymentId]);
            return;
        }

        if ($payment->status !== 'pending') {
            Log::warning('Payment in unexpected state', [
                'payment_id' => $this->paymentId,
                'status' => $payment->status,
            ]);
            return;
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'completed']);
            $payment->order->transitionTo('paid');
        });
    }
}
```

### 2.4 Cache Stampede Prevention

```php
// ❌ BAD: Cache stampede
public function getPopularProducts()
{
    return Cache::remember('popular_products', 3600, function () {
        return Product::popular()->limit(20)->get();
    });
}

// ✅ GOOD: Stale-while-revalidate
public function getPopularProducts()
{
    $cacheKey = 'popular_products';
    $lockKey = $cacheKey . '_lock';

    $products = Cache::get($cacheKey);

    if ($products) {
        if (!Cache::has($lockKey)) {
            Cache::put($lockKey, true, 60);
            dispatch(function () use ($cacheKey, $lockKey) {
                $fresh = Product::popular()->limit(20)->get();
                Cache::put($cacheKey, $fresh, 3600);
                Cache::forget($lockKey);
            })->afterResponse();
        }
        return $products;
    }

    $products = Product::popular()->limit(20)->get();
    Cache::put($cacheKey, $products, 3600);
    return $products;
}
```

### 2.5 File Upload Collisions

```php
// ❌ BAD: Filename collision
public function upload(Request $request)
{
    $file = $request->file('document');
    $filename = $file->getClientOriginalName();
    Storage::putFileAs('uploads', $file, $filename);
}

// ✅ GOOD: Unique filename
public function upload(Request $request)
{
    $file = $request->file('document');
    $extension = $file->getClientOriginalExtension();
    $filename = Str::uuid() . '.' . $extension;
    $path = $file->store('uploads', 'public');
    return ['path' => $path, 'url' => Storage::url($path)];
}
```

---

## 3. Database Transactions

### 3.1 Transaction Boundaries

```php
// ❌ BAD: Partial writes on failure
public function transferFunds(User $from, User $to, int $amount): void
{
    $from->decrement('balance', $amount);
    $to->increment('balance', $amount);
    TransactionLog::create([...]);
}

// ✅ GOOD: All-or-nothing
public function transferFunds(User $from, User $to, int $amount): void
{
    DB::transaction(function () use ($from, $to, $amount) {
        $from->decrement('balance', $amount);
        $to->increment('balance', $amount);
        TransactionLog::create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'amount' => $amount,
        ]);
    }, 5);
}
```

### 3.2 Early Returns Inside Transactions

```php
// ❌ BAD: Early return leaves transaction incomplete
public function processOrder(Order $order)
{
    DB::transaction(function () use ($order) {
        $order->update(['status' => 'processing']);

        if (!$order->hasStock()) {
            return;
        }

        $order->fulfill();
    });
}

// ✅ GOOD: Throw exception to trigger rollback
public function processOrder(Order $order)
{
    DB::transaction(function () use ($order) {
        $order->update(['status' => 'processing']);

        if (!$order->hasStock()) {
            throw new InsufficientStockException('Cannot fulfill order');
        }

        $order->fulfill();
    }, 5);
}
```

### 3.3 Distributed Operations (DB + HTTP)

```php
// ❌ BAD: Holding DB lock during HTTP call
public function processPayment(Order $order)
{
    DB::transaction(function () use ($order) {
        $order->update(['status' => 'processing']);
        $response = Http::timeout(30)->post('https://payment-gateway.com/charge', [
            'amount' => $order->total,
        ]);
        if ($response->successful()) {
            $order->update(['status' => 'paid']);
        }
    });
}

// ✅ GOOD: Two-phase commit
public function processPayment(Order $order)
{
    DB::transaction(function () use ($order) {
        $order->update(['status' => 'processing']);
        PaymentIntent::create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);
    });

    try {
        $response = Http::timeout(30)->post('https://payment-gateway.com/charge', [
            'amount' => $order->total,
        ]);

        DB::transaction(function () use ($order, $response) {
            if ($response->successful()) {
                $order->update(['status' => 'paid']);
                PaymentIntent::where('order_id', $order->id)->update([
                    'status' => 'completed',
                    'transaction_id' => $response->json('id'),
                ]);
            } else {
                $order->update(['status' => 'failed']);
                PaymentIntent::where('order_id', $order->id)->update(['status' => 'failed']);
            }
        });
    } catch (Exception $e) {
        $order->update(['status' => 'failed']);
        throw $e;
    }
}
```

### 3.4 After-Commit Actions

```php
// ❌ BAD: Event fires before commit
DB::transaction(function () use ($order) {
    $order->update(['status' => 'shipped']);
    event(new OrderShipped($order));
});

// ✅ GOOD: Use afterCommit
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderShipped::class => [
            [SendShipmentNotification::class, 'afterCommit' => true],
            [UpdateInventory::class, 'afterCommit' => true],
        ],
    ];
}

// Or manually
DB::transaction(function () use ($order) {
    $order->update(['status' => 'shipped']);
    DB::afterCommit(function () use ($order) {
        event(new OrderShipped($order));
    });
});
```

### 3.5 Nested Transactions

```php
// ❌ BAD: Manual transaction conflicts
public function outerMethod()
{
    DB::beginTransaction();
    try {
        $this->innerMethod();
        DB::commit();
    } catch (Exception $e) {
        DB::rollBack();
        throw $e;
    }
}

public function innerMethod()
{
    DB::beginTransaction();
    try {
        DB::commit();
    } catch (Exception $e) {
        DB::rollBack();
        throw $e;
    }
}

// ✅ GOOD: Closure-based transactions
public function outerMethod()
{
    DB::transaction(function () {
        Model::create([...]);
        $this->innerMethod();
    });
}

public function innerMethod()
{
    DB::transaction(function () {
        AnotherModel::create([...]);
    });
}
```

---

## 4. Idempotency Patterns

### 4.1 API Endpoint Idempotency

```php
// ❌ BAD: Double request creates duplicate
public function store(Request $request)
{
    $order = Order::create([
        'user_id' => auth()->id(),
        'total' => $request->total,
    ]);
    return $order;
}

// ✅ GOOD: Idempotency key
public function store(Request $request)
{
    $request->validate([
        'idempotency_key' => 'required|string|max:255',
        'total' => 'required|numeric|min:0',
    ]);

    $existing = Order::where('idempotency_key', $request->idempotency_key)
        ->where('user_id', auth()->id())
        ->first();

    if ($existing) {
        return response()->json([
            'data' => $existing,
            'message' => 'Order already exists (idempotent)',
        ]);
    }

    $order = Order::create([
        'user_id' => auth()->id(),
        'idempotency_key' => $request->idempotency_key,
        'total' => $request->total,
        'status' => 'pending',
    ]);

    return response()->json(['data' => $order], 201);
}

// ✅ BETTER: Unique constraint
// Migration: $table->unique(['user_id', 'idempotency_key']);

public function store(Request $request)
{
    try {
        $order = Order::create([
            'user_id' => auth()->id(),
            'idempotency_key' => $request->idempotency_key,
            'total' => $request->total,
        ]);
        return response()->json(['data' => $order], 201);
    } catch (QueryException $e) {
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            $order = Order::where('user_id', auth()->id())
                ->where('idempotency_key', $request->idempotency_key)
                ->first();
            return response()->json([
                'data' => $order,
                'message' => 'Order already exists',
            ]);
        }
        throw $e;
    }
}
```

### 4.2 Queue Job Idempotency

```php
// ❌ BAD: Retry creates duplicate
class ChargeCustomerJob implements ShouldQueue
{
    public function handle()
    {
        $response = PaymentGateway::charge($this->amount, $this->customerId);
        Payment::create([
            'transaction_id' => $response->id,
            'amount' => $this->amount,
        ]);
    }
}

// ✅ GOOD: Check + unique constraint
class ChargeCustomerJob implements ShouldQueue
{
    public $tries = 3;
    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'charge-' . $this->orderId;
    }

    public function handle()
    {
        if (Payment::where('order_id', $this->orderId)->exists()) {
            Log::info('Payment already processed', ['order_id' => $this->orderId]);
            return;
        }

        $response = PaymentGateway::charge($this->amount, $this->customerId);

        try {
            Payment::create([
                'order_id' => $this->orderId,
                'transaction_id' => $response->id,
                'amount' => $this->amount,
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return;
            }
            throw $e;
        }
    }
}
```

### 4.3 Webhook Idempotency

```php
// ❌ BAD: Duplicate webhooks processed
public function handleStripeWebhook(Request $request)
{
    $event = $request->all();
    $payment = Payment::where('stripe_id', $event['data']['id'])->first();
    $payment->update(['status' => 'completed']);
}

// ✅ GOOD: Event ID tracking
public function handleStripeWebhook(Request $request)
{
    $payload = $request->all();
    $eventId = $payload['id'];

    if (ProcessedWebhook::where('event_id', $eventId)->exists()) {
        return response()->json(['status' => 'already_processed']);
    }

    if (!$this->verifyStripeSignature($request)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    DB::transaction(function () use ($payload, $eventId) {
        $payment = Payment::where('stripe_id', $payload['data']['object']['id'])
            ->lockForUpdate()
            ->first();

        if ($payment && $payment->status !== 'completed') {
            $payment->update(['status' => 'completed']);
        }

        ProcessedWebhook::create([
            'event_id' => $eventId,
            'provider' => 'stripe',
            'processed_at' => now(),
        ]);
    });

    return response()->json(['status' => 'success']);
}
```

### 4.4 Email Deduplication

```php
// ❌ BAD: Retry sends duplicate email
class SendWelcomeEmailJob implements ShouldQueue
{
    public function handle(User $user)
    {
        Mail::to($user)->send(new WelcomeEmail($user));
    }
}

// ✅ GOOD: Check + unique constraint
Schema::create('sent_emails', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('type');
    $table->timestamps();
    $table->unique(['user_id', 'type']);
});

class SendWelcomeEmailJob implements ShouldQueue
{
    public function handle(User $user)
    {
        try {
            SentEmail::create([
                'user_id' => $user->id,
                'type' => 'welcome',
            ]);
            Mail::to($user)->send(new WelcomeEmail($user));
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                Log::info('Welcome email already sent', ['user_id' => $user->id]);
                return;
            }
            throw $e;
        }
    }
}
```

---

## 5. Runtime Error Prevention

### 5.1 Null Safety

```php
// ❌ BAD: Potential null pointer
$user = User::find($request->user_id);
$name = $user->profile->name;

// ✅ GOOD: Null-safe operator
$user = User::find($request->user_id);
$name = $user?->profile?->name ?? 'Guest';

// ✅ GOOD: Explicit checks
$user = User::find($request->user_id);
if (!$user) {
    abort(404, 'User not found');
}
$profile = $user->profile;
if (!$profile) {
    abort(404, 'Profile not found');
}
$name = $profile->name;
```

### 5.2 Type Safety

```php
// ❌ BAD: No type hints
function calculateTotal($price, $quantity)
{
    return $price * $quantity;
}

// ✅ GOOD: Strict types
declare(strict_types=1);

function calculateTotal(float $price, int $quantity): float
{
    return $price * $quantity;
}
```

### 5.3 Array Access Safety

```php
// ❌ BAD: Direct access
$status = $data['status'];

// ✅ GOOD: Null coalescing
$status = $data['status'] ?? 'pending';

// ✅ GOOD: Array helper
$status = Arr::get($data, 'status', 'pending');

// ✅ GOOD: Validation
$validated = $request->validate([
    'status' => 'required|string|in:pending,processing,completed',
]);
$status = $validated['status'];
```

### 5.4 Division by Zero

```php
// ❌ BAD: No check
$percentage = ($part / $total) * 100;

// ✅ GOOD: Guard clause
$percentage = $total > 0 ? ($part / $total) * 100 : 0;

// ✅ GOOD: Helper
function safePercentage(float $part, float $total, int $decimals = 2): float
{
    return $total > 0 ? round(($part / $total) * 100, $decimals) : 0.0;
}
```

### 5.5 Date Parsing

```php
// ❌ BAD: Unvalidated
$date = Carbon::parse($request->date);

// ✅ GOOD: Validated
$validated = $request->validate([
    'date' => 'required|date_format:Y-m-d',
]);
$date = Carbon::createFromFormat('Y-m-d', $validated['date']);

// ✅ GOOD: Try-catch
try {
    $date = Carbon::parse($request->date);
} catch (Exception $e) {
    return response()->json(['error' => 'Invalid date format'], 422);
}
```

### 5.6 File Operations

```php
// ❌ BAD: No validation
$content = file_get_contents($request->file_path);

// ✅ GOOD: Validate
$filePath = storage_path('app/uploads/' . basename($request->file_path));
if (!file_exists($filePath)) {
    abort(404, 'File not found');
}
if (!is_readable($filePath)) {
    abort(403, 'File not readable');
}
$content = file_get_contents($filePath);

// ✅ GOOD: Storage facade
if (Storage::exists('uploads/' . $request->file_path)) {
    $content = Storage::get('uploads/' . $request->file_path);
}
```

### 5.7 JSON Operations

```php
// ❌ BAD: No error handling
$data = json_decode($request->json_data);

// ✅ GOOD: Check errors
$data = json_decode($request->json_data, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new InvalidJsonException(json_last_error_msg());
}

// ✅ GOOD: JSON cast
class Setting extends Model
{
    protected $casts = ['value' => 'json'];
}

// ✅ GOOD: Validation
$validated = $request->validate(['config' => 'required|json']);
```

### 5.8 External API Failures

```php
// ❌ BAD: No error handling
$response = Http::get('https://api.example.com/data');
$data = $response->json();

// ✅ GOOD: Comprehensive handling
$response = Http::timeout(10)
    ->retry(3, 100)
    ->get('https://api.example.com/data');

if ($response->failed()) {
    Log::error('API request failed', [
        'status' => $response->status(),
        'body' => $response->body(),
    ]);
    throw new ExternalApiException('Failed to fetch data from API');
}

$data = $response->json();
if (!isset($data['required_field'])) {
    throw new ExternalApiException('API response missing required field');
}
```

### 5.9 Memory Exhaustion

```php
// ❌ BAD: Loads all records
$users = User::all();
foreach ($users as $user) {
    // Process
}

// ✅ GOOD: Cursor
User::query()->cursor()->each(function (User $user) {
    // Process
});

// ✅ GOOD: Chunk
User::query()->chunk(1000, function (Collection $users) {
    foreach ($users as $user) {
        // Process
    }
});

// ✅ GOOD: Lazy
User::query()->lazy()->each(function (User $user) {
    // Process
});
```

---

## 6. Bugs & Anti-Patterns

### 6.1 N+1 Queries

```php
// ❌ BAD: N+1
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->user->name;
    echo $order->items->count();
}

// ✅ GOOD: Eager loading
$orders = Order::with(['user', 'items'])->get();
foreach ($orders as $order) {
    echo $order->user->name;
    echo $order->items->count();
}

// ✅ GOOD: Specific columns
$orders = Order::with([
    'user:id,name,email',
    'items:id,order_id,product_id,quantity',
])->get();

// ✅ GOOD: load() for existing
$orders = Order::all();
if ($orders->isNotEmpty()) {
    $orders->load('user');
}
```

### 6.2 Queries in Loops

```php
// ❌ BAD: Query in loop
foreach ($userIds as $userId) {
    $user = User::find($userId);
}

// ✅ GOOD: Single query
$users = User::whereIn('id', $userIds)->get();

// ✅ GOOD: findMany
$users = User::findMany($userIds);
```

### 6.3 Memory Leaks

```php
// ❌ BAD: Growing collection
$results = collect();
User::query()->cursor()->each(function ($user) use ($results) {
    $results->push($user);
});

// ✅ GOOD: Process and discard
User::query()->cursor()->each(function ($user) {
    ProcessUser::dispatch($user);
});

// ✅ GOOD: Generator
function getUsersGenerator()
{
    foreach (User::query()->cursor() as $user) {
        yield $user;
    }
}
```

### 6.4 SQL Injection

```php
// ❌ BAD: String concatenation
$users = DB::select("SELECT * FROM users WHERE name = '" . $request->name . "'");

// ✅ GOOD: Parameter binding
$users = DB::select("SELECT * FROM users WHERE name = ?", [$request->name]);

// ✅ GOOD: Query builder
$users = User::where('name', $request->name)->get();

// ✅ GOOD: Raw with bindings
$users = User::whereRaw("name = ? AND status = ?", [$request->name, 'active'])->get();
```

### 6.5 Mass Assignment

```php
// ❌ BAD: Direct mass assignment
class UserController extends Controller
{
    public function store(Request $request)
    {
        $user = User::create($request->all());
    }
}

// ✅ GOOD: $fillable/$guarded
class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];
}

// ✅ GOOD: Validated data only
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8',
    ]);
    $user = User::create($validated);
}
```

### 6.6 Missing Validation

```php
// ❌ BAD: No validation
public function update(Request $request, User $user)
{
    $user->update($request->all());
    return $user;
}

// ✅ GOOD: Form Request
class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $this->user->id,
            'password' => 'sometimes|string|min:8|confirmed',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user);
    }
}

public function update(UpdateUserRequest $request, User $user)
{
    $user->update($request->validated());
    return $user;
}
```

### 6.7 CSRF/Session Issues

```php
// ❌ BAD: State-changing GET
Route::get('/orders/{order}/cancel', [OrderController::class, 'cancel']);

// ✅ GOOD: Use POST/PUT/DELETE
Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

// ✅ GOOD: Resource routes
Route::resource('orders', OrderController::class);
```

### 6.8 Hardcoded Secrets

```php
// ❌ BAD: Secrets in code
$apiKey = 'sk_live_1234567890abcdef';

// ✅ GOOD: Use config
$apiKey = config('services.stripe.secret');

// ✅ GOOD: Never log secrets
Log::info('API call made', ['api_key' => $apiKey]); // NEVER
Log::info('API call made', ['endpoint' => 'payments']); // Safe
```

### 6.9 Debug Code in Production

```php
// ❌ BAD: Debug code left
public function process(Request $request)
{
    dd($request->all());
    dump($user);
    var_dump($data);
}

// ✅ GOOD: Use logging
public function process(Request $request)
{
    Log::debug('Processing request', ['data' => $request->all()]);
}

// ✅ GOOD: Debugbar in local only
if (app()->isLocal()) {
    Debugbar::info($request->all());
}
```

### 6.10 Dead Code

```php
// ❌ BAD: Unused code
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class OrderService
{
    private $unusedProperty;

    public function process(Order $order)
    {
        $unusedVariable = 'test';
        return $this->calculate($order);
    }

    private function unusedMethod()
    {
        return 'dead code';
    }
}

// ✅ GOOD: Clean code
class OrderService
{
    public function process(Order $order): Order
    {
        return $this->calculate($order);
    }

    private function calculate(Order $order): Order
    {
        // Implementation
    }
}
```

### 6.11 Laravel Anti-Patterns

```php
// ❌ BAD: env() outside config
$debug = env('APP_DEBUG');

// ✅ GOOD: config()
$debug = config('app.debug');

// ❌ BAD: Model::all() without limits
$users = User::all();

// ✅ GOOD: Pagination
$users = User::paginate(20);

// ❌ BAD: Business logic in controller
class OrderController extends Controller
{
    public function store(Request $request)
    {
        // 50 lines of business logic
    }
}

// ✅ GOOD: Delegate to action
class OrderController extends Controller
{
    public function store(CreateOrderRequest $request)
    {
        $order = app(CreateOrderAction::class)->execute($request->validated());
        return new OrderResource($order);
    }
}

// ❌ BAD: No scopes
class ProductController extends Controller
{
    public function index()
    {
        $products = Product::where('status', 'active')
            ->where('stock', '>', 0)
            ->whereNull('deleted_at')
            ->get();
    }
}

// ✅ GOOD: Use scopes
class Product extends Model
{
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}

$products = Product::active()->inStock()->get();
```

---

## 7. Security Patterns

### 7.1 SQL Injection Prevention

```php
// ❌ BAD: Raw SQL with user input
DB::select("SELECT * FROM users WHERE email = '{$request->email}'");

// ✅ GOOD: Parameterized queries
DB::select("SELECT * FROM users WHERE email = ?", [$request->email]);

// ✅ GOOD: Query builder
User::where('email', $request->email)->first();

// ✅ GOOD: Eloquent find
User::find($request->id);
```

### 7.2 XSS Prevention

```php
// ❌ BAD: Raw output
return view('profile', ['bio' => $user->bio]);
// In blade: {!! $bio !!}

// ✅ GOOD: Auto-escaped
return view('profile', ['bio' => $user->bio]);
// In blade: {{ $bio }}

// ✅ GOOD: Purify if HTML allowed
use Stevebauman\Purify\Facades\Purify;
$cleanBio = Purify::clean($request->bio);
$user->update(['bio' => $cleanBio]);
```

### 7.3 Authorization

```php
// ❌ BAD: Manual check
public function update(Request $request, Order $order)
{
    if (auth()->id() !== $order->user_id) {
        abort(403);
    }
}

// ✅ GOOD: Policy
public function update(Request $request, Order $order)
{
    $this->authorize('update', $order);
}

// ✅ GOOD: Middleware
Route::middleware(['can:update,order'])->group(function () {
    Route::put('/orders/{order}', [OrderController::class, 'update']);
});
```

### 7.4 Rate Limiting

```php
// ❌ BAD: No rate limiting
Route::post('/login', [AuthController::class, 'login']);

// ✅ GOOD: Rate limiting
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// ✅ GOOD: Custom rate limiter
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

---

## 8. Performance Patterns

### 8.1 Query Optimization

```php
// ❌ BAD: Select all
$users = User::all();

// ✅ GOOD: Select columns
$users = User::select('id', 'name', 'email')->get();

// ❌ BAD: Multiple count queries
$totalUsers = User::count();
$activeUsers = User::where('status', 'active')->count();
$pendingUsers = User::where('status', 'pending')->count();

// ✅ GOOD: Single query
$stats = User::select([
    DB::raw('COUNT(*) as total'),
    DB::raw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active'),
    DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
])->first();
```

### 8.2 Caching Strategies

```php
// ❌ BAD: No caching
public function getDashboardStats()
{
    return [
        'total_orders' => Order::count(),
        'revenue' => Order::sum('total'),
        'top_products' => Product::withCount('orders')->orderBy('orders_count', 'desc')->limit(10)->get(),
    ];
}

// ✅ GOOD: Cache expensive operations
public function getDashboardStats()
{
    return Cache::remember('dashboard_stats', 300, function () {
        return [
            'total_orders' => Order::count(),
            'revenue' => Order::sum('total'),
            'top_products' => Product::withCount('orders')->orderBy('orders_count', 'desc')->limit(10)->get(),
        ];
    });
}

// ✅ GOOD: Cache with tags
Cache::tags(['orders', 'dashboard'])->remember('dashboard_stats', 300, function () {
    return [...];
});
Cache::tags(['orders'])->flush();
```

### 8.3 Indexing Strategy

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('status')->index();
    $table->timestamp('created_at')->index();
    $table->decimal('total', 10, 2);
    $table->timestamps();

    // Composite index
    $table->index(['user_id', 'status', 'created_at']);
});
```

---

## 9. Testing Patterns

### 9.1 Business Logic Tests

```php
// ❌ BAD: Only tests HTTP 200
it('can create order', function () {
    $response = $this->postJson('/api/orders', ['product_id' => 1, 'quantity' => 1]);
    $response->assertStatus(201);
});

// ✅ GOOD: Tests business logic
it('cannot order more than available stock', function () {
    $product = Product::factory()->create(['stock' => 5]);

    $response = $this->postJson('/api/orders', [
        'product_id' => $product->id,
        'quantity' => 10,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['quantity']);

    expect($product->fresh()->stock)->toBe(5);
});

it('decrements stock on successful order', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $response = $this->postJson('/api/orders', [
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $response->assertStatus(201);
    expect($product->fresh()->stock)->toBe(7);
});
```

### 9.2 Race Condition Tests

```php
// ✅ GOOD: Concurrent request test
it('prevents overselling with concurrent requests', function () {
    $product = Product::factory()->create(['stock' => 1]);

    $responses = ConcurrentRequest::make()
        ->post('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->times(5)
        ->send();

    $successCount = collect($responses)->filter(fn($r) => $r->status() === 201)->count();
    expect($successCount)->toBe(1);
    expect($product->fresh()->stock)->toBe(0);
});
```

### 9.3 Idempotency Tests

```php
// ✅ GOOD: Idempotency test
it('handles duplicate payment attempts idempotently', function () {
    $order = Order::factory()->create(['status' => 'pending']);
    $idempotencyKey = Str::uuid()->toString();

    $response1 = $this->postJson('/api/payments', [
        'order_id' => $order->id,
        'idempotency_key' => $idempotencyKey,
    ]);

    $response2 = $this->postJson('/api/payments', [
        'order_id' => $order->id,
        'idempotency_key' => $idempotencyKey,
    ]);

    $response1->assertStatus(201);
    $response2->assertStatus(200);
    $response2->assertJsonPath('message', 'Payment already exists');

    expect(Payment::count())->toBe(1);
});
```

### 9.4 Transaction Tests

```php
// ✅ GOOD: Transaction rollback test
it('rolls back on failure', function () {
    $from = User::factory()->create(['balance' => 100]);
    $to = User::factory()->create(['balance' => 50]);

    // Mock external API failure
    Http::fake([
        '*' => Http::response(null, 500),
    ]);

    $this->postJson('/api/transfer', [
        'from_id' => $from->id,
        'to_id' => $to->id,
        'amount' => 30,
    ])->assertStatus(500);

    expect($from->fresh()->balance)->toBe(100);
    expect($to->fresh()->balance)->toBe(50);
});
```

### 9.5 Edge Case Tests

```php
// ✅ GOOD: Edge cases
it('handles empty cart', function () {
    $response = $this->postJson('/api/checkout', ['items' => []]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

it('handles deleted product in cart', function () {
    $product = Product::factory()->create();
    $cart = Cart::factory()->create(['product_id' => $product->id]);
    $product->delete();

    $response = $this->postJson('/api/checkout');
    $response->assertStatus(422)
        ->assertJson(['error' => 'Some items are no longer available']);
});
```

---

## 10. Laravel-Specific Deep Patterns

### 10.1 Eloquent Relationships

```php
// ❌ BAD: Incorrect relationship definition
class Order extends Model
{
    public function user()
    {
        return $this->hasOne(User::class);
    }
}

// ✅ GOOD: Correct relationship
class Order extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot('quantity', 'price')
            ->withTimestamps();
    }
}
```

### 10.2 Model Observers

```php
// ❌ BAD: Logic scattered in controller
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create($request->validated());
        $order->user->notify(new OrderCreated($order));
        Cache::forget('user_orders_' . $order->user_id);
        event(new OrderCreated($order));
    }
}

// ✅ GOOD: Observer
class OrderObserver
{
    public function created(Order $order): void
    {
        $order->user->notify(new OrderCreatedNotification($order));
        Cache::forget('user_orders_' . $order->user_id);
    }

    public function updated(Order $order): void
    {
        if ($order->isDirty('status')) {
            event(new OrderStatusChanged($order, $order->getOriginal('status'), $order->status));
        }
    }
}

// In AppServiceProvider
Order::observe(OrderObserver::class);
```

### 10.3 Service Container

```php
// ❌ BAD: Manual instantiation
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $service = new OrderService(new PaymentGateway(), new NotificationService());
        $service->create($request->validated());
    }
}

// ✅ GOOD: Dependency injection
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function store(CreateOrderRequest $request)
    {
        $order = $this->orderService->create($request->validated());
        return new OrderResource($order);
    }
}

// ✅ GOOD: Interface binding
interface PaymentGatewayInterface
{
    public function charge(float $amount, string $token): PaymentResponse;
}

class StripeGateway implements PaymentGatewayInterface { ... }

// In AppServiceProvider
$this->app->bind(PaymentGatewayInterface::class, StripeGateway::class);
```

### 10.4 Middleware

```php
// ❌ BAD: Auth check in controller
class OrderController extends Controller
{
    public function index()
    {
        if (!auth()->check()) {
            return redirect('login');
        }
        // ...
    }
}

// ✅ GOOD: Middleware
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
});

// ✅ GOOD: Custom middleware
class EnsureOrderOwner
{
    public function handle(Request $request, Closure $next)
    {
        $order = $request->route('order');
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }
        return $next($request);
    }
}

Route::middleware(['auth', EnsureOrderOwner::class])->group(function () {
    Route::get('/orders/{order}', [OrderController::class, 'show']);
});
```

### 10.5 Queues

```php
// ❌ BAD: Synchronous processing
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create($request->validated());
        Mail::to($order->user)->send(new OrderConfirmation($order));
        // User waits for email to send
    }
}

// ✅ GOOD: Dispatch to queue
class OrderController extends Controller
{
    public function store(CreateOrderRequest $request)
    {
        $order = Order::create($request->validated());
        SendOrderConfirmation::dispatch($order);
        return new OrderResource($order);
    }
}

// ✅ GOOD: Delayed dispatch
SendReminderEmail::dispatch($user)->delay(now()->addDays(3));

// ✅ GOOD: Queue with retry
class SendOrderConfirmation implements ShouldQueue
{
    public $tries = 3;
    public $backoff = [30, 60, 120];
    public $timeout = 60;

    public function handle(Order $order)
    {
        Mail::to($order->user)->send(new OrderConfirmation($order));
    }

    public function failed(Throwable $exception)
    {
        Log::error('Failed to send order confirmation', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### 10.6 Events & Listeners

```php
// ❌ BAD: Logic in model
class Order extends Model
{
    protected static function booted()
    {
        static::created(function ($order) {
            Mail::to($order->user)->send(new OrderCreated($order));
            Cache::forget('user_orders_' . $order->user_id);
        });
    }
}

// ✅ GOOD: Events
class OrderCreated
{
    public function __construct(public Order $order) {}
}

class SendOrderNotification
{
    public function handle(OrderCreated $event): void
    {
        $event->order->user->notify(new OrderCreatedNotification($event->order));
    }
}

class ClearOrderCache
{
    public function handle(OrderCreated $event): void
    {
        Cache::forget('user_orders_' . $event->order->user_id);
    }
}

// In EventServiceProvider
protected $listen = [
    OrderCreated::class => [
        SendOrderNotification::class,
        ClearOrderCache::class,
        UpdateSearchIndex::class,
    ],
];
```

### 10.7 Form Requests

```php
// ❌ BAD: Validation in controller
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);
        // ...
    }
}

// ✅ GOOD: Form Request
class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'quantity' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $product = Product::find($this->product_id);
                    if ($product && $product->stock < $value) {
                        $fail('Not enough stock available.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.exists' => 'The selected product does not exist.',
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id' => 'product',
            'quantity' => 'quantity',
        ];
    }
}
```

### 10.8 API Resources

```php
// ❌ BAD: Manual transformation
class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('user', 'items')->get();
        return response()->json([
            'data' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'user_name' => $order->user->name,
                    'total' => $order->total,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                        ];
                    }),
                ];
            }),
        ]);
    }
}

// ✅ GOOD: API Resource
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'total' => $this->total,
            'status' => $this->status,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['user', 'items'])->paginate(20);
        return OrderResource::collection($orders);
    }
}
```

### 10.9 Validation Rules

```php
// ❌ BAD: Loose validation
$request->validate([
    'email' => 'required',
    'role' => 'required',
]);

// ✅ GOOD: Strict validation
$request->validate([
    'email' => 'required|email:rfc,dns|unique:users,email',
    'role' => 'required|string|in:admin,user,moderator',
    'password' => 'required|string|min:12|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
    'avatar' => 'nullable|image|mimes:jpg,png|max:2048',
    'birthdate' => 'nullable|date|before:today',
]);
```

---

## 11. Review Checklist by File Type

### Controllers (`app/Http/Controllers/**`)
- [ ] Thin controllers — business logic delegated to services/actions
- [ ] Form Request validation used
- [ ] Authorization via policies or gates
- [ ] No database queries in loops
- [ ] No raw user input in responses (XSS prevention)
- [ ] Proper HTTP status codes
- [ ] Resource wrapping for API responses
- [ ] Rate limiting on public endpoints
- [ ] No business logic in controllers

### Models (`app/Models/**`)
- [ ] `$fillable` or `$guarded` defined
- [ ] Relationships have correct types and foreign keys
- [ ] Scopes defined for reusable queries
- [ ] Accessors/Mutators use proper type casting
- [ ] Soft deletes handled correctly if applicable
- [ ] Observers used for side effects
- [ ] Booted method for model events
- [ ] Custom casts for complex types
- [ ] Enum casting for status fields

### Services / Actions (`app/Services/**`, `app/Actions/**`)
- [ ] Single responsibility
- [ ] Database transactions for multi-step operations
- [ ] Idempotency checks for critical operations
- [ ] Race condition prevention for counters/state changes
- [ ] Proper exception handling with custom exceptions
- [ ] Interface-based design for testability
- [ ] No external API calls inside DB transactions
- [ ] Proper logging

### Jobs / Queue Workers (`app/Jobs/**`)
- [ ] Idempotent — safe to retry
- [ ] Unique job constraints where needed (`WithoutOverlapping`)
- [ ] Failed job handling and retry logic
- [ ] No database transactions held while calling external APIs
- [ ] Proper timeout configuration
- [ ] Proper logging on failure
- [ ] Memory-safe for large datasets

### Migrations (`database/migrations/**`)
- [ ] Foreign keys with proper onDelete/onUpdate
- [ ] Indexing strategy for query performance
- [ ] Down migrations are reversible
- [ ] No data loss in alter operations
- [ ] Missing unique constraints on business-critical fields
- [ ] Proper column types and lengths
- [ ] Soft delete columns where applicable

### Tests (`tests/**`)
- [ ] Feature tests cover business logic, not just HTTP 200
- [ ] Race condition tests with concurrent requests where critical
- [ ] Idempotency tests for retry scenarios
- [ ] Edge cases and failure paths covered
- [ ] Test data setup doesn't leak between tests
- [ ] Mock external APIs
- [ ] Test database transactions
- [ ] Test authorization

### Routes (`routes/**`)
- [ ] Route model binding correctness
- [ ] Middleware application (auth, throttle, etc.)
- [ ] No state-changing GET routes
- [ ] API versioning
- [ ] Named routes for URL generation
- [ ] Route groups for shared attributes

### Config (`config/**`)
- [ ] No hardcoded secrets
- [ ] Environment-specific defaults
- [ ] Proper type casting
- [ ] Documentation comments

### Blade Views (`resources/views/**`)
- [ ] Auto-escaped output (`{{ }}`)
- [ ] No business logic in views
- [ ] Components for reusable UI
- [ ] Proper CSRF tokens on forms

---

## AGENT FINAL INSTRUCTION

When reviewing code:
1. **ALWAYS** load `.coderabbit.yaml` and apply path-specific rules
2. **ALWAYS** load this SKILL.md for Laravel patterns
3. **ALWAYS** check all 6 categories for every file
4. **ALWAYS** provide exact file path and line numbers
5. **ALWAYS** provide copy-pasteable fixes
6. **ALWAYS** explain business impact, not just technical
7. **NEVER** skip a file that matches path filters
8. **NEVER** ignore path-specific instructions from `.coderabbit.yaml`

---

## 12. False Positive Prevention Guide

### 12.1 Business Logic — When NOT to Flag

```php
// ✅ DON'T flag: Form Request + Service validation both exist
class StoreOrderRequest extends FormRequest
{
    public function rules() { return ['qty' => 'required|integer|min:1']; }
}

class OrderService
{
    public function create(array $data)
    {
        $product = Product::findOrFail($data['product_id']);
        if ($product->stock < $data['qty']) { // Service validation exists
            throw new InsufficientStockException();
        }
    }
}
// DON'T say "missing validation" — it's in both layers

// ✅ DON'T flag: State machine library used
class Order extends Model
{
    use HasStates;
    // transitions defined in config/state-machines.php
}
// DON'T say "missing state validation" — handled by library

// ✅ DON'T flag: Money value object used
$order->total = Money::fromDecimal(19.99); // Stored as integer cents
// DON'T say "float precision issue" — Money object handles it

// ✅ DON'T flag: Policy-based auth
public function show(Order $order)
{
    $this->authorize('view', $order); // Policy exists
}
// DON'T say "missing authorization" — policy handles it

// ✅ DON'T flag: Soft delete with unique constraint
Schema::table('users', function ($table) {
    $table->unique(['email', 'deleted_at']);
});
// DON'T say "unique constraint violation on soft delete" — handled
```

### 12.2 Race Conditions — When NOT to Flag

```php
// ✅ DON'T flag: Atomic operation
Product::where('id', 1)->decrement('stock', 5);
// This is ATOMIC — no race condition possible

// ✅ DON'T flag: Pessimistic locking exists
DB::transaction(function () {
    $product = Product::lockForUpdate()->find(1);
    $product->stock -= 5;
    $product->save();
});
// Already safe with lockForUpdate

// ✅ DON'T flag: Cache lock exists
$lock = Cache::lock('submit:' . $id, 30);
if (!$lock->get()) { return; }
// Already protected against concurrent execution

// ✅ DON'T flag: Unique constraint prevents duplicates
Schema::table('subscriptions', function ($table) {
    $table->unique(['user_id', 'status']);
});
// Database prevents duplicates — no race condition

// ✅ DON'T flag: Read-only code
$templates = WhatsAppTemplate::all();
foreach ($templates as $template) {
    echo $template->name; // NO writes = NO race conditions
}

// ✅ DON'T flag: Single-row PK update
User::where('id', 1)->update(['last_login' => now()]);
// InnoDB locks the row automatically — atomic
```

### 12.3 Transactions — When NOT to Flag (CRITICAL)

```php
// ✅ DON'T flag: External API call OUTSIDE transaction
$response = Http::post('meta/api', $data); // No DB lock here
DB::transaction(function () use ($response) {
    $template->update(['status' => 'pending']);
});
// THIS IS CORRECT PATTERN — two-phase commit

// ✅ DON'T flag: Single INSERT
Order::create([...]);
// Single operation = no transaction needed

// ✅ DON'T flag: Read-only code
$orders = Order::where('status', 'pending')->get();
// No writes = no transaction needed

// ✅ DON'T flag: Already uses afterCommit
DB::transaction(function () {
    $order->update(['status' => 'shipped']);
    DB::afterCommit(function () {
        event(new OrderShipped($order));
    });
});
// Already correct

// ❌ NEVER suggest this (ANTI-PATTERN):
DB::transaction(function () {
    $response = Http::post('api/endpoint', $data); // WRONG!
    Model::update([...]);
});
// If you see code like this, suggest REMOVING transaction, not adding it
```

### 12.4 Idempotency — When NOT to Flag

```php
// ✅ DON'T flag: firstOrCreate used
Order::firstOrCreate(
    ['idempotency_key' => $key],
    ['total' => $total]
);
// Already idempotent

// ✅ DON'T flag: Unique constraint + try-catch
try {
    Payment::create(['transaction_id' => $id]); // UNIQUE
} catch (QueryException $e) {
    if (str_contains($e->getMessage(), 'Duplicate')) return;
}
// Already idempotent

// ✅ DON'T flag: Job with uniqueFor
class ProcessJob implements ShouldQueue
{
    public $uniqueFor = 3600;
    public function uniqueId() { return 'job-' . $this->id; }
}
// Already idempotent at queue level

// ✅ DON'T flag: Read-only or GET request
Route::get('/orders/{order}', [OrderController::class, 'show']);
// GET requests are naturally idempotent

// ✅ DON'T flag: Update with same value
$order->update(['status' => 'pending']);
// Running this twice produces same result
```

### 12.5 Runtime Errors — When NOT to Flag

```php
// ✅ DON'T flag: Null-safe operator used
$name = $user?->profile?->name ?? 'Guest';
// Already safe

// ✅ DON'T flag: Validated input
$validated = $request->validate(['date' => 'required|date']);
$date = Carbon::parse($validated['date']);
// Input is validated — safe to parse

// ✅ DON'T flag: findOrFail used
$user = User::findOrFail($id);
// Throws 404 if not found — handled by framework

// ✅ DON'T flag: Optional helper
$avatar = optional($user->profile)->avatar_url;
// Already safe

// ✅ DON'T flag: File validated by Form Request
$request->validate(['avatar' => 'required|image|mimes:jpg,png|max:2048']);
$path = $request->file('avatar')->store('avatars');
// File is validated — safe

// ✅ DON'T flag: JSON cast in model
protected $casts = ['settings' => 'json'];
// Laravel handles encode/decode — no manual json_decode needed

// ✅ DON'T flag: Memory-safe query
User::query()->cursor()->each(function ($user) { ... });
// Already memory-safe
```

### 12.6 Bugs & Anti-Patterns — When NOT to Flag

```php
// ✅ DON'T flag: Eager loading exists
$orders = Order::with(['user', 'items'])->get();
// Already eager-loaded — no N+1

// ✅ DON'T flag: Small table with Model::all()
$countries = Country::all(); // 195 rows
// Acceptable for small lookup tables

// ✅ DON'T flag: env() in config file
// config/services.php
'stripe' => ['key' => env('STRIPE_KEY')],
// Correct usage of env()

// ✅ DON'T flag: Thin controller
class OrderController extends Controller
{
    public function store(CreateOrderRequest $request)
    {
        $order = $this->service->create($request->validated());
        return new OrderResource($order);
    }
}
// 3 lines = thin controller — don't flag

// ✅ DON'T flag: Parameterized raw query
User::whereRaw("status = ? AND created_at > ?", ['active', $date]);
// Uses bindings — no SQL injection

// ✅ DON'T flag: $fillable defined
class User extends Model
{
    protected $fillable = ['name', 'email'];
}
User::create($request->validated()); // Safe
```

---

## 13. Deep Analysis Checklist

Before suggesting ANY fix, verify:

### For Business Logic Issues:
- [ ] I traced the full execution path from controller to DB
- [ ] I checked if validation exists in Form Request
- [ ] I checked if validation exists in Service/Action
- [ ] I verified state transitions are not handled by a library
- [ ] I verified calculations are not using a value object

### For Race Conditions:
- [ ] I confirmed the code performs a WRITE operation
- [ ] I confirmed no atomic operation is used (decrement, update with where)
- [ ] I confirmed no locking exists (lockForUpdate, Cache::lock)
- [ ] I confirmed no unique constraint prevents duplicates
- [ ] I confirmed the code is not read-only

### For Transactions:
- [ ] I confirmed there are MULTIPLE related DB writes
- [ ] I confirmed no external API call happens INSIDE the transaction
- [ ] I confirmed the code is not read-only
- [ ] I confirmed the code is not a single INSERT/UPDATE
- [ ] I confirmed afterCommit is not already used for events

### For Idempotency:
- [ ] I confirmed the operation can be retried (queue job, POST endpoint)
- [ ] I confirmed no firstOrCreate/unique constraint exists
- [ ] I confirmed no uniqueFor on job exists
- [ ] I confirmed the code is not read-only

### For Runtime Errors:
- [ ] I confirmed no null-safe operator is used
- [ ] I confirmed no validation exists before the risky operation
- [ ] I confirmed no findOrFail/optional helper is used
- [ ] I confirmed no type cast handles the conversion

---

## 14. Agent Self-Correction Protocol

If you previously suggested a fix and now realize it was wrong:

1. **Acknowledge the mistake** in the review
2. **Explain why the original code was correct**
3. **Do NOT suggest the incorrect fix again**
4. **Update your internal model** to avoid the same false positive

Example:
```
❌ PREVIOUSLY SUGGESTED (Incorrect):
"Wrap API call + DB update in transaction"

✅ CORRECTED ANALYSIS:
"The code correctly uses two-phase commit: API call happens outside 
the transaction, followed by a short DB update. Wrapping the API call 
in a transaction would hold DB locks during the HTTP request, which 
is an anti-pattern. The current implementation is correct."
```

---

## AGENT FINAL INSTRUCTION (Updated)

When reviewing code:
1. **ALWAYS** load `.coderabbit.yaml` and apply path-specific rules
2. **ALWAYS** load this SKILL.md for Laravel patterns
3. **ALWAYS** use code-explorer subagent for complex files before suggesting
4. **ALWAYS** check "When NOT to report" rules before flagging an issue
5. **ONLY** report 🔴 CERTAIN and 🟠 HIGH confidence issues
6. **NEVER** suggest wrapping external API calls in DB::transaction()
7. **ALWAYS** provide exact file path and line numbers
8. **ALWAYS** provide copy-pasteable fixes
9. **ALWAYS** explain business impact, not just technical
10. **NEVER** report an issue if you're not 100% certain
