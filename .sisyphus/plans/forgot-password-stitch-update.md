# Forgot Password Page - Stitch Design Update

## TL;DR

> **Quick Summary**: Update forgot-password.blade.php to match the Stitch "Forgot Password - FluxFitness" design while using login.blade.php's primary blue color scheme instead of tertiary green.
> 
> **Deliverables**:
> - Updated forgot-password.blade.php matching Stitch layout
> - Primary blue branding and accents (from login.blade.php)
> - All existing functionality preserved
> 
> **Estimated Effort**: Small
> **Parallel Execution**: NO - sequential
> **Critical Path**: Single file update

---

## Context

### Original Request
Update `@resources/views/livewire/auth/forgot-password.blade.php` to match Stitch project "Forgot Password - FluxFitness" design exactly, but use login.blade.php's color schema instead of Stitch's.

### Design Sources
- **Target Design**: Stitch project "Forgot Password - FluxFitness" (9262817033829664561)
- **Color Source**: `@resources/views/livewire/auth/login.blade.php`

### Color Schema Differences
| Element | Stitch (Original) | Login (Target) |
|---------|------------------|----------------|
| Branding/Logo | `tertiary` (green) | `primary` (blue) |
| Lock Icon | `tertiary` | `primary` |
| Input Focus | `tertiary` | `primary` |
| Success Message | `tertiary` | `primary` |
| Geometric Symbol | `tertiary` | `primary` |
| CTA Button | Keep as `tertiary` | Keep as `tertiary` |

---

## Work Objectives

### Core Objective
Update forgot-password.blade.php layout to match Stitch design exactly, using login.blade.php's primary blue color scheme.

### Concrete Deliverables
- `resources/views/livewire/auth/forgot-password.blade.php` - Updated design

### Definition of Done
- [ ] Page renders without errors
- [ ] Form functionality works (send password reset link)
- [ ] Visual layout matches Stitch design
- [ ] Color scheme matches login.blade.php (primary blue branding)

### Must Have
- All PHP/Livewire functionality preserved
- Primary blue branding (from login.blade.php)
- Stitch's layout structure
- Geometric power symbol on right panel
- Glass card badges for stats

### Must NOT Have
- Tertiary green branding (should be primary blue)
- Broken form functionality

---

## Verification Strategy

### QA Policy
- **Frontend/UI**: Playwright verification
- Check page renders at `/forgot-password`
- Verify branding uses primary blue
- Verify form input and submission work
- Screenshot comparison with Stitch design

---

## TODOs

- [ ] 1. Update forgot-password.blade.php design

  **What to do**:
  - Replace entire Blade template with Stitch design structure
  - Apply login.blade.php color scheme:
    - Branding header: `from-primary to-primary-dim` gradient, `text-primary` for FluxFitness text
    - Lock icon: `bg-primary/20` glow, `border-primary/20`, `text-primary`
    - Input focus: `focus:ring-primary/50`, `focus:border-primary`
    - Success message: `bg-primary/10`, `border-primary/30`, `text-primary`
    - Geometric SVG symbol: Change all `text-tertiary` to `text-primary` in SVG
    - Glass badges: First badge uses `bg-primary/20`, `text-primary` (matches Stitch's "Community" card)
  - Keep `tertiary` for CTA button (consistent with login page)
  - Preserve all PHP/Livewire logic at top of file

  **Must NOT do**:
  - Change PHP logic
  - Change validation rules
  - Modify route handling

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: [`tailwindcss-development`, `volt-development`]

  **References**:
  - `resources/views/livewire/auth/login.blade.php` - Color scheme reference
  - Stitch project "Forgot Password - FluxFitness" - Layout structure

  **QA Scenarios**:

  \`\`\`
  Scenario: Page renders with correct branding colors
    Tool: Playwright
    Preconditions: Clean browser session
    Steps:
      1. Navigate to /forgot-password
      2. Wait for page load
      3. Screenshot page
    Expected Result: Branding shows blue (primary) colors, not green (tertiary)
    Evidence: .sisyphus/evidence/forgot-password-branding-colors.png

  Scenario: Form functionality works
    Tool: Playwright
    Preconditions: Valid email in database
    Steps:
      1. Navigate to /forgot-password
      2. Enter email "test@example.com"
      3. Click "Send Reset Link"
      4. Wait for success message
    Expected Result: Success message appears, no errors
    Evidence: .sisyphus/evidence/forgot-password-form-submit.png
  \`\`\`

---

## Final Verification Wave

- [ ] F1. **Visual Verification** — Screenshot and compare with Stitch design
- [ ] F2. **Functionality Test** — Submit form with valid email
- [ ] F3. **Color Scheme Check** — Confirm primary blue branding instead of tertiary green

---

## Success Criteria

### Verification Commands
```bash
# Check page renders
php artisan route:list | grep forgot-password
```

### Final Checklist
- [ ] Branding uses primary blue (not tertiary green)
- [ ] Layout matches Stitch design
- [ ] Form submits and shows success message
- [ ] Geometric symbol uses primary blue
- [ ] All glass badges present with correct colors
