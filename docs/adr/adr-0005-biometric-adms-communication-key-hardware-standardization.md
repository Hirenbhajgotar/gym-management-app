---
title: "ADR-0005: Biometric ADMS Communication Key Hardware Standardization and Sanitization Architecture"
status: "Accepted"
date: "2026-10-20"
authors: "Lead Architect, Security Engineer, Hardware Integration Team"
tags: ["architecture", "decision", "biometrics", "adms", "hardware-integration", "security", "essl", "zkteco"]
supersedes: ""
superseded_by: ""
---

# ADR-0005: Biometric ADMS Communication Key Hardware Standardization and Sanitization Architecture

## Status

**Accepted**

## Context

In multi-tenant commercial gym operations, access control and attendance recording rely heavily on standalone biometric terminals (predominantly manufactured by eSSL, ZKTeco, and compatible OEM firmware, such as eSSL MB-20, KM-20, K90, and SilkBio). Communication between the physical devices and the central server occurs via the **ADMS (Automatic Data Master Server)** push protocol over HTTP.

To prevent unauthorized, unkeyed devices from pushing spoofed attendance logs (`ATTLOG`) or polling server-side command queues, the system enforces mutual communication key authentication using the `pushcommkey` parameter. Historically, the web management interface generated a 32-character hexadecimal key (`bin2hex(random_bytes(16))`) and validated raw input strings up to 64 characters.

However, physical deployment across gym facilities revealed severe real-world hardware incompatibilities:

1. **Hardware Firmware Field Constraints**:
   Standard eSSL and ZKTeco firmware menus under **Menu > Comm. > Cloud Server Setting > Push Comm Key** enforce a strict upper limit of **6 characters**. Attempting to configure a 32-character generated key was impossible on physical devices, as the hardware keypad dialog stops accepting keystrokes after 6 characters.
2. **Keypad Input Method Discrepancies**:
   Budget and entry-level biometric terminals (e.g., eSSL K90, e9c) only feature numeric 10-key keypads without T9 text entry support, allowing only digits `0–9`. While advanced touchscreen and hybrid terminals (e.g., eSSL MB-20, KM-20) allow toggling alphanumeric input, alphanumeric keys are cumbersome and error-prone to type on small terminal screens.
3. **Firmware Integer Parsing & Leading-Zero Truncation**:
   Certain ZKTeco/eSSL firmware revisions parse numeric comm keys internally as unsigned 32-bit integers rather than literal strings. If a key is generated with leading zeros (e.g., `048192`), the terminal firmware strips the leading zero and transmits `pushcommkey=48192`, causing PBKDF2 hash verification on the server to fail.
4. **Human Formatting & Whitespace Artifacts**:
   Gym operators frequently copy, paste, or read 6-digit codes separated by spaces (e.g., `123 456` or ` 123456 `) or from messaging applications. If an input field applies strict character truncation before whitespace sanitization, pasted text can be prematurely clipped or hashed with unwanted whitespace. Similarly, incoming device query strings or headers with accidental whitespace can lead to authentication failures.

A standardized, hardware-compatible architectural pattern was required to bridge high-entropy cryptographic storage on the server with strict 6-character hardware constraints on physical biometric terminals.

## Decision

We designed and implemented a hardware-standardized communication key lifecycle encompassing generation, input sanitization, boundary validation, and constant-time PBKDF2 verification:

### 1. Six-Digit Cryptographic Numeric Key Generation
- Replaced the 32-character hexadecimal generator with `BiometricDevice::generateCommKey()`.
- The generator produces a strictly 6-digit numeric string using `random_int(100000, 999999)`.
- **Zero Leading Zeroes**: Constraining the lower boundary to `100000` guarantees that keys never begin with `0`, neutralizing firmware integer-casting truncation across all terminal models.
- **Universal Hardware Compatibility**: Pure numeric keys are supported across 100% of physical terminals, from budget keypad-only devices to modern touchscreen units.

