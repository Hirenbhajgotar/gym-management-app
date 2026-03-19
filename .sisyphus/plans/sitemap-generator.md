# Sitemap Generation with Spatie Laravel Sitemap

## TL;DR

> **Quick Summary**: Implement automated sitemap generation using spatie/laravel-sitemap with auto-crawling and dynamic CMS page inclusion, scheduled for daily regeneration.
> 
> **Deliverables**:
> - Published sitemap configuration
> - Artisan command for sitemap generation
> - Dynamic CMS pages added to sitemap
> - Scheduled daily regeneration
> 
> **Estimated Effort**: Short
> **Parallel Execution**: NO (sequential tasks)
> **Critical Path**: Config → Command → CMS integration → Schedule

---

## Context

### Original Request
Generate a sitemap using spatie/laravel-sitemap package with auto-crawl, include dynamic CMS pages from database, and schedule daily regeneration.

### Interview Summary
**Key Discussions**:
- **Method**: Auto-crawl approach for static pages
- **Dynamic Content**: Include CMS pages from database
- **Schedule**: Daily automatic regeneration

**Research Findings**:
- Package `spatie/laravel-sitemap` v8.0 already installed
- Public routes identified: home, request-demo, thank-you, business-register, CMS pages, auth
- No sitemap config published yet

---

## Work Objectives

### Core Objective
Create an automated sitemap generation system that includes both static routes (via crawling) and dynamic CMS pages (from database), with daily scheduled regeneration.

### Concrete Deliverables
- Published config/sitemap.php
- Artisan command `php artisan sitemap:generate`
- CMS pages dynamically added to sitemap
- Scheduled task for daily regeneration at midnight

### Definition of Done
- [ ] `php artisan sitemap:generate` creates valid sitemap.xml
- [ ] Sitemap includes static routes via crawling
- [ ] Sitemap includes CMS pages from database
- [ ] Sitemap is accessible at /sitemap.xml
- [ ] Daily schedule configured in app/Console/Kernel.php or routes/console.php

### Must Have
- Valid XML sitemap that passes validation
- Includes homepage and key public routes
- Includes dynamic CMS pages from database

### Must NOT Have
- Auth-protected routes in sitemap
- Duplicate URLs
- Broken or invalid XML

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: None required for this task
- **Framework**: N/A

### QA Policy
Every task MUST include agent-executed QA scenarios.

---

## Execution Strategy

### Sequential Tasks
1. Publish sitemap configuration
2. Create GenerateSitemap artisan command
3. Add CMS pages to sitemap
4. Configure daily schedule
5. Verify sitemap generation

---

## TODOs

- [ ] 1. Publish sitemap configuration

  **What to do**:
  - Run `php artisan vendor:publish --provider="Spatie\Sitemap\SitemapServiceProvider" --tag=sitemap-config`
  - This creates config/sitemap.php with default settings

  **Must NOT do**:
  - Don't modify config unless needed for customization

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  
  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocks**: Task 2
  - **Blocked By**: None

  **References**:
  - Official docs: `https://spatie.be/docs/laravel-sitemap/v8/introduction`
  - Laravel 12 docs: Uses `php artisan vendor:publish` command

  **Acceptance Criteria**:
  - [ ] config/sitemap.php file exists

  **QA Scenarios**:

  Scenario: Config file published
    Tool: Bash
    Preconditions: None
    Steps:
      1. Run `test -f config/sitemap.php && echo "EXISTS" || echo "MISSING"`
    Expected Result: Output contains "EXISTS"
    Evidence: terminal output

  **Commit**: NO

