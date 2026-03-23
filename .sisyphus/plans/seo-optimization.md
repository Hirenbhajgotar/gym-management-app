# SEO Optimization Work Plan - FluxFitness

## TL;DR

> **Quick Summary**: Improve SEO to reach 90%+ GSC score and page 1 Google ranking through structured data, Core Web Vitals optimization, and technical SEO enhancements.
>
> **Deliverables**:
> - SoftwareApplication JSON-LD schema added to homepage
> - BreadcrumbList schema for navigation
> - Image optimization (WebP format, dimensions for CLS)
> - Enhanced robots.txt with proper directives
> - hreflang tags for international SEO
> - Missing OG image placeholder/guidance
>
> **Estimated Effort**: Short (3-4 hours)
> **Parallel Execution**: YES - 3 waves
> **Critical Path**: Task 1 → Task 3 → Task 5 → Task 6 → Verification

---

## Context

### Original Request
Improve SEO to search. Goal is to appear site on page 1. Current GSC performance shows 77%. PageSpeed Insights link provided for analysis.

### Interview Summary
**Key Discussions**:
- Sitemap localhost URLs are development-only - live uses `fluxfitness.in` (NOT a problem)
- User wants work plan for remaining SEO issues
- Primary focus on landing page (frontend) optimization

**Research Findings**:
- `app_new.blade.php` has solid SEO foundation with OG tags, Twitter cards, Organization schema, FAQPage schema
- All images have descriptive alt text with `loading="lazy"`
- Font preloading already implemented
- Images are relatively small (30-64KB) but could use WebP format
- No explicit image dimensions causing potential CLS issues

### Metis Review
**Identified Gaps** (addressed):
- Missing SoftwareApplication schema for rich snippets
- Missing BreadcrumbList for enhanced SERP appearance
- Image CLS issues from missing width/height attributes
- No explicit acceptance criteria for GSC improvement
- Need to verify which layout is actually used for homepage

---

## Work Objectives

### Core Objective
Improve SEO performance from 77% to 90%+ GSC score through technical SEO enhancements targeting Core Web Vitals, structured data, and crawl efficiency.

### Concrete Deliverables
- [x] `resources/views/frontend/layouts/app_new.blade.php` - Add SoftwareApplication JSON-LD schema
- [x] `resources/views/frontend/layouts/app_new.blade.php` - Add BreadcrumbList schema
- [x] `resources/views/livewire/frontend/home-controller.blade.php` - Add image dimensions for CLS
- [x] `public/robots.txt` - Enhanced with proper directives
- [x] `resources/views/frontend/layouts/app_new.blade.php` - Add hreflang tags
- [x] `public/images/og-home.jpg` - Create or provide placeholder guidance

### Definition of Done
- [ ] Google Rich Results Test shows valid schemas for all added types
- [ ] PageSpeed Insights shows improved Core Web Vitals (LCP <2.5s, CLS <0.1)
- [ ] robots.txt returns 200 with proper content
- [ ] OG image is accessible at correct dimensions (1200x630)

### Must Have
- Valid JSON-LD structured data that passes Google's testing tools
- All images have explicit width/height attributes
- robots.txt has proper allow/disallow directives

### Must NOT Have (Guardrails)
- NO changes to sitemap generation or URL structure
- NO changes to backend functionality
- NO changes to authentication or authorization
- NO addition of new database tables or migrations
- NO changes to payment processing

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (app has test suite)
- **Automated tests**: None (SEO changes require visual/manual verification)
- **Framework**: N/A

### QA Policy
Every task includes agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/`.

**Frontend/UI**: Use Playwright — Verify schema rendering, check HTML source
**API/Backend**: N/A for SEO changes

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Schema Markup — foundation):
├── Task 1: Add SoftwareApplication JSON-LD schema
├── Task 2: Add BreadcrumbList JSON-LD schema
└── Task 3: Add hreflang language tags

Wave 2 (Performance — Core Web Vitals):
├── Task 4: Add image dimensions (width/height) for CLS fix
├── Task 5: Add image preloading hints
└── Task 6: Create og-home.jpg placeholder guidance

Wave 3 (Crawl Efficiency):
├── Task 7: Enhance robots.txt with proper directives
└── Task 8: Verify layout rendering (ensure app_new.blade.php is used)

Wave FINAL (Verification):
├── Task F1: Validate JSON-LD with Google Rich Results Test
└── Task F2: Verify PageSpeed improvements
```

### Dependency Matrix