### 2. Centralized String Sanitization (`sanitizeCommKey`)
- Implemented `BiometricDevice::sanitizeCommKey(mixed $rawKey): string` as the centralized normalization pipeline.
- The pipeline removes ASCII and Unicode whitespace (`\s`, `\p{Z}`), Unicode format characters such as zero-width spaces/joiners, soft hyphens, word joiners, byte-order marks (`\p{Cf}`), and NUL bytes, and converts non-scalar or invalid-UTF-8 values to empty strings.
- Sanitization is applied across all mutation pathways (`CreateBiometricDevice`, `EditBiometricDevice`, `biometrics:backfill-adms-comm-keys`, and Filament form live updates) before PBKDF2 hashing occurs.

### 3. Whitespace-Tolerant Authentication Handshake
- Integrated `BiometricDevice::sanitizeCommKey()` directly into `BiometricDevice::verifyCommKey(string $plainKey)`.
- Incoming terminal requests transmitting `pushcommkey` via query parameters, request bodies, or HTTP headers (e.g., `pushcommkey= 123456 ` or `123 456`) are normalized before being passed to `hash_pbkdf2` and `hash_equals`.
- A verifier created before sanitization existed is still accepted through a raw-key fallback controlled by `ADMS_ALLOW_LEGACY_UNSANITIZED_COMM_KEY`. **The fallback only matches when the terminal presents the exact raw form that was hashed** (for example `" 123 456 "`), or when a matching plaintext copy exists and the backfill can re-hash the sanitized form. A terminal that presents only the sanitized form (`123456`) for a raw-only verifier cannot authenticate; that is a documented legacy edge, not a new regression, and the recovery path is a forced rotation. A fallback match is logged with the device id (never the key).

### 4. Resilient Form UX & Boundary Validation
- Configured the Filament `adms_comm_key` field with `maxLength(20)`, a client-side paste buffer well above the longest tolerated formatted representation (`1 2 3 4 5 6` is 11 characters), so the browser cannot clip a code before on-blur cleanup. Filament also adds a server-side `max:20` rule from this option; it is a paste guard, not the key-length policy.
- Added live on-blur state normalization (`afterStateUpdated`) to instantly clean formatted text in the UI.
- Enforced a shared server-side policy through `BiometricDevice::commKeyValidationError()`: **exactly 6** sanitized characters, letters and numbers only, and no all-numeric key starting with `0`. The Filament rule is a UX mirror; the Create/Edit page hooks revalidate through the same model method so bypassing the form cannot persist an overlong or trivially short key. A truncated, overlong, or weak paste fails loudly instead of being hashed.
- Legacy keys longer or shorter than six characters remain verifiable; the policy constrains only *new* keys written by the form, page hooks, or generation.
- Maintained manual entry support for six-character alphanumeric keys for terminals (such as KM-20) where custom alphanumeric identifiers are intentionally desired.

```
┌────────────────────────────────────────────────────────────────────────┐
│         ADMS COMM KEY GENERATION, SANITIZATION & AUTH FLOW             │
└────────────────────────────────────────────────────────────────────────┘

 [ Filament Admin Panel ]
         │
         ├── Click "Generate" ──► BiometricDevice::generateCommKey()
         │                         └─ random_int(100000, 999999) -> "749201"
         │
         ├── Manual Input ──────► Paste: " 749 201 " (maxLength: 20)
         │                         ├─ onBlur: live cleans to "749201"
         │                         └─ Validation: exactly 6 chars, alphanumeric, no leading zero
         ▼
 [ Create / Edit Mutation ]
         │
         ├── BiometricDevice::sanitizeCommKey($key) -> "749201"
         ├── BiometricDevice::generateCommKeySalt()  -> 16-byte random salt
         └── BiometricDevice::hashCommKey(key, salt)-> PBKDF2-SHA256 (versioned iterations)
         │
         ▼
 [ Database Storage ]
         └── Store: `adms_comm_key_hash` + `adms_comm_key_salt` (Plain key discarded)

──────────────────────────────────────────────────────────────────────────

 [ Hardware Terminal (eSSL MB-20 / KM-20) ]
         │
         └── Menu > Comm > Cloud Server > Push Comm Key: "749201"
         │
         ▼ HTTP Push Request
 [ Server: POST /api/iclock/cdata?SN=...&pushcommkey=749201 ]
         │
         ▼
 [ AuthenticateAdmsDevice Middleware ]
         │
         ├── $device = BiometricDevice::findBySerial($sn)
         └── $device->verifyCommKey($providedKey)
                 │
                 ├── BiometricDevice::sanitizeCommKey($providedKey)
                 └── hash_equals(adms_comm_key_hash, hash_pbkdf2(sanitized, salt))
                         │
                         ├── Match ────► HTTP 200 OK (Device Authorized)
                         └── Mismatch ─► HTTP 401 Unauthorized
```