- [ ] 2. Create GenerateSitemap artisan command

  **What to do**:
  - Create app/Console/Commands/GenerateSitemap.php
  - Use SitemapGenerator::create() to crawl the site
  - Write to public_path('sitemap.xml')
  - Follow Laravel 12 conventions (no Kernel.php, use routes/console.php)

  **Must NOT do**:
  - Don't include auth-protected routes

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  
  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocks**: Task 3, 4, 5
  - **Blocked By**: Task 1

  **References**:
  - Package README: Basic crawler example shown above
  - Existing commands in app/Console/Commands/ for code style

  **Acceptance Criteria**:
  - [ ] Command file created at app/Console/Commands/GenerateSitemap.php
  - [ ] Command registered (artisan list shows sitemap:generate)

  **QA Scenarios**:

  Scenario: Command is registered
    Tool: Bash
    Preconditions: Command file created
    Steps:
      1. Run `php artisan list | grep sitemap`
    Expected Result: Output shows "sitemap:generate" command

  **Commit**: NO

- [ ] 3. Add CMS pages to sitemap

  **What to do**:
  - Modify GenerateSitemap command to query CMS pages from database
  - Add each published CMS page to sitemap with route('cms.page', $page->slug)
  - Check for CMS model and its structure

  **Must NOT do**:
  - Don't add unpublished CMS pages

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  
  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocks**: Task 5
  - **Blocked By**: Task 2

  **References**:
  - CMS model likely in app/Models/ or similar
  - Route: route('cms.page', $slug) - see routes/web.php line 150

  **Acceptance Criteria**:
  - [ ] Code queries CMS pages from database
  - [ ] Each published page added to sitemap

  **QA Scenarios**:

  Scenario: Command runs without errors
    Tool: Bash
    Preconditions: Command created, CMS model exists
    Steps:
      1. Run `php artisan sitemap:generate`
    Expected Result: Command completes without errors
    Evidence: terminal output

  **Commit**: NO

- [ ] 4. Configure daily schedule

  **What to do**:
  - Add scheduled task in routes/console.php for daily regeneration
  - Schedule at midnight (0 0 * * *)

  **Must NOT do**:
  - Don't use deprecated Kernel.php approach

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  
  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocks**: None
  - **Blocked By**: Task 2

  **References**:
  - routes/console.php for Laravel 12 scheduling
  - Laravel docs: Schedule configuration

  **Acceptance Criteria**:
  - [ ] Schedule registered in routes/console.php

  **QA Scenarios**:

  Scenario: Schedule is registered
    Tool: Bash
    Preconditions: console.php updated
    Steps:
      1. Run `php artisan schedule:list`
    Expected Result: Shows sitemap:generate scheduled

  **Commit**: NO

- [ ] 5. Verify sitemap generation

  **What to do**:
  - Run sitemap:generate command
  - Verify sitemap.xml is created in public/
  - Check XML validity

  **Must NOT do**:
  - Don't commit the generated sitemap.xml (it's auto-generated)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  
  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocks**: None
  - **Blocked By**: Task 3

  **References**:
  - Public path: public_path('sitemap.xml')

  **Acceptance Criteria**:
  - [ ] public/sitemap.xml exists after running command
  - [ ] Valid XML format
  - [ ] Contains expected URLs

  **QA Scenarios**:

  Scenario: Sitemap is valid XML
    Tool: Bash
    Preconditions: Sitemap generated
    Steps:
      1. Run `xmllint --noout public/sitemap.xml 2>&1 && echo "VALID" || echo "INVALID"`
    Expected Result: Output contains "VALID"

  Scenario: Sitemap contains expected URLs
    Tool: Bash
    Preconditions: Sitemap generated
    Steps:
      1. Run `grep -c "<loc>" public/sitemap.xml`
    Expected Result: Count > 0

  **Commit**: NO

---

## Final Verification Wave

- [ ] F1. **Sitemap Accessible** — Run sitemap:generate, verify public/sitemap.xml exists with valid XML containing expected URLs

---

## Success Criteria

### Verification Commands
```bash
php artisan sitemap:generate
xmllint --noout public/sitemap.xml
curl -I http://localhost/sitemap.xml  # Should return 200
```

### Final Checklist
- [ ] Sitemap command registered and works
- [ ] Static pages crawled and included
- [ ] CMS pages from database included
- [ ] Daily schedule configured
- [ ] Valid XML sitemap generated
