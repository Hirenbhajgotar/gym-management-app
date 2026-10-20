---
table: gym_members
last_updated: 2026-04-03
author: docs-agent
related_features:
  - ../feature/biometric-checkin-flow.md
  - ../feature/biometric-consent-system.md
---

# `gym_members` — Field Reference

## Purpose

Stores gym members/participants. This is the primary entity for biometric operations (NOT the `User` model). All attendance records and biometric templates reference this table.

## Field Reference

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO | auto | — | Primary key |
| `gym_id` | bigint unsigned | NO | — | Member creation | Foreign key to gyms |
| `name` | varchar(255) | NO | — | Member creation | Member full name |
| `profile_photo` | varchar(255) | YES | null | Auto (AvatarService) | URL to profile photo |
| `email` | varchar(255) | NO | — | Member creation | Email for consent emails |
| `phone` | varchar(20) | YES | null | Member creation | Phone number |
| `weight` | float | YES | null | Member creation/update | Weight in kg |
| `height` | float | YES | null | Member creation/update | Height in cm |
| `age` | int | YES | null | Member creation/update | Age in years |
| `dob` | date | YES | null | Member creation | Date of birth |
| `membership_start` | date | YES | null | Member creation/update | Membership start date |
| `membership_end` | date | YES | null | Member creation/update | Membership expiry date |
| `payment_status` | enum | YES | null | Payment processing | PaymentStatus enum (COMPLETED, PENDING, FAILED, etc.) |
| `gym_membership_plan_id` | bigint unsigned | YES | null | Member creation/update | FK to membership plan |
| `trainer_id` | bigint unsigned | YES | null | Member assignment | FK to trainers |
| `membership_plan_id` | bigint unsigned | YES | null | Member assignment | FK to membership plans |
| `user_id` | bigint unsigned | YES | null | Member creation | FK to users (optional login) |
| `is_frozen` | tinyint(1) | NO | 0 | freeze()/unfreeze() | Freeze status |
| `frozen_at` | timestamp | YES | null | freeze() | When frozen |
| `frozen_until` | timestamp | YES | null | freeze() | Auto-unfreeze date |
| `frozen_reason` | text | YES | null | freeze() | Reason for freeze |
| `frozen_by` | bigint unsigned | YES | null | freeze() | User who froze |
| `is_archived` | tinyint(1) | NO | 0 | MemberArchiveService | Whether member is archived (access revoked) |
| `archived_at` | timestamp | YES | null | MemberArchiveService | Timestamp when member was archived |
| `allow_whatsapp_marketing` | tinyint(1) | NO | 1 | toggleWhatsappMarketing() | WhatsApp marketing consent (forced false while archived) |
| `whatsapp_marketing_snapshot` | tinyint(1) | YES | null | MemberArchiveService | Preserved marketing consent value during archival |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Creation timestamp |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Update timestamp |

## Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `PRIMARY` | `id` | PRIMARY | — |
| `gym_members_gym_id_foreign` | `gym_id` | INDEX | Multi-tenant queries |
| `gym_members_email_unique` | `email` | UNIQUE | Email lookups |
| `gym_members_membership_end_index` | `membership_end` | INDEX | Expiry queries |

## Relationships

| Relationship | Type | Foreign Key | Related Model |
|---|---|---|---|
| `gym()` | belongsTo | `gym_id` | `Gym` |
| `attendances()` | morphMany | `attendanceable_id`, `attendanceable_type` | `Attendances` |
| `biometricTemplates()` | hasMany | `gym_member_id` | `BiometricTemplate` |
| `biometricConsents()` | hasMany | `gym_member_id` | `BiometricConsent` |
| `currentAttendance()` | hasOne | — | `Attendances` (scoped to active) |

## Usage by Feature

| Feature | Reads | Writes | Notes |
|---|---|---|---|
| [Biometric Check-in Flow](../feature/biometric-checkin-flow.md) | All fields | — | Uses canAccessGym(), isFrozen(), isCurrentlyCheckedIn() |
| [Biometric Consent System](../feature/biometric-consent-system.md) | `email`, `gym` | — | Triggers consent email on creation |
| [Member Archival Lifecycle](../../adr/adr-0004-member-archival-and-biometric-erasure-lifecycle.md) | `is_archived`, `archived_at`, `allow_whatsapp_marketing` | `is_archived`, `archived_at`, `allow_whatsapp_marketing`, `whatsapp_marketing_snapshot` | Handled by MemberArchiveService & MemberArchiveAuthorization |
| [Door Access Control](../feature/door-access-control.md) | `is_archived` | — | Referenced in door events, denied if is_archived = true |

## Key Methods & Scopes

| Method / Scope | Purpose |
|---|---|
| `canAccessGym()` | Returns bool — evaluates `is_archived` FIRST; then checks membership_end, payment_status, is_frozen |
| `mayHoldBiometricCredential()` | Returns bool — true only when `! $this->is_archived` |
| `assertMayHoldBiometricCredential()` | Throws `MemberArchivedException` if member is archived |
| `archive(?User $operator)` | Explicitly gated archival via `MemberArchiveService` |
| `unarchive(?User $operator)` | Explicitly gated restoration via `MemberArchiveService` (restores marketing snapshot) |
| `toggleWhatsappMarketing(bool $allowed)` | Writes consent; if archived, routes through `recordConsentWhileArchived()` |
| `isFrozen()` | Returns bool — checks is_frozen and auto-unfreezes if frozen_until passed |
| `isCurrentlyCheckedIn()` | Returns bool — checks for active attendance today |
| `currentAttendance()` | Returns active attendance or null |
| `scopeNotArchived(Builder $query)` | Opt-in query scope to filter out archived members without breaking relationships |
| `biometricTemplates()` | HasMany relationship to BiometricTemplate |
| `biometricConsents()` | HasMany relationship to BiometricConsent |
| `activeBiometricConsent()` | Returns active consent or null |

## Migration History

| Migration | Change |
|---|---|
| `2024_xx_xx_create_gym_members_table` | Initial table creation |
| `2026_03_23_120002_create_biometric_consents_table` | Added FK constraints (biometric_consents references gym_members) |
| `2026_10_13_000000_create_gym_member_archive_permissions` | Added permissions and role grants for member archive/unarchive |

## Notes

- **NOT** the same as `User` model — gym members may not have login access
- Biometric operations use this model, not User
- Consent system uses email from this table
- Archiving is an access revocation; archived members cannot hold live biometric credentials and cannot check in. Child relations are mutation-frozen via `GuardsArchivedOwner`.