## Rollout / Backward Compatibility

The deploy boundary is explicit:

- **Keys without whitespace** (including the common 32-character hex keys and all generated six-digit keys) verify exactly as before.
- **Keys whose stored verifier was created from a raw value containing whitespace** are accepted through the raw-key fallback for one release **only when the presented form matches the raw value exactly**, or when a matching plaintext copy lets the backfill re-hash the sanitized form. A terminal that presents only the sanitized form for a raw-only verifier is rejected by design; it must be rotated (`--reprovision-legacy` with a scoped device, the `Rotate Comm Key` table action, or the edit page). A successful fallback re-hashes the sanitized form with a fresh salt. The fallback is controlled by `ADMS_ALLOW_LEGACY_UNSANITIZED_COMM_KEY` (default `true`) and logs a redacted warning when used.
- **Legacy plaintext keys** in `provider_config['pushcommkey']` / `['comm_key']` continue to verify, and the stored side is normalized before comparison. `pushcommkey` is the ADMS alias for every provider. `comm_key` is an ADMS alias for device-native devices (`provider_type` is `device_native`) **and for the legacy `provider_type = null` shape**, which is treated as native everywhere. Cleanup deletes both aliases for those rows and records `removed_aliases` / `retained_aliases` plus the effective provider type in the activity log. A cloud provider's `comm_key` is a provider credential: it is never read or deleted by ADMS code, and the cleanup report counts it as retained by provider policy. A cloud→native switch records `provider_config['comm_key_source'] = 'cloud'`, so the preserved credential does not become a dormant ADMS verifier or get removed by a later rotation/re-provision. The marker is derived by the model on every Eloquent provider-type change, and `ProviderConfigCast` strips a new or changed marker from every Eloquent write, including direct `update()`/`saveQuietly()` and a native→cloud switch. When a writer rewrites an unchanged `comm_key` but omits the marker, the cast re-adds the persisted `comm_key_source = 'cloud'`; a changed `comm_key` without provenance is refused rather than silently dropping the marker. Raw query-builder (`Model::query()->update()`) and `DB::table()` writes bypass attribute casts; that boundary is unenforced, so imports must use Eloquent. A provider-type change while `provider_config` is unreadable is refused with `UnreadableProviderConfigException` unless the config is explicitly replaced. Refused device ids are deduplicated across collectors, and each migration/cleanup save is wrapped so one transient failure does not abort the run after earlier rows committed.
- **Backfill** hashes the sanitized form, reports keys whose sanitized form differs, and returns a non-zero exit code when any key is refused or ambiguous. `--reveal` is validated before any migration or cleanup. Destructive plaintext removal is opt-in via `--clean-plaintext`: before deleting a plaintext copy the command proves the sanitized key against the stored verifier without the raw fallback, re-hashing a raw-only verifier first. Dry-run previews the cleanup count. Run it before disabling the fallback. Each migration, cleanup, and provisioning write locks the row and verifies the hash, salt, stored provider ciphertext, `provider_type`, `adms_enabled`, and `updated_at` still match the snapshot used for the decision; a concurrent rotation, provider-policy change, or unrelated edit wins and is reported as a non-zero conflict instead of being overwritten. Cleanup recomputes the removable aliases and `mayDeleteCommKeyAlias()` from the locked row, so a native→cloud switch cannot delete the now-cloud `comm_key`. The backfill is scoped to `adms_enabled` devices.
- **Backfill flags** are split: `--provision-missing` only touches devices with no key; `--reprovision-legacy` rotates unsalted hashes, whitespace-only placeholder hashes, corrupt salted verifiers that can never match, and versioned hashes whose stored iteration count is outside the safe range, and requires reconfiguring each terminal. A salted, unversioned 64-hex PBKDF2 hash is treated as recognized because it authenticates against the configured/historical counts; a different-count hash cannot be distinguished from a working one, so it is not auto-selected and must be rotated explicitly. It refuses ambiguous aliases before rotating and removes every superseded native alias so a later `--clean-plaintext` cannot report a permanent mismatch. `--reveal` is required for a real provisioning run and is the only opportunity to capture the generated keys; each key is printed immediately after its transaction commits, before activity logging, so a later failure cannot strand an already-saved hash. Activity-log failures are reported separately and do not mark the device as unprovisioned. The command writes plaintext keys to stdout by design: run it interactively or redirect the output to a permission-restricted file, and never retain CI/deploy logs that contain the output. Combining `--provision-missing` and `--reprovision-legacy` targets each device id once and re-reads the row after migration, so a whitespace hash plus plaintext is migrated, not rotated twice.
- **PBKDF2 hashes are self-describing** (`pbkdf2-sha256:{iterations}:{hash}`), so changing `ADMS_PBKDF2_ITERATIONS` no longer invalidates hashes created by an earlier deploy. Unversioned hashes are verified against the configured count and the historical 100,000 default; an unversioned hash created with any other old count cannot authenticate, but the count cannot be detected from the digest, so it is reported as `Configured (unversioned)` and is not selected by `--reprovision-legacy` (rotate it explicitly). Versioned hashes with a count outside `100,000`–`1,000,000` are rejected rather than trusted and are selected by `--reprovision-legacy`. The verification floor is the fixed `BiometricDevice::MIN_PBKDF2_ITERATIONS` constant in every environment; the test suite lowers it at runtime in the testing environment only. `ADMS_PBKDF2_MIN_ITERATIONS` is not a supported production knob.
- **Access logs** must not record the query string for `/api/iclock/*`, nor the path token for a webhook route. The shipped `nginx.conf` maps the original `$request` line and drops the query string. `$request_uri` keeps the original path and query, while `$uri` is rewritten by `try_files`; neither is the log source. The map redacts any `/webhook/<segment>` path, including subpath, double-slash, mixed-encoding, and double-encoded forms, and matches case-insensitively and percent-encoded separators (`/WEBHOOK/`, `%2Fwebhook%2F`, `/%2Fwebhook/`, `%252Fwebhook%252F`), so the access log shows `/api/v1/biometric/webhook/[REDACTED]` without the token and without `pushcommkey`; any production reverse proxy must apply the same rule. The non-canonical uppercase/encoded paths normally return 404, but the request line is logged before routing, so the map covers them as well. nginx error logs may still record the request line on upstream failures, so restrict log access and network exposure; application logs redact exception messages and render traces without arguments.
- **Network exposure**: where the deployment permits it, restrict `/api/iclock/*` to gym egress/VPN addresses. The per-IP throttle and per-device lockout are online-guessing controls, not a substitute for network scoping.
- **New-key enforcement vs legacy verification**: new operator-entered keys must be exactly six alphanumeric characters under the shared model policy, mirrored in the Filament form. Legacy keys of other lengths remain verifiable; the backfill deliberately preserves their length.
- **Legacy `comm_key` alias**: `provider_config['comm_key']` is accepted as a fallback verifier for device-native devices, including the legacy `provider_type = null` shape. Cleanup deletes it for those rows and records the removal in the activity log; an explicit cloud provider's `comm_key` is never read or deleted by ADMS code, including after a cloud→native switch, which records `comm_key_source = 'cloud'`. Conflicting non-empty `pushcommkey` and `comm_key` values are refused and left for operator review; a `pushcommkey`-only row can switch to a cloud provider without a new key. When a device-native row leaves native mode, a filled `comm_key` ADMS alias is translated to `pushcommkey` (unless a `pushcommkey` already exists or the `comm_key` is marked as a cloud credential), so it keeps authenticating and a later native switch does not strand it as a cloud credential.
- **One-time key and token reveal**: Create/Edit no longer write a generated key or device API token into the redirect notification (which would persist it in the sessions table). They store each value **encrypted** in the configured cache under `biometric:device:comm_key_reveal:{id}` / `biometric:device:token_reveal:{id}` for 15 minutes, and only after the outermost transaction commits (queued with `DB::afterCommit()` when an outer transaction is still active). A generated webhook HMAC secret uses the same `WebhookSecretReveal` store and a reveal action instead of clipboard-only delivery. The `Reveal Comm Key` / `Reveal API Token` / `Reveal Webhook Secret` table actions claim the one-time value under a cache lock; the audit entry is written on the first claim and on the first delivery-copy read for that viewer, while simple re-renders are not audited again. The delivery copy is encrypted and scoped to the authenticated user id; a second authorized user gets the "no longer available" state rather than another user's copy. The audit entry is written on the first claim and on the first delivery-copy read for that viewer, not on every re-render or every returning read. The delivery copy keeps the modal readable for up to 15 minutes after the claim, so the effective exposure window is at most about 30 minutes from generation. `stage()` writes a pending copy without touching the live value; `promote()` moves it to the primary slot after the database commit and invalidates every viewer delivery copy; `forgetPending()` drops only the staged copy on a database failure. `put()` clears any superseded pending value, primary, and generation before writing; `forget()` clears all three. A rotation or re-provisioning path invalidates them; a migration that only hashes an unchanged plaintext key does not. A failed `promote()` (missing/evicted pending value or lock contention) also drops the old primary and delivery epoch, so the UI never shows a secret the device no longer accepts. Neither copy is placed in a Livewire snapshot or a session-backed notification. A cache write failure is logged and surfaced to the operator. Webhook-secret generation and API-token regeneration stage the reveal value before the database write, so a cache failure leaves the existing credential valid; a database failure forgets only the staged copy and leaves the old secret and its reveal intact. The manual webhook-secret read-merge-write, superseded-reveal invalidation, and save hold the same per-device lock as the Generate action; the audit entry is written after commit. An ADMS comm-key rotation has already committed and must be rotated again once the cache is healthy. The committed verifier is never rolled back. A lock timeout or lock-creation failure fails closed rather than reading unlocked, because `Cache::pull()` is `get()` + `forget()` and is not itself atomic. `SESSION_ENCRYPT=true` remains recommended for production. A shared, lock-capable cache store is a production prerequisite.
- **Error-report redaction**: stored request context drops Livewire's `components[].snapshot` and the dotted `updates` map, `redact()` also decodes JSON-string values and dotted payload paths, and the error-report edit page hydrates only `status`, `priority`, and `error_type` into the form; `user_description`, `error_message`, `markdown_report`, `stack_trace`, `origin_url`, and `context` are read-only, redacted Placeholders, while `error_hash` is hidden and not rendered. None is in the public Livewire `$data`/snapshot. The deduplication hash is derived from the redacted message (including the redacted SQL for a `QueryException`), not the raw pre-redaction text or raw SQL literals, and is not written to the error log. A status-only save therefore cannot overwrite the stored diagnostic record. Historical rows remain readable because display and export use the same read-time projection — every string leaf within the per-payload byte and leaf caps is passed through the URL and free-text redactors, including escaped JSON snapshots, legacy trace arguments, 48/64-hex bearer tokens, raw `origin_url`/`referer` values, allow-listed header values, denied historical headers (`x-comm-key`, `x-adms-comm-key`, `x-signature`), and the webhook path token. Malformed or truncated escaped JSON fails closed. Both copy actions share `ErrorReport::toRedactedMarkdown()`; stored Markdown header blocks are parsed through the same deny-by-default header allow-list as the page, including headings inside unrelated fences and unfenced blocks with an intro line; a `## Database Error` section exports SQLSTATE, driver code, the redacted driver message, and the redacted SQL. JSON code fences are structurally redacted for biometric PII including plural names (`pins`, `cards`, `photos`, `images`, `tokens`). The free-text redactor matches credential and biometric-PII names with token-boundary rules and bounds the assignment name to the immediate lexical token (so `discard`, `spin`, `postcard`, `secretary`, `token expired`, and similar diagnostics are not corrupted), handles real multiline `print_r`/`var_dump`/`$_GET` output, newline and colon-less labels separated by whitespace/comma/semicolon/slash or an encoded separator (`%20`, `+`, `%2C`, `%2F`, `%3B`, `%5F`), YAML block scalars (`|`, `>`, explicit indentation such as `|2`/`>2`, and zero-indent bodies), JSON documents whose only sensitive key is in the structural key set (`data`, `fingerprint`, `user_id`, `member_id`), JSON/escaped/percent-encoded/double-encoded/serialized forms, six-character alphanumeric comm keys, hyphen/dot/slash-grouped values (including card numbers and `string(6)`/`s:6:` forms), alphabetic URL parameter suffixes (`?pushcommkeyABC`), `pwd`/`passwd`, bare `Basic`/`Digest` credentials, URL userinfo, and digit-grouped values without corrupting comparison operators or JSON structure. Serialized arrays are re-emitted with valid type/length tokens and are `unserialize()`-able; malformed declared lengths and container counts (`a:-1:`, `s:x:`) fail closed without leaving a key suffix, and a second pass is idempotent. Valid JSON documents preserve object-vs-list shape, numeric-key objects, `1.0` fractions, and 20-digit integers; a generic `error`/`errors`/`exception` string is leaf-redacted instead of collapsed to `***`. Base64- or compression-encoded payloads are explicitly out of scope: the redactor does not decode arbitrary blobs, so a secret that has been base64/gzip-encoded before it reaches a log is not detected. The assignment scanner has no fail-open match cap and never appends an unprocessed tail; a cheap candidate prefilter skips the expensive passes for benign leaves, the request path also caps total string leaves, and PCRE failure returns `[REDACTION FAILED]`.
- **Fallback sunset**: `ADMS_ALLOW_LEGACY_UNSANITIZED_COMM_KEY` and the native `comm_key` alias are owned by Platform Engineering and must be removed in the release after two consecutive `biometrics:backfill-adms-comm-keys --clean-plaintext` runs report zero raw-fallback rehashes/fallback warnings and zero remaining plaintext aliases, and no later than **2027-01-31**. The fallback log line, the cleanup mismatch exit code, and the stale-plaintext notice are the telemetry used to confirm the fleet is ready. A null-provider `comm_key` is no longer exempt, so the zero-alias gate is reachable.

