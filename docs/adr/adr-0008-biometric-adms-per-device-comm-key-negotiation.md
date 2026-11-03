---
title: "ADR-0008: Biometric ADMS Per-Device Communication Key Negotiation and Unkeyed Legacy Hardware Support"
status: "Accepted"
date: "2026-11-02"
authors: "Lead Architect, Security Engineer, Hardware Integration Team"
tags: ["architecture", "decision", "biometrics", "adms", "hardware-integration", "security", "essl", "mb20", "zkteco"]
supersedes: ""
superseded_by: ""
---

# ADR-0008: Biometric ADMS Per-Device Communication Key Negotiation and Unkeyed Legacy Hardware Support

## Status

**Accepted**

Amends [ADR-0005](adr-0005-biometric-adms-communication-key-hardware-standardization.md)'s premise that every ADMS terminal authenticates with a communication key. ADR-0005's key generation, sanitisation and PBKDF2 verification lifecycle remain in force for keyed devices.

## Context

In multi-tenant commercial gym environments, physical biometric attendance is captured through a diverse fleet of standalone terminals (predominantly eSSL, ZKTeco, and OEM firmware). Communication with the central cloud application occurs via the HTTP-based ADMS (Automatic Data Master Server) push protocol.

Under ADR-0005, the application established a 6-digit PBKDF2-salted communication key architecture (`pushcommkey`) to authenticate incoming ADMS requests. In that initial design:
1. `BiometricDeviceResource` enforced `adms_comm_key` as mandatory (`required`) whenever ADMS protocol was enabled.
2. `config('biometrics.adms.require_comm_key')` defaulted to `true`, rejecting any terminal that did not authenticate with a verified cryptographic key.

However, physical deployment across commercial partner gyms (specifically with widely deployed budget and legacy terminals such as **eSSL MB20**, **K90**, and legacy **ZKTeco pushver 2.4.1** models) revealed a critical hardware limitation:
- **Missing Push Comm Key Capability**: The firmware on certain terminals (such as the eSSL MB20) contains an ADMS cloud client that supports only `Server Address`, `Server Port`, and `Enable Domain Name`. The terminal possesses no firmware input for an ADMS Cloud Server Communication Key.
- **Local vs Cloud Comm Key Separation**: While these devices provide a `Comm Key` setting under **Menu > Comm. > PC Connection**, that key applies exclusively to legacy standalone SDK connections on TCP port 4370 (pull mode). The internal HTTP client never transmits this key over ADMS push requests (`/api/iclock/cdata?SN=...`).
- **Enforced Lockout Trap**: Because the admin panel previously forced operators to enter a comm key during terminal creation, the database recorded a PBKDF2 hash for the device. When the physical MB20 contacted the server, it omitted `pushcommkey`. The server interpreted this omission as a credential attack, incremented the failed attempt counter, and placed the terminal into a 900-second lockout (`401 Unauthorized`), rendering the device unusable.

A flexible per-device negotiation model was required: allowing hardware incapable of push keys to operate securely via hardware Serial Number (SN) verification, while strictly enforcing cryptographic key verification for all devices where a key has been provisioned.

## Decision

We designed and implemented a per-device ADMS communication key negotiation policy across the admin UI, backend authenticator, and auto-recovery middleware:

### 1. Optional Communication Key in Admin Form (`BiometricDeviceResource`)
- Removed the unconditional `required` rule from `adms_comm_key` in `BiometricDeviceResource`.
- Creation and edition forms allow operators to leave `adms_comm_key` blank for devices that do not support push communication keys (such as eSSL MB20).
- If left blank, `adms_comm_key_hash` and `adms_comm_key_salt` remain `null`, designating the terminal as an unkeyed, serial-authenticated device (`hasCommKeyConfigured() === false`).
- Added informative helper text clarifying that operators should leave the field blank for terminals lacking pushcommkey firmware menus.

### 2. Strict Per-Device Backend Authentication (`AdmsDeviceAuthenticator`)
- **Keyed Terminals (`hasCommKeyConfigured() === true`)**:
  - The incoming request MUST present a valid, matching `pushcommkey` in query parameters, headers, or request body.
  - If a keyed terminal transmits a request without a key, or with an invalid key, the authenticator performs a constant-time dummy PBKDF2 calculation (preventing timing side-channel attacks), logs an authentication failure, and increments the failure counter.
  - After 5 consecutive failures, the terminal is locked out for 900 seconds. An attacker cannot bypass authentication on a keyed terminal simply by omitting the key.
