---
adr: 004
title: Biometric ADMS Push Protocol and Hardware Lifecycle Management
status: accepted
date: 2026-09-23
author: docs-agent
related_features:
  - ../feature/biometric-adms-push-protocol.md
  - ../feature/door-access-control.md
  - ../feature/biometric-checkin-flow.md
---

# ADR-004: Biometric ADMS Push Protocol and Hardware Lifecycle Management

## Status

Accepted

## Context

Many gym facilities use standalone biometric hardware terminals from manufacturers such as **eSSL** and **ZKTeco**, as well as **Hikvision ISAPI** terminals. These devices operate using the **ADMS (Automatic Data Master Server)** push protocol (also known as the `iclock` protocol).

Under ADMS:
1. Devices match biometric data on-device and push verified attendance logs (`ATTLOG`) via HTTP POST to the server.
2. In turn, devices periodically poll the server (`GET /api/iclock/getrequest`) to retrieve commands queued for execution (e.g., enrolling users, deleting users).
3. The gym management system also already has an existing webhook endpoint (`/api/v1/biometric/webhook/{token}`) used for real-time door relay unlock directives.
4. Long-expired members must be cleanly deprovisioned from physical terminal memory to maintain building security without prematurely deleting credentials during brief payment grace periods.

## Decision

We designed and implemented a dual-channel architecture:

### 1. Dual-Channel Protocol Coexistence
- **Coexistence without conflict**: Hardware terminals can be configured to use **both** endpoints simultaneously. Webhooks provide immediate door relay access control decisions, while ADMS handles attendance verification pushes and server-to-device command polling.
- **Dedicated route grouping**: ADMS endpoints are exposed under `/api/iclock/*` (`cdata`, `getrequest`, `devicecmd`) and Hikvision under `/api/v1/biometric/hikvision/event`.
- **Serial-based authentication**: ADMS terminals authenticate via their unique hardware serial number (`SN`), resolving devices with Redis caching for $O(1)$ response time.

### 2. Auto-Checkout Detection
- Since many hardware terminals have only one scanning interface where members swipe both when entering and leaving, `AdmsEventService` checks for open attendance records on the event date before applying anti-passback.
- If an open check-in exists, the scan is automatically treated and finalized as a check-out.

### 3. Server-to-Device Command Queue with Status Enum
- Modeled via `AdmsDeviceCommand` and typed with `AdmsCommandStatusEnum` (`PENDING`, `SENT`, `ACKNOWLEDGED`, `FAILED`).
- Commands are formatted dynamically per the ADMS protocol specification (e.g. `C:{id}:DATA DELETE USERINFO PIN={pin}`, or the legacy `C:{id}:DATA DELETE user Pin={pin}` for terminals configured with the `user` table).
- Terminals dequeue the oldest pending command on poll, mark it `SENT`, and acknowledge execution via `devicecmd`.

### 4. Three-Layer Expired Member Grace Period
- Rather than immediately deleting members on the exact expiration timestamp or relying solely on a global default, member removal from hardware follows a 3-layer grace window:
  $$\text{Cutoff Date} = \text{membership\_end} + \text{gym\_grace\_days (default: 7)} + \text{adms\_grace\_days (default: 10)}$$
- A daily automated Artisan command (`app:cleanup-expired-device-members`) queues `user_delete` commands, deactivates biometric templates, and invalidates Redis caches.

## Alternatives Considered

| Alternative | Why rejected |
|---|---|
| Replacing webhooks entirely with ADMS | ADMS `cdata` responses are simple text `OK` messages; they do not provide real-time relay pulse control directives needed for magnetic doors |
| Polling devices via vendor desktop SDKs | Requires running Windows-only desktop client middleware on gym premises; pure cloud HTTP push is cross-platform and requires zero local servers |
| Immediate hardware deletion on expiration | Gym owners frequently offer grace periods for late renewals; immediate hardware removal causes friction and requires re-enrollment |

## Consequences

### Positive
- Fully native support for standard eSSL, ZKTeco, and Hikvision hardware without proprietary bridge software.
- Full coexistence: existing door access webhooks continue to function alongside ADMS.
- Automatic check-out detection eliminates attendance discrepancies on single-sensor doors.
- High reliability: failed commands can be inspected and retried from the Filament admin panel.
- Three-layer grace period strikes the right balance between security and customer retention.

### Negative / Trade-offs
- Devices must poll the server for commands; deprovisioning happens on the next device poll rather than instantaneous push.
- Dual-mode devices require initial configuration of both the ADMS server address and the webhook URL on the terminal.

## Related Decisions

- [ADR-001](./ADR-001-biometric-provider-abstraction.md) — Biometric Provider Abstraction
- [ADR-002](./ADR-002-door-access-attendance-integrity.md) — Door Access & Attendance Integrity
