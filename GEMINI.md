# Gym Management System - Google Gemini Code Assistant Context

## Project Overview

This is a **Laravel 12** multi-tenant gym management system built with modern PHP architecture, designed for comprehensive gym operations management including members, payments, trainers, and staff coordination.

### Core Technology Architecture

**Multi-tenancy Strategy**: Ensuring complete data isolation between gym clients
**Admin Interface**: Multiple Filament v3 admin panels for different user roles (SuperAdmin, GymOwner, Manager, Trainer, Staff)
**Authentication**: Laravel Sanctum with Spatie Permission-based Role-Based Access Control (RBAC)
**Real-time Features**: Laravel Reverb WebSocket server with Laravel Echo for live updates
**Frontend**: Livewire v3 + Volt components with Tailwind CSS v4 and Flux UI

## Complete Tech Stack & Exact Versions

### Backend Framework & Core

```json
{
  "php": "^8.2",
  "laravel/framework": "^12.0",
  "filament/filament": "^3.3",
  "livewire/volt": "^1.7.0",
  "livewire/flux": "^2.1.1",
  "laravel/reverb": "^1.0",
  "laravel/pulse": "^1.4",
  "laravel/tinker": "^2.10.1"
}
```

### Authentication & Permissions

```json
{
  "laravel/sanctum": "^4.0",
  "spatie/laravel-permission": "^6.20"
}
```

**Role Hierarchy:**

- **super_admin**: Global system management across all tenants
- **gym_owner**: Full gym management within their tenant
- **manager**: Gym operations management and staff coordination
- **trainer**: Member training programs and session management
- **receptionist**: Front desk operations and member check-ins

use `DefaultRole.php` enum for role

### Filament Ecosystem

```json
{
  "filament/filament": "^3.3",
  "dotswan/filament-laravel-pulse": "^1.1",
  "filafly/filament-phosphor-icons": "^0.2.0",
  "joaopaulolndev/filament-general-settings": "^1.0",
  "malzariey/filament-daterangepicker-filter": "^3.3",
  "marcelweidum/filament-expiration-notice": "^1.0",
  "mokhosh/filament-rating": "^1.4",
  "rmsramos/activitylog": "^1.0",
  "ysfkaya/filament-phone-input": "^3.1",
  "codeat3/blade-phosphor-icons": "^2.3",
  "eightynine/filament-excel-import": "^3.1",
  "mokhosh/filament-kanban": "^2.11",
  "propaganistas/laravel-phone": "^6.0",
  "spatie/eloquent-sortable": "^4.5",
  "spatie/laravel-backup": "^9.3"
}
```

### Data Processing & Export

```json
{
  "maatwebsite/excel": "^3.1",
  "flowframe/laravel-trend": "^0.4.0"
}
```

### Payment Gateways

```json
{
  "razorpay/razorpay": "2.*"
}
```

### Third-party SDKs

```json
{
  "facebook/graph-sdk": "*",
  "twilio/sdk": "^8.8"
}
```

### Frontend Build & Styling

```json
{
  "vite": "^6.0",
  "tailwindcss": "^4.0.7",
  "@tailwindcss/vite": "^4.0.7",
  "laravel-vite-plugin": "^1.0",
  "autoprefixer": "^10.4.20",
  "axios": "^1.7.4",
  "concurrently": "^9.0.1"
}
```

### Real-time & WebSockets

```json
{
  "laravel-echo": "^2.0.2",
  "pusher-js": "^8.4.0",
  "predis/predis": "^3.2"
}
```

### Development & Quality Tools

```json
{
  "laravel/pint": "^1.22",
  "laravel/sail": "^1.41",
  "laravel/boost": "^1.0",
  "laravel/pail": "^1.2.2",
  "barryvdh/laravel-debugbar": "^3.15",
  "phpunit/phpunit": "^11.5.3",
  "barryvdh/laravel-ide-helper": "^3.6",
  "fakerphp/faker": "^1.23",
  "larastan/larastan": "^3.0",
  "mockery/mockery": "^1.6",
  "nunomaduro/collision": "^8.6"
}
```

## Laravel 12 Specific Directory Structure

```
app/
├── Console/Commands/          # Auto-registered Artisan commands
├── Filament/
│   ├── SuperAdmin/           # SuperAdmin panel (tenant-independent)
│   │   ├── Resources/        # Global system resources
│   │   └── Pages/           # Custom super-admin pages
│   ├── Admin/               # Tenant-specific admin panel
│   │   ├── Resources/       # Gym management resources
│   │   └── Pages/          # Custom admin pages
│   ├── Manager/            # Manager panel
│   ├── Trainer/            # Trainer panel
│   ├── Staff/              # Staff/Receptionist panel
│   ├── Exports/            # Filament Excel exports
│   └── Imports/            # Filament Excel imports
├── Http/
│   ├── Controllers/         # Standard HTTP controllers
│   └── Requests/           # Form validation classes (mandatory)
├── Livewire/               # Livewire v3 components (App\Livewire namespace)
├── Models/                 # Eloquent models with typed relationships
├── Notifications/          # Laravel notifications (email, database, etc.)
├── Observers/              # Eloquent model observers
├── Jobs/                   # Laravel queue jobs (ShouldQueue interface)
├── Listeners/              # Laravel event listeners
├── Policies/              # Authorization policies (auto-discovered)
├── Providers/             # Service providers
├── Rules/                 # Custom validation rules
├── Services/              # Business logic services
├── Exports/               # Maatwebsite Excel exports
└── Imports/               # Maatwebsite Excel imports

bootstrap/
├── app.php                # Middleware, exceptions, routing registration
└── providers.php          # Application service providers

resources/views/
├── components/            # Blade components
├── livewire/             # Livewire blade templates
├── volt/                 # Volt single-file components
└── layouts/              # Application layouts

routes/
├── web.php               # Web routes
├── api.php              # API routes (Sanctum authentication)
└── console.php          # Artisan commands registration

database/
├── migrations/           # Database schema migrations
├── seeders/             # Database seeders
└── factories/           # Model factories
```

## Core PHP 8.2+ Development Patterns

### Constructor Property Promotion (Mandatory)

```php
// ✅ Correct approach
public function __construct(
    public User $user,
    public PaymentService $paymentService,
    public ?string $membershipType = null
) {}

// ❌ Avoid old syntax
public function __construct(User $user, PaymentService $paymentService)
{
    $this->user = $user;
    $this->paymentService = $paymentService;
}
```

### Type Declarations (Required)

```php
// ✅ Always include return types and parameter types
protected function calculateMembershipFee(User $user, string $planType): float
{
    return match ($planType) {
        'basic' => 50.00,
        'premium' => 100.00,
        'vip' => 150.00,
        default => throw new InvalidArgumentException("Invalid plan: {$planType}")
    };
}

public function getActiveMemberships(): Collection
{
    return $this->memberships()->active()->get();
}
```

### Model Patterns with Casts Method

```php
// ✅ Use casts() method (Laravel 11+)
protected function casts(): array
{
    return [
        'joined_at' => 'datetime',
        'membership_expires_at' => 'datetime',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];
}

// ❌ Avoid $casts property
protected $casts = [...];
```

## Filament v3 Integration Patterns

### Resource Structure

```php
// app/Filament/Admin/Resources/MemberResource.php
namespace App\Filament\Admin\Resources;

use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Tables;
use App\Models\Member;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static ?string $navigationIcon = 'phosphor-users';
    protected static ?string $navigationGroup = 'Gym Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Member Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('membership_type')
                        ->options([
                            'basic' => 'Basic',
                            'premium' => 'Premium',
                            'vip' => 'VIP',
                        ])
                        ->required(),
                    Forms\Components\DatePicker::make('joined_at')
                        ->required()
                        ->default(now()),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('membership_type')
                    ->colors([
                        'success' => 'vip',
                        'warning' => 'premium',
                        'secondary' => 'basic',
                    ]),
                Tables\Columns\TextColumn::make('joined_at')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('membership_type')
                    ->options([
                        'basic' => 'Basic',
                        'premium' => 'Premium',
                        'vip' => 'VIP',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make(),
                ]),
            ]);
    }
}
```

