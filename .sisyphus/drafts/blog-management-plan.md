# Draft: Blog Management System Implementation

## Requirements (Confirmed)
- Create Blog resource in super-admin panel for CRUD operations
- Create Blog model with database backing
- Update BlogListing Livewire component to fetch from database
- Create blog detail page (matching stitch project screen)
- Do NOT modify: `resources/views/livewire/frontend/blog-listing.blade.php`
- Use existing categories from hardcoded data

## Technical Decisions
- Blog model will use similar structure to CmsPage model
- Will add: title, slug, excerpt, content (rich editor), category, author, author_role, read_time, image_url, is_featured, is_published, published_at
- Use Filament's RichEditor for content
- Follow existing CmsPageResource pattern for consistency
- Blog categories stored as enum or simple array

## Scope Boundaries
- INCLUDE: Blog CRUD in super-admin, database model, migrations, frontend fetching from DB
- EXCLUDE: Design changes to blog-listing.blade.php (per user request)

## Open Questions
- Should blog detail page be a separate Livewire component or Filament page?
- Do we need to implement the blog detail page routing in web.php?
