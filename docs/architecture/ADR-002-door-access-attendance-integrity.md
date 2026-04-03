---
adr: 002
title: Door Access Attendance Integrity
status: accepted
date: 2026-04-03
author: docs-agent
related_features:
  - ../features/biometric-checkin-flow.md
  - ../features/door-access-control.md
---

# ADR-002: Door Access Attendance Integrity

## Status

Accepted

## Context

Gyms may have two separate biometric devices: one for attendance recording and one for door access. Without proper coordination, this could create duplicate attendance records. Additionally, if the door fails to unlock (API unreachable, hardware failure), we should not record attendance since the member didn't actually enter.

## Decision

Enforce these rules in `CheckInService`:

1. **Check for existing attendance before creating**: When a `door_access` device processes a check-in, first check if an attendance already exists for this member today. If yes, update door status on existing record instead of creating duplicate.

2. **Door unlock failure = no attendance**: If `DoorControlService.unlock()` returns failed status on a `door_access` device, do NOT create attendance record. The member didn't enter the gym.

3. **Relay/Wiegand treated as success**: These protocols don't provide feedback, so return `skipped` status which counts as success for attendance creation.

4. **Log all door events**: Regardless of outcome, every door attempt logged to `door_events` table.

## Alternatives Considered

| Alternative | Why rejected |
|---|---|
| Always create attendance on door access | Creates duplicates in two-device gyms |
| Create attendance first, update on door failure | Member counted as "present" even if door didn't open |
| Skip door check if no door configured | Doesn't handle partial failures |

## Consequences

### Positive

- No duplicate attendance records in multi-device gyms
- Accurate attendance reflects actual gym entry
- Full audit trail in door_events table
- Door status visible on attendance for reporting

### Negative / Trade-offs

- Additional query to check existing attendance (performance)
- More complex check-in flow logic
- Must ensure door controller is reliable or attendance will be missed

## Related Decisions

- [ADR-001](./ADR-001-biometric-provider-abstraction.md) — Uses biometric providers for identity verification