### Panel Configuration

```php
// app/Providers/Filament/AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('/admin')
        ->login()
        ->colors([
            'primary' => Color::Blue,
        ])
        ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
        ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
        ->middleware([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ])
        ->authMiddleware([
            Authenticate::class,
        ]);
}
```

## Livewire v3 Component Patterns

### Traditional Livewire Component

```php
// app/Livewire/MembershipDashboard.php
namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Member;

#[Layout('layouts.app')]
#[Title('Membership Dashboard')]
class MembershipDashboard extends Component
{
    public string $search = '';
    public string $membershipFilter = 'all';
    
    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    
    public function updatedMembershipFilter(): void
    {
        $this->resetPage();
    }
    
    public function getMembers()
    {
        return Member::query()
            ->when($this->search, fn($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->membershipFilter !== 'all', fn($query) => $query->where('membership_type', $this->membershipFilter))
            ->with(['trainer', 'payments'])
            ->latest()
            ->paginate(20);
    }
    
    public function render()
    {
        return view('livewire.membership-dashboard', [
            'members' => $this->getMembers(),
        ]);
    }
}
```

### Volt Single-File Component (Preferred)

```php
// resources/views/volt/member-search.blade.php
<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Member;

new class extends Component {
    public string $search = '';
    public string $filter = 'active';
    
    public function mount(): void
    {
        $this->search = request('search', '');
    }
    
    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->dispatch('search-updated', search: $this->search);
    }
    
    public function getMembers()
    {
        return Member::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filter === 'active', fn($q) => $q->whereHas('activeMembership'))
            ->with('latestMembership')
            ->paginate(15);
    }
}; ?>

<div>
    <div class="mb-6 flex gap-4">
        <flux:input 
            wire:model.live.debounce.300ms="search" 
            placeholder="Search members..."
            class="flex-1"
        />
        
        <flux:select wire:model.live="filter">
            <option value="all">All Members</option>
            <option value="active">Active</option>
            <option value="expired">Expired</option>
        </flux:select>
    </div>

    <div class="grid gap-4">
        @foreach($this->members as $member)
            <div wire:key="member-{{ $member->id }}" class="bg-white dark:bg-gray-800 rounded-lg p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="font-semibold">{{ $member->name }}</h3>
                        <p class="text-sm text-gray-600">{{ $member->email }}</p>
                    </div>
                    
                    @if($member->latestMembership)
                        <flux:badge variant="success">
                            {{ ucfirst($member->latestMembership->type) }}
                        </flux:badge>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{ $this->members->links() }}
</div>
```

### Model Relationships Pattern

```php
// app/Models/Member.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;

class Member extends Model
{
    use HasFactory, HasRoles;
    
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'date_of_birth' => 'date',
            'emergency_contact' => 'array',
            'medical_conditions' => 'array',
            'is_active' => 'boolean',
        ];
    }
    
    // ✅ Typed relationships
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
    
    public function activeMembership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'active_membership_id');
    }
    
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
    
    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }
    
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
    
    // ✅ Scopes for common queries
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeWithExpiredMembership($query)
    {
        return $query->whereHas('activeMembership', function ($q) {
            $q->where('expires_at', '<', now());
        });
    }
}
```

## Tailwind CSS v4 Conventions

### Important v4 Changes

```css
/* ✅ v4 Import syntax */
@import "tailwindcss";

/* ❌ v3 syntax (don't use) */
@tailwind base;
@tailwind components;
@tailwind utilities;
```

### Opacity Slash Notation (v4)

```html
<!-- ✅ v4 opacity syntax -->
<div class="bg-black/20 text-white/80 border-gray-300/50"></div>

<!-- ❌ v3 syntax (deprecated) -->
<div class="bg-opacity-20 text-opacity-80"></div>
```

### Spacing with Gap Utilities

```html
<!-- ✅ Use gap for spacing -->
<div class="flex gap-4">
    <flux:button>Action 1</flux:button>
    <flux:button>Action 2</flux:button>
</div>

<div class="grid grid-cols-3 gap-6">
    <div>Card 1</div>
    <div>Card 2</div>
    <div>Card 3</div>
</div>

<!-- ❌ Avoid margins for layout spacing -->
<div class="flex">
    <div class="mr-4">Item</div>
    <div>Item</div>
</div>
```

## Flux UI Component Library (Free Edition)

### Available Components

`avatar`, `badge`, `brand`, `breadcrumbs`, `button`, `callout`, `checkbox`, `dropdown`, `field`, `heading`, `icon`, `input`, `modal`, `navbar`, `profile`, `radio`, `select`, `separator`, `switch`, `text`, `textarea`, `tooltip`

### Usage Examples

```php
<!-- Form Components -->
<flux:field label="Member Name" required>
    <flux:input wire:model="form.name" placeholder="Enter full name" />
</flux:field>

<flux:field label="Membership Type">
    <flux:select wire:model="form.membership_type">
        <option value="">Select type...</option>
        <option value="basic">Basic ($50/month)</option>
        <option value="premium">Premium ($100/month)</option>
        <option value="vip">VIP ($150/month)</option>
    </flux:select>
</flux:field>

<!-- Interactive Elements -->
<flux:button 
    variant="primary" 
    wire:click="saveMember" 
    wire:loading.attr="disabled"
>
    <span wire:loading.remove>Save Member</span>
    <span wire:loading>Saving...</span>
</flux:button>

<!-- Status Indicators -->
<flux:badge variant="success">Active</flux:badge>
<flux:badge variant="warning">Expiring Soon</flux:badge>
<flux:badge variant="danger">Expired</flux:badge>

<!-- Modal Example -->
<flux:modal name="member-details" variant="flyout">
    <flux:modal.header>
        <flux:heading size="lg">Member Details</flux:heading>
    </flux:modal.header>
    
    <flux:modal.body>
        <!-- Member information content -->
    </flux:modal.body>
    
    <flux:modal.footer>
        <flux:button variant="ghost" wire:click="$dispatch('modal.close')">Cancel</flux:button>
        <flux:button variant="primary" wire:click="updateMember">Update</flux:button>
    </flux:modal.footer>
</flux:modal>
```

## Docker Development Environment

### Service Architecture

```yaml
# docker-compose.yml services breakdown
services:
  app:          # Laravel application container
  web:          # Nginx web server (port 8000)
  db:           # MySQL 8.0 database (port 3307)
  redis:        # Redis cache/sessions (port 6379)
  phpmyadmin:   # Database management (port 8081)
```

### Environment Variables for Docker

```env
# Database (Docker MySQL)
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=gym_app
DB_USERNAME=user
DB_PASSWORD=password

# Redis (Docker Redis)
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Reverb WebSocket (Docker app container)
REVERB_APP_ID=gym-app
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

# Session & Cache
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# Broadcasting
BROADCAST_CONNECTION=reverb
```

### Development Workflow Commands

```bash
# Start all services
docker-compose up -d

# Laravel setup within container
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate --seed

# Real-time development (concurrent)
docker-compose exec app composer run dev
# This runs: Laravel server + Queue worker + Vite

# Code formatting (mandatory before commits)
docker-compose exec app vendor/bin/pint --dirty

# Run tests
docker-compose exec app php artisan test
docker-compose exec app php artisan test --filter=MemberTest

# Access container shell
docker-compose exec app bash
```

## Testing Patterns & Requirements

### Feature Testing (Preferred Approach)

```php
// tests/Feature/MemberManagementTest.php
declare(strict_types=1);

use App\Models\{User, Member, Membership};
use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Resources\MemberResource\Pages\{ListMembers, CreateMember};

test('gym owner can view members list', function () {
    $gymOwner = User::factory()->gymOwner()->create();
    $members = Member::factory(5)->create();
    
    actingAs($gymOwner);
    
    livewire(ListMembers::class)
        ->assertCanSeeTableRecords($members)
        ->assertCountTableRecords(5);
});

test('gym owner can create new member', function () {
    $gymOwner = User::factory()->gymOwner()->create();
    
    livewire(CreateMember::class)
        ->actingAs($gymOwner)
        ->fillForm([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'membership_type' => 'premium',
            'joined_at' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();
        
    expect(Member::where('email', 'john@example.com')->exists())->toBeTrue();
});

test('member search functionality works correctly', function () {
    $members = Member::factory(10)->create();
    $searchMember = $members->first();
    
    livewire(ListMembers::class)
        ->searchTable($searchMember->name)
        ->assertCanSeeTableRecords([$searchMember])
        ->assertCanNotSeeTableRecords($members->skip(1));
});
```