OpenSpec note: this ADR is the decision record for a security-pattern change. The project's spec-driven workflow also allows a change proposal; if one is required, it should reference this ADR rather than restate the technical decision. For this release the architecture review explicitly waives a separate OpenSpec proposal and records ADR-0005 as the governing decision.

## Consequences

### Positive

- **POS-001**: 100% out-of-the-box compatibility with eSSL and ZKTeco biometric terminals, resolving physical 6-character input limits on device menus.
- **POS-002**: Immunity to firmware-level integer casting bugs by generating keys strictly within `100000`–`999999`, eliminating leading-zero truncation risks.
- **POS-003**: Error-tolerant operator experience: space-separated or untrimmed pastes up to 20 characters are cleaned on blur, and a shared server rule on the sanitized value rejects an overlong or disallowed code instead of silently dropping digits.
- **POS-004**: Preserves existing verifiers: PBKDF2-SHA256 with per-device salts remains the storage primitive; no schema migration is required; no-whitespace keys verify unchanged and whitespace-bearing keys have a one-release fallback.
- **POS-005**: Preserves manual flexibility: gym operators who need alphanumeric keys on advanced touchscreen models (like KM-20) can enter six alphanumeric characters. New keys are exactly six characters after sanitization; generated keys remain six numeric digits.

