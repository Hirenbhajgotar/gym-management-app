# Draft: Permissions Auto-Discover Refactor

## Requirements (confirmed)
- Refactor ManagerPermissionsPage and ReceptionistPermissionsPage to auto-discover resources, pages, and widgets permissions
- Resource permissions categorized by resource name (e.g., lead permissions under "Lead Resource" section)
- Pages are for gym_owner to set permissions for managers and receptionists
- Auto-discover only for admin and staff panels (NOT super-admin)
- Use existing HasFilamentPermissions interface and discover infrastructure
- DRY principle - eliminate duplication between the two pages

## Technical Decisions (proposed)
- Leverage existing `RolesAndPermissionsService::discoverFilamentPermissions()`  infrastructure
- Use Filament panel discovery (`$panel->getResources()`, `getPages()`, `getWidgets()`) directly in BasePermissionsPage
- Dynamic category/grouping derived from permission names rather than hardcoded `PermissionCategory` enum
- Each tab (Resources/Pages/Widgets) will dynamically show sections per discovered class
- `defaultRolePermissions()` determines which permissions appear for each role
- Keep the 4-tab layout but make it dynamic

## Research Findings

### Current Architecture
- `BasePermissionsPage` extends `Page implements HasForms`
- Uses `DefaultPermissionResourceTrait` + `HasTenantAccess` + `InteractsWithForms`
- Builds 4-tab form: Resources, Widgets, Reports, Other
- Each tab uses `buildCategoryTabs()` which filters `getPermissionsByCategory()` by categories from trait methods
- Trait methods check `PermissionSetting` DB → fallback to `RolesAndPermissionsService` methods
- `RolesAndPermissionsService::getRoleResourceCategories()` etc. return hardcoded arrays

### Category/Permission Relationship
- `PermissionCategory` enum stores predefined resource slugs: GYM_MEMBER, LEAD, PAYMENT, etc.
- `Permission` model has `category` column cast to `PermissionCategory`
- `PermissionDescriptionSeeder` manually maps categories
- Permissions follow naming pattern: `{action}_{resource_slug}` (view_any_gym_member, create_lead)
- Page permissions: `page_{slug}`
- Widget permissions: `widget_{slug}`
- Categories are set via `PermissionDescriptionSeeder` and `updateOrCreate`

### Auto-Discovery Infrastructure
- `HasFilamentPermissions` interface with `defaultRolePermissions()` method
- `BaseResource`, `BasePage`, `BaseWidget` implement the interface with sensible defaults
- `RolesAndPermissionsService::discoverFilamentPermissions()` scans panels and generates permissions
- `getDiscoveredDefaultRoleAssignments()` collects role-permission assignments from all Filament classes
- Config `filament-permissions.php`: panels=['super-admin', 'admin'], discover_default_roles=true

### Panel Structure
- **Admin panel**: id='admin', path='admin' - discoverResources in Admin/Resources, etc.
- **Staff panel**: id='staff', path='staff' - shares Admin resources, has own Staff/Resources
- **Super-Admin panel**: has its own resources

### Current Issues
- `BasePermissionsPage` relies on hardcoded category lists from `DefaultPermissionResourceTrait`
- New resources/pages/widgets require manual updates to appear in permission management UI
- Hardcoded `getQuickSetupPermissions()` arrays duplicated in both Manager and Receptionist pages
- Two Blade view files are nearly identical
- No separation between page-specific permissions and resource-specific ones in the UI

## Decisions Made (from interview)
- **Tab Layout**: 3 Tabs - Resources, Pages, Widgets
- **Page/Widget Categorization**: By class-derived slug name
- **Non-Resource Permissions**: Auto-categorize into a catch-all section
- **DB Override Support**: No - pure auto-discovery. Remove PermissionSetting dependency
- **Quick Setup**: Remove hardcoded getQuickSetupPermissions() - replaced by auto-discovered defaultRolePermissions()
- **Reset to Default**: Will use discovered default role assignments from getDiscoveredDefaultRoleAssignments()

## Open Questions
- Deduplication: Admin and Staff panels share resources. How to avoid duplicates?
- The catch-all "other" section: Should non-resource/page/widget permissions get their own section/tab, or be merged into relevant tabs?
## Decisions Made (round 2)
- **Panel Deduplication**: Deduplicate silently by class name
- **Orphan Permissions**: Separate "Other Permissions" section for permissions not tied to any Filament class
- **Page Structure**: Thin wrappers - each page file ~2-3 lines, shared view template