### Volt Component Testing

```php
// tests/Feature/Volt/MemberSearchTest.php
use Livewire\Volt\Volt;

test('member search component filters correctly', function () {
    $activeMembers = Member::factory(3)->active()->create();
    $inactiveMembers = Member::factory(2)->inactive()->create();
    
    Volt::test('member-search')
        ->assertSee($activeMembers->first()->name)
        ->set('filter', 'active')
        ->assertDontSee($inactiveMembers->first()->name);
});

test('search input updates results in real-time', function () {
    $john = Member::factory()->create(['name' => 'John Smith']);
    $jane = Member::factory()->create(['name' => 'Jane Doe']);
    
    Volt::test('member-search')
        ->set('search', 'John')
        ->assertSee('John Smith')
        ->assertDontSee('Jane Doe')
        ->set('search', 'Jane')
        ->assertSee('Jane Doe')
        ->assertDontSee('John Smith');
});
```

## Form Validation Patterns

### Form Request Classes (Mandatory)

```php
// app/Http/Requests/StoreMemberRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Member::class);
    }
    
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:members,email'],
            'phone' => ['required', 'string', 'regex:/^[+]?[0-9\s\-\(\)]+$/'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'membership_type' => ['required', Rule::in(['basic', 'premium', 'vip'])],
            'emergency_contact' => ['required', 'array'],
            'emergency_contact.name' => ['required', 'string', 'max:255'],
            'emergency_contact.phone' => ['required', 'string'],
            'medical_conditions' => ['array'],
            'medical_conditions.*' => ['string', 'max:255'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'email.unique' => 'A member with this email already exists.',
            'phone.regex' => 'Please enter a valid phone number.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
        ];
    }
    
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/[^\+0-9]/', '', $this->phone),
        ]);
    }
}
```

### Livewire Form Object Pattern

```php
// app/Livewire/Forms/MemberForm.php
namespace App\Livewire\Forms;

use Livewire\Form;
use App\Models\Member;

class MemberForm extends Form
{
    public ?Member $member;
    
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $membership_type = '';
    public array $emergency_contact = [];
    
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email,' . $this->member?->id,
            'phone' => 'required|string',
            'membership_type' => 'required|in:basic,premium,vip',
            'emergency_contact.name' => 'required|string',
            'emergency_contact.phone' => 'required|string',
        ];
    }
    
    public function setMember(Member $member): void
    {
        $this->member = $member;
        $this->fill($member->only(['name', 'email', 'phone', 'membership_type']));
        $this->emergency_contact = $member->emergency_contact ?? [];
    }
    
    public function store(): Member
    {
        $this->validate();
        
        return Member::create($this->only(['name', 'email', 'phone', 'membership_type', 'emergency_contact']));
    }
    
    public function update(): void
    {
        $this->validate();
        
        $this->member->update($this->only(['name', 'email', 'phone', 'membership_type', 'emergency_contact']));
    }
}
```

## Real-time Features with Laravel Reverb

### WebSocket Configuration

```php
// config/reverb.php
return [
    'default' => env('REVERB_SERVER', 'reverb'),
    
    'servers' => [
        'reverb' => [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 8080),
            'hostname' => env('REVERB_HOSTNAME'),
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', 6379),
                    'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'),
                    'database' => env('REDIS_DB', 0),
                ],
            ],
        ],
    ],
];
```

### Broadcasting Events

```php
// app/Events/MemberCheckedIn.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Member;

class MemberCheckedIn implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public function __construct(
        public Member $member,
        public string $checkInTime
    ) {}
    
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('gym.' . $this->member->gym_id),
            new Channel('member-activity'),
        ];
    }
    
    public function broadcastWith(): array
    {
        return [
            'member_id' => $this->member->id,
            'member_name' => $this->member->name,
            'check_in_time' => $this->checkInTime,
            'membership_type' => $this->member->membership_type,
        ];
    }
}
```

### Laravel Echo Integration

```javascript
// resources/js/app.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// Listen for member check-ins
window.Echo.private('gym.1')
    .listen('MemberCheckedIn', (e) => {
        // Update UI or trigger Livewire refresh
        Livewire.dispatch('member-checked-in', e);
    });
```

## Multi-Tenancy Implementation Details

### Tenant Model Structure

```php
// app/Models/Tenant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use HasFactory;
    
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'subscription_expires_at' => 'datetime',
        ];
    }
    
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    
    public function getDatabaseName(): string
    {
        return "gym_tenant_{$this->id}";
    }
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('subscription_expires_at', '>', now());
    }
}
```

## Service Layer Patterns

### Payment Processing Service

```php
// app/Services/PaymentService.php
namespace App\Services;

use App\Models\{Member, Payment, Membership};
use App\Events\PaymentProcessed;
use App\Exceptions\PaymentFailedException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private StripeService $stripe,
        private NotificationService $notifications
    ) {}
    
    public function processMembershipPayment(Member $member, string $membershipType, float $amount): Payment
    {
        return DB::transaction(function () use ($member, $membershipType, $amount) {
            // Create payment record
            $payment = Payment::create([
                'member_id' => $member->id,
                'amount' => $amount,
                'currency' => 'USD',
                'type' => 'membership',
                'status' => 'pending',
                'metadata' => ['membership_type' => $membershipType],
            ]);
            
            try {
                // Process with payment gateway
                $stripePayment = $this->stripe->charge($amount, $member->stripe_customer_id);
                
                $payment->update([
                    'status' => 'completed',
                    'gateway_transaction_id' => $stripePayment->id,
                    'processed_at' => now(),
                ]);
                
                // Create or extend membership
                $this->createOrExtendMembership($member, $membershipType);
                
                // Dispatch events
                PaymentProcessed::dispatch($payment);
                
                return $payment;
                
            } catch (\Exception $e) {
                $payment->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
                throw new PaymentFailedException("Payment failed: {$e->getMessage()}");
            }
        });
    }
    
    private function createOrExtendMembership(Member $member, string $type): Membership
    {
        $existingMembership = $member->activeMembership;
        
        if ($existingMembership && $existingMembership->expires_at > now()) {
            // Extend existing membership
            $existingMembership->update([
                'expires_at' => $existingMembership->expires_at->addMonth(),
                'type' => $type,
            ]);
            
            return $existingMembership;
        }
        
        // Create new membership
        return Membership::create([
            'member_id' => $member->id,
            'type' => $type,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);
    }
}
```

## Job Queue Patterns

### Background Processing

```php
// app/Jobs/ProcessMembershipExpiration.php
namespace App\Jobs;

use App\Models\{Member, Membership};
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class ProcessMembershipExpiration implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;
    
    public int $tries = 3;
    public int $timeout = 300;
    
    public function __construct(
        public Membership $membership
    ) {}
    
    public function handle(NotificationService $notificationService): void
    {
        if ($this->membership->expires_at <= now()) {
            $this->membership->update(['is_active' => false]);
            
            // Notify member
            $notificationService->sendMembershipExpiredNotification($this->membership->member);
            
            // Notify gym staff
            $notificationService->notifyStaffOfExpiredMembership($this->membership);
        }
    }
}
```

### Scheduled Commands