- **1, 2, 3**: — — 4-8, None
- **4**: 1, 2 — 5-8, F1
- **5**: 4 — 6-8, F1
- **6**: — — 7-8, F1
- **7**: — — 8, None
- **8**: — — F1, F2

### Agent Dispatch Summary

- **1**: **3** — T1 → `writing`, T2 → `writing`, T3 → `writing`
- **2**: **3** — T4 → `visual-engineering`, T5 → `visual-engineering`, T6 → `visual-engineering`
- **3**: **2** — T7 → `writing`, T8 → `quick`
- **FINAL**: **2** — F1 → `unspecified-high`, F2 → `playwright`

---

## TODOs

- [ ] 1. Add SoftwareApplication JSON-LD Schema

  **What to do**:
  - Add SoftwareApplication JSON-LD schema to `resources/views/frontend/layouts/app_new.blade.php` in the `<head>` section
  - Include: name, applicationCategory, operatingSystem, offers (price: 0, priceCurrency: INR), aggregateRating
  - Place before the existing Organization schema script

  **Must NOT do**:
  - Don't modify any backend files
  - Don't change existing Organization schema
  - Don't add duplicate schema types

  **Recommended Agent Profile**:
  > - **Category**: `writing`
    - Reason: Adding structured data markup is documentation/serialization work
  > - **Skills**: []
    - No specific skills needed for JSON-LD addition

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3)
  - **Blocks**: Tasks 4-8, F1
  - **Blocked By**: None

  **References**:

  **Pattern References** (existing code to follow):
  - `resources/views/frontend/layouts/app_new.blade.php:27-41` - Existing JSON-LD Organization schema pattern to follow
  - `resources/views/livewire/frontend/home-controller.blade.php:928-987` - Existing FAQPage schema pattern to follow

  **API/Type References** (contracts to implement against):
  - Google Structured Data: https://schema.org/SoftwareApplication
  - Required fields: name, applicationCategory, operatingSystem

  **External References** (libraries and frameworks):
  - Google Rich Results Test: https://search.google.com/test/rich-results
  - Schema.org SoftwareApplication: https://schema.org/SoftwareApplication

  **Acceptance Criteria**:

  - [ ] JSON-LD script tag added before `</head>`
  - [ ] Schema contains: @type "SoftwareApplication", name "Fluxfitness", offers with price "0"
  - [ ] HTML validates without errors

  **QA Scenarios**:

  ```
  Scenario: JSON-LD schema renders correctly in HTML source
    Tool: Playwright
    Preconditions: Homepage loaded
    Steps:
      1. Navigate to homepage (fluxfitness.in)
      2. Open browser developer tools (F12)
      3. View page source or inspect <head>
      4. Search for 'application/ld+json' script tags
      5. Find the SoftwareApplication schema
    Expected Result: Schema exists with correct @type, name, and offers fields
    Failure Indicators: Missing schema, syntax errors, missing required fields
    Evidence: .sisyphus/evidence/task-1-schema-render.html

  Scenario: Schema validates with Google Rich Results Test
    Tool: Playwright
    Preconditions: Local dev server running
    Steps:
      1. Navigate to Google Rich Results Test
      2. Enter URL: http://localhost:8000
      3. Click "Test URL"
      4. Check for "SoftwareApplication" in detected items
    Expected Result: SoftwareApplication schema detected and valid
    Failure Indicators: Schema not detected, validation errors
    Evidence: .sisyphus/evidence/task-1-rich-results.png
  ```

  **Evidence to Capture**:
  - [ ] HTML source snippet showing added schema
  - [ ] Google Rich Results Test screenshot

  **Commit**: YES
  - Message: `feat(seo): add SoftwareApplication JSON-LD schema for rich snippets`
  - Files: `resources/views/frontend/layouts/app_new.blade.php`

---

