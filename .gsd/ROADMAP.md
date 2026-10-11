# ROADMAP.md

> **Current Milestone**: None (v1.0 Complete)  
> **Next Milestone**: Run `/new-milestone` to initialize next milestone  

---

## Milestone History

### [v1.0 — Biometric Consent Notification (Email & WhatsApp)](milestones/v1.0-SUMMARY.md)
**Status**: ✅ Complete (2026-10-11)  
**Archive**: `.gsd/milestones/v1.0/`

#### Deliverables (All Satisfied)
- [x] Header action on `ListGymMembers` to send biometric consent requests to all eligible active members
- [x] Table bulk action on `GymMemberResource` to send biometric consent requests to selected members
- [x] Dual-gated WhatsApp channel option: visible ONLY IF tenant WhatsApp is enabled AND approved biometric consent template is available
- [x] Asynchronous queued execution preventing request timeout on large member lists
- [x] Idempotent skipping of already consented members and respect for WhatsApp opt-in gates
- [x] Comprehensive positive and negative test cases verifying dual-gate availability, recipient filtering, and queued delivery
- [x] Member welcome email includes biometric attendance notice and consent submission link when gym has biometric device available
- [x] Member welcome email hides biometric attendance notice when gym has no biometric devices available

---

## Active Phases

*No active phases. Run `/new-milestone` to initialize next milestone.*