- **Unkeyed Terminals (`hasCommKeyConfigured() === false`)**:
  - If no communication key has been provisioned in the admin panel, the authenticator validates the terminal purely against its registered `device_serial` (`SN`) and verifies that the terminal is not in `Maintenance` or deleted status.
  - Successfully returns `AdmsAuthenticationResult::success($device, keyVerified: false)`.

### 3. Server-Wide Compliance Gate Retention (`ADMS_REQUIRE_COMM_KEY`)
- Changed `config('biometrics.adms.require_comm_key')` default from `true` to `false` in `config/biometrics.php` and `.env.example`, allowing per-device negotiation out-of-the-box.
- Maintained the configuration parameter as an optional server-wide enforcement override: if set to `true` in high-security enterprise environments, the authenticator will refuse any unkeyed device, enforcing zero-trust hardware onboarding.

### 4. Middleware Inactive Auto-Recovery (`AuthenticateAdmsDevice`)
- Updated auto-recovery logic in `AuthenticateAdmsDevice` middleware: an inactive terminal without a configured comm key is permitted to auto-recover to `Active` status upon receiving a valid request matching its registered serial number, provided the global compliance override is not active.

## Consequences

### Positive

- **POS-001**: **Universal Hardware Support**: Seamlessly onboard and operate budget and legacy terminals (such as eSSL MB20) that lack cloud push key firmware capability.
- **POS-002**: **No Security Downgrade for Keyed Terminals**: Terminals with provisioned communication keys retain 100% of their PBKDF2 verification, constant-time dummy checks, and brute-force lockout protections.
- **POS-003**: **Zero Accidental Lockouts**: Operators no longer need to enter dummy keys that trigger 15-minute authentication lockouts on physical hardware.
- **POS-004**: **Enterprise Compliance Ready**: System administrators requiring mandatory keys across 100% of terminals can still enforce `ADMS_REQUIRE_COMM_KEY=true` globally.

### Negative

- **NEG-001**: **Serial-Only Trust Model for Unkeyed Hardware**: Terminals operating without a comm key rely on the uniqueness and secrecy of their hardware Serial Number (`SN`) on the local network or internet. An adversary possessing the exact serial number could theoretically submit spoofed attendance events if network access is unrestricted.
- **NEG-002**: **Operator Responsibility**: The gym owner or administrator must know whether their hardware model supports `pushcommkey` to decide whether to provide a key during onboarding.

## Alternatives Considered

### Alternative 1: Model-Based Automatic Key Detection

- **ALT-001**: **Description**: Maintain an internal database table of device models (e.g., `MB20`, `SilkBio`, `SpeedFace`) and automatically toggle key requirement based on selected model.
- **ALT-002**: **Rejection Reason**: Firmware versions on identical models vary drastically between manufacturing batches; some MB20 firmware builds support pushcommkey while older ones do not. Static model cataloging introduces fragile heuristics and maintenance overhead.

### Alternative 2: Opportunistic Key Verification (Verify Key Only If Sent)

- **ALT-003**: **Description**: If incoming request contains `pushcommkey`, verify it; if request omits `pushcommkey`, authenticate by serial number regardless of whether a key was configured on the device.
- **ALT-004**: **Rejection Reason**: Completely subverts security for keyed terminals. An attacker knowing only the serial number could simply omit the `pushcommkey` parameter to bypass authentication on any secured terminal.

## Implementation Notes

- **IMP-001**: Form changes in `BiometricDeviceResource` only require a key during legacy-to-cloud transitions (`commKeyTransitionRequiresKey`), leaving standard ADMS creation and edition optional.
- **IMP-002**: The `AdmsDeviceAuthenticator` branches on `hasCommKeyConfigured()` before executing PBKDF2 verification, avoiding unnecessary hashing overhead for unkeyed devices.
- **IMP-003**: Existing test suites (`AdmsAuthenticationAndTenancyTest`, `BiometricDeviceCommKeyFormTest`) have been expanded to verify both unkeyed serial-only authentication and strict keyed rejection.

