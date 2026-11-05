# Changelog

All notable changes to the **Gym Management System** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- **Native `OPERLOG` Ingestion & Auxiliary Tables Support**:
  - Implemented `AdmsController::handleOperlog()` to handle device-native operational/audit events pushed to `/iclock/cdata?table=OPERLOG`.
  - Added support for auxiliary push tables (`USERINFO`, `BIODATA`, `FINGERTMP`, `OPTIONS`) in `AdmsController::cdata()`, acknowledging records with `200 OK` and logging payloads.
  - Acknowledges `OPERLOG` with `200 OK` and updates device heartbeat, eliminating the 5-second infinite HTTP 400 retry loop on physical terminals.
  - See [ADR-0010](docs/adr/adr-0010-biometric-adms-operlog-handling-and-clean-wire-token-standardization.md).
- **ZKTeco Push Protocol Handshake Options Negotiation**:
  - Enhanced `AdmsController::handleHandshake()` to respond to `options=all` queries with the official ZKTeco configuration block (`GET OPTION FROM: {SN}`) containing `TransFlag` with `EnrollUser` and `ChgUser` directives, `ServerVer`, and `PushOptionsFlag`.
  - Enables bidirectional terminal synchronization and remote user display name storage.

### Fixed
- **Biometric ADMS USERINFO Dual-Token Casing & Name Synchronization**:
  - Enhanced `AdmsDeviceCommand::userCommandString()` to emit dual attribute tokens under the `USERINFO` wire schema: `C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tNAME={name}\tPri=0\tPrivilege=0`.
  - Fixes blank LCD display names on eSSL MB20 and Linux/ZEM terminals (`ZLM60_TFT`) that strictly require uppercase `NAME=`, while preserving mixed-case `Name=` and `Pri=0` for standard ZKTeco firmware.
  - Confirmed via live hardware telemetry that eSSL MB20 drops mixed-case `Name=` and clears previously stored names if `NAME=` is absent.
  - Safely omits trailing `Passwd=` and `Card=` to avoid clearing existing terminal credentials.
  - Confirmed via hardware telemetry that eSSL MB20 strictly rejects lowercase `user` table schema (`return_code: -1004`), mandating `USERINFO` schema format.
  - See [ADR-0010](docs/adr/adr-0010-biometric-adms-operlog-handling-and-clean-wire-token-standardization.md).


---

## [v1.8.10] - 2026-11-04 (Current GitHub Release)

### Breaking Changes
- **ADMS Communication Key Enforcement Is Now Opt-In (`ADMS_REQUIRE_COMM_KEY=false`)**:
  - `config('biometrics.adms.require_comm_key')` now defaults to `false` (previously `true`). Terminals without a provisioned communication key authenticate by hardware Serial Number (`SN`) instead of being rejected.
  - Set `ADMS_REQUIRE_COMM_KEY=true` in `.env` to retain server-wide enforcement; every unkeyed terminal is then refused with `ERR: Unauthorized`.
  - Device registration no longer requires an ADMS communication key. Leave the field blank for terminals that do not send `pushcommkey` (e.g. eSSL MB20, K90).
  - See [ADR-0008](docs/adr/adr-0008-biometric-adms-per-device-comm-key-negotiation.md).

### Added
- **Member Archival & Physical Access Revocation Lifecycle**:
  - Introduced dedicated Spatie permissions `archive_gym_member` and `unarchive_gym_member` gated to gym owners and managers (migration `2026_10_13_000000_create_gym_member_archive_permissions.php`).
  - Added `MemberArchiveAuthorization` asserting permissions on explicit, authenticated operators without ambient guard fallbacks to prevent unattributable worker mutations.
  - Implemented `MemberArchiveService` with pessimistic row locking (`lockForUpdate`) as the single source of truth for member archive and unarchive state transitions.
  - Updated `GymMember::canAccessGym()` to evaluate `is_archived` before membership or payment validity, immediately denying door access and reporting `member_archived` in `MembershipGuard`.
  - Added marketing consent snapshotting (`whatsapp_marketing_snapshot`): forces `allow_whatsapp_marketing` to false while archived and faithfully restores it on unarchive. Importers preserve this invariant via `recordConsentWhileArchived()`.
  - Added `GuardsArchivedOwner` trait on child relation managers (`PaymentsRelationManager`, `MeasurementsRelationManager`, `BiometricTemplatesRelationManager`) enforcing read-only banners and server-side mutation blocks on archived members.
  - Introduced console command `app:archive-inactive-gym-members` (`--years=2`, `--dry-run`) executing chunked, transaction-isolated automated archival sweeps.
  - Added Filament single and bulk actions on `GymMemberResource` for archiving and unarchiving members with confirmation dialogs and outcome notifications.
  - Introduced opt-in `notArchived()` query scope, deliberately avoiding global Eloquent scopes to preserve revenue reporting, invoice history, and lifetime value calculations.
- **Cryptographic Biometric Deprovisioning & Erasure Hardening**:
  - Implemented synchronous credential revocation in `MemberArchiveService` cascading to `BiometricDeProvisioner` to queue hardware `DATA DELETE user` commands and erase cloud templates immediately upon archival.
  - Enforced grant-side biometric protection: `GymMember::assertMayHoldBiometricCredential()` and row-locked checks in `BiometricEnrollController` reject enrollment for archived members with `MemberArchivedException`.
  - Added `is_erasure_obligation` column to `biometric_templates` (`2026_10_16_000000_add_erasure_obligation_to_biometric_templates_table.php`) to separate orphan markers and cleanup obligations from real enrollments, preventing accidental superseding or false audit entries.
  - Introduced time-based cloud erasure cooldowns (`erasure_first_attempted_at`) and retry counters (`erasure_attempts`, `erasure_requested_at`) via migrations `2026_10_17_000000` and `2026_10_17_000100` to prevent infinite worker loops while tolerating temporary provider outages.
  - Added terminal sync status `BiometricTemplateSyncStatusEnum::DeletionUnresolvable` for corrupted provider references or exhausted cooldowns.
  - Added monotonic hardware credential epoch tracking via `epoch_credential_user_add_id` on `adms_device_commands` (`2026_10_18_000000_add_epoch_credential_to_adms_device_commands_table.php`), allowing re-enrolled members' deletions to be safely re-driven without getting stuck at attempt caps.
  - Added `BiometricOperatorAttentionWidget` to Filament Admin dashboard and "Needs Operator" filter with "Retry Erasure" modal action on `BiometricTemplatesRelationManager`.
- **Fail-Closed Biometric Webhook Authentication**:
  - Enforced mandatory HMAC-SHA256 signature verification (`X-Hub-Signature-256`, `X-Signature`) in `BiometricWebhookController`. Devices lacking a configured `webhook_secret` are rejected with HTTP 401, closing the URL token bearer loophole.
  - Added console command `biometrics:provision-webhook-secrets` (`--gym=`, `--dry-run`, `--reveal`, `--replace-unreadable`) to generate secure 64-character hex secrets for all unprovisioned devices. A device whose `provider_config` cannot be decrypted, or whose stored secret is not a string, is skipped and reported (non-zero exit) instead of having its recoverable ciphertext replaced; `--replace-unreadable` is the explicit opt-in to discard it. The Generate Webhook Secret table action now uses the one-time reveal modal (with a clipboard fallback) instead of clipboard-only delivery on HTTP/LAN panels.
