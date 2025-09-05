# Gym Management Software with CRM Rules for Kilo Code AI Agent

These rules define the behavior and constraints for the Kilo Code AI agent when working on the Gym Management Software project with an integrated Lead and Follow-up CRM system in Visual Studio Code. They ensure consistent coding practices, protect sensitive files, and align with the project's Laravel 12-based, multi-tenant architecture for managing gym operations and CRM functionalities.

## 1. Coding Standards

- **Language and Framework**: Use PHP 8.2 with Laravel 12 for backend, TypeScript for frontend components, and Livewire v3 with Volt for dynamic interfaces. Adhere to `gemini.md` tech stack versions (e.g., Filament v3.3, Livewire v3, Tailwind CSS v4).
- **Naming Conventions**:
  - Use `camelCase` for variables and methods (e.g., `memberProfile`, `createLead`).
  - Use `PascalCase` for class, interface, and enum names (e.g., `GymMember`, `ILeadService`, `DefaultRole`).
  - File names should be `kebab-case` (e.g., `gym-member.php`, `lead-follow-up.ts`).
- **Formatting**:
  - Use 4 spaces for PHP and 2 spaces for TypeScript/JavaScript indentation.
  - Use single quotes (`'`) for string literals.
  - Ensure all files end with a newline.
  - Run `vendor/bin/pint --dirty` for PHP and Prettier for TypeScript/JavaScript before committing to enforce formatting (per Laravel Pint v1).
- **Code Structure**:
  - Follow Laravel’s MVC pattern and Filament’s resource-based structure.
  - Use constructor property promotion in PHP (e.g., `public function __construct(public Member $member) {}`).
  - Include explicit type declarations for parameters and return types (e.g., `: Collection`, `: bool`).
  - Use PHPDoc blocks for public methods and classes; avoid inline comments unless explaining complex logic.
  - Use Volt single-file components for Livewire where possible (e.g., `resources/views/volt/lead-search.blade.php`).
  - Follow Tailwind CSS v4 conventions, using `gap` for spacing and `dark:` for dark mode support.

## 2. File Access Restrictions

- **Protected Files**:
  - Deny write access to `.env`, `config/app.php`, `config/database.php`, `config/auth.php`, and `config/reverb.php` to prevent accidental modification of sensitive configurations.
  - Restrict modifications to `app/Models/Tenant.php`, `app/Models/User.php`, and `app/Models/Member.php` unless explicitly approved, as they contain core multi-tenant and business logic.
  - Protect `bootstrap/app.php` and `bootstrap/providers.php` to maintain application middleware and service provider integrity.
- **Allowed Files**:
  - AI can edit files under `app/Filament/`, `app/Http/Livewire/`, `resources/views/volt/`, `resources/js/`, and `app/Services/` for feature implementation or fixes.
  - Documentation updates are allowed in `docs/` but must follow the structure in `docs/README.md`.
  - Migrations in `database/migrations/` can be created or edited, but must include all existing column attributes to avoid data loss.

## 3. Project-Specific Guidelines

- **Multi-Tenancy**:
  - Ensure all database queries respect tenant isolation using `tenant_id` or tenant-specific database connections (e.g., `gym_tenant_{id}`).
  - Use `app/Models/Tenant.php` for tenant-related logic and verify tenancy middleware (`EnsureTenantDatabase`) is applied.
- **Gym Management Features**:
  - Member models must include `id`, `name`, `email`, `phone`, `membership_type`, `joined_at`, `is_active`, `emergency_contact` (JSON), and `tenant_id`.
  - Payment processing must validate amounts (non-negative, max $9999.99) and log to `logs/payments.log`.
  - Check-in system must prevent duplicate check-ins within 1 hour and broadcast events via Laravel Reverb.
  - Training sessions must include `scheduled_at`, `duration_minutes`, `max_participants`, and `is_group_session`.
- **CRM Features**:
  - Lead models must include `id`, `name`, `email`, `phone`, `status`, `last_contacted`, and `tenant_id`.
  - Follow-up actions must trigger notifications (email, database, or SMS) and log to `logs/crm-follow-ups.log`.
  - Validate CRM inputs (e.g., email format, phone regex `/^[+]?[0-9\s\-\(\)]+$/`) before processing.
- **Error Handling**:
  - Use custom exception classes (e.g., `PaymentFailedException`, `TenantNotFoundException`) with structured logging to `logs/app-errors.log`.
  - API and Livewire endpoints must return JSON errors (e.g., `{ "error": "Invalid input", "status": 422 }`).
  - Log errors with context (e.g., `Log::error('Payment failed', ['member_id' => $member->id, 'error' => $e->getMessage()])`).
