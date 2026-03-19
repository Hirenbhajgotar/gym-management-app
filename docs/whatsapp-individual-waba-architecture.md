# Individual WABA Per Tenant Architecture

## Executive Summary

**Current State:** Shared WABA (ISV Model)
- All tenants share ONE WABA ID from master account
- Risk: One tenant's issues can affect ALL tenants at scale

**Target State:** Individual WABA Per Tenant
- Each tenant has their own WABA connected to their subaccount
- Full isolation - one tenant's issues don't affect others
- Requires Meta Tech Provider Program + Embedded Signup

---

## Architecture Comparison

```
CURRENT (Shared WABA - Risky at Scale):
┌─────────────────────────────────────────────────────────────┐
│  Master Twilio Account                                       │
│  └── Master WABA ID: 12345 (SHARED)                         │
│      ├── Subaccount A → Phone 1 → Templates (shared WABA)   │
│      ├── Subaccount B → Phone 2 → Templates (shared WABA)   │
│      └── Subaccount C → Phone 3 → Templates (shared WABA)   │
│                                                              │
│  RISK: One template blocked → potential WABA restrictions    │
└─────────────────────────────────────────────────────────────┘

TARGET (Individual WABA - Full Isolation):
┌─────────────────────────────────────────────────────────────┐
│  Master Twilio Account (Tech Provider)                       │
│      │                                                       │
│      ├── Subaccount A                                        │
│      │   └── WABA A (Tenant A's own)                        │
│      │       └── Phone A → Templates A → Full isolation     │
│      │                                                       │
│      ├── Subaccount B                                        │
│      │   └── WABA B (Tenant B's own)                        │
│      │       └── Phone B → Templates B → Full isolation     │
│      │                                                       │
│      └── Subaccount C                                        │
│          └── WABA C (Tenant C's own)                        │
│              └── Phone C → Templates C → Full isolation     │
│                                                              │
│  BENEFIT: Tenant B blocked → A and C unaffected             │
└─────────────────────────────────────────────────────────────┘
```

---

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Feature Flag Strategy | **Global flag** | All tenants use individual WABA when enabled |
| Existing Tenant Migration | **Force migrate all** | All tenants moved to individual WABA |
| Fallback Behavior | **Allow manual WABA connection** | If Embedded Signup fails, manual connection possible |
| Meta Business Portfolio | **Tenant creates their own** | Standard Embedded Signup flow |

---

## Phase 1: Meta Tech Provider Program Setup (External)

### Prerequisites (Manual Steps - Not Code)

| Step | Action | Owner | Time |
|------|--------|-------|------|
| 1.1 | Register for Meta Tech Provider Program | Business | 1-2 weeks |
| 1.2 | Create Meta Business Portfolio for your company | Business | 1 day |
| 1.3 | Create Meta App with "Facebook Login for Business" | Developer | 1 day |
| 1.4 | Submit app for Meta App Review | Developer | 2-4 weeks |
| 1.5 | Complete Access Verification | Business | 1-2 weeks |
| 1.6 | Notify Twilio about Tech Provider enrollment | Business | 1-2 days |

**Required Meta App Permissions:**
- `whatsapp_business_management`
- `whatsapp_business_messaging`
- `business_management`

---

## Phase 2: Database Schema Changes

### 2.1 Migration: Add WABA Fields to `whatsapp_accounts`

**File:** `database/migrations/xxxx_add_individual_waba_support.php`

New columns:
- `waba_name` (string, nullable) - Display name of WABA
- `waba_status` (enum: PENDING, ACTIVE, RESTRICTED, BANNED)
- `meta_business_portfolio_id` (string, nullable)
- `meta_access_token` (text, encrypted, nullable)
- `embedded_signup_completed` (boolean, default false)
- `embedded_signup_session_id` (string, nullable)
- `waba_mode` (enum: 'shared', 'individual', 'manual')

### 2.2 Migration: WABA Setup Sessions

**File:** `database/migrations/xxxx_create_waba_setup_sessions_table.php`

Tracks Embedded Signup sessions for security.

### 2.3 Migration: Migration Tracking

**File:** `database/migrations/xxxx_create_waba_migrations_table.php`

Tracks migration status for existing tenants.

---

## Phase 3: Configuration Changes

### 3.1 Update `config/services.php`

```php
'twilio' => [
    // Existing
    'master_sid' => env('TWILIO_ACCOUNT_SID'),
    'master_token' => env('TWILIO_AUTH_TOKEN'),
    
    // NEW: Meta/Facebook Integration
    'meta_app_id' => env('META_APP_ID'),
    'meta_app_secret' => env('META_APP_SECRET'),
    'meta_api_version' => env('META_API_VERSION', 'v19.0'),
    
    // NEW: Embedded Signup Configuration
    'embedded_signup' => [
        'enabled' => env('TWILIO_EMBEDDED_SIGNUP_ENABLED', false),
        'redirect_uri' => env('META_REDIRECT_URI'),
        'config_id' => env('META_CONFIG_ID'),
    ],
    
    // Keep for backward compatibility (migration)
    'shared_waba_id' => env('TWILIO_WABA_ID'),
    
    // Feature flag for architecture (GLOBAL)
    'use_individual_waba' => env('TWILIO_USE_INDIVIDUAL_WABA', false),
],
```