- [ ] 2. Add BreadcrumbList JSON-LD Schema

  **What to do**:
  - Add BreadcrumbList JSON-LD schema to `resources/views/frontend/layouts/app_new.blade.php`
  - Include breadcrumb trail: Home > Features/Pricing/Contact (based on section)
  - Add as `@stack('breadcrumbs')` or inline in head

  **Must NOT do**:
  - Don't modify navigation structure
  - Don't change route definitions
  - Don't add duplicate schema

  **Recommended Agent Profile**:
  > - **Category**: `writing`
    - Reason: Structured data markup follows existing pattern
  > - **Skills**: []
    - No specific skills needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3)
  - **Blocks**: Tasks 4-8, F1
  - **Blocked By**: None

  **References**:

  **Pattern References** (existing code to follow):
  - `resources/views/frontend/layouts/app_new.blade.php:27-41` - JSON-LD script pattern
  - Google BreadcrumbList format: https://schema.org/BreadcrumbList

  **External References** (libraries and frameworks):
  - Schema.org BreadcrumbList: https://schema.org/BreadcrumbList
  - Google breadcrumb guidelines: https://developers.google.com/search/docs/appearance/structured-data/breadcrumb

  **Acceptance Criteria**:

  - [ ] BreadcrumbList schema added to head
  - [ ] Contains Home as first item, then section names
  - [ ] URLs match actual page URLs

  **QA Scenarios**:

  ```
  Scenario: BreadcrumbList schema renders and validates
    Tool: Playwright
    Preconditions: Homepage loaded
    Steps:
      1. Navigate to homepage
      2. Inspect <head> for breadcrumb JSON-LD
      3. Copy schema to Google Rich Results Test
      4. Verify BreadcrumbList type detected
    Expected Result: BreadcrumbList schema valid with correct itemListElement
    Failure Indicators: Invalid schema syntax, missing ListItem properties
    Evidence: .sisyphus/evidence/task-2-breadcrumb-validation.html
  ```

  **Evidence to Capture**:
  - [ ] HTML source showing BreadcrumbList schema
  - [ ] Validation result screenshot

  **Commit**: YES
  - Message: `feat(seo): add BreadcrumbList JSON-LD schema for enhanced SERP`
  - Files: `resources/views/frontend/layouts/app_new.blade.php`

---

- [ ] 3. Add hreflang Language Tags

  **What to do**:
  - Add hreflang tags to `resources/views/frontend/layouts/app_new.blade.php`
  - Include: en-in (English, India - primary), xdefault
  - Add both in head and HTTP headers (for images)

  **Must NOT do**:
  - Don't add language variants that don't exist
  - Don't add incorrect language codes
  - Don't modify HTML lang attribute

  **Recommended Agent Profile**:
  > - **Category**: `writing`
    - Reason: Simple meta tag addition
  > - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2)
  - **Blocks**: Tasks 4-8
  - **Blocked By**: None

  **References**:

  **Pattern References** (existing code to follow):
  - `resources/views/frontend/layouts/app_new.blade.php` - Existing meta tag pattern

  **External References** (libraries and frameworks):
  - Google hreflang guidelines: https://support.google.com/webmasters/answer/189077

  **Acceptance Criteria**:

  - [ ] hreflang="en-in" tag present
  - [ ] hreflang="xdefault" tag present
  - [ ] Tags point to correct canonical URLs

  **QA Scenarios**:

  ```
  Scenario: hreflang tags present in HTML head
    Tool: Playwright
    Preconditions: Homepage loaded
    Steps:
      1. Navigate to homepage
      2. View page source
      3. Search for 'hreflang'
      4. Verify en-in and xdefault tags present
    Expected Result: Two hreflang meta tags found with correct language codes
    Failure Indicators: Missing tags, incorrect URLs, wrong language codes
    Evidence: .sisyphus/evidence/task-3-hreflang-check.html
  ```

  **Evidence to Capture**:
  - [ ] Screenshot of hreflang tags in HTML

  **Commit**: YES
  - Message: `feat(seo): add hreflang tags for international SEO`
  - Files: `resources/views/frontend/layouts/app_new.blade.php`

---

