# STATE.md — Project State

## Current Position
- **Milestone**: v2.0 — Member Archive System Refactor
- **Phase**: Phase 6 — Review remediation
- **Status**: Complete

## Completed
- [x] `MemberArchiveService` owns archive/unarchive/archivableMembers and the consent snapshot
- [x] `ArchiveInactiveMembersAction` orchestrates the batch, transactional per chunk
- [x] `ArchiveInactiveMembersResult` typed result contract
- [x] Model delegates archive/unarchive to the service
- [x] `ArchiveInactiveGymMembersCommand` reduced to a thin wrapper
- [x] `notArchived()` / `archived()` opt-in visibility scopes
- [x] `is_archived` enforced in `canAccessGym()` and reported by `MembershipGuard`
- [x] `archive` / `unarchive` permissions wired through config, policy and Filament actions
- [x] Both importers write `whatsapp_marketing_snapshot`
- [x] Round-1 review findings remediated; global `NotArchivedScope` reverted

## Notes
The global `#[ScopedBy]` approach was reverted — see ROADMAP.md Phase 2 for the reasoning and the consumer regressions it caused.