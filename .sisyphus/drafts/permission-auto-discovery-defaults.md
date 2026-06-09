# Draft: Permission Auto-Discovery for Default Roles

## Requirements (confirmed)
- Auto-discover resources, pages, and widgets and their default role assignments
- Replace hardcoded `config/default-roles-and-permissions.php` with declarative approach
- Resources/pages/widgets declare which roles get their permissions by default
- Create `HasFilamentPermissions` method for declaring default roles
- Create `BaseResource`, `BasePage`, and `BaseWidget` abstract classes
- Allow easy override of default role assignments per resource/page/widget

## Technical Decisions
- **Interface Enhancement**: Add `getDefaultRoles(): array` method to `HasFilamentPermissions`
- **BaseResource**: Abstract class in `app/Filament/BaseResources/` implementing the interface
- **BasePage**: Abstract class in `app/Filament/BasePages/` implementing the interface
- **BaseWidget**: Abstract class in `app/Filament/BaseWidgets/` implementing the interface
- **Auto-Discovery**: Service scans resources/pages/widgets implementing the interface, collects default roles
- **Backward Compatible**: Existing classes without the method get empty default (no breaking change)

## Research Findings
- **79 classes** already implement `HasFilamentPermissions` (marker interface currently)
- **3 existing abstract classes**: `BaseAttendanceResource`, `BaseEmployeeResource`, `BaseResources/BaseAttendanceResource`
- `discoverFilamentPermissions()` already scans for `HasFilamentPermissions` implementations
- Current default permissions are hardcoded in `config/default-roles-and-permissions.php`
- `getDynamicDefaultPermissions()` generates permissions from allowed resources list

## Scope Boundaries
- INCLUDE: Interface enhancement, BaseResource/BasePage/BaseWidget creation, Service updates, Seeder/Command updates
- INCLUDE: Retrofit existing resources with default role declarations
- EXCLUDE: Changing permission naming conventions
- EXCLUDE: Modifying Spatie Permission package behavior

## Resolved Questions
- Keep `config/default-roles-and-permissions.php` as fallback for safety
- BaseResource goes in `app/Filament/BaseResources/`
- BasePage goes in `app/Filament/BasePages/`
- BaseWidget goes in `app/Filament/BaseWidgets/`
- Use DefaultRole enum values for type safety
- Add method to base classes only, existing classes add it incrementally

## Architecture Design

### 1. Enhanced HasFilamentPermissions Interface
```php
interface HasFilamentPermissions
{
    /**
     * Get the default roles that should receive this resource's permissions.
     * Return empty array if this resource should not be a default for any role.
     */
    public static function getDefaultRoles(): array;
}
```

### 2. BaseResource Abstract Class
```php
abstract class BaseResource extends Resource implements HasFilamentPermissions
{
    /**
     * Default: No roles get this resource's permissions.
     * Override in child resources to assign defaults.
     */
    public static function getDefaultRoles(): array
    {
        return [];
    }
    
    // Common permission-related methods
    public static function canAccess(): bool { ... }
    public static function getPermissionSlug(): string { ... }
}
```

### 3. BasePage Abstract Class
```php
abstract class BasePage extends Page implements HasFilamentPermissions
{
    public static function getDefaultRoles(): array
    {
        return [];
    }
    
    public static function canAccess(): bool { ... }
    public static function getPagePermissionSlug(): string { ... }
}
```

### 4. BaseWidget Abstract Class
```php
abstract class BaseWidget extends Widget implements HasFilamentPermissions
{
    public static function getDefaultRoles(): array
    {
        return [];
    }
    
    public static function canAccess(): bool { ... }
    public static function getWidgetPermissionSlug(): string { ... }
}
```

### 5. Service Updates
- `discoverFilamentPermissions()` now also collects `getDefaultRoles()` from each resource/page/widget
- New method `getDiscoveredDefaultRoleAssignments(): array` returns role→resource mappings
- Merges with fallback config for complete picture

### 6. Seeder/Command Updates
- Uses discovered defaults instead of hardcoded config
- Config serves as fallback for resources not implementing the interface
