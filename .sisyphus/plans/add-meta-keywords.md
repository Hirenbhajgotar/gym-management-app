# Add Meta Keywords Field to CMS Pages

## TL;DR

> **Quick Summary**: Add a `meta_keywords` field to handle dynamic SEO keywords in CMS pages.
> 
> **Deliverables**:
> - Migration to add `meta_keywords` column
> - Model update with fillable field
> - Filament form field in SEO section
> 
> **Estimated Effort**: Short
> **Parallel Execution**: NO - sequential tasks (migration → model → form)

---

## Context

### Original Request
Add field to handle meta keywords dynamically in:
- `app/Filament/SuperAdmin/Resources/CmsPageResource.php`
- `app/Models/CmsPage.php`

### User Preferences
- **Field Type**: Textarea (best for comma-separated SEO keywords)
- **Migration**: Create new migration

---

## Work Objectives

### Core Objective
Add `meta_keywords` field to CMS pages for dynamic SEO keyword management.

### Concrete Deliverables
- [x] Migration file: Add `meta_keywords` column to `cms_pages` table
- [x] Model update: Add `meta_keywords` to `$fillable` array
- [x] Form update: Add `meta_keywords` Textarea to SEO section

### Must Have
- Meta keywords field visible in SEO section of CMS page form
- Field persisted to database

---

## Execution Strategy

### Task Sequence
```
1. Create migration
2. Update model
3. Update Filament form
4. Run tests
```

---

## TODOs

- [ ] 1. **Create migration for meta_keywords column**

  **What to do**:
  - Create migration to add `meta_keywords` column (TEXT type) to `cms_pages` table
  
  **Recommended Agent Profile**:
  - **Category**: `quick`
  - Reason: Simple artisan command execution

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocks**: Task 2, 3
  
  **Acceptance Criteria**:
  - [ ] Migration created successfully
  - [ ] php artisan migrate runs without errors

- [ ] 2. **Add meta_keywords to CmsPage model fillable**

  **What to do**:
  - Add `'meta_keywords'` to `$fillable` array in `app/Models/CmsPage.php`
  
  **Recommended Agent Profile**:
  - **Category**: `quick`
  - Reason: Simple model edit

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 1
  
  **References**:
  - `app/Models/CmsPage.php:19-26` - Existing fillable structure

  **Acceptance Criteria**:
  - [ ] `meta_keywords` added to fillable array

- [ ] 3. **Add meta_keywords field to CmsPageResource form**

  **What to do**:
  - Add Textarea field to SEO section in `CmsPageResource.php`
  - Place after `meta_description` field
  
  **Recommended Agent Profile**:
  - **Category**: `quick`
  - Reason: Simple Filament form addition

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 2
  
  **References**:
  - `app/Filament/SuperAdmin/Resources/CmsPageResource.php:65-72` - SEO section structure

  **Acceptance Criteria**:
  - [ ] Textarea field added to SEO section
  - [ ] Field label: "Meta Keywords"

- [ ] 4. **Run tests to verify changes**

  **What to do**:
  - Run existing CMS page tests to ensure nothing broke
  
  **Recommended Agent Profile**:
  - **Category**: `quick`
  - Reason: Running test commands

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 3
  
  **Acceptance Criteria**:
  - [ ] Tests pass

---

## Success Criteria

- Migration created and run successfully
- Model accepts meta_keywords in mass assignment
- Filament form displays meta_keywords field in SEO section
- Existing tests pass
