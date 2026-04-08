# Blog Management System Implementation Plan

## TL;DR

> **Quick Summary**: Implement a complete blog management system with database backing, super-admin CRUD interface, and frontend integration. The existing frontend design (blog-listing.blade.php) will NOT be modified.

> **Deliverables**:
> - Blog model with migration
> - BlogResource Filament resource in super-admin
> - Updated BlogListing Livewire to fetch from database
> - Blog detail page (Livewire component)

> **Estimated Effort**: Medium
> **Parallel Execution**: YES - 2 waves
> **Critical Path**: Migration → Model → Resource → Frontend Update

---

## Context

### Original Request
Implement blog page generator in super-admin panel. Already have:
- `@App\Livewire\Frontend\BlogListing.php` (hardcoded data)
- `@resources\views\livewire\frontend\blog-listing.blade.php` (CANNOT be modified)
- Stitch project with blog detail screen

Need to create:
- Blog resource in super-admin panel to create and manage blogs
- Blog detail page

### Interview Summary
**Key Discussions**:
- User explicitly said NOT to modify blog-listing.blade.php design
- Categories should match existing: All, Gym Management Software, Tips, Fitness Trends, Business Growth, Case Studies
- Blog data includes: title, excerpt, category, author, authorRole, date, readTime, image
- CmsPageResource used as reference pattern

**Research Findings**:
- CmsPage model exists with similar structure (title, slug, content, SEO fields)
- SuperAdminPanelProvider auto-discovers resources in `app/Filament/SuperAdmin/Resources`
- Content Management navigation group already exists

### Metis Review
**Identified Gaps** (addressed):
- Need to handle featured article logic separately (is_featured flag)
- Image storage - should use media library or URL field
- Read time calculation vs manual entry decision needed
- Blog detail page routing needs to be added to routes/web.php

---

## Work Objectives

### Core Objective
Create a complete blog management system with database backing, allowing super-admins to CRUD blog articles from Filament panel, while the existing frontend continues to work with the new database-driven data.

### Concrete Deliverables
- Blog model: `app/Models/Blog.php`
- Database migration: `database/migrations/YYYY_MM_DD_HHMMSS_create_blogs_table.php`
- Filament resource: `app/Filament/SuperAdmin/Resources/BlogResource.php`
- Blog pages: List, Create, Edit (auto-generated)
- Updated BlogListing Livewire: Fetch from database instead of hardcoded array
- Blog detail Livewire: `app/Livewire/Frontend/BlogDetail.php`
- Route for blog detail: `/blog/{slug}` in routes/web.php

### Definition of Done
- [ ] Super-admin can create, edit, delete blog posts
- [ ] BlogListing page displays blogs from database
- [ ] Blog detail page accessible at /blog/{slug}
- [ ] Featured article logic works (is_featured flag)
- [ ] Category filtering works
- [ ] Search works
- [ ] No changes to blog-listing.blade.php design

### Must Have
- Database-backed blog storage
- CRUD operations via Filament super-admin
- Frontend fetches from database
- Proper slug-based URLs

### Must NOT Have (Guardrails)
- NO changes to `resources/views/livewire/frontend/blog-listing.blade.php` design
- NO breaking existing frontend functionality
- NO duplicate blog entries (unique slug constraint)

---

## Verification Strategy (MANDATORY)

### Test Decision
- **Infrastructure exists**: YES
- **Automated tests**: NO (using agent-executed QA)
- **Framework**: N/A (no unit tests)
- **QA Policy**: Every task will include agent-executed QA scenarios

### QA Policy
Every task includes agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/`.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - can run in parallel):
├── Task 1: Create Blog migration
├── Task 2: Create Blog model with relationships
├── Task 3: Create BlogResource (Filament)
└── Task 4: Run migration

Wave 2 (Integration):
├── Task 5: Update BlogListing Livewire to fetch from DB
├── Task 6: Create BlogDetail Livewire component
├── Task 7: Add blog route to web.php
└── Task 8: Test full flow
```

### Dependency Matrix
- Tasks 1-4: Can run in parallel (no dependencies)
- Task 5: Depends on Task 2 (needs model)
- Task 6: Depends on Task 2 (needs model)
- Task 7: Depends on Task 6 (needs component)
- Task 8: Depends on Tasks 5, 6, 7

---

## TODOs

---

- [ ] 1. **Create Blog Migration**

  **What to do**:
  - Create migration: `database/migrations/2026_04_04_120000_create_blogs_table.php`
  - Fields: title, slug (unique), excerpt, content (longtext), category, author, author_role, read_time, image_url, is_featured (boolean), is_published (boolean), published_at (timestamp nullable), created_at, updated_at

  **Must NOT do**:
  - Don't add fields not in original blog data

  **References**:
  - `database/migrations/2024_01_01_000000_create_cms_pages_table.php` - Similar CMS structure pattern

  **QA Scenarios**:
  - Migration can be created: YES
  - `php artisan migrate` runs without error: verified

- [ ] 2. **Create Blog Model**

  **What to do**:
  - Create `app/Models/Blog.php`
  - Use `$fillable` for allfillable fields
  - Use `$casts` for: is_featured (boolean), is_published (boolean), published_at (datetime)
  - Add `scopePublished()`, scopeFeatured(), scopeCategory()
  - Add `getRouteKeyName()` returning 'slug'

  **References**:
  - `app/Models/CmsPage.php` - Model pattern to follow

  **QA Scenarios**:
  - Model file passes PHP syntax check: `php -l app/Models/Blog.php`

