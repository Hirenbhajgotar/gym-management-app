# Biometric Templates Order & Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Gym Member biometric templates relation manager list newest records first and add a header action that reloads only that table's records.

**Architecture:** Two contained edits to `table()` in `BiometricTemplatesRelationManager`: a `defaultSort('created_at', 'desc')` and a `Refresh` header action that triggers a Livewire re-render of the relation manager. Every re-render re-queries this component's table, so no extra query, service, or state reset is needed. Tests live in the existing PHPUnit feature test that already drives this relation manager.

**Tech Stack:** PHP 8.2, Laravel 12, Filament 3 (`Filament\Tables\Actions\Action`), Livewire 3, PHPUnit 11.

**Spec:** Approved in-conversation short design (Approach A, 2026-11-17): descending order by `created_at`; Refresh reloads only this table's records via a component re-render; no device/provider re-sync, no poll, no notification, no changes to other tables or row actions. Kept out of `docs/superpowers/specs/` per AGENTS.md documentation rule.

## Global Constraints

- Never run destructive database commands against `gym_app` during testing; use `--env=testing` / composer test scripts only.
- Artisan commands go through `docker-compose exec app` per AGENTS.md.
- `declare(strict_types=1);` is already present in both files — keep it.
- Explicit return types on all new methods; curly braces even for single-line bodies.
- PHPUnit classes only (no Pest). Use `RefreshDatabase` and the existing `TestCase` setup in `BiometricEnrollmentSyncTest`.
- Run `vendor/bin/pint --dirty --format agent` inside the container before finalizing.

## Review Focus

- **Pending placeholders (`enrolled_at = null`) must still sort correctly** — they are the newest records and would be misplaced by any `enrolled_at` sort. Pinned in Task 1's test with a `pending()` factory row.
- **Refresh must not clear table state** (active filter, sort, search) — a `resetTable()` shortcut would silently discard it. Pinned in Task 2's test by setting a filter before the refresh call and asserting it survives.
- **Refresh must actually re-query** — a no-op handler that gets `#[Renderless]` or `skipRender()` would leave stale rows on screen. Pinned in Task 2's test by inserting a record after the component mounts and asserting it appears after the action.
- **Archived owner** — this manager is read-only for archived members but reads stay open; Refresh must remain visible and must not trip `GuardsArchivedOwner`. Pinned in Task 2's test by archiving the member.
- **No create/edit action regression** — the existing `test_relation_manager_has_no_create_or_edit_action` asserts an empty header; it must be updated, not deleted. Pinned in Task 2.

---

### Task 1: Default descending ordering by `created_at`

**Files:**
- Modify: `app/Filament/Admin/Resources/GymMemberResource/RelationManagers/BiometricTemplatesRelationManager.php` (end of `table()` chain, currently `->headerActions([])` at the final line)
- Test: `tests/Feature/BiometricEnrollmentSyncTest.php`

**Interfaces:**
- Consumes: existing `BiometricTemplate::factory()` with `pending()` state; existing `Livewire::test(BiometricTemplatesRelationManager::class, ['ownerRecord' => $this->member, 'pageClass' => EditGymMember::class])` setup used throughout that test file.
- Produces: table rows ordered newest-first by `created_at`; `assertCanSeeTableRecords([...], inOrder: true)` must pass for that order.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/BiometricEnrollmentSyncTest.php`:

```php
public function test_relation_manager_default_sorts_templates_by_created_at_descending(): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->owner);
    Filament::setTenant($this->gym);

    $oldest = BiometricTemplate::factory()->create([
        'gym_id' => $this->gym->id,
        'gym_member_id' => $this->member->id,
        'created_at' => now()->subDays(3),
    ]);
    $middle = BiometricTemplate::factory()->create([
        'gym_id' => $this->gym->id,
        'gym_member_id' => $this->member->id,
        'created_at' => now()->subDay(),
    ]);
    // Pending placeholders carry enrolled_at = null; they must still sort by created_at.
    $newest = BiometricTemplate::factory()->pending()->create([
        'gym_id' => $this->gym->id,
        'gym_member_id' => $this->member->id,
        'created_at' => now(),
    ]);

    Livewire::test(
        BiometricTemplatesRelationManager::class,
        ['ownerRecord' => $this->member, 'pageClass' => EditGymMember::class]
    )->assertCanSeeTableRecords([$newest, $middle, $oldest], inOrder: true);
}
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `docker-compose exec app php artisan test --compact --filter=test_relation_manager_default_sorts_templates_by_created_at_descending`
Expected: FAIL — rows are in insertion/ID order, and the pending row is not first.

- [ ] **Step 3: Add the default sort**

In `app/Filament/Admin/Resources/GymMemberResource/RelationManagers/BiometricTemplatesRelationManager.php`, append `->defaultSort('created_at', 'desc')` to the `table()` chain as its final method (after `->headerActions([])`). Do not add `->sortable()` to columns — the default sort must apply without changing the column configuration.