```php
// app/Console/Commands/CheckMembershipExpirations.php
namespace App\Console\Commands;

use App\Jobs\ProcessMembershipExpiration;
use App\Models\Membership;
use Illuminate\Console\Command;

class CheckMembershipExpirations extends Command
{
    protected $signature = 'gym:check-expirations';
    protected $description = 'Check for expiring memberships and process them';
    
    public function handle(): int
    {
        $expiringMemberships = Membership::where('expires_at', '<=', now())
            ->where('is_active', true)
            ->get();
            
        $this->info("Found {$expiringMemberships->count()} expiring memberships");
        
        foreach ($expiringMemberships as $membership) {
            ProcessMembershipExpiration::dispatch($membership);
        }
        
        $this->info('Expiration processing jobs dispatched');
        
        return self::SUCCESS;
    }
}
```

## Authorization & Security Patterns

### Policy Implementation

```php
// app/Policies/MemberPolicy.php
namespace App\Policies;

use App\Models\{User, Member};

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['SuperAdmin', 'GymOwner', 'Manager', 'Trainer', 'Receptionist']);
    }
    
    public function view(User $user, Member $member): bool
    {
        if ($user->hasRole('SuperAdmin')) {
            return true;
        }
        
        // Tenant-specific access
        return $user->tenant_id === $member->tenant_id &&
               $user->hasAnyRole(['GymOwner', 'Manager', 'Trainer', 'Receptionist']);
    }
    
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['GymOwner', 'Manager', 'Receptionist']);
    }
    
    public function update(User $user, Member $member): bool
    {
        if ($user->hasRole('SuperAdmin')) {
            return true;
        }
        
        return $user->tenant_id === $member->tenant_id &&
               $user->hasAnyRole(['GymOwner', 'Manager']);
    }
    
    public function delete(User $user, Member $member): bool
    {
        return $user->hasAnyRole(['SuperAdmin', 'GymOwner']) &&
               ($user->hasRole('SuperAdmin') || $user->tenant_id === $member->tenant_id);
    }
}
```

### Gate Definitions

```php
// app/Providers/AuthServiceProvider.php
namespace App\Providers;

use App\Models\{User, Member, Payment};
use App\Policies\{MemberPolicy, PaymentPolicy};
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Member::class => MemberPolicy::class,
        Payment::class => PaymentPolicy::class,
    ];
    
    public function boot(): void
    {
        Gate::define('manage-gym-settings', function (User $user) {
            return $user->hasRole('GymOwner');
        });
        
        Gate::define('view-financial-reports', function (User $user) {
            return $user->hasAnyRole(['SuperAdmin', 'GymOwner', 'Manager']);
        });
        
        Gate::define('assign-trainers', function (User $user) {
            return $user->hasAnyRole(['GymOwner', 'Manager']);
        });
    }
}
```

## Performance & Optimization Guidelines

### Query Optimization Patterns

```php
// ✅ Prevent N+1 queries with eager loading
public function getGymDashboardData(): array
{
    $members = Member::with([
        'activeMembership',
        'trainer:id,name',
        'latestPayment:id,amount,status,created_at'
    ])->active()->get();
    
    $trainers = User::whereHas('role', fn($q) => $q->where('name', 'Trainer'))
        ->withCount(['assignedMembers', 'todaySessions'])
        ->get();
        
    return compact('members', 'trainers');
}

// ✅ Use database queries for aggregations
public function getMonthlyRevenue(): float
{
    return Payment::where('status', 'completed')
        ->whereMonth('created_at', now()->month)
        ->sum('amount');
}
```

### Caching Strategy

```php
// app/Services/DashboardService.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Models\{Member, Payment, TrainingSession};

class DashboardService
{
    public function getGymStatistics(): array
    {
        return Cache::remember('gym.stats.' . auth()->user()->tenant_id, 300, function () {
            return [
                'total_members' => Member::count(),
                'active_members' => Member::active()->count(),
                'monthly_revenue' => Payment::thisMonth()->completed()->sum('amount'),
                'today_sessions' => TrainingSession::today()->count(),
                'equipment_maintenance_due' => Equipment::maintenanceDue()->count(),
            ];
        });
    }
    
    public function clearGymCache(): void
    {
        Cache::forget('gym.stats.' . auth()->user()->tenant_id);
        Cache::tags(['gym', 'tenant.' . auth()->user()->tenant_id])->flush();
    }
}
```

## Error Handling & Logging

### Exception Handling

```php
// app/Exceptions/PaymentFailedException.php
namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentFailedException extends Exception
{
    public function __construct(
        string $message = 'Payment processing failed',
        public readonly ?array $paymentData = null,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
    
    public function render(Request $request): Response
    {
        return response()->view('errors.payment-failed', [
            'message' => $this->getMessage(),
            'paymentData' => $this->paymentData,
        ], 422);
    }
    
    public function context(): array
    {
        return [
            'payment_data' => $this->paymentData,
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()?->tenant_id,
        ];
    }
}
```

### Structured Logging

```php
// Throughout the application
use Illuminate\Support\Facades\Log;

// ✅ Structured logging with context
Log::info('Member membership renewed', [
    'member_id' => $member->id,
    'membership_type' => $membershipType,
    'payment_amount' => $amount,
    'tenant_id' => $member->tenant_id,
]);

Log::error('Payment gateway error', [
    'error' => $exception->getMessage(),
    'payment_id' => $payment->id,
    'gateway_response' => $gatewayResponse,
]);
```

## Advanced Filament Patterns

### Custom Actions

```php
// Within a Filament Resource
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\{TextInput, Select, DatePicker};

Tables\Actions\Action::make('renew_membership')
    ->label('Renew Membership')
    ->icon('phosphor-credit-card')
    ->color('success')
    ->form([
        Select::make('membership_type')
            ->options([
                'basic' => 'Basic - $50/month',
                'premium' => 'Premium - $100/month',
                'vip' => 'VIP - $150/month',
            ])
            ->required(),
        DatePicker::make('start_date')
            ->default(now())
            ->required(),
        TextInput::make('discount_percentage')
            ->numeric()
            ->suffix('%')
            ->default(0),
    ])
    ->action(function (Member $record, array $data): void {
        $amount = match ($data['membership_type']) {
            'basic' => 50.00,
            'premium' => 100.00,
            'vip' => 150.00,
        };
        
        if ($data['discount_percentage'] > 0) {
            $amount = $amount * (1 - $data['discount_percentage'] / 100);
        }
        
        app(PaymentService::class)->processMembershipPayment(
            $record, 
            $data['membership_type'], 
            $amount
        );
        
        $this->notify('success', 'Membership renewed successfully!');
    });
```

### Custom Widgets

```php
// app/Filament/Admin/Widgets/GymStatsWidget.php
namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\{Member, Payment, TrainingSession};
use App\Services\DashboardService;

class GymStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        $stats = app(DashboardService::class)->getGymStatistics();
        
        return [
            Stat::make('Total Members', $stats['total_members'])
                ->description('Active gym members')
                ->descriptionIcon('phosphor-users')
                ->color('success'),
                
            Stat::make('Monthly Revenue', ' . number_format($stats['monthly_revenue'], 2))
                ->description('This month\'s earnings')
                ->descriptionIcon('phosphor-currency-dollar')
                ->color('success'),
                
            Stat::make('Today\'s Sessions', $stats['today_sessions'])
                ->description('Training sessions scheduled')
                ->descriptionIcon('phosphor-calendar')
                ->color('info'),
                
            Stat::make('Maintenance Due', $stats['equipment_maintenance_due'])
                ->description('Equipment requiring attention')
                ->descriptionIcon('phosphor-wrench')
                ->color($stats['equipment_maintenance_due'] > 0 ? 'danger' : 'success'),
        ];
    }
}
```

## Advanced Testing Strategies

### Integration Testing