- [ ] 4. Add Image Dimensions for CLS Fix

  **What to do**:
  - Add width and height attributes to all `<img>` tags in `resources/views/livewire/frontend/home-controller.blade.php`
  - Images to fix: banner.jpg, crm-integration.jpg, user-management.jpg, attandence-tracking.jpg, multi-location.jpg
  - Use actual image dimensions from file system

  **Must NOT do**:
  - Don't change image sources
  - Don't add inline styles for dimensions
  - Don't modify the aspect ratio (width:height must match actual)

  **Recommended Agent Profile**:
  > - **Category**: `visual-engineering`
    - Reason: UI/UX modification for performance improvement
  > - **Skills**: []
    - No specific skills needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 5, 6)
  - **Blocks**: Task 7, F1, F2
  - **Blocked By**: Task 1, 2

  **References**:

  **Pattern References** (existing code to follow):
  - `resources/views/livewire/frontend/home-controller.blade.php:69` - Current img tag pattern (has loading="lazy")

  **Image Dimensions** (from file system):
  - banner.jpg: Need to add after measuring
  - crm-integration.jpg: Need to add after measuring
  - user-management.jpg: Need to add after measuring
  - attandence-tracking.jpg: Need to add after measuring
  - multi-location.jpg: Need to add after measuring

  **Acceptance Criteria**:

  - [ ] All <img> tags have width and height attributes
  - [ ] Aspect ratios match original images
  - [ ] Images still render correctly
  - [ ] No layout shift on page load (CLS improvement)

  **QA Scenarios**:

  ```
  Scenario: All images have explicit dimensions
    Tool: Playwright
    Preconditions: Homepage loaded
    Steps:
      1. Navigate to homepage
      2. Open browser DevTools
      3. Run: document.querySelectorAll('img')
      4. Check each img for width and height attributes
    Expected Result: All 5 feature images have width and height attributes
    Failure Indicators: Some images missing dimensions, wrong aspect ratio
    Evidence: .sisyphus/evidence/task-4-image-dimensions.html

  Scenario: CLS improvement verified
    Tool: Playwright
    Preconditions: Chrome DevTools open
    Steps:
      1. Navigate to homepage with Performance tab open
      2. Reload page
      3. Check "Cumulative Layout Shift" metric
      4. Target: < 0.1
    Expected Result: CLS score improved (was causing 77% score)
    Failure Indicators: CLS still high, images still shifting
    Evidence: .sisyphus/evidence/task-4-cls-improvement.png
  ```

  **Evidence to Capture**:
  - [ ] List of images with their dimensions
  - [ ] DevTools screenshot showing CLS metric

  **Commit**: YES
  - Message: `perf(seo): add image dimensions to fix CLS issues`
  - Files: `resources/views/livewire/frontend/home-controller.blade.php`

---

- [ ] 5. Add Image Preloading Hints

  **What to do**:
  - Add `<link rel="preload">` tags in head for above-the-fold images
  - Priority: banner.jpg (hero image) and critical fonts
  - Add as="image" with fetchpriority="high" for LCP optimization

  **Must NOT do**:
  - Don't preload all images (only critical above-the-fold)
  - Don't add unnecessary DNS prefetch
  - Don't duplicate existing preconnect hints

  **Recommended Agent Profile**:
  > - **Category**: `visual-engineering`
    - Reason: Performance optimization for Core Web Vitals
  > - **Skills**: []
    - No specific skills needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 6)
  - **Blocks**: Task 7, F1, F2
  - **Blocked By**: Task 4

  **References**:

  **Pattern References** (existing code to follow):
  - `resources/views/frontend/layouts/app_new.blade.php:12-13` - Existing preconnect pattern

  **External References** (libraries and frameworks):
  - MDN preload: https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/rel/preload
  - Google LCP guidance: https://web.dev/articles/optimize-lcp

  **Acceptance Criteria**:

  - [ ] banner.jpg has preload tag with fetchpriority="high"
  - [ ] Preload tag placed in head before CSS
  - [ ] No duplicate preload tags

  **QA Scenarios**:

  ```
  Scenario: Preload hints render correctly
    Tool: Playwright
    Preconditions: Homepage loaded
    Steps:
      1. Navigate to homepage
      2. View page source
      3. Search for 'preload'
      4. Verify banner.jpg preload tag exists
    Expected Result: <link rel="preload" as="image" href="/images/banner.jpg" fetchpriority="high">
    Failure Indicators: Missing preload tag, wrong attributes
    Evidence: .sisyphus/evidence/task-5-preload-check.html
  ```

  **Evidence to Capture**:
  - [ ] Screenshot of preload tag in HTML head

  **Commit**: YES
  - Message: `perf(seo): add image preloading for LCP improvement`
  - Files: `resources/views/frontend/layouts/app_new.blade.php`

---

