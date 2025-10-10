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
- Follow Kilo Code rules (.kilocode/rules/laravel-gym-app.md)
- Use artisan commands for file generation
- Test with PHPUnit/Pest, eager loading for N+1 prevention
- Run pint before commits, use Volt for interactive components