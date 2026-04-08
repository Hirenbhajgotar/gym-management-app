# Stitch Blog Page Implementation Plan

## TL;DR

> **Quick Summary**: Implement the FluxFitness Blog Listing page using the Stitch design from project `projects/16114337191860100902`. This is a frontend-only blog page with the "Kinetic Pulse" dark theme aesthetic.

> **Deliverables**:
> - Livewire Blog page at `/blog` route
> - Featured article section with hero image
> - Article cards grid with category tags
> - Search and filter functionality
> - Newsletter subscription section
> - Footer with links

> **Estimated Effort**: Short
> **Parallel Execution**: NO - sequential (small task)
> **Critical Path**: Create Livewire component → Add route → Verify page loads

---

## Context

### Original Request
User provided a Stitch MCP project (`projects/16114337191860100902`) containing a FluxFitness Blog Listing design. The design features:
- **Creative Theme**: "Kinetic Pulse" - high-end editorial performance aesthetic
- **Color Palette**: Deep charcoal (#0e0e0e) background with electric lime (#ccff00) accents
- **Typography**: Lexend (headlines) + Inter (body)
- **Key Elements**: Featured article, article grid, newsletter signup, footer

### Design Specifications (from designMd)
- **No-Line Rule**: No 1px borders - use background color shifts for sections
- **Glassmorphism**: Use for sticky navigation and floating elements (surface-variant at 60% opacity, 20px backdrop blur)
- **Surface Hierarchy**:
  - Base: `surface` (#0e0e0e)
  - Cards: `surface-container-low` (#131313)
  - Active: `surface-container-high` (#201f1f)
- **Primary Button**: `primary_container` (#cafd00) with `on_primary_container` (#4a5e00) text

---

## Work Objectives

### Core Objective
Create a frontend blog page that matches the Stitch design specifications exactly.

### Concrete Deliverables
- [ ] Livewire component: `app/Livewire/Frontend/BlogListing.php`
- [ ] Blade view: `resources/views/livewire/frontend/blog-listing.blade.php`
- [ ] Route added to `routes/web.php`
- [ ] Newsletter form submission handler

### Definition of Done
- [ ] Page loads at `/blog` route
- [ ] Featured article section displays correctly
- [ ] Article grid shows cards with images, categories, titles, authors
- [ ] Search bar present and functional
- [ ] Category filter tabs work
- [ ] Newsletter subscription form present
- [ ] Footer matches design

### Must Have
- Dark theme matching exact color specs
- Responsive layout (desktop/mobile)
- Glassmorphism navigation
- Category tags with proper colors

### Must NOT Have
- Solid 1px borders (use background shifts instead)
- Flat colors (use gradients where specified)
- White text for body (use on_surface_variant)

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: NO (frontend-only, no tests needed)
- **Automated tests**: None
- **QA Policy**: Agent-executed manual verification

### QA Scenarios
1. **Page Load**: Navigate to `/blog`, verify page renders without errors
2. **Visual Match**: Compare rendered output against design screenshot
3. **Responsive**: Test on mobile viewport (390px width)
4. **Navigation**: Test category filter clicks

---

## Execution Strategy

### Sequential Tasks
1. Create Livewire component with sample blog data
2. Create Blade view matching Stitch design
3. Add route to web.php
4. Verify page renders correctly

---

## TODOs

- [ ] 1. Create BlogListing Livewire Component

  **What to do**:
  - Create `app/Livewire/Frontend/BlogListing.php`
  - Add sample blog data (featured article + 8-10 article cards)
  - Include category data: Retention, Tech Stack, Marketing, Operations, Case Study, Finance, Growth, Strategy, Future Tech
  - Add search and category filter properties
  - Add newsletter form handling

  **References**:
  - `app/Livewire/Frontend/HomeController.php` - for component structure and conventions
  - `app/Livewire/Frontend/RequestDemo.php` - for additional patterns

- [ ] 2. Create Blog Listing Blade View

  **What to do**:
  - Create `resources/views/livewire/frontend/blog-listing.blade.php`
  - Match exact design from Stitch project:
    - Navigation with glassmorphism
    - Featured article section with hero image
    - Article cards grid with category tags
    - Search bar and category filter tabs
    - Newsletter subscription section
    - Footer with product/legal links
  - Use exact colors from design system
  - Apply "No-Line Rule" - background shifts not borders

  **References**:
  - HTML from Stitch project shows exact structure
  - Design system colors:
    - background: #0e0e0e
    - primary: #f3ffca
    - primary_container: #cafd00
    - surface_container_low: #131313
    - surface_container_high: #201f1f

- [ ] 3. Add Blog Route

  **What to do**:
  - Add route in `routes/web.php`
  - Route: GET `/blog` → BlogListing::class
  - Named route: `blog`

  **References**:
  - `routes/web.php` lines 23-25 - home route pattern

- [ ] 4. Verify Page

  **What to do**:
  - Run `php artisan route:clear`
  - Verify route is registered
  - Test page renders correctly

---

## Success Criteria

### Verification Commands
```bash
php artisan route:list --path=blog  # Should show /blog route
curl -I http://localhost/blog      # Should return 200
```

### Final Checklist
- [ ] Page loads at /blog
- [ ] Featured article section visible
- [ ] Article grid displays all cards
- [ ] Search bar present
- [ ] Category tabs work
- [ ] Newsletter form visible
- [ ] Footer matches design