- [ ] 6. Create OG Image Placeholder Guidance

  **What to do**:
  - Check if `public/images/og-home.jpg` exists
  - If missing, create placeholder guidance in a new file or provide instructions
  - Ensure image meets specs: 1200x630px minimum, 5MB max
  - Update og:image meta tag to point to correct file

  **Must NOT do**:
  - Don't create low-quality placeholder image
  - Don't use screenshot of homepage (not optimal)
  - Don't commit large image files

  **Recommended Agent Profile**:
  > - **Category**: `visual-engineering`
    - Reason: Social media optimization requires visual design
  > - **Skills**: []
    - No specific skills needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 5)
  - **Blocks**: Task 7, F1
  - **Blocked By**: None

  **References**:

  **Pattern References** (existing code to follow):
  - `resources/views/frontend/layouts/app_new.blade.php:24` - Current og:image reference

  **Image Requirements**:
  - Dimensions: 1200x630px minimum (1200x1200 for square)
  - Format: JPG or PNG
  - Max size: 5MB
  - Content: Branding, value proposition, no text (or minimal)

  **Acceptance Criteria**:

  - [ ] og-home.jpg exists in public/images/
  - [ ] og:image meta tag points to existing file
  - [ ] Image dimensions are 1200x630 or larger

  **QA Scenarios**:

  ```
  Scenario: OG image exists and is accessible
    Tool: Playwright
    Preconditions: Homepage loaded
    Steps:
      1. Navigate to homepage
      2. View page source
      3. Find og:image meta tag
      4. Extract URL from content attribute
      5. Open URL in new tab to verify image loads
    Expected Result: Image loads at 1200x630 or larger dimensions
    Failure Indicators: 404 error, image not found, wrong dimensions
    Evidence: .sisyphus/evidence/task-6-og-image-check.png

  Scenario: Social media share preview test
    Tool: Playwright
    Preconditions: Facebook/LN LinkedIn debug tools
    Steps:
      1. Open Facebook Sharing Debugger
      2. Enter URL: https://fluxfitness.in
      3. Click "Debug"
      4. Check "What I see" section
    Expected Result: OG image displays correctly
    Failure Indicators: Missing image, low resolution warning
    Evidence: .sisyphus/evidence/task-6-social-preview.png
  ```

  **Evidence to Capture**:
  - [ ] Screenshot of og:image file existence
  - [ ] Social media debug tool screenshot

  **Commit**: YES (if creating file)
  - Message: `feat(seo): add og-home.jpg for social media sharing`
  - Files: `public/images/og-home.jpg` (if created)

---

