# Work Plan: Blog SEO Fields + Detail Page Redesign

## TL;DR

> Add SEO fields (meta_title, meta_description, meta_keywords, og_title, og_description, og_image) to Blog model and super-admin panel, then redesign the blog detail page to match the Stitch project 16114337191860100902's high-end editorial "Kinetic Pulse" design with proper typography for blockquotes, bullet lists, numbered lists, and paragraphs.

---

## Context

### Original Request
- Add SEO related fields like meta title, meta description, meta keywords to BlogResource.php
- When blog is posted from super-admin panel, design should match Stitch project 16114337191860100902 detail page
- Design should include fonts, blockquotes, bullet lists, numbered list, paragraph formatting
- **DO NOT change/update anything in blog-listing.blade.php**

### Requirements Confirmed
- SEO fields needed: `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`
- Blog detail page design: "The Kinetic Pulse" theme from Stitch project 16114337191860100902
- Fonts: Lexend (headlines), Inter (body)
- Colors: Dark theme with Electric Lime (#ccff00) accents
- Content formatting: blockquotes, bullet lists, numbered lists, paragraphs

### Key Constraints
- **MUST NOT** modify `resources/views/livewire/frontend/blog-listing.blade.php`
- Use existing RichEditor toolbar buttons (already configured)
- Follow Laravel 12 + Filament v3 conventions
- Run Pint formatter before finalizing

---

## Work Objectives

### Core Deliverables

1. **Database Migration**: Add SEO columns to blogs table
2. **Blog Model Update**: Add SEO fields to $fillable and casts
3. **BlogResource Form Update**: Add SEO fields section to super-admin form
4. **Blog Detail Page Redesign**: Match Stitch project 16114337191860100902 design
5. **Content Styling**: Style blockquotes, lists, paragraphs to match design system

### Must Have
- SEO fields in create/edit forms
- Meta tags rendered in blog detail view
- OG (Open Graph) tags for social sharing
- Properly styled content (blockquotes, lists, paragraphs)
- Responsive design matching the high-end editorial aesthetic

### Must NOT Have (Guardrails)
- No changes to blog-listing.blade.php
- No changes to existing blog listing functionality
- No changes to existing search/filter/pagination

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: None required for this UI task
- **Framework**: N/A

### QA Policy
Every task MUST include agent-executed QA scenarios.

**QA Scenarios:**

> **Scenario: Blog SEO fields appear in super-admin form**
> Tool: Playwright
> Preconditions: Logged in as super-admin, on Blog create/edit page
> Steps:
>   1. Navigate to /super-admin/blogs/create
>   2. Scroll to SEO section
>   3. Verify meta_title, meta_description, meta_keywords fields are visible
>   4. Verify OG fields (og_title, og_description, og_image) are visible
> Expected Result: All 6 SEO fields are visible and editable

> **Scenario: Blog detail page displays with correct design**
> Tool: Playwright
> Preconditions: Published blog exists, navigate to /blog/{slug}
> Steps:
>   1. Navigate to blog detail page
>   2. Take screenshot of page
>   3. Verify dark theme background (#0e0e0e)
>   4. Verify headline uses Lexend font
>   5. Verify Electric Lime (#ccff00) accent color visible
> Expected Result: Page matches Stitch project 16114337191860100902 design

> **Scenario: Content formatting renders correctly**
> Tool: Playwright
> Preconditions: Blog with various content types exists
> Steps:
>   1. Create blog with blockquote, bullet list, numbered list, paragraph
>   2. View blog detail page
>   3. Verify blockquote styled with left border accent
>   4. Verify bullet list renders with custom bullets
>   5. Verify numbered list renders with proper numbering
> Expected Result: All content types styled according to design system

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - SEO Fields):
├── Task 1: Create migration for SEO columns
├── Task 2: Update Blog model with SEO fields
└── Task 3: Add SEO section to BlogResource form

Wave 2 (Frontend - Detail Page Design):
├── Task 4: Redesign blog-detail.blade.php with Kinetic Pulse theme
├── Task 5: Add content styling for blockquotes, lists, paragraphs
└── Task 6: Add meta tags and OG tags to blog detail page

Wave 3 (Verification):
├── Task 7: Run Pint formatter
├── Task 8: Test migration
└── Task 9: Verify design matches Stitch project
```

### Dependency Matrix

- **1**: - - 2
- **2**: 1 - 3
- **3**: 2 - 4
- **4**: 3 - 5
- **5**: 4 - 6
- **6**: 3 - 7
- **7**: 6 - 8, 9
- **8**: 7 -
- **9**: 7 -

---

## TODOs

### Wave 1: SEO Fields Implementation

- [ ] 1. Create database migration for SEO columns

  **What to do**:
  - Create migration: `database/migrations/XXXX_XX_XX_XXXXXX_add_seo_columns_to_blogs_table.php`
  - Add columns: `meta_title` (VARCHAR 255), `meta_description` (TEXT), `meta_keywords` (TEXT), `og_title` (VARCHAR 255), `og_description` (TEXT), `og_image` (VARCHAR 500)
  - All VARCHAR fields should be nullable

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: YES (Wave 1)
  - **Parallel Group**: Wave 1 (with Tasks 2, 3)
  - **Blocks**: Task 2 (model needs table)
  - **Blocked By**: None

  **References**:
  - `database/migrations/2026_04_04_120000_create_blogs_table.php` - Existing migration pattern
  - `app/Models/Blog.php` - Model to update

  **QA Scenarios**:
  - [ ] Migration runs successfully: `php artisan migrate`
  - [ ] Columns exist in database: `DESCRIBE blogs;`

  **Commit**: YES
  - Message: `feat(blog): add SEO columns to blogs table`
  - Files: `database/migrations/*_add_seo_columns_to_blogs_table.php`

- [ ] 2. Update Blog model with SEO fields

  **What to do**:
  - Add SEO fields to `$fillable` array
  - Add SEO fields to `casts()` method
  - Fields: meta_title, meta_description, meta_keywords, og_title, og_description, og_image

  **Must NOT do**:
  - Don't change existing fields or relationships

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: YES (Wave 1)
  - **Parallel Group**: Wave 1 (with Tasks 1, 3)
  - **Blocks**: Task 3 (form needs model update)
  - **Blocked By**: Task 1

  **References**:
  - `app/Models/Blog.php` - Current model
  - `app/Models/GymMember.php` - Example of casts() method pattern

  **QA Scenarios**:
  - [ ] Blog::create() accepts SEO fields without error
  - [ ] SEO fields are mass assignable

  **Commit**: YES
  - Message: `feat(blog): add SEO fields to Blog model`
  - Files: `app/Models/Blog.php`

- [ ] 3. Add SEO section to BlogResource form

  **What to do**:
  - Add new Section to form schema for SEO fields
  - Include all 6 SEO fields with appropriate input types
  - Use TextInput for meta_title, og_title (maxLength: 255)
  - Use Textarea for meta_description, og_description (maxLength: 500)
  - Use Textarea for meta_keywords
  - Use TextInput for og_image (maxLength: 500, url validation)

  **Must NOT do**:
  - Don't remove existing form fields
  - Don't change existing sections

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: YES (Wave 1)
  - **Parallel Group**: Wave 1 (with Tasks 1, 2)
  - **Blocks**: None
  - **Blocked By**: Task 2

  **References**:
  - `app/Filament/SuperAdmin/Resources/BlogResource.php` - Current form
  - Existing RichEditor section for reference

  **QA Scenarios**:
  - [ ] SEO section appears in create form
  - [ ] SEO section appears in edit form
  - [ ] All 6 fields are editable

  **Commit**: YES
  - Message: `feat(blog): add SEO fields to BlogResource form`
  - Files: `app/Filament/SuperAdmin/Resources/BlogResource.php`

### Wave 2: Blog Detail Page Redesign

- [ ] 4. Redesign blog-detail.blade.php with Kinetic Pulse theme

  **What to do**:
  - Restructure entire page to match Stitch project 16114337191860100902
  - Use dark theme background (#0e0e0e)
  - Use Lexend font for headlines (font-headline class)
  - Use Inter font for body (already available in project)
  - Use Electric Lime (#ccff00) for primary accent
  - Maintain existing structure: Back link, Header, Author info, Featured image, Content, Share, Related articles
  - **DO NOT modify blog-listing.blade.php**

  **Must NOT do**:
  - Don't change blog-listing.blade.php
  - Don't break existing routing

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: [`tailwindcss-development`]
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: YES (Wave 2)
  - **Parallel Group**: Wave 2 (with Tasks 5, 6)
  - **Blocks**: Task 5 (needs base layout)
  - **Blocked By**: Task 3

  **References**:
  - `resources/views/livewire/frontend/blog-detail.blade.php` - Current file
  - `resources/views/livewire/frontend/blog-listing.blade.php` - Design reference (DO NOT EDIT)
  - Stitch project 16114337191860100902 design system

  **QA Scenarios**:
  - [ ] Page loads with dark theme (#0e0e0e background)
  - [ ] Headlines use Lexend font
  - [ ] Primary accents use Electric Lime (#ccff00)
  - [ ] All existing content sections still present

  **Commit**: YES
  - Message: `feat(blog): redesign detail page with Kinetic Pulse theme`
  - Files: `resources/views/livewire/frontend/blog-detail.blade.php`

- [ ] 5. Add content styling for blockquotes, lists, paragraphs

  **What to do**:
  - Style RichEditor content output to match design system
  - Blockquotes: Left border with Electric Lime accent, italic text
  - Bullet lists: Custom bullets matching theme
  - Numbered lists: Styled numbers with theme colors
  - Paragraphs: Proper spacing and typography
  - Add custom CSS via Tailwind prose extension or inline styles

  **Must NOT do**:
  - Don't break existing content rendering
  - Don't modify blog-listing.blade.php

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: [`tailwindcss-development`]
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: YES (Wave 2)
  - **Parallel Group**: Wave 2 (with Tasks 4, 6)
  - **Blocks**: None
  - **Blocked By**: Task 4

  **References**:
  - `resources/views/livewire/frontend/blog-detail.blade.php` - Container
  - Tailwind Typography plugin (prose) for reference

  **QA Scenarios**:
  - [ ] Blockquote renders with left border accent
  - [ ] Bullet list shows custom styled bullets
  - [ ] Numbered list shows styled numbers
  - [ ] Paragraphs have proper spacing

  **Commit**: YES
  - Message: `feat(blog): add content styling for blockquotes lists paragraphs`
  - Files: `resources/views/livewire/frontend/blog-detail.blade.php`

- [ ] 6. Add meta tags and OG tags to blog detail page

  **What to do**:
  - Inject meta title, meta description, meta keywords into page head
  - Inject OG title, OG description, OG image into page head
  - Use Laravel's SEO meta facade or inline meta tags
  - Use fallback to blog title/description/excerpt/image if SEO fields are empty

  **Must NOT do**:
  - Don't break page rendering
  - Don't modify blog-listing.blade.php

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: YES (Wave 2)
  - **Parallel Group**: Wave 2 (with Tasks 4, 5)
  - **Blocks**: None
  - **Blocked By**: Task 3

  **References**:
  - `resources/views/livewire/frontend/blog-detail.blade.php` - Where to add tags
  - `app/Livewire/Frontend/BlogDetail.php` - Component to pass SEO data

  **QA Scenarios**:
  - [ ] View page source shows meta tags
  - [ ] View page source shows OG tags
  - [ ] Tags use SEO fields when available
  - [ ] Tags fallback to default fields when SEO empty

  **Commit**: YES
  - Message: `feat(blog): add meta and OG tags to blog detail`
  - Files: `resources/views/livewire/frontend/blog-detail.blade.php`

### Wave 3: Verification

- [ ] 7. Run Pint formatter

  **What to do**:
  - Run `vendor/bin/pint --dirty` on all modified files
  - Verify no formatting errors

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: NO (final step)
  - **Parallel Group**: N/A
  - **Blocks**: None
  - **Blocked By**: Task 6

  **References**:
  - Project Pint configuration

  **QA Scenarios**:
  - [ ] Pint runs without errors
  - [ ] All files pass formatting check

  **Commit**: YES (if changes needed)
  - Message: `style(blog): fix formatting issues`
  - Files: Modified PHP files

- [ ] 8. Test migration

  **What to do**:
  - Run `php artisan migrate` to apply new columns
  - Verify migration succeeds

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: NO (final step)
  - **Parallel Group**: N/A
  - **Blocks**: None
  - **Blocked By**: Task 1

  **References**:
  - Database configuration

  **QA Scenarios**:
  - [ ] Migration runs successfully
  - [ ] New columns exist in blogs table

  **Commit**: NO

- [ ] 9. Verify design matches Stitch project

  **What to do**:
  - Compare rendered blog detail page with Stitch project 16114337191860100902 screenshots
  - Verify all design elements are present

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`playwright`]
  - **Skills Evaluated but Omitted**: N/A

  **Parallelization**:
  - **Can Run In Parallel**: NO (final step)
  - **Parallel Group**: N/A
  - **Blocks**: None
  - **Blocked By**: Tasks 4, 5, 6

  **References**:
  - Stitch project 16114337191860100902

  **QA Scenarios**:
  - [ ] Dark theme matches (#0e0e0e)
  - [ ] Typography matches (Lexend headlines)
  - [ ] Color accents match (Electric Lime #ccff00)
  - [ ] Content formatting matches design

  **Commit**: NO

---

## Final Verification Wave

> After ALL implementation tasks complete, run final verification.

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Verify all "Must Have" items are implemented:
  - SEO fields in migration
  - SEO fields in model
  - SEO fields in form
  - Blog detail page redesign
  - Content styling
  - Meta/OG tags

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run Pint + syntax check on all modified files

- [ ] F3. **Real Manual QA** — `unspecified-high` (+ `playwright`)
  Execute QA scenarios from all tasks

- [ ] F4. **Scope Fidelity Check** — `deep`
  Verify NO changes to blog-listing.blade.php

---

## Commit Strategy

- **1**: `feat(blog): add SEO columns to blogs table`
- **2**: `feat(blog): add SEO fields to Blog model`
- **3**: `feat(blog): add SEO fields to BlogResource form`
- **4**: `feat(blog): redesign detail page with Kinetic Pulse theme`
- **5**: `feat(blog): add content styling for blockquotes lists paragraphs`
- **6**: `feat(blog): add meta and OG tags to blog detail`
- **7**: `style(blog): fix formatting issues` (if needed)

---

## Success Criteria

### Verification Commands
```bash
php artisan migrate  # Migration runs successfully
vendor/bin/pint --dirty  # Code formatted correctly
```

### Final Checklist
- [ ] All SEO fields added to database
- [ ] All SEO fields in Blog model
- [ ] All SEO fields in BlogResource form
- [ ] Blog detail page redesigned with Kinetic Pulse theme
- [ ] Content styling for blockquotes, lists, paragraphs
- [ ] Meta tags and OG tags implemented
- [ ] NO changes to blog-listing.blade.php
- [ ] Pint formatting passes
- [ ] All tests pass (if any)