- [ ] **Step 4: Run the test to confirm it passes**

Run: `docker-compose exec app php artisan test --compact --filter=test_relation_manager_default_sorts_templates_by_created_at_descending`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Admin/Resources/GymMemberResource/RelationManagers/BiometricTemplatesRelationManager.php tests/Feature/BiometricEnrollmentSyncTest.php
git commit -m "feat: sort biometric templates newest first"
```

---

### Task 2: Table-scoped Refresh header action

**Files:**
- Modify: `app/Filament/Admin/Resources/GymMemberResource/RelationManagers/BiometricTemplatesRelationManager.php` (replace `->headerActions([])`)
- Test: `tests/Feature/BiometricEnrollmentSyncTest.php`

**Interfaces:**
- Consumes: `defaultSort('created_at', 'desc')` from Task 1; `Filament\Tables\Actions\Action` is already imported in the relation manager.
- Produces: a table action named `refresh`, visible as a gray `heroicon-m-arrow-path` button; calling it re-renders the relation manager and therefore re-queries its rows without touching filters/sort/search or calling any domain service.

- [ ] **Step 1: Write the failing tests and update the existing header assertion**

Add to `tests/Feature/BiometricEnrollmentSyncTest.php`:

```php
public function test_relation_manager_refresh_header_action_reloads_only_its_table_records(): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->owner);
    Filament::setTenant($this->gym);

    // Reads stay open for an archived owner; Refresh is inert and must remain available.
    $this->member->forceFill(['is_archived' => true, 'archived_at' => now()])->save();
    $this->member->refresh();

    $initial = BiometricTemplate::factory()->create([
        'gym_id' => $this->gym->id,
        'gym_member_id' => $this->member->id,
        'created_at' => now()->subDay(),
    ]);
    $inactive = BiometricTemplate::factory()->inactive()->create([
        'gym_id' => $this->gym->id,
        'gym_member_id' => $this->member->id,
        'created_at' => now(),
    ]);

    $component = Livewire::test(
        BiometricTemplatesRelationManager::class,
        ['ownerRecord' => $this->member, 'pageClass' => EditGymMember::class]
    )
        ->assertTableActionExists('refresh')
        ->assertTableActionVisible('refresh')
        ->filterTable('active_only')
        ->assertCanNotSeeTableRecords([$inactive]);

    // Written after mount: it is visible only if the action re-queries the table.
    $later = BiometricTemplate::factory()->create([
        'gym_id' => $this->gym->id,
        'gym_member_id' => $this->member->id,
        'created_at' => now(),
    ]);

    $component->callTableAction('refresh')
        ->assertCanSeeTableRecords([$later, $initial], inOrder: true)
        // The filter must survive the refresh; a resetTable() shortcut would drop it.
        ->assertCanNotSeeTableRecords([$inactive]);
}
```

In the existing `test_relation_manager_has_no_create_or_edit_action`, replace `$this->assertEmpty($table->getHeaderActions());` with:

```php
$this->assertSame(
    ['refresh'],
    collect($table->getHeaderActions())
        ->map(fn ($action): ?string => $action->getName())
        ->all()
);
```

Keep the existing `$this->assertNull($table->getAction('edit'));` assertion unchanged.

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `docker-compose exec app php artisan test --compact --filter='test_relation_manager_refresh_header_action_reloads_only_its_table_records|test_relation_manager_has_no_create_or_edit_action'`
Expected: FAIL — `refresh` action does not exist; header action list is empty.

- [ ] **Step 3: Implement the header action**

In `BiometricTemplatesRelationManager::table()`, replace `->headerActions([])` with:

```php
->headerActions([
    Action::make('refresh')
        ->label('Refresh')
        ->icon('heroicon-m-arrow-path')
        ->color('gray')
        // The action round-trip re-renders this Livewire component, and Filament
        // re-queries its table on every render; the dispatch documents the intent.
        ->action(fn () => $this->dispatch('$refresh')),
])
```

Leave `->defaultSort('created_at', 'desc')` as the final method in the chain. Do not call `resetTable()` (it would discard filters/sort/search) and do not add a notification.

- [ ] **Step 4: Run the tests to confirm they pass**

Run: `docker-compose exec app php artisan test --compact --filter='test_relation_manager_refresh_header_action_reloads_only_its_table_records|test_relation_manager_has_no_create_or_edit_action'`
Expected: PASS.

- [ ] **Step 5: Run the whole relation-manager test file and Pint**

Run: `docker-compose exec app php artisan test --compact tests/Feature/BiometricEnrollmentSyncTest.php`
Run: `docker-compose exec app vendor/bin/pint --dirty --format agent`
Expected: all tests in the file pass; Pint reports no remaining style issues.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Admin/Resources/GymMemberResource/RelationManagers/BiometricTemplatesRelationManager.php tests/Feature/BiometricEnrollmentSyncTest.php
git commit -m "feat: add table-scoped refresh action for biometric templates"
```