- **Biometric Device ADMS Communication Key Hardware Standardization**:
  - Replaced 32-character hexadecimal generation with 6-digit numeric generation (`BiometricDevice::generateCommKey()`) using `random_int(100000, 999999)`, guaranteeing universal compatibility with eSSL (MB-20, KM-20, K90) and ZKTeco physical keypad limits (maximum 6 characters).
  - Constrained generated values to $100000$–$999999$ to avoid leading zeros, eliminating firmware integer-casting bugs that truncate leading zeros on entry-level terminals.
  - Implemented centralized sanitization helper `BiometricDevice::sanitizeCommKey()` covering ASCII/Unicode whitespace, Unicode format characters (zero-width joiners/spaces, soft hyphen, word joiner, BOM), and NUL bytes; invalid UTF-8 normalizes to empty.
  - Integrated sanitization into `BiometricDevice::verifyCommKey()` with a one-release raw-key fallback (`ADMS_ALLOW_LEGACY_UNSANITIZED_COMM_KEY`) that re-hashes the sanitized form on a successful raw match. The fallback only matches when the terminal presents the exact raw form or a matching plaintext exists; a sanitized-only presentation of a raw-only verifier is rejected and must be rotated. The ADR and feature guide document this exact-raw contract and the `Rotate Comm Key` recovery path.
  - Updated the `BiometricDeviceResource` form field with a 20-character paste buffer, live on-blur cleanup, dehydration normalization, and a shared model policy: exactly 6 alphanumeric characters, no numeric leading zero. Page hooks revalidate through the same policy so bypassing the form cannot persist an overlong or trivially short key. Legacy keys of other lengths remain verifiable; new keys are exactly six.
  - Updated `CreateBiometricDevice` and `EditBiometricDevice` to sanitize before PBKDF2 hashing and install the verifier in the same INSERT/UPDATE as the rest of the record, so a second write cannot leave a device without a verifier. The generated key and device API token are published to a short-lived non-session reveal cache only after Filament's transaction commits; a delivery copy keeps the modal readable across table re-renders, and cache-write failures are surfaced for rotation. A routine blank save no longer deletes legacy plaintext; the edit page preserves an unreadable `provider_config` ciphertext unless the operator explicitly confirms replacement. Native/null rotations remove `comm_key` with an audit.
  - Added self-describing PBKDF2 verifiers (`pbkdf2-sha256:{iterations}:{hash}`). Changing `ADMS_PBKDF2_ITERATIONS` invalidates only unversioned legacy hashes; those verify against the configured count and the historical 100,000 default. Unversioned hashes created with any other old count cannot authenticate, but the count cannot be detected from the digest, so a salted unversioned 64-hex hash is reported as `Configured (unversioned)` and is not auto-selected; rotate it explicitly. Versioned hashes outside 100,000–1,000,000 and corrupt salted verifiers are selected by `--reprovision-legacy`. Uppercase-hex verifiers compare case-insensitively. The verification floor is the fixed 100,000 constant; a testing-only runtime override is ignored outside the testing environment.
  - Added a per-device failed-attempt lockout (`ADMS_AUTH_MAX_FAILURES`, default 5; `ADMS_AUTH_LOCKOUT_SECONDS`, default 900) with a uniform `ERR: Unauthorized` response. The lockout is checked **before** key verification, so it caps online guesses; a locked-out terminal is rejected even with the correct key until the cooldown expires or an operator runs `biometrics:reset-adms-lockout`. This deliberately trades a known-serial denial of service for a bounded guessing window (documented in ADR-0005 NEG-002). Rotation and re-provisioning clear the state; cache failures degrade to a logged fail-open instead of a 500. Missing-key requests perform dummy PBKDF2 work to reduce timing leakage. Insecure-deployment warnings are emitted once per process instead of on every request.
  - Hardened the `biometric-api` limiter with IPv6 `/64` keying (IPv4-mapped addresses normalized), added production boot warnings for disabled throttling/key enforcement/low iterations/non-shared cache stores, and redacted error reports at every layer: `pushcommkey`/`comm_key` from URLs, headers, input, markdown, exception messages, stack traces, and the exception session payload; Livewire `components[].snapshot` and the dotted `updates` map are dropped from stored request data; the error-report edit page hydrates only `status`, `priority`, and `error_type`, so `user_description`, error message, markdown, trace, context, and origin URL are never serialized into the public form state or `wire:snapshot`, and a status-only save cannot overwrite the stored report. `redactText()` now preserves line structure, matches credential and biometric-PII names (so `Error:`/`Exception:`/`data:` diagnostics survive), handles `Basic`/`Digest`, PHP `print_r`/`var_dump`/`$_GET` forms, human labels and whitespace/comma/semicolon/slash-separated labels (bounded to the immediate token, so `token expired` and `token=abc123 user=bob` survive correctly), YAML block scalars, hyphenated/CamelCase header names, array suffixes, percent-encoded pairs (up to three layers), bare escaped JSON objects, JSON documents (without corrupting comparison operators or object/array shape, numeric-key objects, `1.0` fractions, or 20-digit integers), PHP serialized arrays (valid `unserialize()` output with correct lengths and idempotent second pass; malformed lengths fail closed), and legacy `verifyCommKey('...')`/authenticator trace arguments; it redacts 48/64-hex bearer tokens, scans linearly with a cheap candidate prefilter, caps its input and URL parameter count, and fails closed on a PCRE error or malformed/truncated escaped snapshot. Base64/compression-encoded blobs are explicitly out of scope and are not decoded. Denied historical headers and allow-listed header values are re-redacted at display time, and both copy actions use a clipboard fallback for HTTP/LAN panels. Removed the query string and the webhook path token from nginx access logs. `provider_config` now uses a fail-closed encrypted-array cast: a corrupt or unreadable payload returns an actionable "Unreadable" state (and a 401 on auth) instead of a 500, and every edit path preserves the ciphertext unless the operator explicitly replaces it; the edit page hydrates only non-secret provider fields, with blank secret inputs meaning "keep the stored value".
  - Hardened the redactor against JSON documents whose only sensitive key is in the structural key set (`data`, `fingerprint`, `user_id`, `member_id`), six-character alphanumeric and hyphen/grouped comm keys, `string(6)`/`s:6:` values, alphabetic or dash-suffixed URL parameter names, malformed serialized container counts, `pwd`/`passwd`, bare `Basic`/`Digest`, URL userinfo, YAML explicit-indentation and zero-indent block bodies, and bare/encoded separators. The digit-group scanner is atomic, so a 12 KB value no longer returns `[REDACTION FAILED]`; mixed and double-encoded webhook paths are redacted in application output and the nginx access-log map.
  - Split the backfill command into `--provision-missing` and `--reprovision-legacy` (the historical `--provision` flag is no longer accepted; use `--provision-missing` for unkeyed devices and `--reprovision-legacy` for verifier rotation, both with `--reveal`), validated `--reveal` before any mutation, emitted each generated key immediately after its transaction committed (with `try/finally` and per-device failure handling), made refusals/ambiguities/mismatches return a failing exit code, and moved destructive plaintext removal behind the opt-in `--clean-plaintext` flag. Cleanup proves the sanitized key against the stored verifier without the raw fallback, re-hashes raw-only verifiers first, tracks removed vs retained aliases honestly (including a cloud provider's protected `comm_key`), deduplicates refused device ids, wraps each save, and dry-run previews the count. `--reprovision-legacy` includes whitespace-only, unsafe/out-of-range, and corrupt salted verifiers (salted unversioned 64-hex hashes are treated as recognized), refuses ambiguous aliases before rotating, removes superseded non-empty aliases, and combined flags target each device once. Corrupt `provider_config` rows are reported and skipped per row, activity-log failures are separated from provisioning failures, and every write locks the row and refuses to overwrite a concurrent rotation, provider-type/`adms_enabled` change, or unrelated edit by comparing `provider_type`, `adms_enabled`, and `updated_at` as well as hash/salt/ciphertext. Cleanup recomputes the removable aliases from the locked row, so a native→cloud switch retains the protected `comm_key`.
  - Serialized every `provider_config` read-modify-write (edit page, Generate Webhook Secret, Rotate Comm Key, and provisioning) on one per-device cache lock, merged the edit form against the row re-read under the lock, invalidated the manual secret's reveal before releasing the lock, and made a failed `promote()` clear stale primary/delivery copies. The webhook-secret infolist badge now reports short, whitespace-only, or malformed legacy values as "Weak — regenerate" instead of "Configured".
  - Applied one provider-aware `comm_key` policy everywhere: `pushcommkey` is the ADMS alias for every provider; `comm_key` is an ADMS alias for device-native devices, including the legacy `provider_type = null` shape; cleanup deletes it for those rows with an activity-log audit; cloud-provider `comm_key` values are never read or deleted by ADMS code, including after a cloud→native switch (recorded with server-derived `comm_key_source = 'cloud'` on every Eloquent path; `ProviderConfigCast` strips new/changed submitted provenance from direct `update()`/`saveQuietly()`, re-adds a persisted cloud marker when an unchanged `comm_key` is rewritten without it, and refuses a changed credential without provenance, while raw query-builder/`DB::table()` writes remain an unenforced import boundary. A device-native `comm_key` ADMS alias is translated to `pushcommkey` when the provider leaves native mode, so a native→cloud→native round trip does not strand the only plaintext key.), and conflicting aliases are refused for review. Provider-type changes are blocked while the stored configuration is unreadable unless the operator explicitly replaces it.
  - Updated hardware terminal setup guide card (`adms-setup-card.blade.php`) step 7 to describe exactly six characters for numeric and alphanumeric keys, and to show the configured/not-configured state instead of implying the key can be read back.
- **Biometric ADMS Multi-Device Table Schema Negotiation & Wire Protocol Standardization**:
  - Resolved hardware return code `-1004` ("The data and equipment configuration are inconsistent") on standard eSSL and ZKTeco attendance devices running `pushver=2.4.1` by standardizing the default wire format to `USERINFO` in `AdmsDeviceCommand::toAdmsCommandString()`.
  - Added support for both `USERINFO` (`PIN=`) and lowercase `user` (`Pin=`) wire schemas with delimiter handling against protocol injection: `Name` control characters are replaced with spaces, while a `Pin` containing a control character rejects the command (an identity field is never repaired into another PIN). `Passwd`/`Card` were removed from the serializer because no producer ever wrote them.
  - Implemented multi-device per-terminal schema isolation stored inside `provider_config['adms_user_table']`, resolving wire formats with fallback order: device override → global `ADMS_USER_TABLE` → default `USERINFO`.
  - Snapshotted the resolved schema on every queued command (`adms_device_commands.user_table`), so flipping the per-device selector cannot rewrite a command already in flight, and deleted terminals keep the schema their commands were sent under.
  - Normalized `ADMS_USER_TABLE` at the configuration boundary: a blank, misspelled or unknown value falls back to `USERINFO` and is reported by the production boot guard. It is never coerced to the legacy `user` schema.
  - Added an administrative selector in `BiometricDeviceResource` allowing gym owners to configure `ADMS User Table Format` (`USERINFO` vs `user`) per terminal. Leaving it blank inherits the global default instead of writing a permanent per-device override.
  - Updated `EditBiometricDevice` form lifecycle to safely hydrate and preserve `adms_user_table` within `provider_config` across edits.
  - Updated `AdmsProtocolSanitizerTest` to assert wire formatting, per-device snapshotting and the schema-independent commands under both schemas.
  - Published [ADR-0007: Biometric ADMS Multi-Device Table Schema Negotiation and Wire Protocol Standardization](docs/adr/adr-0007-biometric-adms-multi-device-table-schema-negotiation.md).
- **Biometric ADMS Per-Device Communication Key Negotiation & Unkeyed Legacy Hardware Support**:
  - Removed mandatory comm key requirement from `BiometricDeviceResource` form creation and edition, allowing operators to register budget/legacy devices that do not support ADMS `pushcommkey` (e.g., eSSL MB20, K90) without entering dummy credentials.
  - Implemented strict per-device authentication in `AdmsDeviceAuthenticator`: devices with a configured key (`hasCommKeyConfigured() === true`) strictly require a matching `pushcommkey` and enforce brute-force lockout on mismatch/omission; devices without a configured key (`hasCommKeyConfigured() === false`) authenticate seamlessly via hardware Serial Number (`device_serial`) and active device status.
  - Updated auto-recovery in `AuthenticateAdmsDevice` middleware so inactive unkeyed devices automatically recover to `Active` status upon receiving verified serial communication.
  - Published [ADR-0008: Biometric ADMS Per-Device Communication Key Negotiation and Unkeyed Legacy Hardware Support](docs/adr/adr-0008-biometric-adms-per-device-comm-key-negotiation.md).
  - Added a **Remove Comm Key** row action so an existing keyed terminal can be converted to serial-only authentication in place: it clears the PBKDF2 verifier and the ADMS aliases, drops a stale cloud provenance marker (keeping it while a cloud-sourced `comm_key` survives), forgets any pending one-time reveal and resets the failure counter. It is refused while `ADMS_REQUIRE_COMM_KEY=true` and hidden when the device has no key, its ADMS protocol is disabled, or its `provider_config` is unreadable.
  - Moved the ADMS alias policy onto `BiometricDevice` (`removableCommKeyAliases()`, `stripCommKeyAliases()`) so the backfill, Rotate Comm Key and Remove Comm Key share one rule, and rotation now also drops a stale `comm_key_source` marker.
  - Unkeyed devices remain refused under global enforcement without accruing a lockout (the refusal is unconditional, so a counter would cap nothing), and each refusal is logged with the device id and a hashed serial. Dummy PBKDF2 work is still performed so response timing cannot reveal whether a serial is keyed.
  - An unreadable `provider_config` now fails closed: a device with no stored verifier but rotted ciphertext is treated as keyed and never degrades to serial-only authentication.
  - Hardened the communication key field to reject non-string Livewire state instead of normalising it to blank, and restored the inline explanation that a new key is mandatory when switching from device-native to a cloud provider.
- **Biometric Device Deletion with Enrolled Templates**:
  - Terminals can finally be deleted while member templates are enrolled. Deleting the device row cannot erase credentials held in the terminal's flash, so enrolled templates are parked at `DeletionUnresolvable` and flagged as erasure obligations instead of being reported as `Deleted`.
  - A null `enrolled_by_device_id` is no longer treated as proof of erasure: `BiometricDeProvisioner` distinguishes a row that provably never reached hardware from one whose terminal was removed, and only the former converges to `Deleted`.
  - The ADMS command ledger survives its terminal: `adms_device_commands.device_id` now nulls on delete and a `device_serial` snapshot is recorded, so the evidence needed to prove a `user_delete` was never sent is retained.
  - Attendance resolution no longer matches hardware templates orphaned by device deletion on unrelated terminals.
  - Device deletion now warns about the hardware consequence, writes an activity-log entry for every deletion, and enforces the gym tenant predicate in `BiometricDevicePolicy::delete()`, including a per-record check on the bulk-delete path.
  - The tombstone, the device delete, and the audit entry now share one transaction, so a failed delete cannot leave a live terminal whose credentials were already tombstoned. A row that provably never reached hardware (no confirmation, no delivered `user_add`) converges to `Deleted` instead of being parked.
  - Access-control history survives its terminal: `door_events.device_id` and `biometric_event_receipts.device_id` null on delete (with a `device_serial` snapshot on both tables) instead of cascading, and the delete modal states that consequence.
  - Added an audited **Confirm Physical Erasure** action that clears `is_erasure_obligation` after the gym has physically wiped a decommissioned terminal, so the compliance flag is no longer permanent. It is limited to hardware-backed rows with no bound terminal and to MANAGER+ (`dischargeBiometricErasure`): a cloud credential is erased through its provider, and a terminal that still exists is erased through the terminal. The discharge is a guarded update, so a repeated confirmation writes no second audit entry.
  - A dead-lettered hardware erase (delivery cap, or a PIN the ADMS wire cannot address) now parks the template at `DeletionUnresolvable` with the obligation flag, so it appears in "Needs Operator" instead of hiding with no automated or manual exit. A confirmed provider erase clears the flag.
  - Orphaned ledger rows (null `device_id`) are terminalised `CANCELLED`: `PENDING` and `SENT` rows are cancelled inside the device-deletion transaction (counted in the deletion audit), and `app:reclaim-stale-device-commands` cancels any that remain. Resend stays reachable on a `FAILED` orphan so it can explain the refusal, and the ledger shows the snapshotted terminal serial.
  - A `PENDING` command whose PIN cannot be addressed is quarantined as `FAILED` (`device_return_code = unaddressable_pin`) on first sight, instead of permanently blocking the oldest 50 lease candidates for every later command. The de-provisioner refuses to queue a `user_delete` for such a PIN and parks the template for a physical wipe.
  - Deletion now fails closed when the audit cannot join its transaction: `ACTIVITY_LOGGER_ENABLED=false` or a separate `ACTIVITY_LOGGER_DB_CONNECTION` refuses the delete, and a `deleting` listener that returns `false` rolls the tombstone and ledger cancellation back and reports failure.
  - A dead-lettered command keeps a `max_attempt_count` high-water mark, so an operator resend (which resets `attempt_count`) cannot make the row prunable and destroy the evidence; the reset is audited with the previous count and return code.
  - Deleting a gym is refused while it still has biometric terminals: the cascade would skip the tombstone and delete the access log and erasure register with it. Retire each terminal through Device Management first.
  - Blanket `is_erasure_obligation` rows always surface in "Needs Operator", whatever state the lifecycle converged them to.
  - The template-reference cache key carries the terminal scope plus a per-gym generation counter, so two terminals sharing a PIN no longer evict each other on every alternating punch.
- **Biometric ADMS Native Path & Legacy ASP.NET (.aspx) Compatibility**:
  - Registered native `/iclock/*` endpoints (`cdata`, `getrequest`, `devicecmd`) via Laravel 12 `withRouting(then: ...)` in `bootstrap/app.php` alongside existing `/api/iclock/*` routes, enabling compatibility with path-restricted standalone biometric hardware (eSSL MB-20, K90, SilkBio).
  - Added `.aspx` route aliases (`/cdata.aspx`, `/getrequest.aspx`, `/devicecmd.aspx`) across both root `/iclock/` and `/api/iclock/` route groups to accommodate legacy ASP.NET firmware proxies (`iClock Proxy/1.09`).
  - Added automated test assertions in `tests/Feature/AdmsProtocolTest.php` validating `cdata`, `getrequest`, and `devicecmd` operations both with and without `.aspx` extensions on non-prefixed paths.
  - Documented production Nginx port 80 bypass configuration allowing plain HTTP transport for ADMS endpoints while maintaining strict HTTPS redirects for all other traffic.
  - Published [ADR-0006: Biometric ADMS Legacy Hardware Endpoint, ASPX Routing, and Transport Compatibility](docs/adr/adr-0006-biometric-adms-legacy-hardware-endpoint-and-transport-compatibility.md).
- **Architecture Documentation**:
  - Published [ADR-0004: Member Archival Lifecycle, Cryptographic Biometric Credential Erasure, and Access Revocation Architecture](docs/adr/adr-0004-member-archival-and-biometric-erasure-lifecycle.md).
  - Published [ADR-0005: Biometric ADMS Communication Key Hardware Standardization and Sanitization Architecture](docs/adr/adr-0005-biometric-adms-communication-key-hardware-standardization.md).
  - Published [ADR-0006: Biometric ADMS Legacy Hardware Endpoint, ASPX Routing, and Transport Compatibility](docs/adr/adr-0006-biometric-adms-legacy-hardware-endpoint-and-transport-compatibility.md).
  - Published [ADR-0007: Biometric ADMS Multi-Device Table Schema Negotiation and Wire Protocol Standardization](docs/adr/adr-0007-biometric-adms-multi-device-table-schema-negotiation.md).
  - Published [ADR-0008: Biometric ADMS Per-Device Communication Key Negotiation and Unkeyed Legacy Hardware Support](docs/adr/adr-0008-biometric-adms-per-device-comm-key-negotiation.md).
  - Published [ADR-0009: Biometric ADMS USERINFO Dual-Token Name and Attribute Standardization](docs/adr/adr-0009-biometric-adms-userinfo-dual-token-name-standardization.md).
  - Created [Biometric Templates Field Reference](docs/biometric/database/biometric-templates-fields.md).
  - Updated field references for [Gym Members](docs/biometric/database/gym-members-fields.md), [ADMS Device Commands](docs/biometric/database/adms-device-commands-fields.md), and [Biometric Devices](docs/biometric/database/biometric-devices-fields.md).

### Changed
- **Docker PHP Runtime Upgrade**:
  - Upgraded Docker base container image from PHP 8.2 (`php:8.2-fpm`) to PHP 8.3 (`php:8.3-fpm`) in [`Dockerfile`](Dockerfile).
- **Subscription Renewal Schedule Registration (`routes/console.php`)**:
  - Updated `Schedule::job()` registration to pass the `SubscriptionRenewalJob::class` string rather than instantiating the job (`new SubscriptionRenewalJob()`), deferring instantiation to scheduled run time and preventing unnecessary overhead during console kernel and route bootstrapping.

### Fixed
- **eSSL MB20 Biometric User Name Display Blank on Device**:
  - Resolved an issue where member names remained completely blank on eSSL MB20 physical terminal LCD screens despite the device returning execution code `Return=0` (Success) on `user_add` commands.
  - Standardized dual name tokens (`Name=%s\tNAME=%s`) and dual privilege tokens (`Pri=0\tPrivilege=0`) for the `USERINFO` wire schema in `AdmsDeviceCommand::toAdmsCommandString()`, ensuring compatibility with uppercase C tokenizers on Linux/ZEM-based firmware while preserving backward compatibility with mixed-case parsers.
  - Added case-insensitive name payload extraction (`$payload['Name'] ?? $payload['name'] ?? $payload['NAME'] ?? ''`) in `AdmsDeviceCommand::serializedName()`.
  - Enforced single trailing newline (`\n`) on all command responses returned by `AdmsController::getRequest()` to meet line-oriented socket parsing requirements on embedded terminals.
  - Added test coverage in `AdmsProtocolSanitizerTest` (dual token assertions, case-insensitive payload aliases, and delimiter sanitization) and `AdmsProtocolTest` (trailing newline verification).
- **Queue Serialization in Subscription Renewal (`SubscriptionRenewalJob`)**:
  - Resolved `RazorpayService` lazily on demand within `processPayment()` rather than resolving and assigning it to an instance property in `__construct()`. This prevents queue worker serialization errors and stale service state when dispatching `SubscriptionRenewalJob` to queued drivers (e.g. Redis).
- **Razorpay Configuration Resilience (`config/services.php`)**:
  - Hardened Razorpay environment variable mapping (`key_id`, `key_secret`, and `webhook_secret`) with explicit `(string)` casting and empty string fallbacks (`(string) env('...', '')`), avoiding null values and strict typing runtime errors when configuration is evaluated without configured secrets.

## [v1.8.0] - 2026-10-11

### Breaking Changes
- **Enforce ADMS Device Communication Key by Default (`ADMS_REQUIRE_COMM_KEY=true`)**:
  - `config('biometrics.adms.require_comm_key')` now defaults to `true` (previously `false`). All unkeyed deployments will reject unauthenticated ADMS requests until provisioned.
  - PBKDF2 iterations are now configurable via `config('biometrics.adms.pbkdf2_iterations')` (default 100,000).
  - Backfill command (`biometrics:backfill-adms-comm-keys`) now detects and re-provisions legacy unsalted SHA-256 hashes when run with `--provision`.
  - Removed orphaned `BIOMETRIC_FALLBACK_PIN` from `.env.example`.

### Fixed
- **Door Controller TLS SNI (`DoorControlService`)**:
  - Replaced IP-in-URL rewriting with `CURLOPT_RESOLVE` to prevent DNS rebinding TOCTOU without breaking TLS SNI certificate validation on HTTPS door controllers.
- **Member Biometric Enrollment (`EnrollBiometricAction`)**:
  - Unified enrollment into modal action `EnrollBiometricAction` on Gym Member resource, deprecating and retiring the standalone `EnrollMember` page.
  - Replaced invalid `user_info` command type with `user_add`.
  - Canonical `idempotency_key` format (`user_add:{gym_id}:{device_id}:{member_id}[:stage]:{template_id}`) with `UniqueConstraintViolationException` handling and failure notification to operator.
  - Sibling terminals in the same gym are automatically provisioned via `BiometricTemplatePropagator` with `Propagated` lifecycle status.
- **Command Cancellation & Idempotency (`AdmsDeviceCommand`)**:
  - Added `cancelInFlightFor()` static helper ensuring correct SQL NULL-key semantics across cancel blocks.
  - Added `markCancelled()` method that sets status to `CANCELLED` (for both `PENDING` and `SENT` commands) and clears `idempotency_key` to prevent collisions upon retry.
  - Added `cancelDeleteCommands()` static helper for batch cancellation across writers.
  - Preserved `idempotency_key` across command execution and failures in `markAcknowledged()`, enabling reconcilers to re-drive existing failed commands within retry caps.
  - Reset `attempt_count` to 0 on Filament command resend (both single and bulk actions).
- **Nightly Enrollment Reconciler (`ReconcileBiometricEnrollmentsCommand`)**:
  - Nightly scheduled reconciliation for active `Synced` and `Propagated` templates, queueing replacement `user_add` commands when hardware delivery commands are missing or failed, with safeguards for archived/expired members and live de-provisioning commands.
- **Durable Hardware Delivery Tracking (`BiometricTemplate`)**:
  - Added `hardware_confirmed_at` timestamp column and data migration to record when hardware acknowledges credential installation across active templates, preserving delivery evidence independently of command log pruning.
  - A hardware acknowledgement is terminal confirmation: `AdmsDeviceCommand::reconcileUserAdd()` now promotes a `Pending` placeholder to `Synced` and activates it, so the 7-day abandoned-placeholder sweep can no longer revoke a credential the terminal is holding.
- **Superseded & Pending Placeholder Cleanup (`CleanupSupersededTemplatesCommand`)**:
  - Validates `--days > 0` and `--pending-days > 0` to prevent accidental immediate retirement.
  - Chunks records with `chunkById(100)` for memory safety.
  - Retires `Superseded` rows with a **soft delete** rather than destroying them, satisfying OpenSpec §3's "kept for audit/retention"; a `superseded_reason` discriminator distinguishes a plain re-enrolment from an operator overriding a GDPR Art. 17 erasure request, and the override is written to the activity log.
  - Pending placeholders delivered to hardware are handed to `BiometricDeProvisioner`, which emits a `user_delete` and parks the row at `PendingDeletion`; the command summary now reports those separately instead of counting them as purged.
  - Placeholders that were never delivered are retired outright, because there is no hardware credential to erase.
- **Consent Gate on Command Resend (`AdmsDeviceCommandResource`)**:
  - `resend` and `resendSelected` now refuse to re-queue a `user_add`/`user_update` when the target member has no active `BiometricConsent` (ADR-003 / OpenSpec §4). `user_delete` is exempt so erasure remains possible after consent is revoked.
- **Permission Sync Dynamic Set (`SyncPermissionsCommand`)**:
  - Strict separation between Filament-discovered permissions and hardcoded dynamic defaults: `permissions:sync` applies discovered permissions by default (falling back to dynamic defaults only if discovery resolves empty). Added explicit `--include-dynamic` flag to opt into merging dynamic default permissions, with per-role change logging.
- **Webhook & Event Response Handling (`BiometricWebhookController`)**:
  - Added missing denial reasons (`no_active_check_in`, `already_checked_in`, `anti_passback`, `device_offline`, `untrusted_door_controller_url`).
  - Propagates `$doorResult->failureReason` in response and adjusts display message/LED to warning when check-in succeeds but door fails to unlock.
- **Test Suite & CI Hardening**:
  - Corrected `DB_PASSWORD` from `root` to `password` and forced `DB_HOST=127.0.0.1` in `phpunit.xml`.
  - Removed `environment: Testing` from CI workflows and reduced `lint.yml` permissions to `contents: read`.
  - Added pre-boot safety guard in `TestCase` and `WhatsAppSetupTestCase` before `parent::setUp()` triggers `migrate:fresh`.
  - Removed duplicate safety guard from `StaffPanelTestCase`.
  - Deleted unused `FreezesClock` trait and removed vestigial `phpstan:dtos` composer script.
  - Replaced blanket `2026_09_28_130000` migration with an intentional no-op log.

### Added
- **Biometric ADMS Push Protocol (eSSL, ZKTeco, Hikvision)**:
  - Added native ADMS (`iclock`) push endpoints: `/api/iclock/cdata` (handshake & `ATTLOG` attendance push), `/api/iclock/getrequest` (command queue polling), and `/api/iclock/devicecmd` (execution acknowledgments).
  - Added Hikvision ISAPI XML event push endpoint `/api/v1/biometric/hikvision/event` with device token authentication.
  - Dual-channel coexistence: terminals can simultaneously use the vendor webhook (`/api/v1/biometric/webhook/{token}`) for real-time door relay unlock directives, and ADMS for attendance logging & member provisioning.
  - Implemented `AdmsEventService` with automated check-out detection (treating check-ins as check-outs if an open session already exists today) and anti-passback locking.
  - Added `AdmsDeviceCommand` model, migration, factory, and `AdmsCommandStatusEnum` (`PENDING`, `SENT`, `ACKNOWLEDGED`, `FAILED`) implementing Filament's `HasLabel` and `HasColor`.
  - Added three-layer expired member deprovisioning via `CleanupExpiredDeviceMembers` scheduled daily at 03:00, calculating cutoff via: `membership_end` + `gym_grace_days` (default 7) + `adms_grace_days` (default 10). Automatically queues `DATA DELETE user` commands and deactivates biometric templates.
  - Filament Admin UI updates: added `device_serial` input, `adms_enabled` toggle with dynamic setup instructions, and table column/action in `BiometricDeviceResource`; added tenant-scoped `AdmsDeviceCommandResource` with retry/resend capabilities.
  - Comprehensive feature test suites in `tests/Feature/AdmsProtocolTest.php` (11 tests) and `tests/Feature/CleanupExpiredDeviceMembersTest.php` (5 tests).
  - Technical documentation in `docs/biometric/feature/biometric-adms-push-protocol.md`, `docs/biometric/database/adms-device-commands-fields.md`, and `docs/biometric/architecture/ADR-004-adms-push-protocol-and-lifecycle.md`.
- **Multi-Channel Biometric Consent Distribution (Email & WhatsApp)**:
  - Added on-demand consent request distribution in Filament Admin panel: `RequestBiometricConsentAction` header action on `ListGymMembers` (targeting all active unconsented members in the tenant) and table bulk action on `GymMemberResource` (targeting selected members).
  - Dynamic dual-gate channel selection modal: `Send via WhatsApp` toggle is conditionally rendered ONLY when `NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($gym)` passes (Gate 1: tenant WhatsApp notifications enabled and sender number active; Gate 2: approved `biometric_consent` template available).
  - Two-tier asynchronous queued fan-out pipeline: `FanOutBiometricConsentRequestsJob` chunks eligible members in batches of 200 on `config('queue.notifications_queue')`, dispatching per-recipient `SendBiometricConsentNotificationJob` workers to prevent HTTP 504 gateway timeouts on large member rosters.
  - Multi-channel delivery: delivers email via `BiometricConsentMail` and WhatsApp via `NotificationChannelResolver` / `WhatsAppChannel`, mapping variables (`{{1}}` member name, `{{2}}` gym name, `{{3}}` consent URL) with `WhatsAppVariableResolver`, wallet credit balance verification via `WhatsAppSendCharge`, and graceful text fallback in dev/sandbox environments.
  - Eligibility and idempotency verification: `BiometricConsentEligibility` pre-filters and double-checks members, skipping already-consented (`hasActiveBiometricConsent() === true`), archived, or opted-out members, and logging aggregated statistics via `BiometricConsentDispatchReport`.
  - Centralized signed URL factory: `BiometricConsentUrlFactory` generates tamper-proof 7-day signed routes (`consent.biometric.show`) with configurable expiry (`config('biometrics.consent_url_expiry_days')`).
  - Hardware-aware onboarding welcome notification: integrated `InteractsWithBiometricConsent` into `WelcomeNotification`, embedding a biometric attendance notice and 7-day signed consent link when the member's gym has active biometric hardware (`Gym::hasActiveBiometricDevice()`), and cleanly omitting it when no devices are deployed.
  - Added `NotificationType::BIOMETRIC_CONSENT` enum case.
  - Dynamic queue worker command `app:work-queues` (`WorkQueuesCommand`) to poll derived queues (`default`, `notifications`, `broadcast`) based on configuration without hardcoded values.
  - Tenant notification setting backfill migration: `2026_10_08_000000_backfill_biometric_consent_notification_setting` automatically sets `notification_biometric_consent = true` in `gym_settings.notifications` for all existing gyms.
  - Architectural Decision Record: documented architecture, trade-offs, and design decisions in `docs/adr/adr-0003-biometric-consent-distribution-and-dual-gate-architecture.md`.
  - Comprehensive feature test coverage: 90 tests covering dual-gate availability, Filament action authorization and modal schema, queued job execution, welcome email conditional display, worker queue derivation, and template lookup.

### Changed
- **Gym Settings & Notification Preferences (`GymSettings`)**:
  - Added null safety guards on tenant resolution (`getGym()`) across `mount()`, `getWhatsAppNotificationTab()`, `afterStateUpdated()`, `save()`, and `downloadInvoice()`.
  - Added `notification_biometric_consent` toggle in WhatsApp and Email notification setting tabs.
- **WhatsApp Template Resolver & Messaging Services (`WhatsAppVariableResolver`, `MetaMessagingService`, `TwilioMessagingService`)**:
  - Fixed array key handling in `WhatsAppVariableResolver` to safely support integer indices from decoded JSON templates and avoid runtime type errors.
  - Strict typing, explicit array shape docblocks, and narrowed error return types in `MetaMessagingService` and `TwilioMessagingService`.

### Removed
- **Legacy Single-Channel Consent & Welcome Mailables**:
  - Removed deprecated `SendBiometricConsentEmail` job (replaced by `SendBiometricConsentNotificationJob`).
  - Removed redundant `WelcomeMemberMail` and `welcome-member.blade.php` view, consolidating member onboarding into `WelcomeNotification`.

## [v1.7.2] - 2026-09-19 (Current GitHub Release)

### Added
- **Invoice PDF Engine & Modern Printable Views**:
  - Introduced shared printable styling component `resources/views/invoices/partials/styles.blade.php` with `DejaVu Sans` typography, CSS `@page` margins, brand styling, badge statuses, info cards, itemized billing tables, and summary sections.
  - Re-engineered `resources/views/invoices/default.blade.php` (for SaaS subscription invoices) and `resources/views/invoices/member.blade.php` (for gym member receipts) with clean modern layout, dynamic status badges (`Completed`, `Pending`, `Failed`), gym and member address rendering, payment method references, and gym owner contact details.
  - Added dedicated `invoices` filesystem disk in `config/filesystems.php` (configured with `driver: local`, `visibility: private`, `throw: true`) and updated `filesystems.invoice_disk` configuration.
  - Added `GymMember::memberResourceUrl(self|int $member, ?string $type = 'index'): string` helper to reliably generate Filament resource URLs for gym members across views.
  - Added dedicated test suite in `tests/Feature/Filament/Admin/Clusters/Reports/Pages/MemberReportsTest.php` covering report generation, authorization, tenant isolation, plan distribution, churn metrics, and cache lifecycle.
  - Added database migration `2026_09_18_185000_add_unique_to_invoice_number_columns.php` enforcing unique database constraints on `invoice_number` across both `payments` and `member_payments` tables.
- **Frontend User Profile Hub & Modular Architecture**:
  - Modularized the user profile view (`ProfileController` / `profile-controller.blade.php`) into 8 dedicated partial blade components: `_header.blade.php`, `_profile_card.blade.php`, `_subscription.blade.php`, `_plans.blade.php`, `_gyms.blade.php`, `_payments.blade.php`, `_qr_modal.blade.php`, and `_settings_modal.blade.php`.
  - Added standalone high-resolution SVG QR code generation (`getCheckInQrCodeSvg()`, `getCheckoutQrCodeSvg()`) on `Gym` model using cached SVG generation (`QrGenerator`), decoupling QR rendering from Filament panel dependencies.
  - Implemented timing-safe token verification methods `hasValidCheckInQrCodeToken()` and `hasValidCheckoutQrCodeToken()` on `Gym` model using `hash_equals()`.
  - Added dedicated factory state helpers `withCheckInQrCodeToken()` and `withCheckoutQrCodeToken()` in `GymFactory`.
  - Added comprehensive feature test suite in `tests/Feature/Frontend/ProfileTest.php` with 17 tests covering profile updates, password changes, checkout upgrades/renewals, invoice downloads, timing-safe QR verification, mass assignment guarding, and session management.
- **Request Demo Hub & High-Converting Landing Architecture**:
  - Dedicated configuration file `config/request-demo.php` encapsulating rate limiting rules (`REQUEST_DEMO_RATE_LIMIT_MAX`, `REQUEST_DEMO_RATE_LIMIT_DECAY`), notification overrides (`REQUEST_DEMO_FORCE_CONTACT_EMAIL`), DNS validation caching/TTL (`REQUEST_DEMO_DNS_CHECK_ENABLED`, `REQUEST_DEMO_DNS_CACHE_TTL`), dynamic WhatsApp messages, operator case study testimonials, and objection-handling FAQs.
  - Re-engineered `/request-demo` page (`request-demo.blade.php`) featuring a dark-themed SaaS layout, 20-minute walkthrough agenda breakdown, real-time phone and email validation, verified gym case study metrics, Alpine.js interactive FAQ accordion, and direct WhatsApp support CTA (`https://wa.me/...`).
  - Modernized `RequestDemo` Livewire component with typed properties, `whatsAppUrl` computed property, `resetForm()` lifecycle state reset, and isolated testimonial/FAQ retrieval methods.
  - Added dedicated test suite in `tests/Feature/HomeControllerTest.php` for isolated home controller rendering verification.
- **Public Landing Page Redesign & Instant Callback Hub**:
  - Re-architected public homepage (`HomeController` / `home-controller.blade.php`), navigation (`navbar.blade.php`), and footer (`footer.blade.php`) with modern SaaS styling, interactive feature showcases, ROI calculator, and FAQ sections.
  - Introduced `RequestCallbackAction` and `HomeController::requestCallback()` enabling instant lead capture with E.164 phone normalization, rate limiting (3 requests/hour per IP), and 15-minute idempotency deduplication into `RequestContact`.
  - Added `contact_whatsapp` config parameter in `config/app.php` (`CONTACT_WHATSAPP` env var) for direct WhatsApp customer support link resolution.
  - Added `Enterprise & Franchise` tier definition to `PlanSeeder`.
  - Added dedicated unit and feature test coverage in `RequestCallbackActionTest` and `HomeControllerTest`.
- **Member Membership Freeze & Plan Extension Engine**:
  - Introduced `MemberFreezeService` providing atomic freeze/unfreeze operations with optional membership expiration extension (`extend_membership`).
  - Added gym-level configurable threshold settings (`min_freeze_days` default 7, `max_freeze_days` default 60) in `GymSettings`.
  - Added automatic expiration date reconciliation on early, exact, or late unfreezes, capped by individual membership plan durations.
  - Implemented automatic synchronization of latest non-expired `MemberPayment` records using `updateQuietly()` to preserve report cache integrity.
  - Added row locking (`lockForUpdate()`) for concurrency safety and full activity audit logging on both freeze and unfreeze events.
  - Enhanced `CheckMembershipExpiry` command (`gym:check-membership-expiry`) with `--dry-run` preview capability and automated unfreezing for expired freeze periods.
  - Enhanced `GymMemberResource` table action `toggle_freeze` with duration options, plan extension toggle, role-based authorization gates (`SUPER_ADMIN`, `ADMIN`, `GYM_OWNER`, `MANAGER`, `RECEPTIONIST`), and contextual notifications.
  - Added comprehensive test suite in `GymMemberFreezeExtensionTest` with 27 unit/feature tests covering boundary conditions, proration logic, notifications, activity logs, and authorization.

### Changed
- **Invoice Generation & Authorization Hardening (`InvoiceService`)**:
  - Standardized invoice number generation to format `INV-{YYYYMMDD}-{S|M}{gym_id}-{key}` with 4-digit key padding and regex format validation.
  - Added `ensureInvoiceNumber(InvoiceablePayment $payment): string` return type for guaranteed format parity across invoice creators.
  - Implemented `eagerLoadRelations()` to pre-load relational attributes (`user`, `gym.address.city`, `gym.address.state`, `member.address.city`, `member.address.state`, `membershipPlan`) preventing N+1 queries during PDF generation.
  - Hardened authorization checks in `assertAccessible()` and `MemberInvoiceController::show()` to permit member user owners (`$payment->member->user_id === $authUser->id`) as well as tenant-scoped staff roles (`SUPER_ADMIN`, `GYM_OWNER`, `MANAGER`, `RECEPTIONIST`).
  - Removed `invoice_number` and `invoice_path` from `$fillable` arrays on both `Payment` and `MemberPayment` models to guard against mass assignment.
  - Updated `GymMemberImporter` to delegate invoice number generation directly to `resolve(InvoiceService::class)->ensureInvoiceNumber($payment)` and pass Carbon date instances directly in `beforeSave()`.
  - Added explicit return type hints and generics on `MemberReports::getFilteredMembersQuery()`, `getGrowthData()`, and `getPlanDistribution()`.
  - Updated `resources/views/filament/clusters/reports/pages/member-reports.blade.php` to render member links via `$member->memberResourceUrl($member, 'edit')`.
- **Frontend User Profile Controller Modernization & Subscription Upgrades**:
  - Re-engineered `ProfileController` with strict type hints, return types, PHPDoc blocks, and computed property caching.
  - Added prorated plan upgrade checkout session handling (`CheckoutSession::TYPE_UPGRADE`) when `subscriptions.proration_enabled` is true.
  - Added plan renewal checkout session support (`CheckoutSession::TYPE_RENEWAL`) resolving real-time active plan pricing.
  - Added secure invoice download endpoint (`downloadInvoice()`) delegating to `InvoiceService` with user-scoped payment authorization.
  - Optimized `User::subscription()` helper to reuse eager-loaded `activeSubscription` relation when available, eliminating redundant database queries.
  - Introduced `Payment::profileLatestPaymentsCacheKey()` helper and updated `PaymentObserver::clearCache()` for authenticated user payment caching consistency.
  - Registered `\Illuminate\Session\Middleware\AuthenticateSession` middleware in `bootstrap/app.php` to ensure session revocation across devices on password updates.
  - Deprecated legacy profile layout `resources/views/frontend/layouts/profile.blade.php` to standardize on `x-frontend.layouts.app_new`.
- **Gym Model Security Hardening**: Guarded sensitive QR code tokens (`qr_code_token`, `qr_code_checkout_token`) from mass assignment in `Gym` model.
- **Request Demo Resilience & Validation Hardening**:
  - Enhanced `ValidEmailDomain` rule with cached DNS lookups (`checkdnsrr` for MX or A records), configurable TTL, fail-open network exception handling, and a testable static closure resolver seam (`ValidEmailDomain::$resolver`).
  - Hardened `RequestDemoNotification` with queued retries (`$tries = 3`), exponential `$backoff = [60, 300, 900]`, constructor property promotion, and structured error logging on permanent dispatch failure.
  - Added `throttle:60,1` rate-limiting middleware to `/request-demo` route in `routes/web.php`.
  - Updated `bootstrap/app.php` reverse proxy configuration to dynamically parse `TRUSTED_PROXIES` environment variable (supporting wildcard `*`, comma-separated IP lists, and fallback to RFC1918 subnets).
  - Explicitly configured `APP_ENV=testing` in `phpunit.xml`.
- **Plan Pricing Caching & Model Touch Invalidation**:
  - Refactored `HomeController` to use Livewire `#[Computed(persist: true)]` for `plans` and `enterprisePlans` with isolated cache keys (`Plan::ALL_PLANS_FOR_HOME_CACHE_KEY`, `Plan::ALL_ENTERPRISE_PLANS_FOR_HOME_CACHE_KEY`).
  - Added `$touches = ['plan']` and `plan()` relationship to `PlanFeature` model to cascade timestamp updates and trigger `PlanObserver::clearCache()` on feature mutations.
  - Added `standard()` and `enterprise()` query scopes and modernized scope method signatures to `void` on `Plan` model.
- **Reverse Proxy & Header Trust Configuration**:
  - Configured `$middleware->trustProxies()` in `bootstrap/app.php` across RFC1918 private subnets and loopback addresses to accurately resolve client IP (`Request::getClientIp()`) and protocols behind Nginx/Docker.
  - Added `/health` proxy location block in `nginx.conf`.
- **Meta WABA Template Language Resolution**: Refactored `MetaTemplateService::resolveLanguage()` to delegate directly to `TemplateFieldMapper::sanitizeLanguage()`, preserving both 2-letter ISO language codes (e.g. `en`, `hi`, `es`) and full locale codes (`en_US`, `en_GB`) for WABA templates.
- **WhatsApp Setup Wizard Step Alignment & Slug Redirection**: Updated `EmbeddedSignupController` redirects (for OAuth callbacks, manual connection, and error states) to use semantic step slugs (`?setup_step=whats-app-business`) instead of hardcoded numeric step indices, decoupling controller navigation from wizard step renumbering.
- **WABA Wizard Step Schema Alignment**: Aligned `SetupWizardSchema` step constants and `HasSetupState` state machine accessibility checks so that WABA Billing Mode is Step 2 (`WABA_BILLING_MODE_STEP = 2`) and WABA Account Connection is Step 3 (`WABA_ACCOUNT_STEP = 3`), removing deprecated dead constants.
- **Embedded Signup Test Architecture**: Extracted `seedEmbeddedSignupSession()` helper to `WhatsAppSetupTestCase` to standardize session initialization across embedded signup controller tests.

### Fixed
- **Churn Risk Payment Status Enum Handling**:
  - Updated `ChurnRiskService::calculateRiskScore()` and `identifyRiskFactors()` to check against `PaymentStatus::FAILED` enum in addition to raw string values.
- **Gym Member Latest Payment Association**:
  - Removed redundant `where('gym_id', $this->gym_id)` constraint on `GymMember::latestPayment()` `hasOne` relationship to ensure accurate latest payment resolution.
- **Invoice Service Test Coverage**:
  - Expanded `tests/Feature/InvoiceServiceTest.php` with tests verifying member owner authorization, disk validation exceptions, invoice number uniqueness and format assertions, blade rendering, address inclusion, and queue job integration.
- **Request Demo Error Handling & Test Reliability**:
  - Updated `RequestDemo::submitRequest()` to rethrow `ValidationException` for native Livewire form error bag handling while logging unexpected runtime exceptions.
  - Refactored `RequestDemoTest` to verify DNS resolution pass/fail, rate limiter counting on both valid and invalid attempts, queued admin notifications, form reset state, and WhatsApp URL generation.
- **App Configuration & Migration Idempotency**:
  - Hardened `APP_PREVIOUS_KEYS` array parsing in `config/app.php` to prevent type errors when environment variable is unset or empty.
  - Added `Schema::hasColumn()` existence guard in `2026_05_10_000000_add_waba_status_synced_at_to_whatsapp_accounts_table.php` migration to prevent duplicate column addition errors on re-runs.
- **Meta API Test Mocking**: Added comprehensive `Http::fake()` coverage for Meta Graph API token exchange, debug token, phone numbers, and WABA details in `EmbeddedSignupControllerTest`, preventing live network calls and asserting asynchronous `ImportWhatsAppTemplatesJob` dispatching and session flash flags on manual connect.
- **WABA Language Resolution Test Coverage**: Added unit test coverage in `MetaTemplateServiceTest` verifying language resolution for 2-letter ISO codes (`en`, `hi`), full locale codes (`en_US`), and `null` fallbacks.
- **Setup State Step Comments**: Corrected inverted step-number comments in `HasSetupState::determineSetupStep()` and `HasSetupState::isStepAccessible()`.

---

## [v1.7.1] - 2026-09-09

### Added
- **Multi-Language WhatsApp Template Support**: Added `language` attribute support to `whatsapp_templates`, including `CreateTemplateData` and `UpdateTemplateData` DTOs, database migration, and provider synchronization.
- **WhatsApp Template Locale Sanitization**: Introduced `TemplateFieldMapper::sanitizeLanguage()` with ISO/BCP47 regex validation and provider-aware fallbacks (`MetaTemplateService::defaultTemplateLanguage()` / `TwilioTemplateService::TWILIO_DEFAULT_LANGUAGE`).

### Changed
- Updated `MetaMessagingService`, `MetaTemplateService`, `TwilioTemplateService`, and `SendBroadcastJob` to dynamically resolve and dispatch template messages using each template's configured language.

---

## [v1.7.0] - 2026-09-08

### Added
- **WhatsApp Broadcast Refunds**: Automated refunding of message credits for failed recipients or cancelled broadcast batches.
- **Member Marketing Snapshots**: Added WhatsApp marketing snapshots and archival status tracking to member records.
- **Twilio & Meta Failure Classifier**: Intelligent categorization of delivery errors to distinguish between invalid numbers, throttles, and template rejections.

### Changed
- Refactored `SendBroadcastJob` and `SendMessageChunkJob` to utilize high-throughput batching with exponential backoff and rate-limiting.
- Enhanced PT commission calculations with percentage-based salary allocations per member.

### Fixed
- Fixed strict type casting and nullability issues across WhatsApp broadcast queue listeners.
- Resolved race conditions during credit reservation and final reconciliation.

---

## [v1.6.0] - 2026-07-30

### Added
- **Dynamic Plan Pricing & Multi-Currency**: Introduced `PlanPrices` with flexible billing interval support and localized pricing.
- **SaaS Checkout Sessions**: Integrated Razorpay order checkout sessions for self-service gym subscription upgrades.
- **Member Payment Transactions Ledger**: Detailed transaction logs tracking discounts, tax breakdowns, and payment method reconciliations.
- **Lead Reminder Tracking**: Automated background reminders for unattended leads and stale follow-ups.

### Security
- Added unique gym-member constraint to enforce data isolation per tenant gym.
- Enhanced API token permission scoping for multi-gym owner accounts.

---

## [v1.5.0] - 2026-05-29

### Added
- **Meta Cloud API (WABA) Direct Integration**: Direct Embedded Signup flow for gym owners to link Meta Business Accounts without third-party brokers.
- **WhatsApp Phone Number Management**: Automated monthly phone number cost deductions and credit transaction logging.
- **Broadcast Queue Indexing**: Optimized database indexes for large-scale broadcast recipient lookups.

### Changed
- Unified WhatsApp provider interface supporting seamless runtime switching between Meta Cloud API and Twilio WhatsApp.
- Backfilled and normalized granular permissions across Manager and Staff roles.

---

## [v1.4.0] - 2026-04-17

### Added
- **Provider-Agnostic Biometric Hardware Integration**: Native engine supporting ZKTeco, Hikvision, and eSSL devices for fingerprint and facial recognition.
- **Biometric Consent Management**: Built-in consent capture workflow complying with DPDP and GDPR requirements before biometric enrollment.
- **Automated Door Access Control**: Real-time door event logging (`DoorEvents`) and automated entry authorization based on active membership status.
- **Dynamic Anti-Proxy QR Attendance**: Rotating QR tokens and kiosk scanning interface with geofencing validation.
- **Public CMS & Blog Engine**: SEO-ready blog posts, CMS pages, and meta tags managed from the SuperAdmin panel.

### Fixed
- Fixed offline attendance synchronization to resolve duplicate timestamps during hardware reconnection.

---

## [v1.3.0] - 2026-02-28

### Added
- **Financial Expense Management**: Expense logging, hierarchical categories, system default templates, and quarterly/monthly budget limits with threshold alerts.
- **Advanced Reporting & BI Analytics**:
  - Saved report filter presets and automated scheduled email reports.
  - Secure, time-expiring shareable report links.
  - Report KPI target goals with real-time progress indicators.
- **Member Body Measurements**: Historical body composition tracking (weight, body fat %, measurements) with trend charts.
- **Membership Freeze Flow**: Ability to temporarily pause member subscriptions without losing remaining active days.
- **Security Audit Logs**: Tracked user login activity, IP addresses, and user-agent metadata.

---

## [v1.2.0] - 2025-12-30

### Added
- **WhatsApp Marketing & Automation Suite**:
  - WhatsApp template builder with multi-variable placeholders.
  - Broadcast campaign scheduler with recipient filtering.
  - Prepaid WhatsApp credit wallet with package purchasing via Razorpay.
- **Automated Tax Invoicing**: Branded PDF invoice and payment receipt generation for gym memberships and SaaS subscriptions.
- **SaaS Subscription Provisioning**: Multi-tier subscription plans with automatic feature provisioning and grace periods.
- **Razorpay Payment Gateway**: Seamless online payment links, webhook verification, and automated signature validation.

---

## [v1.1.0] - 2025-09-15

### Added
- **Multi-Channel Lead Ingestion CRM**:
  - Automated webhooks for Facebook Lead Ads, Instagram Ads, IndiaMART, JustDial, and WhatsApp inquiries.
  - Interactive drag-and-drop Lead Kanban board.
  - Heuristic lead scoring and conversion tracking.
- **Multi-Channel Notification System**: Configurable SMS, WhatsApp, and Email notification templates with event logs.

---

## [v1.0.0] - 2025-05-18

### Added
- **Multi-Tenancy Foundation**: Tenant data isolation, gym onboarding, and scoped query filters.
- **Multi-Role Filament v3 Admin Architecture**: Dedicated panels for SuperAdmin, Gym Owner (Admin), Manager, Trainer, and Staff with Spatie RBAC.
- **Core Member & Trainer Management**: Member registration, profile data, emergency contacts, trainer allocation, and membership plan assignment.
- **Payment & Membership Tracking**: Manual payment recording, payment status tracking, and membership duration calculations.
- **Bulk Data Import & Export**: Excel/CSV tools for member and payment data migration.
- **Master Geographic Datasets**: Pre-populated datasets for Countries, States, and Cities.
- **Activity Logging & Error Diagnostics**: Spatie ActivityLog integration and centralized exception monitoring.