```php
// tests/Feature/Integration/MembershipWorkflowTest.php
declare(strict_types=1);

use App\Models\{User, Member, Payment, Membership};
use App\Services\PaymentService;
use App\Jobs\ProcessMembershipExpiration;
use Illuminate\Support\Facades\Queue;

test('complete membership workflow processes correctly', function () {
    Queue::fake();
    
    $gymOwner = User::factory()->gymOwner()->create();
    $member = Member::factory()->create(['tenant_id' => $gymOwner->tenant_id]);
    
    // Test membership creation
    $paymentService = app(PaymentService::class);
    $payment = $paymentService->processMembershipPayment($member, 'premium', 100.00);
    
    expect($payment->status)->toBe('completed');
    expect($payment->amount)->toBe(100.00);
    
    $membership = $member->fresh()->activeMembership;
    expect($membership)->not->toBeNull();
    expect($membership->type)->toBe('premium');
    expect($membership->is_active)->toBeTrue();
    
    // Test expiration job dispatching
    ProcessMembershipExpiration::dispatch($membership);
    Queue::assertPushed(ProcessMembershipExpiration::class);
});

test('expired memberships are automatically deactivated', function () {
    $member = Member::factory()->create();
    $expiredMembership = Membership::factory()->create([
        'member_id' => $member->id,
        'expires_at' => now()->subDay(),
        'is_active' => true,
    ]);
    
    // Run expiration job
    $job = new ProcessMembershipExpiration($expiredMembership);
    $job->handle(app(NotificationService::class));
    
    expect($expiredMembership->fresh()->is_active)->toBeFalse();
});
```

### Filament Resource Testing

```php
// tests/Feature/Filament/MemberResourceTest.php
use App\Filament\Admin\Resources\MemberResource;
use App\Filament\Admin\Resources\MemberResource\Pages\{ListMembers, CreateMember, EditMember};

test('gym owner can manage members through filament', function () {
    $gymOwner = User::factory()->gymOwner()->create();
    $members = Member::factory(10)->create(['tenant_id' => $gymOwner->tenant_id]);
    
    // Test list page
    livewire(ListMembers::class)
        ->actingAs($gymOwner)
        ->assertCanSeeTableRecords($members);
    
    // Test creation
    livewire(CreateMember::class)
        ->actingAs($gymOwner)
        ->fillForm([
            'name' => 'Test Member',
            'email' => 'test@gym.com',
            'phone' => '+1234567890',
            'membership_type' => 'premium',
        ])
        ->call('create')
        ->assertHasNoErrors();
        
    expect(Member::where('email', 'test@gym.com')->exists())->toBeTrue();
});

test('trainers cannot access member financial information', function () {
    $trainer = User::factory()->trainer()->create();
    $member = Member::factory()->create(['tenant_id' => $trainer->tenant_id]);
    
    livewire(EditMember::class, ['record' => $member->id])
        ->actingAs($trainer)
        ->assertForbidden();
});
```

## Development Workflow & Commands

### Daily Development Commands

```bash
# Start Docker environment
docker-compose up -d

# Install dependencies (first time)
docker-compose exec app composer install
docker-compose exec app npm install

# Development server (concurrent: Laravel + Queue + Vite)
docker-compose exec app composer run dev

# Alternative: Individual processes
docker-compose exec app php artisan serve --host=0.0.0.0 --port=8000
docker-compose exec app php artisan queue:work --tries=3
docker-compose exec app npm run dev
```

### Code Quality & Testing Workflow

```bash
# Format code (MANDATORY before commits)
docker-compose exec app vendor/bin/pint --dirty

# Run tests
docker-compose exec app php artisan test
docker-compose exec app php artisan test --filter=MemberTest
docker-compose exec app php artisan test tests/Feature/

# Clear caches for testing
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan view:clear
```

### Database Management

```bash
# Run migrations
docker-compose exec app php artisan migrate

# Fresh migration with seeding
docker-compose exec app php artisan migrate:fresh --seed

# Create new migration
docker-compose exec app php artisan make:migration create_training_sessions_table --create=training_sessions

# Create model with factory, seeder, policy
docker-compose exec app php artisan make:model TrainingSession --factory --seeder --policy --migration
```

### Filament Resource Generation

```bash
# Generate complete Filament resource
docker-compose exec app php artisan make:filament-resource Customer --generate

# Create custom Filament page
docker-compose exec app php artisan filament:page Settings

# Create Filament widget
docker-compose exec app php artisan make:filament-widget StatsOverview --stats-overview

# Optimize Filament (production)
docker-compose exec app php artisan filament:optimize
```

## Environment Configuration

### Docker Environment Variables

```env
# Application
APP_NAME="Gym Management System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (Docker MySQL)
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=gym_app
DB_USERNAME=user
DB_PASSWORD=password

# Redis (Docker Redis)
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=predis

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Queue
QUEUE_CONNECTION=redis

# WebSockets (Laravel Reverb)
REVERB_APP_ID=gym-management
REVERB_APP_KEY=your-reverb-key
REVERB_APP_SECRET=your-reverb-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

# Broadcasting
BROADCAST_CONNECTION=reverb

# Vite (for asset compilation)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# Filament
FILAMENT_FORCE_HTTPS=false

# Laravel Pulse
PULSE_ENABLED=true
PULSE_INGEST=redis
PULSE_STORAGE=database

# Mail (configure as needed)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

## Common Business Logic Patterns

### Member Check-in System

```php
// app/Services/CheckInService.php
namespace App\Services;

use App\Models\{Member, CheckIn};
use App\Events\MemberCheckedIn;
use Carbon\Carbon;

class CheckInService
{
    public function checkInMember(Member $member): CheckIn
    {
        // Verify active membership
        if (!$member->hasActiveMembership()) {
            throw new \Exception('Member does not have an active membership');
        }
        
        // Prevent duplicate check-ins (within 1 hour)
        $recentCheckIn = CheckIn::where('member_id', $member->id)
            ->where('created_at', '>', now()->subHour())
            ->first();
            
        if ($recentCheckIn) {
            throw new \Exception('Member already checked in recently');
        }
        
        $checkIn = CheckIn::create([
            'member_id' => $member->id,
            'checked_in_at' => now(),
            'location' => 'Main Entrance',
        ]);
        
        // Broadcast real-time event
        MemberCheckedIn::dispatch($member, now()->toTimeString());
        
        return $checkIn;
    }
    
