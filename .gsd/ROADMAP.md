# ROADMAP.md

> **Current Milestone**: v2.0 — Member Archive System Refactor
> **Goal**: Refactor the member archiving system into proper Service → Action → Command architecture, with archived members hidden from "current roster" queries explicitly rather than globally.

## Must-Haves
- [x] `MemberArchiveService` — handles archive/unarchive/archivableMembers logic, and is the single writer of the WhatsApp-consent snapshot
- [x] `ArchiveInactiveMembersAction` — orchestrates the batch archive operation, per-chunk transactional, returning a typed result
- [x] `ArchiveInactiveMembersResult` — typed contract separating attempted / archived / skipped counts
- [x] Refactored `ArchiveInactiveGymMembersCommand` — thin command delegating to Action
- [x] `notArchived()` / `archived()` model scopes as the opt-in visibility API
- [x] `is_archived` enforced explicitly at the access-control decision points (`canAccessGym()`)
- [x] Archive lifecycle authorized by dedicated `archive` / `unarchive` permissions
- [x] Importers write the consent snapshot, so archiving by import is reversible
- [x] Comprehensive PHPUnit tests

## Phases

### Phase 1: Service & Action Layer
**Status**: ✅ Complete
**Objective**: Create `MemberArchiveService` and `ArchiveInactiveMembersAction`

### Phase 2: Archived-member visibility
**Status**: ✅ Complete — **approach changed after review**
**Objective**: Make "current roster" queries opt out of archived members without silently changing every other query in the application

A global `#[ScopedBy(NotArchivedScope::class)]` on `GymMember` was tried and **reverted**. Archiving is a visibility concern, not a data-retention one: the global scope also applied to relationship eager-loads, which silently broke revenue-export identity, inverted invoice ownership for archived members, under-counted lifetime value, made the Filament Archived tab uneditable, hid archived members from the importer's duplicate lookup, and skipped archived members in biometric de-provisioning — across 77 files, of which only two had been compensated. It was also not achieving its security goal, since access denial rested on the relation resolving to `null` rather than on `is_archived`.

Archived rows now remain resolvable by default; `notArchived()` marks the roster queries that must exclude them, and revocation is enforced directly in `canAccessGym()`.

### Phase 3: Command Refactor
**Status**: ✅ Complete
**Objective**: Refactor `ArchiveInactiveGymMembersCommand` to thin wrapper

### Phase 4: Tests & Verification
**Status**: ✅ Complete
**Objective**: PHPUnit coverage for service guards, eligibility filtering, batch counts, both visibility scopes, access denial, and import-time archiving

### Phase 5: Lint & Static Analysis
**Status**: ✅ Complete
**Objective**: Pint + PHPStan on all touched files, and the affected test suites green

### Phase 6: Review remediation
**Status**: ✅ Complete
**Objective**: Address the round-1 review of the global-scope changeset

### Phase 7: Round-2 review remediation
**Status**: ✅ Complete (2026-10-13)
**Objective**: Address the round-2 review of the archive changeset — the control layer, not the data layer

Round 2 made the right architectural call (the global scope stayed gone) but left the feature
inert on deploy and broke consent on the import path. This phase closed:

- **Permissions existed nowhere.** `archive_gym_member` / `unarchive_gym_member` were declared in
  `config/filament-permissions.php` but no migration or seeder created them, so `Gate` failed
  closed for every role including `gym_owner`. A migration now creates them and grants them to
  `super_admin`, `gym_owner` and `manager`, strictly additively.
- **Consent was destroyed by import.** `beforeSave()` read `is_archived` off the mutable import
  row, which Filament fills *before* the hook runs, and a mapped-but-blank `Is Archived` cell
  wrote `null`. The archive transition is now read from persisted state, both boolean columns
  ignore blank state, and the importer no longer forces opt-in or opt-out on revival.
- **The CSV importer could not revive anyone.** `Rule::unique` rejected the archived member's
  phone before processing, and there was no update branch. It now upserts, rejects only a genuine
  duplicate of an *active* member, and routes archiving through `MemberArchiveService`.
- **`skippedCount` could never move.** `archive()` read stale in-memory state and the eligible
  query already excluded archived rows. It now re-reads the row under `lockForUpdate()`, which
  makes the counter real and makes a double-archive impossible.
- **The gate test was red.** `setUp()` granted the permissions both via the role and directly, so
  revoking the role left the direct grant in place.

Also fixed: `--years` validation and `withoutOverlapping()`/`onOneServer()` on the nightly job,
the zero-eligible case returning before its only `Log::info`, counters mutated inside a
transaction, `member_archived` never being emitted by the biometric cache or mapped to a device
message, seven unguarded notification/widget queries, and relation managers allowing an archived
member's payments and biometric templates to be mutated.

**Verification**: `vendor/bin/pint --dirty` clean. The four archiving suites plus the two CSV
importer suites pass — 80 tests, 371 assertions — with the previously-red permission test green.

**Known pre-existing failures, unrelated to this phase** (both reproduced on a pristine tree with
this phase stashed): `GymMemberPaymentUpdateTest` passes `gym_id` to `User::factory()` although the
`users` table has no such column; and `SendMembershipExpiryAlertsCommandTest` fails in
`Tests\TestCase::tearDown()` because the test swaps the `Cache` facade for a partial Mockery mock
that has no `flush()`. Neither file is touched by this changeset.

---

## Milestone History

### [v1.0 — Biometric Consent Notification (Email & WhatsApp)](milestones/v1.0-SUMMARY.md)
**Status**: ✅ Complete (2026-10-11)
**Archive**: `.gsd/milestones/v1.0/`