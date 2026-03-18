# SEO Improvements Work Plan

## TL;DR

> **Quick Summary**: Add missing SEO meta tags (canonical URL, JSON-LD structured data, Twitter Cards, robots), implement dynamic page titles via Livewire attributes, and improve image accessibility with descriptive alt text.

> **Deliverables**:
> - Updated HomeController.php with Title attribute and SEO data
> - Enhanced app_new.blade.php layout with canonical URL, Twitter Cards, JSON-LD Organization schema
> - Added FAQ JSON-LD to home-controller.blade.php
> - Improved image alt attributes throughout

> **Estimated Effort**: Short
> **Parallel Execution**: YES - 3 waves
> **Critical Path**: Task 1 → Task 2 → Task 3 → Task 6 (final verification)

---

## Context

### Original Request
Improve SEO of gym management website to rank #1 on Google Search. Analyze HomeController.php and app_new.blade.php and implement meta tags, Open Graph, canonical URLs, JSON-LD, heading hierarchy, and image alt attributes.

### Interview Summary
**Key Discussions**:
- HomeController.php needs `#[Title]` attribute and dynamic SEO data
- app_new.blade.php layout missing canonical URL, Twitter Cards, JSON-LD
- home-controller.blade.php needs FAQ schema and better image alt text
- RequestDemo already has `#[Title]` - no changes needed there
- All changes must be non-breaking

### Current SEO State
| Element | Status |
|---------|--------|
| Title Tag | ⚠️ Partial - hardcoded fallback, no #[Title] on HomeController |
| Meta Description | ⚠️ Static - hardcoded fallback |
| Open Graph | ⚠️ Basic - only title, description, type |
| Canonical URL | ❌ Missing |
| JSON-LD | ❌ Missing |
| Heading hierarchy | ✅ Good |
| Image alt | ⚠️ Partial |
| Robots meta | ❌ Missing |
| Twitter Cards | ❌ Missing |

---

## Work Objectives

### Core Objective
Implement comprehensive SEO improvements to improve Google ranking without breaking existing functionality.

### Concrete Deliverables
- `app/Livewire/Frontend/HomeController.php` - Add Title attribute + SEO data array
- `resources/views/frontend/layouts/app_new.blade.php` - Add canonical, Twitter Cards, JSON-LD
- `resources/views/livewire/frontend/home-controller.blade.php` - Add FAQ schema, improve alt text

### Definition of Done
- [ ] Home page loads without errors
- [ ] Page source shows all new meta tags present
- [ ] JSON-LD validates in Google Rich Results Test
- [ ] No JavaScript console errors
- [ ] All existing functionality works

### Must Have
- Canonical URL tag in layout
- JSON-LD Organization schema in layout
- FAQPage schema in home page
- Twitter Card meta tags
- Dynamic SEO data from controller

### Must NOT Have (Guardrails)
- NO new package dependencies
- NO breaking changes to existing functionality
- NO changes to RequestDemo (already has #[Title])
- NO sitemap.xml (flagged separately)
- NO robots.txt (flagged separately)

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: None required - SEO changes are static markup
- **Framework**: N/A

### QA Policy
Every task MUST include agent-executed QA scenarios. Verification via browser tools.

**Verification Approach**:
- Use Playwright to verify page renders correctly
- Use curl/Bash to check HTML source for meta tags
- Use Google Rich Results Test (manual) for JSON-LD validation

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - can run parallel):
├── Task 1: Add Title attribute + SEO data to HomeController
└── Task 2: Add canonical URL + robots meta to layout

Wave 2 (Core SEO - can run parallel):
├── Task 3: Add Twitter Card meta tags to layout
├── Task 4: Add JSON-LD Organization schema to layout
└── Task 5: Add FAQ JSON-LD to home page view

Wave 3 (Enhancements - can run parallel):
├── Task 6: Improve image alt attributes
└── Task 7: Final verification