    public function checkOutMember(Member $member): void
    {
        $activeCheckIn = $member->activeCheckIn;
        
        if ($activeCheckIn) {
            $activeCheckIn->update([
                'checked_out_at' => now(),
                'duration_minutes' => now()->diffInMinutes($activeCheckIn->checked_in_at),
            ]);
        }
    }
}
```

### Training Session Management

```php
// app/Models/TrainingSession.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrainingSession extends Model
{
    use HasFactory;
    
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'max_participants' => 'integer',
            'is_group_session' => 'boolean',
            'session_notes' => 'array',
        ];
    }
    
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
    
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'session_members')
                    ->withPivot(['attended', 'performance_rating', 'notes'])
                    ->withTimestamps();
    }
    
    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'session_equipment');
    }
    
    // ✅ Business logic methods
    public function canAcceptMoreParticipants(): bool
    {
        return $this->members()->count() < $this->max_participants;
    }
    
    public function isScheduledForToday(): bool
    {
        return $this->scheduled_at->isToday();
    }
    
    public function getAttendanceRate(): float
    {
        $totalParticipants = $this->members()->count();
        $attendedParticipants = $this->members()->wherePivot('attended', true)->count();
        
        return $totalParticipants > 0 ? ($attendedParticipants / $totalParticipants) * 100 : 0;
    }
}
```

## Security Best Practices

### Input Sanitization & Validation

```php
// app/Http/Requests/StorePaymentRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }
    
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'exists:members,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'payment_method' => ['required', Rule::in(['card', 'cash', 'bank_transfer'])],
            'description' => ['nullable', 'string', 'max:500'],
            'metadata' => ['array'],
            'metadata.invoice_number' => ['nullable', 'string', 'max:50'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'amount.min' => 'Payment amount must be at least $0.01',
            'amount.max' => 'Payment amount cannot exceed $9,999.99',
            'member_id.exists' => 'Selected member does not exist',
        ];
    }
    
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => (float) str_replace([', '], '', $this->amount),
        ]);
    }
}
```

### API Authentication with Sanctum

```php
// routes/api.php
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::middleware([
    EnsureFrontendRequestsAreStateful::class,
    'auth:sanctum',
    'tenant.database'
])->group(function () {
    Route::apiResource('members', MemberController::class);
    Route::apiResource('payments', PaymentController::class);
    
    // Custom API endpoints
    Route::post('members/{member}/check-in', [MemberController::class, 'checkIn']);
    Route::post('members/{member}/renew-membership', [MemberController::class, 'renewMembership']);
    Route::get('dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('reports/revenue/{period}', [ReportController::class, 'revenue']);
});

// Public API endpoints (rate limited)
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('webhooks/payment', [WebhookController::class, 'handlePayment']);
});
```

## Performance Monitoring with Laravel Pulse

### Pulse Configuration

```php
// config/pulse.php
return [
    'domain' => env('PULSE_DOMAIN'),
    'path' => env('PULSE_PATH', 'pulse'),
    
    'storage' => [
        'driver' => env('PULSE_STORAGE', 'database'),
        'database' => [
            'connection' => env('PULSE_DB_CONNECTION', null),
            'chunk' => 1000,
        ],
    ],
    
    'ingest' => [
        'driver' => env('PULSE_INGEST', 'storage'),
        'buffer' => 5000,
        'trim' => [
            'lottery' => [1, 1000],
            'keep' => '7 days',
        ],
        'redis' => [
            'connection' => env('PULSE_REDIS_CONNECTION'),
            'chunk' => 1000,
        ],
    ],
    
    'recorders' => [
        Pulse\Recorders\CacheInteractions::class => [
            'enabled' => env('PULSE_CACHE_INTERACTIONS_ENABLED', true),
        ],
        Pulse\Recorders\Exceptions::class => [
            'enabled' => env('PULSE_EXCEPTIONS_ENABLED', true),
            'sample_rate' => env('PULSE_EXCEPTIONS_SAMPLE_RATE', 1),
        ],
        Pulse\Recorders\Queues::class => [
            'enabled' => env('PULSE_QUEUES_ENABLED', true),
            'sample_rate' => env('PULSE_QUEUES_SAMPLE_RATE', 1),
        ],
        Pulse\Recorders\Servers::class => [
            'enabled' => env('PULSE_SERVERS_ENABLED', true),
        ],
        Pulse\Recorders\SlowQueries::class => [
            'enabled' => env('PULSE_SLOW_QUERIES_ENABLED', true),
            'threshold' => env('PULSE_SLOW_QUERIES_THRESHOLD', 1000),
        ],
        Pulse\Recorders\UserRequests::class => [
            'enabled' => env('PULSE_USER_REQUESTS_ENABLED', true),
            'sample_rate' => env('PULSE_USER_REQUESTS_SAMPLE_RATE', 1),
        ],
    ],
];
```

## Advanced Livewire Patterns

### Real-time Dashboard with WebSockets

```php
// resources/views/volt/gym-dashboard.blade.php
<?php

use Livewire\Volt\Component;
use App\Models\{Member, TrainingSession, Equipment};
use App\Services\DashboardService;

new class extends Component {
    public array $stats = [];
    public array $recentCheckIns = [];
    public array $todaySessions = [];
    
    public function mount(): void
    {
        $this->loadDashboardData();
    }
    
    protected function getListeners(): array
    {
        return [
            'member-checked-in' => 'handleMemberCheckIn',
            'refresh-stats' => 'loadDashboardData',
        ];
    }
    
    public function loadDashboardData(): void
    {
        $service = app(DashboardService::class);
        
        $this->stats = $service->getGymStatistics();
        $this->recentCheckIns = $service->getRecentCheckIns(10);
        $this->todaySessions = $service->getTodaySessions();
    }
    
    public function handleMemberCheckIn($event): void
    {
        $this->dispatch('$refresh');
        $this->dispatch('toast', message: "#{$event['member_name']} checked in", type: 'success');
    }
    
    public function refreshData(): void
    {
        $this->loadDashboardData();
        $this->dispatch('toast', message: 'Dashboard updated', type: 'info');
    }
}; ?>

<div wire:poll.30s="loadDashboardData">
    <!-- Real-time Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Active Members</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['active_members'] ?? 0 }}</p>
                </div>
                <flux:icon name="users" class="h-12 w-12 text-blue-500" />
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Monthly Revenue</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">${{ number_format($stats['monthly_revenue'] ?? 0, 2) }}</p>
                </div>
                <flux:icon name="currency-dollar" class="h-12 w-12 text-green-500" />
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Todays Sessions</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['today_sessions'] ?? 0 }}</p>
                </div>
                <flux:icon name="calendar" class="h-12 w-12 text-purple-500" />
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Maintenance Due</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['equipment_maintenance_due'] ?? 0 }}</p>
                </div>
                <flux:icon name="wrench" class="h-12 w-12 {{ $stats['equipment_maintenance_due'] > 0 ? 'text-red-500' : 'text-gray-400' }}" />
            </div>
        </div>
    </div>

    <!-- Recent Check-ins Live Feed -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Recent Check-ins</flux:heading>
                <flux:button size="sm" wire:click="refreshData">
                    <flux:icon name="refresh" class="h-4 w-4" />
                </flux:button>
            </div>
            
            <div class="space-y-3">
                @forelse($recentCheckIns as $checkIn)
                    <div wire:key="checkin-{{ $checkIn['id'] }}" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" src="{{ $checkIn['member']['avatar'] ?? '' }}" />
                            <div>
                                <p class="font-medium">{{ $checkIn['member']['name'] }}</p>
                                <p class="text-sm text-gray-600">{{ $checkIn['member']['membership_type'] }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">{{ $checkIn['checked_in_at'] }}</p>
                            <flux:badge variant="success" size="sm">Active</flux:badge>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-4">No recent check-ins</p>
                @endforelse
            </div>
        </div>
        
        <!-- Today's Training Sessions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
            <flux:heading size="lg" class="mb-4">Todays Sessions</flux:heading>
            
            <div class="space-y-3">
                @forelse($todaySessions as $session)
                    <div wire:key="session-{{ $session['id'] }}" class="border dark:border-gray-600 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium">{{ $session['name'] }}</h4>
                                <p class="text-sm text-gray-600">
                                    {{ $session['trainer']['name'] }} • 
                                    {{ $session['scheduled_at'] }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm">{{ $session['participants_count'] }}/{{ $session['max_participants'] }}</p>
                                <flux:badge 
                                    variant="{{ $session['participants_count'] >= $session['max_participants'] ? 'danger' : 'success' }}"
                                    size="sm"
                                >
                                    {{ $session['participants_count'] >= $session['max_participants'] ? 'Full' : 'Available' }}
                                </flux:badge>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-4">No sessions scheduled</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
```

## Advanced Database Patterns

### Migration Best Practices

```php
// database/migrations/2024_01_01_000000_create_members_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            
            // Membership information
            $table->enum('membership_type', ['basic', 'premium', 'vip'])->default('basic');
            $table->timestamp('joined_at');
            $table->boolean('is_active')->default(true);
            
            // Contact information
            $table->json('emergency_contact')->nullable();
            $table->text('address')->nullable();
            
            // Health information
            $table->json('medical_conditions')->nullable();
            $table->text('notes')->nullable();
            
            // System fields
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['email', 'is_active']);
            $table->index(['membership_type', 'is_active']);
            $table->index('joined_at');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
```

### Factory Definitions

```php
// database/factories/MemberFactory.php
namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;
    
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'date_of_birth' => $this->faker->dateTimeBetween('-65 years', '-18 years'),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
            'membership_type' => $this->faker->randomElement(['basic', 'premium', 'vip']),
            'joined_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'is_active' => $this->faker->boolean(85), // 85% active
            'emergency_contact' => [
                'name' => $this->faker->name(),
                'phone' => $this->faker->phoneNumber(),
                'relationship' => $this->faker->randomElement(['spouse', 'parent', 'sibling', 'friend']),
            ],
            'address' => $this->faker->address(),
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }
    
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
    
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
    
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'membership_type' => 'premium',
        ]);
    }
    
    public function withMedicalConditions(): static
    {
        return $this->state(fn (array $attributes) => [
            'medical_conditions' => [
                $this->faker->randomElement(['asthma', 'diabetes', 'heart_condition', 'back_injury']),
            ],
        ]);
    }
}
```

## Export & Import Functionality

### Excel Export Implementation

```php
// app/Exports/MembersExport.php
namespace App\Exports;

