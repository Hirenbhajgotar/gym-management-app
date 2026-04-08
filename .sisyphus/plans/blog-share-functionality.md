# Blog Share Functionality Implementation Plan

## TL;DR

> **Quick Summary**: Add working share and copy-link functionality to the blog detail page share section (lines 56-66) with Web Share API support and toast feedback.

> **Deliverables**:
> - Modified blog-detail.blade.php with functional share buttons
> - Share button uses Web Share API with clipboard fallback
> - Link button copies URL to clipboard with toast notification

> **Estimated Effort**: Quick (single file, 30 min)
> **Parallel Execution**: NO - single task
> **Critical Path**: Modify blade file → Add JavaScript → Verify

---

## Context

### Original Request
User asked to implement share article functionality in `resources/views/livewire/frontend/blog-detail.blade.php` at lines 56-66.

### Current State
The share section contains two buttons (share icon and link icon) that have no click handlers - they are purely visual. The buttons have hover effects and styling but don't do anything when clicked.

### Technical Context
- **Framework**: Laravel 12 with Livewire v3
- **Styling**: Tailwind CSS v4
- **Toast System**: Existing Toast Livewire component triggered via `dispatch('show-toast', type, message)`
- **JavaScript**: Alpine.js available via Livewire (inline @click handlers work)

---

## Work Objectives

### Core Objective
Make the share buttons functional:
1. **Share Button**: Opens native share dialog (Web Share API) if available, otherwise copies URL
2. **Link Button**: Copies current page URL to clipboard and shows inline toast notification

### Concrete Deliverables
- Modified `resources/views/livewire/frontend/blog-detail.blade.php` with working buttons
- Inline JavaScript for clipboard and share functionality
- Simple inline toast notification (created via DOM, no Livewire component needed)

### Definition of Done
- [ ] Share button opens native share dialog (desktop/mobile)
- [ ] Share button falls back to clipboard copy if Web Share API unavailable
- [ ] Link button copies current URL to clipboard
- [ ] Toast shows "Link copied!" after copy action
- [ ] Buttons retain their existing visual styling

### Must Have
- Web Share API support (native share on mobile)
- Clipboard fallback for desktop browsers
- Toast feedback on copy actions

### Must NOT Have
- Don't add social media direct links (keep it simple)
- Don't change the button visual design/colors
- Don't add any server-side changes (no new routes/controllers)

---

## Verification Strategy

### Test Approach
- Manual browser testing - verify click handlers work
- Check toast appears after clicking link button
- Test Web Share API on mobile (or use browser DevTools mobile emulation)

### QA Scenarios

**Scenario: Share button click**
- **Preconditions**: Page loaded, blog article visible
- **Steps**: Click share button
- **Expected Result**: Native share dialog opens (or URL copied if API unavailable)
- **Evidence**: Screenshot of share dialog or toast confirmation

**Scenario: Link button click**
- **Preconditions**: Page loaded
- **Steps**: Click link button
- **Expected Result**: URL copied to clipboard, toast shows "Link copied!"
- **Evidence**: Toast visible in screenshot

---

## Execution Strategy

This is a single-file modification with inline JavaScript. No server-side changes needed.

**Files to modify:**
- `resources/views/livewire/frontend/blog-detail.blade.php` (lines 56-66 share section)

**Implementation approach:**
1. Add `@click` handlers to existing buttons
2. Add inline JavaScript via Livewire's `@script` or plain `<script>` tag
3. Use `navigator.clipboard.writeText()` for copy functionality
4. Use `navigator.share()` for Web Share API with try/catch fallback

---

## Acceptance Criteria

- [ ] Button click handlers are added
- [ ] Share uses Web Share API with clipboard fallback
- [ ] Link button copies URL to clipboard
- [ ] Inline toast notification shows "Link copied!" after copy (DOM-based, not Livewire)
- [ ] Visual styling unchanged from original
- [ ] Works on both desktop and mobile browsers

---

## References

### Existing Toast Component
- File: `app/Livewire/Toast.php`
- Trigger: `dispatch('show-toast', 'success', 'Message here')` or `dispatch('show-toast', 'error', 'Message here')`
- Usage: Place toast component in layout (check if already present)

### Clipboard API
```javascript
navigator.clipboard.writeText(url).then(() => {
    // Success
}).catch(err => {
    // Fallback for older browsers
});
```

### Web Share API
```javascript
if (navigator.share) {
    navigator.share({
        title: document.title,
        url: window.location.href
    }).catch(() => {
        // User cancelled or error - fallback to clipboard
    });
} else {
    // Web Share not available - use clipboard
}
```

### Note on Toast
The blog layout (`resources/views/frontend/layouts/blog.blade.php`) does NOT include the Toast Livewire component. Using a simple JavaScript inline toast (created via DOM manipulation) instead of Livewire's Toast to avoid requiring layout changes.