- [ ] 7. Enhance robots.txt with Proper Directives

  **What to do**:
  - Update `public/robots.txt` with comprehensive directives
  - Include: Allow, Disallow, Sitemap, Crawl-delay, Sitemap location
  - Disallow: /admin, /filament, /api/, /download-failed-rows/
  - Allow: /, /request-demo, /business-register, /page/*

  **Must NOT do**:
  - Don't block CSS/JS resources (needed for rendering)
  - Don't block sitemap location
  - Don't add wildcard patterns incorrectly

  **Recommended Agent Profile**:
  > - **Category**: `writing`
    - Reason: Configuration file update
  > - **Skills**: []
    - No specific skills needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Task 8)
  - **Blocks**: None
  - **Blocked By**: None

  **References**:

  **Pattern References** (existing code to follow):
  - `public/robots.txt` - Current minimal robots.txt

  **External References** (libraries and frameworks):
  - Google robots.txt guidelines: https://developers.google.com/search/docs/crawling-indexing/robots/robots_txt

  **Acceptance Criteria**:

  - [ ] robots.txt returns HTTP 200
  - [ ] Contains Sitemap directive pointing to fluxfitness.in/sitemap.xml
  - [ ] Disallows /admin, /filament, /api/
  - [ ] Allows frontend routes
  - [ ] Contains Crawl-delay: 1 for polite crawling

  **QA Scenarios**:

  ```
  Scenario: robots.txt accessible and valid
    Tool: Playwright
    Preconditions: None
    Steps:
      1. Navigate to https://fluxfitness.in/robots.txt
      2. Verify HTTP 200 response
      3. Check content matches expected directives
    Expected Result: Valid robots.txt with proper allow/disallow rules
    Failure Indicators: 404, syntax errors, missing sitemap
    Evidence: .sisyphus/evidence/task-7-robots-txt.txt

  Scenario: Test with Google Search Console robots.txt tester
    Tool: Playwright
    Preconditions: GSC access
    Steps:
      1. Open GSC robots.txt tester
      2. View current robots.txt
      3. Verify no blocked resources warnings
    Expected Result: No critical resources blocked
    Failure Indicators: Blocked CSS/JS, incorrect disallows
    Evidence: .sisyphus/evidence/task-7-gsc-check.png
  ```

  **Evidence to Capture**:
  - [ ] Full robots.txt content
  - [ ] GSC verification screenshot

  **Commit**: YES
  - Message: `feat(seo): enhance robots.txt with proper crawl directives`
  - Files: `public/robots.txt`

---

- [ ] 8. Verify Layout Rendering (app_new.blade.php)

  **What to do**:
  - Verify that the homepage actually uses `app_new.blade.php` layout
  - Check `HomeController` to confirm layout assignment
  - If using wrong layout, create note for correction
  - Verify old app.blade.php is not being used

  **Must NOT do**:
  - Don't change layout assignment unless necessary
  - Don't modify controller logic

  **Recommended Agent Profile**:
  > - **Category**: `quick`
    - Reason: Verification task only
  > - **Skills**: []
    - No specific skills needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Task 7)
  - **Blocks**: F1, F2
  - **Blocked By**: None

  **References**:

  **Pattern References** (existing code to follow):
  - `app/Livewire/Frontend/HomeController.php` - Livewire component to check
  - `resources/views/livewire/frontend/home-controller.blade.php` - Template file

  **Acceptance Criteria**:

  - [ ] Homepage uses app_new.blade.php (with SEO meta tags)
  - [ ] Old app.blade.php with "Laravel" title is NOT used
  - [ ] All SEO meta tags render in HTML head

  **QA Scenarios**:

  ```
  Scenario: Homepage uses correct layout with SEO tags
    Tool: Playwright
    Preconditions: Homepage loaded
    Steps:
      1. Navigate to homepage
      2. View page source
      3. Search for 'All-in-one Gym Management Software'
      4. Verify this is the title (not "Laravel")
      5. Check for og:description meta tag
    Expected Result: Title is "All-in-one Gym Management Software | Fluxfitness", OG tags present
    Failure Indicators: Title shows "Laravel", wrong layout loaded
    Evidence: .sisyphus/evidence/task-8-layout-check.html
  ```

  **Evidence to Capture**:
  - [ ] Page source showing correct title and meta tags

  **Commit**: NO (observation only, fix if needed)

---

## Final Verification Wave

- [ ] F1. **JSON-LD Validation** — `unspecified-high`
  Validate all added schemas with Google Rich Results Test. Check each schema type: SoftwareApplication, BreadcrumbList. Verify no duplicate schemas, no JSON syntax errors. Use Playwright to fetch pages and inspect head content.
  Output: `SoftwareApplication [VALID/INVALID] | BreadcrumbList [VALID/INVALID] | Errors [N] | VERDICT`

- [ ] F2. **PageSpeed Improvements** — `playwright`
  Re-run PageSpeed Insights on https://fluxfitness.in. Compare LCP, CLS, INP scores. Target: LCP <2.5s, CLS <0.1, INP <200ms. Verify 77% → 90%+ improvement.
  Output: `LCP [score] | CLS [score] | INP [score] | Overall [percentage] | VERDICT`

---

## Commit Strategy

- **1**: `feat(seo): add SoftwareApplication JSON-LD schema for rich snippets`
- **2**: `feat(seo): add BreadcrumbList JSON-LD schema for enhanced SERP`
- **3**: `feat(seo): add hreflang tags for international SEO`
- **4**: `perf(seo): add image dimensions to fix CLS issues`
- **5**: `perf(seo): add image preloading for LCP improvement`
- **6**: `feat(seo): add og-home.jpg for social media sharing`
- **7**: `feat(seo): enhance robots.txt with proper crawl directives`
- **8**: No commit (observation only)

---

## Success Criteria

### Verification Commands
```bash
# JSON-LD validation
# Visit: https://search.google.com/test/rich-results
# Enter URL: https://fluxfitness.in

# PageSpeed Insights
# Visit: https://pagespeed.web.dev
# Enter URL: https://fluxfitness.in
# Target: 90%+ performance score
```

### Final Checklist
- [ ] All JSON-LD schemas valid and present
- [ ] Image dimensions added to all feature images
- [ ] LCP improved (hero image preloaded)
- [ ] CLS improved (explicit dimensions)
- [ ] robots.txt has proper directives
- [ ] OG image exists and accessible
- [ ] hreflang tags present
- [ ] No "Laravel" title in production

---

## Decisions Needed

1. **OG Image**: Should I create a placeholder/generate one, or will you provide the image?
2. **LocalBusiness Schema**: Do you want to add local SEO schema for India targeting?
3. **Review Mode**: Standard review or high-accuracy Momus review?

---

*Plan Generated: March 20, 2026*
*Estimated Duration: 3-4 hours*
*Parallel Execution: YES (max 3 concurrent tasks)*