- [ ] 3. **Create BlogResource Filament**

  **What to do**:
  - Create `app/Filament/SuperAdmin/Resources/BlogResource.php`
  - Use static `$model = Blog::class`
  - Navigation: belongs to 'Content Management' group
  - Form schema: Title, Slug (auto-generate from title), Excerpt, Content (RichEditor), Category (Select), Author, Author Role, Read Time, Image URL, Is Featured toggle, Is Published toggle, Published At date-time
  - Table columns: Title, Slug, Category, Author, Is Featured, Is Published, Created At
  - Add navigation badge showing published count

  **References**:
  - `app/Filament/SuperAdmin/Resources/CmsPageResource.php` - Pattern to follow exactly

  **QA Scenarios**:
  - BlogResource file passes PHP syntax: `php -l app/Filament/SuperAdmin/Resources/BlogResource.php`
  - Filament discovers resource in super-admin panel

- [ ] 4. **Run Migration**

  **What to do**:
  - Run `php artisan migrate`
  - Verify table created: `php artisan tinker` → `Schema::hasTable('blogs')`

  **QA Scenarios**:
  - Migration runs without error: verified
  - Table exists in database

- [ ] 5. **Update BlogListing Livewire**

  **What to do**:
  - Modify `app/Livewire/Frontend/BlogListing.php`
  - Replace hardcoded `$articles = $this->getArticles()` with database query
  - Add `$featuredArticle` computed property (first published featured, or first published)
  - Categories array from DB or keep static? Keep static as per existing categories
  - Keep `$search` and `$selectedCategory` filtering logic but apply to DB query
  - Keep `getArticles()` method but rename to `getArticlesFromDatabase()` returning Builder
  - DO NOT modify `resources/views/livewire/frontend/blog-listing.blade.php`

  **Must NOT do**:
  - DO NOT touch `resources/views/livewire/frontend/blog-listing.blade.php`

  **References**:
  - `app/Livewire/Frontend/BlogListing.php` - Original component (READ ONLY, don't modify view)
  - `app/Models/Blog.php` - Query scopes

  **QA Scenarios**:
  - BlogListing page loads without error
  - Blog posts appear from database
  - Category filter works
  - Search works

- [ ] 6. **Create BlogDetail Livewire**

  **What to do**:
  - Create `app/Livewire/Frontend/BlogDetail.php`
  - Use `#[Title('Blog Title | FluxFitness Blog')]`
  - Use `#[Layout('frontend.layouts.blog')]`
  - Accept `$slug` parameter
  - Query blog by slug with orFail()
  - Related articles method (same category, exclude current)
  - Render view: `livewire.frontend.blog-detail`

  **References**:
  - `app/Livewire/Frontend/BlogListing.php` - Layout reference
  - `resources/views/livewire/frontend/blog-listing.blade.php` - Layout name (blog)

  **QA Scenarios**:
  - Page loads at /blog/test-slug
  - Shows 404 for invalid slug
  - Shows blog content correctly

- [ ] 7. **Add Blog Routes**

  **What to do**:
  - Add to `routes/web.php`:
  ```php
  Route::get('/blog/{slug}', \App\Livewire\Frontend\BlogDetail::class)->name('blog.show');
  ```
  - Keep existing `Route::get('/blog', BlogListing::class)->name('blog')`

  **References**:
  - `routes/web.php` - Current blog route

  **QA Scenarios**:
  - Route registered: `php artisan route:list | grep blog`
  - /blog/{slug} accessible

- [ ] 8. **Test Full Flow**

  **What to do**:
  - Create test blog via Filament super-admin
  - Verify appears in blog listing
  - Verify category filter works
  - Verify search works
  - Navigate to blog detail page
  - Verify blog-listing.blade.php design unchanged (visual check)

  **QA Scenarios**:
  - Full CRUD works from super-admin
  - Frontend displays correctly
  - Detail page works

---

## Final Verification Wave (MANDATORY)

- [ ] F1. **Plan Compliance Audit** — Verify all deliverables implemented
- [ ] F2. **Code Quality Review** — Check syntax, Pint formatting
- [ ] F3. **Real Manual QA** — Test CRUD, frontend display, detail page
- [ ] F4. **Scope Fidelity Check** — Ensure blog-listing.blade.php unchanged

---

## Commit Strategy

- 1: `feat(blog): add blog migration and model`
- 2: `feat(blog): add BlogResource Filament`
- 3: `feat(blog): update frontend to use database`
- 4: `feat(blog): add blog detail page`

---

## Success Criteria

### Verification Commands
```bash
php artisan migrate  # Blogs table created
php artisan tinker  # Blog::count() > 0 after seeding
```

### Final Checklist
- [ ] Blog model exists with proper fields
- [ ] Migration runs successfully
- [ ] BlogResource appears in super-admin
- [ ] Can create/edit/delete blogs
- [ ] BlogListing shows database blogs
- [ ] Blog detail page works at /blog/{slug}
- [ ] blog-listing.blade.php unchanged