use App\Models\Member;
use Maatwebsite\Excel\Concerns\{FromCollection, WithHeadings, WithMapping, ShouldAutoSize};
use Illuminate\Support\Collection;

class MembersExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private ?string $membershipType = null,
        private ?bool $activeOnly = null
    ) {}
    
    public function collection(): Collection
    {
        return Member::query()
            ->with(['activeMembership', 'trainer'])
            ->when($this->membershipType, fn($q) => $q->where('membership_type', $this->membershipType))
            ->when($this->activeOnly, fn($q) => $q->active())
            ->get();
    }
    
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Membership Type',
            'Join Date',
            'Status',
            'Trainer',
            'Last Payment',
            'Membership Expiry',
        ];
    }
    
    public function map($member): array
    {
        return [
            $member->id,
            $member->name,
            $member->email,
            $member->phone,
            ucfirst($member->membership_type),
            $member->joined_at->format('Y-m-d'),
            $member->is_active ? 'Active' : 'Inactive',
            $member->trainer?->name ?? 'Not Assigned',
            $member->latestPayment?->created_at->format('Y-m-d') ?? 'No payments',
            $member->activeMembership?->expires_at->format('Y-m-d') ?? 'No active membership',
        ];
    }
}
```

### Import with Validation

```php
// app/Imports/MembersImport.php
namespace App\Imports;

use App\Models\Member;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\{
    ToModel, 
    WithHeadingRow, 
    WithValidation, 
    SkipsErrors, 
    SkipsOnError
};

class MembersImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use SkipsErrors;
    
    public function model(array $row): ?Member
    {
        return new Member([
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? null,
            'date_of_birth' => $row['date_of_birth'] ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['date_of_birth']) : null,
            'membership_type' => strtolower($row['membership_type']),
            'joined_at' => $row['join_date'] ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['join_date']) : now(),
            'is_active' => true,
        ]);
    }
    
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email',
            'membership_type' => 'required|in:basic,premium,vip',
        ];
    }
}
```

## Notification System

### Custom Notification Classes

```php
// app/Notifications/MembershipExpiringNotification.php
namespace App\Notifications;

use App\Models\{Member, Membership};
use Illuminate\Notifications\{Notification, Messages\MailMessage};
use Illuminate\Notifications\Messages\DatabaseMessage;

class MembershipExpiringNotification extends Notification
{
    public function __construct(
        public Member $member,
        public Membership $membership,
        public int $daysUntilExpiry
    ) {}
    
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
    
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Membership Expiring Soon')
            ->greeting("Hi {$this->member->name}!")
            ->line("Your {$this->membership->type} membership expires in {$this->daysUntilExpiry} days.")
            ->line('To avoid interruption of your gym access, please renew your membership.')
            ->action('Renew Membership', url('/member/renew'))
            ->line('Thank you for being a valued member of our gym!');
    }
    
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'membership_expiring',
            'member_id' => $this->member->id,
            'membership_id' => $this->membership->id,
            'days_until_expiry' => $this->daysUntilExpiry,
            'message' => "Membership expires in {$this->daysUntilExpiry} days",
        ];
    }
}
```

## Artisan Commands for Gym Management

### Custom Artisan Commands

```bash
# Available custom commands for this project

# Membership management
docker-compose exec app php artisan gym:check-expirations        # Check and process expired memberships
docker-compose exec app php artisan gym:send-renewal-reminders   # Send renewal reminders to expiring members
docker-compose exec app php artisan gym:generate-invoices        # Generate monthly invoices

# Equipment management  
docker-compose exec app php artisan gym:check-equipment-maintenance  # Check equipment maintenance schedules
docker-compose exec app php artisan gym:equipment-report             # Generate equipment usage reports

# Data management
docker-compose exec app php artisan gym:backup-tenant-data {tenant_id}  # Backup specific tenant data
docker-compose exec app php artisan gym:migrate-tenant {tenant_id}      # Run migrations for specific tenant
docker-compose exec app php artisan gym:seed-demo-data                  # Seed demonstration data

# Reports and analytics
docker-compose exec app php artisan gym:generate-revenue-report {month} {year}  # Monthly revenue reports
docker-compose exec app php artisan gym:member-activity-report                  # Member activity analysis

# System maintenance
docker-compose exec app php artisan gym:cleanup-old-sessions    # Clean up old training sessions
docker-compose exec app php artisan gym:optimize-images         # Optimize uploaded images
```

### Command Implementation Example

```php
// app/Console/Commands/SendRenewalReminders.php
namespace App\Console\Commands;

use App\Models\{Member, Membership};
use App\Notifications\MembershipExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendRenewalReminders extends Command
{
    protected $signature = 'gym:send-renewal-reminders 
                           {--days=7 : Days before expiration to send reminder}
                           {--tenant= : Specific tenant ID}';
    
    protected $description = 'Send membership renewal reminders to members';
    
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $tenantId = $this->option('tenant');
        
        $expiringMemberships = Membership::with('member')
            ->where('is_active', true)
            ->whereBetween('expires_at', [
                now()->addDays($days)->startOfDay(),
                now()->addDays($days)->endOfDay(),
            ])
            ->when($tenantId, fn($q) => $q->whereHas('member', fn($q) => $q->where('tenant_id', $tenantId)))
            ->get();
            
        $this->info("Found {$expiringMemberships->count()} memberships expiring in {$days} days");
        
        $progressBar = $this->output->createProgressBar($expiringMemberships->count());
        $progressBar->start();
        
        foreach ($expiringMemberships as $membership) {
            $membership->member->notify(
                new MembershipExpiringNotification(
                    $membership->member,
                    $membership,
                    $days
                )
            );
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        $this->info('Renewal reminders sent successfully');
        
        return self::SUCCESS;
    }
}
```

## Data Analytics & Reporting

### Revenue Analytics with Laravel Trend

```php
// app/Services/AnalyticsService.php
namespace App\Services;

use App\Models\{Payment, Member, TrainingSession};
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class AnalyticsService
{
    public function getRevenueData(string $period = '30days'): array
    {
        [$start, $perPeriod] = match ($period) {
            '7days' => [now()->subDays(7), 'perDay'],
            '30days' => [now()->subDays(30), 'perDay'],
            '12months' => [now()->subYear(), 'perMonth'],
            default => [now()->subDays(30), 'perDay'],
        };
        
        return Trend::model(Payment::class)
            ->between($start, now())
            ->{$perPeriod}()
            ->sum('amount')
            ->map(fn (TrendValue $value) => [
                'date' => $value->date,
                'revenue' => $value->aggregate,
            ])
            ->toArray();
    }
    
    public function getMembershipTypeDistribution(): array
    {
        $distribution = Member::selectRaw('membership_type, COUNT(*) as count')
            ->active()
            ->groupBy('membership_type')
            ->get()
            ->mapWithKeys(fn ($item) => [ucfirst($item->membership_type) => $item->count]);
            
        return $distribution->toArray();
    }
    
    public function getTrainerPerformanceMetrics(): array
    {
        return User::whereHas('roles', fn($q) => $q->where('name', 'Trainer'))
            ->withCount([
                'assignedMembers',
                'trainingSessions' => fn($q) => $q->whereMonth('scheduled_at', now()->month),
                'completedSessions' => fn($q) => $q->where('status', 'completed'),
            ])
            ->get()
            ->map(function ($trainer) {
                $completionRate = $trainer->training_sessions_count > 0 
                    ? ($trainer->completed_sessions_count / $trainer->training_sessions_count) * 100 
                    : 0;
                    
                return [
                    'name' => $trainer->name,
                    'assigned_members' => $trainer->assigned_members_count,
                    'monthly_sessions' => $trainer->training_sessions_count,
                    'completion_rate' => round($completionRate, 1),
                ];
            })
            ->toArray();
    }
}
```

## Advanced Form Patterns with Filament

### Complex Form with Relationships

```php
// Within a Filament Resource form() method
use Filament\Forms\Components\{Section, Grid, TextInput, Select, Repeater, FileUpload};