### Negative

- **NEG-001**: Lower theoretical entropy: a six-digit numeric PIN has 900,000 generated values (about 19.8 bits), significantly less than a 32-character hexadecimal token ($16^{32}$).
- **NEG-002**: The ~20-bit PIN requires online guessing controls. The implemented posture is: a per-IP `throttle:biometric-api` limiter (120 requests/minute, partitioned by route category, IPv6 keyed by `/64` with IPv4-mapped addresses normalized), a per-device failed-attempt lockout (default five failures, fifteen-minute cooldown) enforced **before** key verification, a uniform `ERR: Unauthorized` response that does not distinguish unknown serials from bad keys, and production boot warnings when the throttle is disabled or key enforcement is off. Because the lockout is checked first, it actually caps guesses: a locked-out terminal is rejected even when it presents the correct key, and the lockout is not cleared by a guess during the cooldown. The tradeoff is explicit — an attacker who knows a serial can deny service to that terminal for the cooldown, so recovery is `php artisan biometrics:reset-adms-lockout` (per device or fleet) or waiting for expiry. Rotation and re-provisioning clear the counter and lockout. The lockout state lives in the configured cache; a cache outage is logged critically and degrades to "no lockout" rather than a 500, so a shared store (Redis, database, memcached, or DynamoDB) is required in production. A terminal whose key is misconfigured cannot self-recover until the key is corrected; this is deliberate and is why the reset command exists.
- **NEG-003**: Key collisions in a 900,000-value space are possible across a large fleet (birthday probability rises quickly above a few hundred devices). Keys are not unique across devices. Rotation and incident response should use the device id, not the key value. A uniquely indexed keyed fingerprint with retry-on-collision remains the longer-term option.
- **NEG-004**: No server-side pepper is applied. A database-read compromise therefore allows offline brute force of the 900,000-value key space despite the salted PBKDF2 hash. This is accepted for the current threat model; a pepper would require key-management work outside this change.