### 3.2 Environment Variables

```env
# Meta/Facebook
META_APP_ID=your_meta_app_id
META_APP_SECRET=your_meta_app_secret
META_API_VERSION=v19.0
META_REDIRECT_URI=https://your-domain.com/whatsapp/embedded-signup/callback
META_CONFIG_ID=your_config_id

# Feature Flags
TWILIO_EMBEDDED_SIGNUP_ENABLED=true
TWILIO_USE_INDIVIDUAL_WABA=true

# Backward Compatibility (during migration)
TWILIO_WABA_ID=existing_shared_waba_id
```

---

## Phase 4: Service Layer Changes

### 4.1 New Service: `EmbeddedSignupService.php`

**File:** `app/Services/EmbeddedSignupService.php`

Methods:
- `getLoginUrl(int $gymId): string` - Generate Facebook Login URL
- `handleCallback(string $code, string $state): array` - Process callback
- `exchangeCodeForToken(string $code): array` - Get access token
- `getWabaDetails(string $wabaId, string $accessToken): array` - Fetch WABA info
- `connectWabaToSubaccount(...)` - Connect WABA to Twilio
- `validateSession(string $sessionId, int $gymId): bool` - CSRF protection

### 4.2 Update `TwilioAccountManager.php`

**File:** `app/Services/TwilioAccountManager.php`

New methods:
- `createSubaccountWithIndividualWaba(...)`
- `registerSenderWithWaba(...)`
- `getWabaMode(int $gymId): string`
- `connectManualWaba(...)` - For manual WABA connection

### 4.3 Update `WhatsAppTemplateService.php`

**File:** `app/Services/WhatsAppTemplateService.php`

Modify to work with individual WABA templates.

---

## Phase 5: Filament UI Changes

### 5.1 New Controller: `EmbeddedSignupController.php`

**File:** `app/Http/Controllers/WhatsApp/EmbeddedSignupController.php`

### 5.2 Update `WhatsAppSetup.php`

**File:** `app/Filament/Admin/Pages/WhatsAppSetup.php`

New flow (7 Steps):
1. Create Subaccount
2. Business Profile
3. Facebook Login (Embedded Signup)
4. WABA Connection
5. Phone Number
6. Templates
7. Welcome Credits

### 5.3 New Page: `WabaMigration.php`

**File:** `app/Filament/Admin/Pages/WhatsApp/WabaMigration.php`

For migrating existing tenants.

### 5.4 New Page: `ManualWabaConnection.php`

**File:** `app/Filament/Admin/Pages/WhatsApp/ManualWabaConnection.php`

For manual WABA connection fallback.

---

## Phase 6: Routes

**File:** `routes/web.php` or `routes/whatsapp.php`

```php
Route::prefix('whatsapp/embedded-signup')
    ->middleware(['auth', 'tenant'])
    ->group(function () {
        Route::get('/redirect', [EmbeddedSignupController::class, 'redirect']);
        Route::get('/callback', [EmbeddedSignupController::class, 'callback']);
    });
```

---

## Phase 7: Jobs & Events

### 7.1 Jobs

- `SyncWabaStatus.php` - Periodic WABA status sync
- `ProcessEmbeddedSignupCallback.php` - Async callback processing
- `ConnectWabaToSubaccount.php` - Connect after signup
- `MigrateTenantToIndividualWaba.php` - Migrate existing tenants

### 7.2 Events

- `WabaCreated.php`
- `WabaConnected.php`
- `WabaRestricted.php`
- `EmbeddedSignupCompleted.php`
- `TenantWabaMigrationStarted.php`
- `TenantWabaMigrationCompleted.php`

---

## Phase 8: Tests

### 8.1 Test Files

- `tests/Feature/Services/EmbeddedSignupServiceTest.php`
- `tests/Feature/Filament/WhatsAppSetupIndividualWabaTest.php`
- `tests/Feature/Services/TwilioAccountManagerIndividualWabaTest.php`
- `tests/Feature/Jobs/MigrateTenantToIndividualWabaTest.php`

---

## Phase 9: Migration Strategy

### 9.1 New Tenants

When `TWILIO_USE_INDIVIDUAL_WABA=true`:
- All new signups → Individual WABA flow (7 steps)
- Embedded Signup required

### 9.2 Existing Tenants (Force Migrate)