public static function form(Form $form): Form
{
    return $form->schema([
        Section::make('Member Information')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),
                ]),
                
                Grid::make(3)->schema([
                    TextInput::make('phone')
                        ->tel()
                        ->required(),
                    Select::make('membership_type')
                        ->options([
                            'basic' => 'Basic - $50/month',
                            'premium' => 'Premium - $100/month', 
                            'vip' => 'VIP - $150/month',
                        ])
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn ($state, callable $set) => 
                            $set('monthly_fee', match($state) {
                                'basic' => 50.00,
                                'premium' => 100.00,
                                'vip' => 150.00,
                                default => 0
                            })
                        ),
                    TextInput::make('monthly_fee')
                        ->numeric()
                        ->prefix('$')
                        ->disabled()
                        ->dehydrated(false),
                ]),
            ]),
            
        Section::make('Emergency Contact')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('emergency_contact.name')
                        ->label('Contact Name')
                        ->required(),
                    TextInput::make('emergency_contact.phone')
                        ->label('Contact Phone')
                        ->tel()
                        ->required(),
                ]),
                
                Select::make('emergency_contact.relationship')
                    ->label('Relationship')
                    ->options([
                        'spouse' => 'Spouse',
                        'parent' => 'Parent',
                        'sibling' => 'Sibling',
                        'friend' => 'Friend',
                        'other' => 'Other',
                    ])
                    ->required(),
            ]),
            
        Section::make('Health Information')
            ->schema([
                Repeater::make('medical_conditions')
                    ->schema([
                        TextInput::make('condition')
                            ->required(),
                        TextInput::make('severity')
                            ->placeholder('Mild, Moderate, Severe'),
                        TextInput::make('notes')
                            ->placeholder('Additional notes'),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Add Medical Condition')
                    ->collapsible(),
            ]),
            
        Section::make('Documents')
            ->schema([
                FileUpload::make('profile_photo')
                    ->image()
                    ->maxSize(2048)
                    ->directory('member-photos'),
                FileUpload::make('medical_documents')
                    ->multiple()
                    ->acceptedFileTypes(['pdf', 'jpg', 'png'])
                    ->maxSize(5120)
                    ->directory('member-documents'),
            ]),
    ]);
}
```

## Error Handling & User Experience

### Global Exception Handler

```php
// bootstrap/app.php (Laravel 12)
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use App\Exceptions\{PaymentFailedException, TenantNotFoundException};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureTenantDatabase::class,
        ]);
        
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (PaymentFailedException $e) {
            return response()->view('errors.payment-failed', [
                'message' => $e->getMessage(),
                'payment_data' => $e->paymentData,
            ], 422);
        });
        
        $exceptions->render(function (TenantNotFoundException $e) {
            return response()->view('errors.tenant-not-found', [
                'message' => 'Gym not found or access denied',
            ], 404);
        });
    })
    ->create();
```

# MCP Tools Guidlines

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.2.30
- filament/filament (FILAMENT) - v3
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/pulse (PULSE) - v1
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v3
- livewire/volt (VOLT) - v1
- larastan/larastan (LARASTAN) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4
- laravel-echo (ECHO) - v2

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `fluxui-development` — Develops UIs with Flux UI Free components. Activates when creating buttons, forms, modals, inputs, dropdowns, checkboxes, or UI components; replacing HTML form elements with Flux; working with flux: components; or when the user mentions Flux, component library, UI components, form fields, or asks about available Flux components.
- `volt-development` — Develops single-file Livewire components with Volt. Activates when creating Volt components, converting Livewire to Volt, working with @volt directive, functional or class-based Volt APIs; or when the user mentions Volt, single-file components, functional Livewire, or inline component logic in Blade files.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== fluxui-free/core rules ===

# Flux UI Free

- Flux UI is the official Livewire component library. This project uses the free edition, which includes all free components and variants but not Pro components.
- Use `<flux:*>` components when available; they are the recommended way to build Livewire interfaces.
- IMPORTANT: Activate `fluxui-development` when working with Flux UI components.

=== volt/core rules ===

# Livewire Volt

- Single-file Livewire components: PHP logic and Blade templates in one file.
- Always check existing Volt components to determine functional vs class-based style.
- IMPORTANT: Always use `search-docs` tool for version-specific Volt documentation and updated code examples.
- IMPORTANT: Activate `volt-development` every time you're working with a Volt or single-file component-related task.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

=== filament/filament rules ===

## Filament

- Filament is used by this application, check how and where to follow existing application conventions.
- Filament is a Server-Driven UI (SDUI) framework for Laravel. It allows developers to define user interfaces in PHP using structured configuration objects. It is built on top of Livewire, Alpine.js, and Tailwind CSS.
- You can use the `search-docs` tool to get information from the official Filament documentation when needed. This is very useful for Artisan command arguments, specific code examples, testing functionality, relationship management, and ensuring you're following idiomatic practices.
- Utilize static `make()` methods for consistent component initialization.

### Artisan

- You must use the Filament specific Artisan commands to create new files or components for Filament. You can find these with the `list-artisan-commands` tool, or with `php artisan` and the `--help` option.
- Inspect the required options, always pass `--no-interaction`, and valid arguments for other options when applicable.

### Filament's Core Features

- Actions: Handle doing something within the application, often with a button or link. Actions encapsulate the UI, the interactive modal window, and the logic that should be executed when the modal window is submitted. They can be used anywhere in the UI and are commonly used to perform one-time actions like deleting a record, sending an email, or updating data in the database based on modal form input.
- Forms: Dynamic forms rendered within other features, such as resources, action modals, table filters, and more.
- Infolists: Read-only lists of data.
- Notifications: Flash notifications displayed to users within the application.
- Panels: The top-level container in Filament that can include all other features like pages, resources, forms, tables, notifications, actions, infolists, and widgets.
- Resources: Static classes that are used to build CRUD interfaces for Eloquent models. Typically live in `app/Filament/Resources`.
- Schemas: Represent components that define the structure and behavior of the UI, such as forms, tables, or lists.
- Tables: Interactive tables with filtering, sorting, pagination, and more.
- Widgets: Small component included within dashboards, often used for displaying data in charts, tables, or as a stat.

### Relationships

- Determine if you can use the `relationship()` method on form components when you need `options` for a select, checkbox, repeater, or when building a `Fieldset`:

<code-snippet name="Relationship example for Form Select" lang="php">
Forms\Components\Select::make('user_id')
    ->label('Author')
    ->relationship('author')
    ->required(),
</code-snippet>

## Testing

- It's important to test Filament functionality for user satisfaction.
- Ensure that you are authenticated to access the application within the test.
- Filament uses Livewire, so start assertions with `livewire()` or `Livewire::test()`.

### Example Tests

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1))
        ->searchTable($users->last()->email)
        ->assertCanSeeTableRecords($users->take(-1))
        ->assertCanNotSeeTableRecords($users->take($users->count() - 1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Howdy',
            'email' => 'howdy@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Howdy',
        'email' => 'howdy@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Multiple Panels (setup())" lang="php">
    use Filament\Facades\Filament;

    Filament::setCurrentPanel('app');
</code-snippet>

<code-snippet name="Calling an Action in a Test" lang="php">
    livewire(EditInvoice::class, [
        'invoice' => $invoice,
    ])->callAction('send');

    expect($invoice->refresh())->isSent()->toBeTrue();
</code-snippet>

## Version 3 Changes To Focus On

- Resources are located in `app/Filament/Resources/` directory.
- Resource pages (List, Create, Edit) are auto-generated within the resource's directory - e.g., `app/Filament/Resources/PostResource/Pages/`.
- Forms use the `Forms\Components` namespace for form fields.
- Tables use the `Tables\Columns` namespace for table columns.
- A new `Filament\Forms\Components\RichEditor` component is available.
- Form and table schemas now use fluent method chaining.
- Added `php artisan filament:optimize` command for production optimization.
- Requires implementing `FilamentUser` contract for production access control.
</laravel-boost-guidelines>