## Alternatives Considered

### Retaining 32-Character Hexadecimal Keys

- **ALT-001**: **Description**: Maintain `bin2hex(random_bytes(16))` as the default generated key format and instruct operators to manually shorten it if their device firmware requires.
- **ALT-002**: **Rejection Reason**: Physical biometric terminals universally reject strings longer than 6 characters. Forcing manual truncation resulted in confusion, broken device connections, and support escalations for on-site gym technicians.

### 6-Character Random Alphanumeric Generation

- **ALT-003**: **Description**: Generate random 6-character alphanumeric strings combining uppercase letters, lowercase letters, and numbers (e.g., `K9b2X1`).
- **ALT-004**: **Rejection Reason**: Many budget and standard gym terminals (like eSSL K90 or older attendance clocks) only possess a 10-key numeric keypad with no text entry method. Generating alphanumeric characters would fail on keypad-only devices and cause significant friction when typing on small rubber buttons.

### Modifying Database Schema to Plaintext or Short Hashes

- **ALT-005**: **Description**: Store comm keys in plaintext or use a lightweight hash to save computational cycles.
- **ALT-006**: **Rejection Reason**: Storing authentication secrets in plaintext violates data security standards. PBKDF2 with 100,000 iterations and per-device random salts is essential to protect lower-entropy 6-digit keys against precomputed rainbow table attacks.

