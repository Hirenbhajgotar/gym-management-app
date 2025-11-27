# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

A comprehensive multi-tenant gym management SaaS platform built with Laravel 12 and Filament v3. The system provides CRM capabilities for lead management from social media platforms (Facebook, Instagram), membership management, trainer management, attendance tracking, and payment processing via Razorpay.

## Technology Stack

- **Framework**: Laravel 12
- **Admin Panel**: Filament v3 (multi-panel architecture)
- **Frontend**: Livewire v3, Volt, and Flux UI
- **Database**: MySQL (production), SQLite (testing)
- **CSS**: Tailwind CSS v4
- **Real-time**: Laravel Reverb
- **Authentication**: Laravel Sanctum
- **Permissions**: Spatie Laravel Permission (with team/tenant scoping)
- **Testing**: PHPUnit
- **Queue**: Laravel Queue
- **Payment Gateway**: Razorpay
- **Notifications**: Twilio (WhatsApp), Email

## Development Commands

### Starting the Development Environment
```bash
# Start all services (Laravel server, queue worker, Vite)
composer run dev
```

This command runs concurrently:
- PHP development server on port 8000
- Queue listener (single attempt)
- Vite dev server with hot reload

### Running Individual Services
```bash
# Laravel development server only
php artisan serve

# Queue worker (for background jobs)
php artisan queue:listen --tries=1

# Frontend assets (Vite)
npm run dev

# Build for production
npm run build
```

### Testing

```bash
# Run all tests
composer run test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run specific test file
php artisan test tests/Feature/SubscriptionRenewalTest.php

# Run with coverage
php artisan test --coverage
```

Test database is configured in `phpunit.xml` to use MySQL with database name `gym_app_test`.

### Code Quality

```bash
# Format code using Laravel Pint (PSR-12 compliant)
vendor/bin/pint

# Check code without making changes
vendor/bin/pint --test

# Static analysis (Larastan available)
vendor/bin/phpstan analyse

# Generate IDE helper files
php artisan ide-helper:generate
php artisan ide-helper:models
```

### Database

```bash
# Run migrations
php artisan migrate

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Rollback last migration
php artisan migrate:rollback

# Check migration status
php artisan migrate:status
```

### Debugging

```bash
# View real-time logs
php artisan pail

# Clear all caches
php artisan optimize:clear

# Regenerate config cache
php artisan config:cache
```

## Architecture

### Multi-Tenant Structure

The application implements **team-based multi-tenancy** using Filament's tenant feature:

- **Tenant Model**: `Gym` (located at `app/Models/Gym.php`)
- **Tenant Attribute**: `slug` (used in URLs like `/admin/{gym-slug}/...`)
- **Scoping Middleware**: `ApplyTenantScopes` applies global scopes to models (GymMember, Trainer, Lead, MembershipPlan, etc.)
- **Permission Scoping**: `TeamsPermission` middleware integrates Spatie permissions with tenant context

All tenant-scoped data must belong to a `Gym`. The current tenant is accessed via `Filament::getTenant()`.

### Filament Panel Architecture

The application has **three separate Filament panels**:

1. **Admin Panel** (`/admin/{tenant}`)
   - Multi-tenant panel for gym owners, managers, receptionists, trainers
   - Path: `app/Filament/Admin/`
   - Provider: `AdminPanelProvider`
   - Features: Full gym management, CRM, attendance, payments

2. **SuperAdmin Panel** (`/super-admin`)
   - Global administration (non-tenanted)
   - Path: `app/Filament/SuperAdmin/`
   - Provider: `SuperAdminPanelProvider`
   - Features: User management, plans, feedback, system settings, error reports

3. **Staff Panel** (`/staff/{tenant}`)
   - Limited panel for gym staff
   - Path: `app/Filament/Staff/`
   - Provider: `StaffPanelProvider`

### Role-Based Access Control

Roles are managed via Spatie Laravel Permission with team scoping:

- **DefaultRole Enum**: `app/Enums/DefaultRole.php` defines system roles
- **Permission Categories**: Organized via `PermissionCategory` enum
- **Team Context**: Permissions are scoped per gym (tenant)
- **Custom Permissions**: Managed through Permission Settings resource
- **Services**: `RolesAndPermissionsService` handles role/permission creation

**Important**: When querying users or checking permissions, always consider the current tenant context. Use `setPermissionsTeamId($gymId)` before permission checks.

### Core Domain Models

Key relationships to understand:

- **Gym**: Central tenant model
  - Has many: GymMember, Trainer, MembershipPlan, Lead, Attendances
  - Belongs to many: User (pivot table: `gym_user` with `role` and `active` columns)
  - Has one: BusinessDetail
  - Has many: TenantSocialConfig (for lead generation integrations)

- **User**: System-wide users
  - Belongs to many: Gym (can belong to multiple gyms with different roles)
  - Has one: Subscription (for gym owners)
  - Uses Spatie HasRoles trait

- **GymMember**: Members of a specific gym
  - Belongs to: Gym, MembershipPlan, Trainer (optional)
  - Has many: MemberPayment, Attendances
  - Has one: EmployeeDetail (morphed)

- **Subscription**: Billing for gym owners
  - Belongs to: User, Plan
  - Has many: Payment, SubscriptionFeature
  - Observer: `SubscriptionObserver` handles state changes

- **Lead**: CRM leads from social media
  - Belongs to: Gym
  - Has many: FollowUp
  - Source platforms: Facebook, Instagram (via webhooks)

### Service Layer

Services centralize business logic (located in `app/Services/`):