Wave FINAL:
├── Task F1: Plan compliance audit (oracle)
├── Task F2: Code quality review (unspecified-high)
├── Task F3: Real manual QA (unspecified-high)
└── Task F4: Scope fidelity check (deep)
-> Present results -> Get explicit user okay
```

### Dependency Matrix
- **1**: — — 2, 3, 4, 5
- **2**: 1 — 3, 4, 5
- **3**: 1, 2 — 6
- **4**: 1, 2 — 6
- **5**: 1, 2 — 6
- **6**: 3, 4, 5 — F1, F2, F3, F4

---

## TODOs

- [ ] 1. Add Title attribute and SEO data to HomeController

  **What to do**:
  - Add `use Livewire\Attributes\Title;` import
  - Add `#[Title('Best Gym Management Software - Fluxfitness')]` attribute above class declaration
  - Modify render() method to pass `$seo` array with title, description, keywords
  - Keep existing $plans and $enterprisePlans data

  **Must NOT do**:
  - Do not change existing logic for plan fetching
  - Do not modify selectPlan, startFreeTrial, requestDemo methods
  - Do not remove any existing functionality

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple attribute addition and array passing
  - **Skills**: []
  - **Skills Evaluated but Omitted**:
    - `volt-development`: Not needed - this is PHP attribute, not Blade component

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Task 2)
  - **Blocks**: Tasks 3, 4, 5
  - **Blocked By**: None

  **References**:
  - `app/Livewire/Frontend/RequestDemo.php:17` - Example of #[Title] usage already in project

  **Acceptance Criteria**:
  - [ ] HomeController has #[Title] attribute with proper title
  - [ ] render() passes $seo array to view
  - [ ] Page still loads with plans data

  **QA Scenarios**:
  ```
  Scenario: HomeController has Title attribute
    Tool: Bash
    Preconditions: None
    Steps:
      1. Read app/Livewire/Frontend/HomeController.php
      2. Verify #[Title] attribute exists
      3. Verify $seo array is passed to view
    Expected Result: #[Title] found, $seo array present in return view()
    Evidence: .sisyphus/evidence/task-1-title-attribute.png

  Scenario: Page renders without errors
    Tool: Playwright
    Preconditions: Server running
    Steps:
      1. Navigate to home page
      2. Verify page loads
      3. Check no console errors
    Expected Result: Page loads, no errors
    Evidence: .sisyphus/evidence/task-1-page-load.png
  ```

  **Commit**: YES
  - Message: `feat(seo): add dynamic SEO meta tags`
  - Files: `app/Livewire/Frontend/HomeController.php`

- [ ] 2. Add canonical URL and robots meta to layout

  **What to do**:
  - Add `<link rel="canonical">` tag in <head> section
  - Add `<meta name="robots">` tag
  - Use url()->current() for dynamic canonical URL

  **Must NOT do**:
  - Do not remove existing meta tags
  - Do not change any styling

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple HTML meta tag addition
  - **Skills**: []
  - **Skills Evaluated but Omitted**:
    - `tailwindcss-development`: No styling changes

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Task 1)
  - **Blocks**: Task 3, 4, 5
  - **Blocked By**: Task 1

  **References**:
  - `resources/views/frontend/layouts/app_new.blade.php:7-15` - Existing meta tags location

  **Acceptance Criteria**:
  - [ ] Canonical URL tag present with proper href
  - [ ] Robots meta tag present with "index, follow"

  **QA Scenarios**:
  ```
  Scenario: Canonical URL in page source
    Tool: Bash
    Preconditions: None
    Steps:
      1. curl -s http://localhost | grep 'rel="canonical"'
    Expected Result: Found with correct URL
    Evidence: .sisyphus/evidence/task-2-canonical.png

  Scenario: Robots meta in page source
    Tool: Bash
    Preconditions: None
    Steps:
      1. curl -s http://localhost | grep 'name="robots"'
    Expected Result: Found with "index, follow"
    Evidence: .sisyphus/evidence/task-2-robots.png
  ```

  **Commit**: YES
  - Message: `feat(seo): add canonical and robots meta`
  - Files: `resources/views/frontend/layouts/app_new.blade.php`

- [ ] 3. Add Twitter Card meta tags to layout

  **What to do**:
  - Add Twitter Card meta tags after Open Graph section
  - Include: twitter:card, twitter:title, twitter:description, twitter:image
  - Use same values as Open Graph for consistency

  **Must NOT do**:
  - Do not duplicate existing Open Graph tags
  - Do not add any Twitter-specific JavaScript

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple meta tag addition
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 5)
  - **Blocks**: Task 6
  - **Blocked By**: Tasks 1, 2

  **References**:
  - `resources/views/frontend/layouts/app_new.blade.php:11-15` - Open Graph location

  **Acceptance Criteria**:
  - [ ] All 4 Twitter Card meta tags present

  **QA Scenarios**:
  ```
  Scenario: Twitter Cards in page source
    Tool: Bash
    Preconditions: None
    Steps:
      1. curl -s http://localhost | grep 'twitter:card'
      2. curl -s http://localhost | grep 'twitter:title'
    Expected Result: Both tags found
    Evidence: .sisyphus/evidence/task-3-twitter.png
  ```

  **Commit**: YES
  - Message: `feat(seo): add Twitter Cards`
  - Files: `resources/views/frontend/layouts/app_new.blade.php`

- [ ] 4. Add JSON-LD Organization schema to layout

  **What to do**:
  - Add Organization schema in <head> using <script type="application/ld+json">
  - Include: @context, @type, name, url, logo, description, contactPoint, sameAs
  - Use config() for dynamic values where available

  **Must NOT do**:
  - Do not add any visual elements
  - Do not modify existing scripts

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Adding JSON-LD script block
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 3, 5)
  - **Blocks**: Task 6
  - **Blocked By**: Tasks 1, 2

  **References**:
  - `config/app.php` - For app name, URL, contact details

  **Acceptance Criteria**:
  - [ ] JSON-LD script present with valid Organization schema

  **QA Scenarios**:
  ```
  Scenario: JSON-LD Organization schema present
    Tool: Bash
    Preconditions: None
    Steps:
      1. curl -s http://localhost | grep 'application/ld+json'
      2. Verify "@type\": \"Organization\"" found
    Expected Result: Schema found and valid
    Evidence: .sisyphus/evidence/task-4-jsonld-org.png
  ```

  **Commit**: YES
  - Message: `feat(seo): add Organization JSON-LD`
  - Files: `resources/views/frontend/layouts/app_new.blade.php`