## Implementation Notes

- **IMP-001**: Model method [BiometricDevice::generateCommKey()](../../app/Models/BiometricDevice.php) provides the single source of truth for key generation. The Filament action and `biometrics:backfill-adms-comm-keys` both call it; no inline `random_int` copies remain.
- **IMP-002**: Centralized sanitization via [BiometricDevice::sanitizeCommKey()](../../app/Models/BiometricDevice.php) is invoked in form dehydration, before create/edit model persistence, in the backfill command, and on incoming authentication verification.
- **IMP-003**: Terminal hardware setup instructions in [adms-setup-card.blade.php](../../resources/views/filament/forms/components/adms-setup-card.blade.php) guide technicians to enter the exactly-six-character key configured for the device; the wording remains valid for both numeric and alphanumeric manual keys.
- **IMP-004**: Automated coverage is split across [BiometricCommKeyHelpersTest.php](../../tests/Unit/Biometric/BiometricCommKeyHelpersTest.php), [BiometricDeviceCommKeyTest.php](../../tests/Unit/Biometric/BiometricDeviceCommKeyTest.php), [BiometricDeviceCommKeyFormTest.php](../../tests/Feature/Filament/BiometricDeviceCommKeyFormTest.php), [AdmsAuthenticationAndTenancyTest.php](../../tests/Feature/AdmsAuthenticationAndTenancyTest.php), [BackfillAdmsCommKeyHashesCommandTest.php](../../tests/Feature/BackfillAdmsCommKeyHashesCommandTest.php), [BiometricCommKeyVerifierUpgradeTest.php](../../tests/Feature/BiometricCommKeyVerifierUpgradeTest.php), [CommKeyRevealTest.php](../../tests/Feature/CommKeyRevealTest.php), [BiometricDeviceRevealActionTest.php](../../tests/Feature/Filament/BiometricDeviceRevealActionTest.php), [ErrorReportEditTest.php](../../tests/Feature/Filament/SuperAdmin/ErrorReportEditTest.php), [ExceptionMarkdownFormatterTest.php](../../tests/Feature/ExceptionMarkdownFormatterTest.php), [BiometricDeviceObserverTest.php](../../tests/Unit/Observers/BiometricDeviceObserverTest.php), [RequestOriginResolverTest.php](../../tests/Unit/Services/RequestOriginResolverTest.php), and [ResetAdmsAuthLockoutCommandTest.php](../../tests/Feature/ResetAdmsAuthLockoutCommandTest.php), [BiometricLogRedactorRound7Test.php](../../tests/Feature/BiometricLogRedactorRound7Test.php), [ErrorReportingRound7Test.php](../../tests/Feature/ErrorReportingRound7Test.php), [ClipboardHelperTest.php](../../tests/Unit/ClipboardHelperTest.php), [AdmsSetupCardTest.php](../../tests/Feature/Filament/AdmsSetupCardTest.php), [BiometricLogRedactorRound8Test.php](../../tests/Feature/BiometricLogRedactorRound8Test.php), [ErrorReportMarkdownProjectionTest.php](../../tests/Feature/ErrorReportMarkdownProjectionTest.php), [BiometricDeviceCommKeyVerifierPolicyTest.php](../../tests/Unit/Biometric/BiometricDeviceCommKeyVerifierPolicyTest.php), [BiometricDeviceWebhookSecretRound8Test.php](../../tests/Feature/Filament/BiometricDeviceWebhookSecretRound8Test.php), and [OneTimeSecretStoreTest.php](../../tests/Unit/Biometric/OneTimeSecretStoreTest.php). Coverage includes deterministic generation, Unicode/format-character sanitization, PBKDF2/legacy/provider_config verification, iteration-bound and uppercase-hash handling, self-healing verifier upgrades (including a failed-write path), form policy and leading-zero rejection, the exactly-six policy, non-string Livewire state, HTTP whitespace tolerance, sanitized-first raw-verifier rejection, lockout expiry/isolation/reset (including cache failure and disabled devices), cloud `comm_key` preservation and provenance, null-provider cleanup, one-time reveal cache behavior (including lock-timeout fail-closed), duplicate-target reprovisioning, whitespace placeholders under `--reprovision-legacy`, corrupt `provider_config` (including preservation on an unrelated save and explicit replacement), edit-page snapshot withholding, mid-run provisioning failure, activity-log failure separation, and markdown/error-report redaction (including PHP dump/label/escaped-JSON forms, malformed/truncated escaped snapshots, JSON structure preservation, denied historical headers, bearer trace arguments, raw URLs, and biometric PII in context exports).

## References

- **REF-001**: [ADR-0004: Biometric ADMS Push Protocol and Hardware Lifecycle Management](../biometric/architecture/ADR-004-adms-push-protocol-and-lifecycle.md)
- **REF-002**: [Biometric ADMS Push Protocol Feature Guide](../biometric/feature/biometric-adms-push-protocol.md)
- **REF-003**: [Biometric Devices Database Field Reference](../biometric/database/biometric-devices-fields.md)
- **REF-004**: ZKTeco ADMS / Push SDK Specification Version 2.0+