- **SubscriptionService**: Handles subscription creation, renewal, trial management
- **RazorpayService**: Payment gateway integration
- **AttendanceService**: Check-in/check-out logic
- **NotificationService**: Multi-channel notifications (Email, WhatsApp, Filament)
- **TwilioWhatsAppService**: WhatsApp messaging via Twilio
- **SocialMediaLeadProcessor**: Base class for lead ingestion from social platforms
- **LeadConversionService**: Converts leads to gym members
- **RolesAndPermissionsService**: Role and permission management

### Background Jobs

Critical scheduled jobs (defined in `routes/console.php`):

- **SubscriptionRenewalJob**: Daily at 2 AM - auto-renews subscriptions
- **CleanResolvedErrorReportsJob**: Daily at 1 AM - cleanup old error reports
- **Subscription Expiry Alerts**: Daily at 9 AM - notifies about expiring subscriptions
- **Backup**: Daily at 1 AM - database backups using spatie/laravel-backup
- **WhatsApp Balance Check**: Daily - monitors WhatsApp message credits
- **Attendance Auto-Checkout**: Twice daily at 11 PM and 1 AM
- **Membership Expiry Check**: Daily - updates expired memberships

**Queue Driver**: Configure via `QUEUE_CONNECTION` (database/redis)

### Import/Export System

Uses custom import architecture:

- **Base Classes**: `BaseImporter`, `BaseExcelImport` (app/Imports/)
- **Filament Action**: `ExcelImportAction` for UI integration
- **Event**: `ImportCompleted` fired after successful imports
- **Entities**: GymMembers, Trainers, Managers, Receptionists, MembershipPlans
- **Export**: Uses Filament's native exporter with custom exporters in `app/Filament/Exports/`

## Development Patterns

### Filament Resource Organization

Resources are organized by panel and feature:

```
app/Filament/
├── Admin/                    # Tenant-scoped admin panel
│   ├── Clusters/            # Feature groupings (Attendances, Permissions)
│   ├── Resources/           # CRUD resources
│   ├── Pages/               # Custom pages
│   └── Widgets/             # Dashboard widgets
├── SuperAdmin/              # Global admin panel
│   ├── Clusters/            # (PermissionManager, Location, GymManagement)
│   └── Resources/
├── Staff/                   # Limited staff panel
├── BasePages/               # Shared base page classes
├── BaseResources/           # Shared base resource classes
├── BaseWidgets/             # Shared widget base classes
└── Components/              # Reusable Livewire components
```

### When Creating New Features

1. **Multi-Tenant Resources**: Always extend base classes or implement tenant scoping
2. **Permissions**: Add permissions via `PermissionCategory` enum and seed them
3. **Scheduled Tasks**: Add to `routes/console.php` with appropriate frequency
4. **Notifications**: Use `NotificationService` for multi-channel delivery
5. **Payments**: Integrate via `RazorpayService` for consistency
6. **Social Media Leads**: Extend `SocialMediaLeadProcessor` base class

### Database Queries in Tenant Context

When the tenant middleware is active, tenant-scoped models are automatically filtered. However:

- Raw queries or manual model instantiation may bypass scoping
- Always verify tenant context when accessing sensitive data
- Use `Gym::getCurrentTenantUsers()` for getting users in current gym

### Testing Considerations

- Tests use separate MySQL database (`gym_app_test`)
- Tenant context must be set up in feature tests accessing tenant-scoped resources
- Use factories for creating test data with proper relationships
- Queue and cache drivers set to `sync` and `array` respectively in tests

## Key Configuration

### Environment Variables

Critical `.env` variables:

- `APP_ENV`: Environment (local/production)
- `DB_*`: Database configuration
- `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`: Payment gateway credentials
- `RAZORPAY_WEBHOOK_SECRET`: Webhook validation
- `QUEUE_CONNECTION`: Queue driver (database/redis)
- `MAIL_*`: Email configuration
- `TWILIO_*`: WhatsApp/SMS configuration
- `FACEBOOK_*`, `INSTAGRAM_*`: Social media API credentials

### Webhooks

The application receives webhooks for:

1. **Razorpay Payments**: `/payments/webhook`
2. **Facebook Leads**: Configured via TenantSocialConfig
3. **Instagram Leads**: Configured via TenantSocialConfig

Webhook handling is in `routes/api.php` and processed by respective service classes.

## Production Deployment

Critical steps for production:

```bash
# Install dependencies (production only)
composer install --optimize-autoloader --no-dev

# Build assets
npm run build

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Set up cron for scheduled tasks
# Add to crontab: * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Queue Worker

Run persistent queue worker in production:

```bash
php artisan queue:work --tries=3 --timeout=90
```

Consider using Supervisor or systemd to keep the worker running.

### Storage Permissions

Ensure web server can write to:
- `storage/app/`
- `storage/framework/`
- `storage/logs/`
- `bootstrap/cache/`

## Common Gotchas

1. **Permissions Not Working**: Clear permission cache after role/permission changes:
   ```bash
   php artisan permission:cache-reset
   ```

2. **Tenant Scope Issues**: Ensure `ApplyTenantScopes` middleware is applied and tenant is set

3. **Import Templates**: Template files are excluded from SPA to allow downloads (see `AdminPanelProvider`)

4. **IDE Helpers**: Regenerate after model changes for proper IDE autocomplete

5. **Spatie Permissions**: Team ID context must be set before checking permissions in multi-tenant scenarios

6. **Observer Side Effects**: `GymObserver` and `SubscriptionObserver` have important side effects (role assignment, billing)