Migration Process:
1. Create migration queue of all existing tenants
2. Send notification to tenant about migration
3. Tenant clicks "Migrate to Individual WABA"
4. Tenant goes through Embedded Signup
5. New WABA created for tenant
6. Phone number migrated (requires verification)
7. Templates re-created in new WABA
8. Traffic switched to new WABA
9. Old connection deactivated

**Migration Command:**
```bash
php artisan whatsapp:migrate-to-individual-waba
```

---

## Implementation Timeline

| Phase | Description | Duration | Dependencies |
|-------|-------------|----------|--------------|
| 1 | Meta Tech Provider Setup | 4-6 weeks | External (Meta approval) |
| 2 | Database Schema | 1 day | None |
| 3 | Configuration | 0.5 day | Phase 1 |
| 4 | Services | 3-5 days | Phase 2, 3 |
| 5 | Filament UI | 2-3 days | Phase 4 |
| 6 | Routes | 0.5 day | Phase 5 |
| 7 | Jobs & Events | 1 day | Phase 4 |
| 8 | Tests | 2-3 days | Phase 4-7 |
| 9 | Migration | 1-2 days | Phase 4-8 |
| **Total** | | **~3 weeks (after Meta approval)** | |

---

## File Structure

```
app/
├── Http/Controllers/WhatsApp/
│   └── EmbeddedSignupController.php (NEW)
├── Services/
│   ├── EmbeddedSignupService.php (NEW)
│   ├── TwilioAccountManager.php (UPDATE)
│   ├── WhatsAppTemplateService.php (UPDATE)
│   └── WabaMigrationService.php (NEW)
├── Jobs/
│   ├── SyncWabaStatus.php (NEW)
│   ├── ProcessEmbeddedSignupCallback.php (NEW)
│   ├── ConnectWabaToSubaccount.php (NEW)
│   └── MigrateTenantToIndividualWaba.php (NEW)
├── Events/
│   ├── WabaCreated.php (NEW)
│   ├── WabaConnected.php (NEW)
│   ├── WabaRestricted.php (NEW)
│   ├── EmbeddedSignupCompleted.php (NEW)
│   ├── TenantWabaMigrationStarted.php (NEW)
│   └── TenantWabaMigrationCompleted.php (NEW)
├── Models/
│   ├── WhatsAppAccount.php (UPDATE)
│   ├── WabaSetupSession.php (NEW)
│   └── WabaMigration.php (NEW)
├── Enums/
│   ├── WabaStatusEnum.php (NEW)
│   └── WabaModeEnum.php (NEW)
└── Filament/Admin/Pages/WhatsApp/
    ├── WabaMigration.php (NEW)
    └── ManualWabaConnection.php (NEW)

database/migrations/
├── xxxx_add_individual_waba_support.php (NEW)
├── xxxx_create_waba_setup_sessions_table.php (NEW)
└── xxxx_create_waba_migrations_table.php (NEW)

tests/Feature/
├── Services/
│   ├── EmbeddedSignupServiceTest.php (NEW)
│   └── TwilioAccountManagerIndividualWabaTest.php (NEW)
├── Filament/
│   └── WhatsAppSetupIndividualWabaTest.php (NEW)
└── Jobs/
    └── MigrateTenantToIndividualWabaTest.php (NEW)
```

---

## Checklist

### Phase 2: Database Schema
- [x] Create `WabaStatusEnum`
- [x] Create `WabaModeEnum`
- [x] Create migration: add individual WABA support
- [x] Create migration: waba_setup_sessions table
- [x] Create migration: waba_migrations table
- [x] Update `WhatsAppAccount` model
- [x] Create `WabaSetupSession` model
- [x] Create `WabaMigration` model

### Phase 3: Configuration
- [x] Update `config/services.php`
- [x] Update `.env.example`

### Phase 4: Services
- [x] Create `EmbeddedSignupService`
- [x] Update `TwilioAccountManager` (individual WABA support added)
- [ ] Update `WhatsAppTemplateService` (optional - works with current implementation)
- [x] Create `WabaMigrationService`

### Phase 5: Filament UI
- [x] Create `EmbeddedSignupController`
- [x] Update `WhatsAppSetup` page (embedded signup flow added)
- [x] Create `WabaMigration` page
- [x] Create `ManualWabaConnection` page

### Phase 6: Routes
- [x] Add Embedded Signup routes

### Phase 7: Jobs & Events
- [x] Create `MigrateTenantToIndividualWaba` job
- [x] Create `SyncWabaStatus` job
- [ ] Create `ProcessEmbeddedSignupCallback` job (optional - handled in controller)
- [ ] Create `ConnectWabaToSubaccount` job (optional - handled in service)
- [x] Create Events (all 6 created)

### Phase 8: Tests
- [ ] Write service tests
- [ ] Write Filament tests
- [ ] Write job tests

### Phase 9: Migration
- [x] Create migration command
- [ ] Test migration flow (requires Meta app setup)