- **Testing**:
  - Generate feature tests in `tests/Feature/` and unit tests in `tests/Unit/` using PHPUnit 11.5.3 or Pest (if used in the project).
  - Test files must follow naming conventions (e.g., `MemberManagementTest.php`, `LeadSearchTest.php`).
  - Test Filament resources and Livewire components using `livewire()` or `Volt::test()` (e.g., `livewire(ListMembers::class)->assertCanSeeTableRecords($members)`).
  - Use model factories with states (e.g., `Member::factory()->active()->create()`).
  - Run minimal tests with `php artisan test --filter=TestName` to verify changes.
- **Dependencies**:
  - Use only approved packages listed in `package.json` and `composer.json` (e.g., `filament/filament:^3.3`, `spatie/laravel-permission:^6.20`).
  - Use Flux UI free components (e.g., `flux:button`, `flux:badge`) for frontend; fallback to Blade if unavailable.
  - Validate external API usage (e.g., Stripe for payments, SMS gateways) via Kilo Code’s Context7 lookup or `search-docs` tool.

## 4. AI Behavior

- **Orchestrator Mode**:
  - Break down tasks (e.g., adding a CRM lead follow-up feature) into subtasks: planning (`Architect` mode), implementation (`Code` mode), testing (`Debug` mode).
  - Prioritize tasks based on `docs/roadmap.md` or user-specified priorities.
- **Debug Mode**:
  - Check `logs/app-errors.log`, `logs/payments.log`, and `logs/crm-follow-ups.log` for debugging.
  - Run `php artisan test`, `php artisan pint --dirty`, and `npm run typecheck` before suggesting changes.
  - Use `tinker` or `database-query` tools for debugging Eloquent models or database issues.
- **Context Awareness**:
  - Reference `docs/specs/gym-management.md`, `docs/specs/crm.md`, and `gemini.md` for project specifications.
  - Use user-provided context (e.g., via `@FILENAME`) to tailor responses to specific components or modules.
  - Leverage `search-docs` tool for Laravel, Filament, Livewire, and Tailwind CSS v4 documentation, passing relevant queries (e.g., `['multi-tenancy', 'filament resource', 'livewire volt']`).

## 5. Version Control

- **Commits**:
  - Commit messages must follow the format: `[Type]: Short description` (e.g., `feat: Add CRM lead creation endpoint`, `fix: Resolve payment validation bug`).
  - Do not auto-commit; allow manual review before committing.
- **File Management**:
  - Place new files in appropriate directories (e.g., Filament resources in `app/Filament/`, Volt components in `resources/views/volt/`, services in `app/Services/`).
  - Update `docs/api.md` for new or modified API endpoints or Filament resources.
  - Create migrations with `php artisan make:migration` and include all existing column attributes.

## 6. Team Collaboration

- Store this `rule.md` file in `.kilocode/rules/` to ensure consistent behavior across the team.
- Encourage team members to update rules via the Kilo Code UI or by editing this file under version control.

## 7. Rule Verification

- Verify rules are active using the Kilo Code UI (bottom-right icon in the Kilo Code window).
- Ensure Markdown formatting is clear and structured for AI comprehension.
- Project-specific rules in `.kilocode/rules/` take precedence over global rules in `~/.kilocode/rules/`.

## 8. Additional Guidelines from `gemini.md`

- **Multi-Tenancy**:
  - Use `Tenant::getDatabaseName()` for tenant-specific database connections.
  - Apply `EnsureTenantDatabase` middleware to tenant-specific routes.
- **Filament**:
  - Create resources with `php artisan make:filament-resource --generate`.
  - Use `Forms\Components` and `Tables\Columns` for schemas, with fluent method chaining.
  - Implement `FilamentUser` contract for production access control.
- **Livewire/Volt**:
  - Prefer Volt single-file components in `resources/views/volt/`.
  - Use `wire:model.live` for real-time updates and `wire:loading` for loading states.
  - Test Volt components with `Volt::test()` and ensure single root elements.
- **Tailwind CSS v4**:
  - Use `@import "tailwindcss";` in CSS files.
  - Use slash notation for opacity (e.g., `bg-black/20`) and `gap` for spacing.
  - Support dark mode with `dark:` classes, matching project conventions.
- **Real-time Features**:
  - Use Laravel Reverb for WebSocket events (e.g., `MemberCheckedIn`) and configure in `config/reverb.php`.
  - Integrate Laravel Echo in `resources/js/app.js` for client-side event handling.
- **Performance**:
  - Use eager loading to prevent N+1 queries (e.g., `Member::with(['activeMembership', 'trainer'])`).
  - Cache dashboard data with `Cache::remember` and clear with `Cache::forget`.
  - Monitor performance with Laravel Pulse (`config/pulse.php`).
- **Artisan Commands**:
  - Use `php artisan` commands for file generation (e.g., `make:model`, `make:filament-resource`).
  - Run custom commands like `gym:check-expirations` or `gym:send-renewal-reminders` for business logic.