## Amendment (2026-11-03): Un-keying an Existing Device

### Context

§1 above made the key optional at creation, but the edit form still treats a blank key as "keep the current key". Terminals keyed by the pre-ADR-0008 form — for example an MB20 that was forced to store a dummy key before the mandatory rule was dropped — therefore could not be converted to serial-only authentication without deleting and recreating the device, which discards its command ledger and attendance history.

### Decision

Add an explicit **Remove Comm Key** row action in `BiometricDeviceResource`, placed beside `revealCommKey`/`rotateCommKey` and authorised by `rotateCredentials`:

- It nulls `adms_comm_key_hash`/`adms_comm_key_salt` and removes the ADMS key aliases from `provider_config` under the per-device provider-config lock: `pushcommkey` always, and `comm_key` only when `mayDeleteCommKeyAlias()` reports a device-native row whose credential is not cloud-sourced (on a cloud provider `comm_key` is a provider secret, per ADR-0007). A stale `comm_key_source` marker is dropped, but the marker is **kept** when a cloud-sourced `comm_key` survives: it is what keeps that provider credential out of the ADMS alias set. The alias rule is shared with `rotateCommKey` so the two actions cannot drift.
- It is refused while `ADMS_REQUIRE_COMM_KEY=true`, because the device would then be rejected permanently, and it is hidden when the device has no configured key, its ADMS protocol is disabled, or its `provider_config` is unreadable.
- It forgets any pending one-time reveal and clears the auth failure counter/lockout, mirroring rotation.
- It records `adms_comm_key_removed` in the activity log with the removed aliases, the provider type and an optional operator reason.
- Blank-on-edit keeps meaning "keep the current key"; there is no bulk action.

### Consequences

- **Positive**: the legacy fleet this ADR was written for can be converted in place, without destroying device history.
- **Negative**: un-keying is a deliberate downgrade to serial-only trust (NEG-001). The confirmation modal instructs the operator to clear the key on the terminal's own menu, and the authenticator logs any key a now-unkeyed terminal still sends.

## Amendment (2026-11-03): Fail-Closed Unreadable Configuration

### Decision

- **An unreadable `provider_config` is treated as keyed.** `hasCommKeyConfigured()` deliberately swallows a decrypt failure and answers `false`, so the authenticator also checks `providerConfigIsUnreadable()`. A device whose ciphertext cannot be decrypted therefore never falls through to serial-only authentication: it is rejected until the configuration is repaired or replaced, and Remove Comm Key is hidden for the same rows.
- **Refusal under global enforcement writes no failure counter.** With `ADMS_REQUIRE_COMM_KEY=true` an unkeyed terminal is refused unconditionally, so a counter would cap nothing; the refusal is logged instead. Dummy PBKDF2 work still runs so response timing does not reveal whether a serial is keyed or unkeyed. When enforcement is off, an unkeyed terminal that still sends a `pushcommkey` authenticates by serial and logs the unexpected key.
- **One alias policy.** `BiometricDevice::removableCommKeyAliases()` and `stripCommKeyAliases()` are the single source of truth for the backfill, Rotate Comm Key and Remove Comm Key. Both actions also drop a stale `comm_key_source` marker when no `comm_key` remains, so rotation cannot leave provenance behind.
- **Form hardening.** Non-string Livewire state is rejected by the field rule instead of being normalised to blank, and a new key is mandatory only for the native→cloud transition; helper text explains why.

## References

- **REF-001**: [ADR-0005: Biometric ADMS Communication Key Hardware Standardization](file:///c:/xampp/htdocs/projects/gym-app/docs/adr/adr-0005-biometric-adms-communication-key-hardware-standardization.md)
- **REF-002**: [ADR-0006: Biometric ADMS Legacy Hardware Endpoint and Transport Compatibility](file:///c:/xampp/htdocs/projects/gym-app/docs/adr/adr-0006-biometric-adms-legacy-hardware-endpoint-and-transport-compatibility.md)
- **REF-003**: [ADR-0007: Biometric ADMS Multi-Device Table Schema Negotiation](file:///c:/xampp/htdocs/projects/gym-app/docs/adr/adr-0007-biometric-adms-multi-device-table-schema-negotiation.md)