- [ ] 5. Add FAQ JSON-LD to home page view

  **What to do**:
  - Add FAQPage schema in home-controller.blade.php
  - Include 3-5 relevant FAQ questions about gym management software
  - Place at bottom of page in <script> tag

  **Must NOT do**:
  - Do not modify any existing content
  - Do not add visible FAQ section

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Adding JSON-LD script to Blade template
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 3, 4)
  - **Blocks**: Task 6
  - **Blocked By**: Tasks 1, 2

  **References**:
  - `resources/views/livewire/frontend/home-controller.blade.php` - Add at end before </div>

  **Acceptance Criteria**:
  - [ ] FAQPage JSON-LD present with valid structure
  - [ ] At least 3 FAQ questions included

  **QA Scenarios**:
  ```
  Scenario: FAQ JSON-LD in home page
    Tool: Bash
    Preconditions: None
    Steps:
      1. curl -s http://localhost | grep 'FAQPage'
    Expected Result: FAQPage schema found
    Evidence: .sisyphus/evidence/task-5-faq.png
  ```

  **Commit**: YES
  - Message: `feat(seo): add FAQ schema`
  - Files: `resources/views/livewire/frontend/home-controller.blade.php`

- [ ] 6. Improve image alt attributes

  **What to do**:
  - Review all images in home-controller.blade.php
  - Replace generic alt text with descriptive text
  - Include relevant keywords naturally in alt text
  - Add width/height attributes where missing

  **Must NOT do**:
  - Do not change image sources
  - Do not add decorative/empty alt text

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Alt text improvements
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Task 7)
  - **Blocks**: Final verification
  - **Blocked By**: Tasks 3, 4, 5

  **References**:
  - `resources/views/livewire/frontend/home-controller.blade.php:69-70, 172-174, 190-192, 308-310, 326-328` - Images to improve

  **Acceptance Criteria**:
  - [ ] All images have descriptive alt text
  - [ ] No generic "image" or "photo" alt text

  **QA Scenarios**:
  ```
  Scenario: Image alt text improved
    Tool: Bash
    Preconditions: None
    Steps:
      1. curl -s http://localhost | grep -o 'alt="[^"]*"' | wc -l
    Expected Result: All images have alt attributes
    Evidence: .sisyphus/evidence/task-6-alt-text.png
  ```

  **Commit**: YES
  - Message: `feat(seo): improve image alt text`
  - Files: `resources/views/livewire/frontend/home-controller.blade.php`

- [ ] 7. Final verification of all SEO changes

  **What to do**:
  - Verify all meta tags render correctly
  - Check JSON-LD is valid
  - Ensure no JavaScript errors
  - Test page on mobile viewport

  **Must NOT do**:
  - Do not make structural changes

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Verification only
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Task 6)
  - **Blocks**: Final verification wave
  - **Blocked By**: Task 6

  **References**:
  - N/A - Verification task

  **Acceptance Criteria**:
  - [ ] Page loads without errors
  - [ ] All new meta tags present in source

  **QA Scenarios**:
  ```
  Scenario: Full SEO audit
    Tool: Playwright
    Preconditions: Server running
    Steps:
      1. Navigate to home page
      2. Take screenshot
      3. Check console for errors
    Expected Result: Clean load, no errors
    Evidence: .sisyphus/evidence/task-7-final-audit.png
  ```

  **Commit**: NO

---

## Final Verification Wave (MANDATORY)

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists. For each "Must NOT Have": search codebase for forbidden patterns. Check evidence files exist.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `vendor/bin/pint --dirty` for code formatting. Check no unused imports added. Verify JSON-LD syntax is valid.
  Output: `Pint [PASS/FAIL] | JSON-LD [VALID/INVALID] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Start from clean state. Execute EVERY QA scenario from EVERY task. Test page loads correctly. Save to `.sisyphus/evidence/final-qa/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff. Verify 1:1 — everything in spec was built, nothing beyond spec was built.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | VERDICT`

---

## Commit Strategy

- **1**: `feat(seo): add dynamic SEO meta tags` — HomeController.php
- **2**: `feat(seo): add canonical and robots meta` — app_new.blade.php
- **3**: `feat(seo): add Twitter Cards` — app_new.blade.php
- **4**: `feat(seo): add Organization JSON-LD` — app_new.blade.php
- **5**: `feat(seo): add FAQ schema` — home-controller.blade.php
- **6**: `feat(seo): improve image alt text` — home-controller.blade.php

---

## Success Criteria

### Verification Commands
```bash
# Check page loads
curl -s https://yoursite.com | grep -o '<title>.*</title>'

# Check canonical
curl -s https://yoursite.com | grep -o 'rel="canonical"'

# Check JSON-LD
curl -s https://yoursite.com | grep -o 'application/ld+json'
```

### Final Checklist
- [ ] All Must Have items implemented
- [ ] All Must NOT items avoided
- [ ] Page renders without errors
- [ ] All meta tags present in source
