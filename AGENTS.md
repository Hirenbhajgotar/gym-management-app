<!-- OPENSPEC:START -->
# OpenSpec Instructions

These instructions are for AI assistants working in this project.

Always open `@/openspec/AGENTS.md` when the request:
- Mentions planning or proposals (words like proposal, spec, change, plan)
- Introduces new capabilities, breaking changes, architecture shifts, or big performance/security work
- Sounds ambiguous and you need the authoritative spec before coding

Use `@/openspec/AGENTS.md` to learn:
- How to create and apply change proposals
- Spec format and conventions
- Project structure and guidelines

Keep this managed block so 'openspec update' can refresh the instructions.

<!-- OPENSPEC:END -->

# Agent Guidelines for Gym Management App

## Build/Lint/Test Commands
- **Build**: `npm run build` (Vite) or `composer run dev` (full stack)
- **Lint**: `vendor/bin/pint --dirty` (PHP formatting)
- **Test**: `composer run test` or `php artisan test`
- **Single test**: `php artisan test --filter=TestName`

## Code Style Guidelines
- **PHP**: Laravel 12, PHP 8.2, constructor property promotion, explicit return types, PHPDoc blocks
- **Naming**: camelCase variables/methods, PascalCase classes, kebab-case files
- **Formatting**: 4 spaces PHP, Laravel Pint preset, single quotes
- **Frontend**: Tailwind v4 (`@import "tailwindcss"`, gap for spacing, dark: mode)
- **Components**: Volt single-file for Livewire, Flux UI components
- **Architecture**: MVC pattern, Filament resources, multi-tenant with tenant_id

## Framework Rules
- Follow Laravel Boost guidelines (.cursor/rules/laravel-boost.mdc)
- Follow Gemini rules (GEMINI.md)
- Follow Kilo Code rules (.kilocode/rules/laravel-gym-app.md)
- Use artisan commands for file generation
- Use docker-compose command for docker and artisan commands
- Test with PHPUnit/Pest, eager loading for N+1 prevention
- Run pint before commits, use Volt for interactive components
- Run larastan/phpstan for static analysis