---
name: laravel-code-review
description: Deep Laravel code review skill focusing on business logic, race conditions, transactions, idempotency, runtime errors, and bugs
license: MIT
compatibility: opencode
metadata:
  audience: laravel-developers
  workflow: code-review
---

# Laravel Code Review Skill

## When to use this skill
Use this skill when performing code reviews on Laravel/PHP codebases. This skill activates deep analysis mode for:
- Business logic validation
- Race condition detection
- Database transaction auditing
- Idempotency verification
- Runtime error prevention
- Bug and anti-pattern identification

## Laravel Best Practices Context

### Eloquent & Database
- **Always use eager loading** (`with()`, `load()`) to prevent N+1 queries
- **Use query scopes** for reusable query logic
- **Use Form Requests** for validation, not controller validation
- **Use atomic operations** (`increment()`, `decrement()`, `update()`) for counters
- **Use database transactions** for multi-step writes
- **Use pessimistic locking** (`lockForUpdate()`) for critical read-modify-write
- **Use `cursor()` or `lazy()`** for large datasets to prevent memory exhaustion
- **Never use `env()` outside config files** — use `config()` instead
- **Always define `$fillable` or `$guarded`** on models

### Business Logic Patterns
- **Use Service Classes** or **Action Classes** for complex business logic
- **Use Policy Classes** for authorization, not inline gates
- **Use Events/Listeners** for side effects (emails, notifications)
- **Use Value Objects** for complex calculations (Money, Percentage)
- **Validate state transitions** in model methods or State Machines

### Race Condition Prevention
```php
// ❌ BAD: Read-modify-write
$product = Product::find(1);
$product->stock -= 1;
$product->save();

// ✅ GOOD: Atomic operation
Product::where('id', 1)->decrement('stock', 1);

// ✅ GOOD: Pessimistic locking with validation
DB::transaction(function () {
    $product = Product::where('id', 1)->lockForUpdate()->first();
    if ($product->stock < 1) {
        throw new InsufficientStockException();
    }
    $product->decrement('stock', 1);
}, 5); // 5 retry attempts
```

### Idempotency Patterns
```php
// ✅ Idempotent job using unique constraint
class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        try {
            Payment::create([
                'transaction_id' => $this->transactionId, // UNIQUE
                'amount' => $this->amount,
                'status' => 'completed',
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                Log::info('Payment already processed');
                return;
            }
            throw $e;
        }
    }
}

// ✅ Idempotent API endpoint using idempotency key
public function store(Request $request)
{
    $request->validate([
        'idempotency_key' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0',
    ]);

    $payment = Payment::firstOrCreate(
        ['idempotency_key' => $request->idempotency_key],
        ['amount' => $request->amount, 'status' => 'pending']
    );

    if ($payment->wasRecentlyCreated) {
        // Process new payment
        $this->processPayment($payment);
    }

    return $payment;
}
```

### Transaction Best Practices
```php
// ✅ Wrap related operations
DB::transaction(function () use ($order, $user) {
    $order->update(['status' => 'paid']);
    $user->decrement('wallet_balance', $order->total);
    TransactionLog::create([...]);
}, 5); // retry 5 times on deadlock

// ✅ After-commit actions for events
DB::transaction(function () use ($order) {
    $order->update(['status' => 'shipped']);

    // This event fires AFTER the transaction commits
    event(new OrderShipped($order));
});

// In EventServiceProvider:
OrderShipped::class => [
    [SendShipmentNotification::class, 'afterCommit' => true],
],
```

### Runtime Error Prevention
```php
// ✅ Null-safe operator
$avatar = $user->profile?->avatar?->url;

// ✅ Safe array access
$status = $data['status'] ?? 'pending';

// ✅ Safe division
$percentage = $total > 0 ? ($part / $total) * 100 : 0;

// ✅ Validated file upload
if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
    $path = $request->file('avatar')->store('avatars');
}

// ✅ JSON error handling
$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new InvalidJsonException(json_last_error_msg());
}

// ✅ Memory-safe large queries
User::query()->cursor()->each(function ($user) {
    // Process user
});
```

## Review Checklist by File Type

### Controllers
- [ ] Thin controllers — business logic delegated to services/actions
- [ ] Form Request validation used
- [ ] Authorization via policies or gates
- [ ] No database queries in loops
- [ ] No raw user input in responses (XSS prevention)

### Models
- [ ] `$fillable` or `$guarded` defined
- [ ] Relationships have correct types and foreign keys
- [ ] Scopes defined for reusable queries
- [ ] Accessors/Mutators use proper type casting
- [ ] Soft deletes handled correctly if applicable

### Services / Actions
- [ ] Single responsibility
- [ ] Database transactions for multi-step operations
- [ ] Idempotency checks for critical operations
- [ ] Race condition prevention for counters/state changes
- [ ] Proper exception handling with custom exceptions

### Jobs / Queue Workers
- [ ] Idempotent — safe to retry
- [ ] Unique job constraints where needed (`WithoutOverlapping`)
- [ ] Failed job handling and retry logic
- [ ] No database transactions held while calling external APIs

### Migrations
- [ ] Foreign keys with proper onDelete/onUpdate
- [ ] Indexing strategy for query performance
- [ ] Down migrations are reversible
- [ ] No data loss in alter operations

### Tests
- [ ] Feature tests cover business logic, not just HTTP 200
- [ ] Race condition tests with concurrent requests where critical
- [ ] Idempotency tests for retry scenarios
- [ ] Edge cases and failure paths covered
