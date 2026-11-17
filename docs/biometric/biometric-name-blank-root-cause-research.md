# Biometric Name Blank on eSSL MB20 — Root-Cause Research & Fix

> **Status:** Research findings (primary-source verified) · **Date:** 2026-11-17
> **Target device:** eSSL MB20, serial `EUF7262500413`, firmware `ZLM60-NF28VA-Ver2.0.12`
> **Live device row:** `biometric_devices.id = 6`, name `Back Door - ADMS` (DB query, `biometric_devices`)
> **Method:** graphify → repository source → ADRs → tests → `storage/logs/laravel.log` → read-only DB query. All claims cite the owning source with `path:line`.

---

## 1. Symptom

A member is enrolled on the terminal via this application but the **name is blank on the LCD / User-Mgt menu** while the **PIN (ID) and fingerprint are present**. Saving the name manually on the keypad works; re-enrolling from the software blanks it again. Adding a fingerprint for attendance shows the ID but a blank name. Running `php artisan biometrics:query-user` **does return the name**.

This exact symptom is already recorded in the repository: web-initiated enrollment pushes `DATA UPDATE USERINFO … Name=Hiren …`, the device returns `Return=0`, telemetry confirms the internal SQLite DB stores `Name=Hiren`, yet the LCD `Menu -> User Mgt -> All Users -> 369 -> Edit` shows `Name: [blank]` ([`docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md:21-25`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)). Six earlier wire-format combinations all returned `Return=0` yet left the name blank ([`docs/biometric/adms-userinfo-name-synchronization-findings.md:21`](adms-userinfo-name-synchronization-findings.md), [`:30-49`](adms-userinfo-name-synchronization-findings.md)).

---

## 2. Root cause (the decisive finding)

**The wire format is not what blanks the name on the LCD.** The name is written correctly to the device database and the device acknowledges the command. The blank display is a **GUI-process cache synchronisation problem on ZLM60_TFT firmware**, with a second contributing path at keypad fingerprint enrollment.

### 2.1 Primary cause — ADMS daemon writes SQLite; TFT GUI reads a separate in-memory cache

- The ADMS push client (`iclock` daemon) is a headless background process that writes records directly to on-disk SQLite (`/mnt/mtd/device.db`) ([`docs/adr/adr-0011-…:27-29`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)).
- The LCD UI is an independent foreground process (`main` / `ssr_tft`) that reads an **in-memory struct cache (`user.dat`) loaded at boot** ([`docs/adr/adr-0011-…:29`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)).
- On standard MB20 firmware, **background database mutations do not trigger an asynchronous GUI memory reload until the device restarts / warm-boots** ([`docs/adr/adr-0011-…:30`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)). A reboot forces the GUI to re-read `device.db` and display the name ([`docs/adr/adr-0011-…:50-51`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)).

### 2.2 Live hardware proof that the name IS on the device

- `user_add` commands for PIN `9` carry `{"Pin": "9", "Name": "Stefan"}`, snapshot `user_table = USERINFO`, and are `acknowledged` with `device_return_code = "0"` (read-only query of `adms_device_commands` ids **4** at 15:43:04 and **8** at 17:55:58 for `device_id = 6`).
- After that `user_add`, the query command sent as `C:9:DATA QUERY USERINFO PIN=9` ([`storage/logs/laravel.log:9`](../../storage/logs/laravel.log)) made the device dump its record as `USER PIN=9\tName=Stefan\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000…` via `OPERLOG` ([`storage/logs/laravel.log:11-12`](../../storage/logs/laravel.log)), acknowledged with `Return=0` ([`storage/logs/laravel.log:16-19`](../../storage/logs/laravel.log)).
- A hardware reboot command (`CONTROL DEVICE 03000000`, DB command id **6**, 15:55:19) was already issued earlier that day — consistent with ADR-0011's prescribed remedy ([`docs/adr/adr-0011-…:48-51`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)).

Therefore: a `user_add` with the name is accepted and persisted; the only unexplained layer is the LCD, which ADR-0011 identifies as the unrefreshed GUI cache.

### 2.3 Secondary cause — keypad fingerprint enrollment writes `Name=""` and freezes the display profile

- Operators enrolling fingerprints on the numeric keypad skip the alphanumeric name prompt; the terminal commits `Name=""`, emits `OPLOG 4`, and firmware safeguard logic **locks the local display profile from being overwritten by subsequent `DATA UPDATE` commands** without a fresh purge or restart ([`docs/adr/adr-0011-…:32-34`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)).
- Live telemetry shows exactly this event: `OPLOG 4\t0\t2026-11-17 17:59:30` ([`storage/logs/laravel.log:1-2`](../../storage/logs/laravel.log)) — after the 17:55:58 `user_add`, before the 18:02 query.
- This explains "I add a fingerprint for attendance → ID shows, name blank": the keypad creates the user record with a valid PIN but an empty name ([`docs/adr/adr-0011-…:32-34`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)).

---

## 3. Why manual save works but software re-enroll blanks the name

| Path | Which process writes | Result |
|---|---|---|
| Manual keypad save | Foreground TFT GUI (`ssr_tft`) writes its own in-memory cache **and** storage | Name appears immediately on LCD ([`docs/adr/adr-0011-…:27-30`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)) |
| Software re-enroll | Headless ADMS daemon writes SQLite only; GUI cache not invalidated | DB has the name, LCD stays blank until reboot ([`docs/adr/adr-0011-…:27-30`, `:50-51`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)) |

The enrollment path itself does send the name. `EnrollBiometricAction` queues `user_add` with `command_payload = ['Pin' => (string) $member->id, 'Name' => $member->name]` ([`app/Filament/Admin/Actions/EnrollBiometricAction.php:345-355`](../../app/Filament/Admin/Actions/EnrollBiometricAction.php)); sibling fan-out does the same ([`app/Biometrics/Services/BiometricTemplatePropagator.php:105-115`](../../app/Biometrics/Services/BiometricTemplatePropagator.php), [`:271-274`](../../app/Biometrics/Services/BiometricTemplatePropagator.php)); the reconciler re-drive does too ([`app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php:228-231`](../../app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php)). The serializer extracts `Name`/`name`/`NAME` case-insensitively ([`app/Models/AdmsDeviceCommand.php:421-432`](../../app/Models/AdmsDeviceCommand.php)) and emits `C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPrivilege=0` ([`app/Models/AdmsDeviceCommand.php:458-467`](../../app/Models/AdmsDeviceCommand.php)).

---

## 4. Why `query-user` returns a name while the terminal displays blank

`biometrics:query-user` queues `user_query` with `['Pin' => $pin]` ([`app/Console/Commands/AdmsQueryUserCommand.php:64-88`](../../app/Console/Commands/AdmsQueryUserCommand.php)), serialised as `DATA QUERY USERINFO PIN={pin}` ([`app/Models/AdmsDeviceCommand.php:454-456`](../../app/Models/AdmsDeviceCommand.php)). The device answers by dumping its **ADMS-daemon/SQLite view** back over `POST /iclock/cdata?table=OPERLOG` ([`storage/logs/laravel.log:9-12`](../../storage/logs/laravel.log); documented at [`docs/biometric/feature/biometric-adms-push-protocol.md:277-278`](biometric-adms-push-protocol.md)).

That view contains the correct `Name=Stefan`. It is **not** the GUI's view: the two processes hold separate state, and only a full GUI restart reloads SQLite into RAM ([`docs/adr/adr-0011-…:22-30`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)). Hence "query returns a name, LCD shows blank" is expected, not contradictory.

---

## 5. Wire format: what the code actually sends (verified, and why it is a red herring for the LCD)

- Schema is chosen from the command row's snapshotted `user_table`, falling back to config then `USERINFO` ([`app/Models/AdmsDeviceCommand.php:350-376`](../../app/Models/AdmsDeviceCommand.php), [`:183-194`](../../app/Models/AdmsDeviceCommand.php), [`:224-230`](../../app/Models/AdmsDeviceCommand.php); [`app/Enums/AdmsUserTableEnum.php:56-74`](../../app/Enums/AdmsUserTableEnum.php)); global default is `USERINFO` ([`config/biometrics.php:7`](../../config/biometrics.php), [`:74-90`](../../config/biometrics.php)); per-device override key is `provider_config['adms_user_table']` ([`app/Models/BiometricDevice.php:294-310`](../../app/Models/BiometricDevice.php)).
- Live commands for the MB20 all have `user_table = USERINFO`; the query wire string logged is `C:9:DATA QUERY USERINFO PIN=9` with `"user_table":"USERINFO"` ([`storage/logs/laravel.log:9`](../../storage/logs/laravel.log)). So **query and enrollment use the same `USERINFO`/`PIN` dialect** — there is no dialect mismatch between them on this device.
- CRLF framing is correct: `rtrim($commandString) . "\r\n"` ([`app/Http/Controllers/Api/AdmsController.php:159`](../../app/Http/Controllers/Api/AdmsController.php); asserted at [`tests/Feature/AdmsProtocolTest.php:281-284`](../../tests/Feature/AdmsProtocolTest.php)).
- UTF-8 charset is set via `plainResponse()` → `Content-Type: text/plain; charset=utf-8` ([`app/Http/Controllers/Api/AdmsController.php:573-576`](../../app/Http/Controllers/Api/AdmsController.php); asserted at [`tests/Feature/AdmsProtocolTest.php:824-825`](../../tests/Feature/AdmsProtocolTest.php)). ADR-0010 identified missing charset as a historical text-drop cause ([`docs/adr/adr-0010-…:48-49`](../../docs/adr/adr-0010-biometric-adms-operlog-handling-and-clean-wire-token-standardization.md)) — it is already fixed.
- `Passwd`/`Card` are **not** emitted and payload overrides are ignored ([`app/Models/AdmsDeviceCommand.php:458-467`](../../app/Models/AdmsDeviceCommand.php), [`tests/Feature/AdmsProtocolSanitizerTest.php:108-127`](../../tests/Feature/AdmsProtocolSanitizerTest.php)). The safety warning that omitting these can clear locally enrolled credentials existed in ADR-0010 ([`docs/adr/adr-0010-…:89`](../../docs/adr/adr-0010-biometric-adms-operlog-handling-and-clean-wire-token-standardization.md)) but the equivalent in-code comment was deleted in commit `9730a982` and the fields were later removed entirely — worth re-documenting, but not the LCD cause.

**Conclusion:** changing `Name=` to `NAME=`, adding/removing `Passwd=`/`Card=`/`Grp=`/`TZ=`, or changing `Pri`↔`Privilege` has repeatedly returned `Return=0` and written the name to SQLite ([`docs/biometric/adms-userinfo-name-synchronization-findings.md:21-49`](adms-userinfo-name-synchronization-findings.md), `:262-308`, `:407-432`) without fixing the LCD — exactly what the GUI-cache model predicts.

---

## 6. Spec contradictions / risks found

1. **Wire format has flip-flopped with no stable hardware verification.** `Privilege=0\tCard=` → `Pri=0\tPasswd=\tCard=\tGrp=1\tTZ=…` in `9730a982` ("MB20 dropped names with Privilege= token") → back to `Privilege=0` in `fe053f37` ("Empty tokens wiped member names") (git history; current code [`app/Models/AdmsDeviceCommand.php:458-467`](../../app/Models/AdmsDeviceCommand.php)). ADR-0010 mandates **dual** tokens `Name=…\tNAME=…\tPri=0\tPrivilege=0` ([`docs/adr/adr-0010-…:83-88`](../../docs/adr/adr-0010-biometric-adms-operlog-handling-and-clean-wire-token-standardization.md)) while ADR-0013 mandates a **single** `Name=` + `Privilege=0` ([`docs/adr/adr-0013-…:45-60`](../../docs/adr/adr-0013-biometric-adms-userinfo-clean-wire-format-and-name-persistence.md)). Both claim live proof; neither explains the GUI cache.
2. **CHANGELOG vs ADR dialect contradiction.** CHANGELOG says the MB20 "strictly rejects lowercase `user` … mandating `USERINFO`" ([`CHANGELOG.md:61`](../../CHANGELOG.md)) yet also documents dual-token casing as the fix ([`CHANGELOG.md:56-59`](../../CHANGELOG.md)); ADR-0007 keeps lowercase `user` for modern devices ([`docs/adr/adr-0007-…:43-48`](../../docs/adr/adr-0007-biometric-adms-multi-device-table-schema-negotiation.md)). The follow-up "modern dialect" lead (`DATA UPDATE user Pin=…\tName=…\tGroup=…\tExpiredTime=…`) is **contradicted for this device by local evidence**: ADR-0010 records MB20 rejecting `user` with `-1004` ([`docs/adr/adr-0010-…:24-36`](../../docs/adr/adr-0010-biometric-adms-operlog-handling-and-clean-wire-token-standardization.md)) and live commands are all `USERINFO` with `Return=0`. Do **not** switch this MB20 to `user`.
3. **ADR POS-004 unverified.** ADR-0007 claims full BioTime 8.0/8.5 / Push SDK wire adherence ([`docs/adr/adr-0007-…:130-133`](../../docs/adr/adr-0007-biometric-adms-multi-device-table-schema-negotiation.md)); the findings file itself concedes no hardware capture evidence backs the spec claims beyond the one OPERLOG dump. Treat "spec says" as unverified.
4. **Test coverage gap.** No test asserts a `user_add` name on the **HTTP wire**. `AdmsProtocolTest`'s `getrequest` tests use `userDelete` only ([`tests/Feature/AdmsProtocolTest.php:271-293`](../../tests/Feature/AdmsProtocolTest.php), [`:859-875`](../../tests/Feature/AdmsProtocolTest.php)); auxiliary-table ingestion posts `'dummy'` and only checks `200 OK` ([`tests/Feature/AdmsProtocolTest.php:845-857`](../../tests/Feature/AdmsProtocolTest.php)); `AdmsProtocolSanitizerTest` checks the model serializer only ([`tests/Feature/AdmsProtocolSanitizerTest.php:71-127`](../../tests/Feature/AdmsProtocolSanitizerTest.php)). ADR-0007 IMP-004 promised updates that are not present for name-over-wire.
5. **Scope creep (noted, not fixed).** Outbound command payloads are logged with `Log::info('ADMS: Command sent to device', …)` ([`app/Http/Controllers/Api/AdmsController.php:149-157`](../../app/Http/Controllers/Api/AdmsController.php)) beyond ADR-0011 §4's inbound-audit logging scope ([`docs/adr/adr-0011-…:60-63`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)); it also logs the member name. Redaction exists for presentation ([`app/Biometrics/Services/BiometricLogRedactor.php:326-327`](../../app/Biometrics/Services/BiometricLogRedactor.php)) but not on this log line.
6. **No automatic post-enroll refresh.** `markAcknowledged()` calls `reconcileUserAdd()` on success ([`app/Models/AdmsDeviceCommand.php:767-771`](../../app/Models/AdmsDeviceCommand.php)), and `reconcileUserAdd()` only promotes template status ([`app/Models/AdmsDeviceCommand.php:883-927`](../../app/Models/AdmsDeviceCommand.php)); reboot commands are created **only** manually by `AdmsRebootDeviceCommand` ([`app/Console/Commands/AdmsRebootDeviceCommand.php:15`](../../app/Console/Commands/AdmsRebootDeviceCommand.php), [`:67`](../../app/Console/Commands/AdmsRebootDeviceCommand.php), [`:81`](../../app/Console/Commands/AdmsRebootDeviceCommand.php)) and `RebootBiometricDeviceAction` ([`app/Filament/Admin/Actions/RebootBiometricDeviceAction.php:107`](../../app/Filament/Admin/Actions/RebootBiometricDeviceAction.php), [`:128`](../../app/Filament/Admin/Actions/RebootBiometricDeviceAction.php)). This is the actual gap.

---

## 7. Concrete fix

### Immediate operational workaround (no code change)
After every enrollment / fingerprint registration on an MB20, run `php artisan biometrics:reboot-device --device-id=6` (signature [`app/Console/Commands/AdmsRebootDeviceCommand.php:15`](../../app/Console/Commands/AdmsRebootDeviceCommand.php)) or use the Filament **Reboot** action ([`app/Filament/Admin/Actions/RebootBiometricDeviceAction.php:45-53`](../../app/Filament/Admin/Actions/RebootBiometricDeviceAction.php), ADR-0012 [`:45-53`](../../docs/adr/adr-0012-biometric-terminal-remote-reboot-action-and-database-notification.md)). This forces `ssr_tft` to reload SQLite and render the name ([`docs/adr/adr-0011-…:50-51`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)).

### Code fix (recommended)
1. **Queue a display-refresh reboot automatically after a successful `user_add`** for ADMS/TFT devices. Hook into the success branch of `AdmsDeviceCommand::markAcknowledged()` ([`app/Models/AdmsDeviceCommand.php:767-771`](../../app/Models/AdmsDeviceCommand.php)) — or, cleaner, a dedicated action/listener invoked from `reconcileUserAdd()` ([`app/Models/AdmsDeviceCommand.php:883-927`](../../app/Models/AdmsDeviceCommand.php)) — that creates a `reboot` command (`C:{id}:CONTROL DEVICE 03000000`, serializer already at [`app/Models/AdmsDeviceCommand.php:360`](../../app/Models/AdmsDeviceCommand.php)). Reuse the in-flight duplicate guard from `AdmsRebootDeviceCommand` ([`app/Console/Commands/AdmsRebootDeviceCommand.php:67`](../../app/Console/Commands/AdmsRebootDeviceCommand.php)) and `RebootBiometricDeviceAction` ([`app/Filament/Admin/Actions/RebootBiometricDeviceAction.php:107`](../../app/Filament/Admin/Actions/RebootBiometricDeviceAction.php)) so a reboot loop is impossible (ADR-0011 §2, [`:53-55`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)). Gate it behind a config flag because reboot pauses check-ins for 30–60 s (ADR-0011 NEG-001, [`:76`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)).
2. **Do not change the wire format again for this bug.** Keep `C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPrivilege=0` ([`app/Models/AdmsDeviceCommand.php:458-467`](../../app/Models/AdmsDeviceCommand.php)) — it is acknowledged and the name is present on the device (DB ids 4/8 + log lines 9–12). Stop the `Privilege`/`Pri` oscillation; reconcile ADR-0010 and ADR-0013 into one decision backed by a captured `DATA QUERY USERINFO` payload.
3. **Handle the keypad-blank path explicitly.** If the operator registers the fingerprint on the keypad and skips the name, the GUI writes `Name=""` and locks the record ([`docs/adr/adr-0011-…:32-34`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)). Provide an operator action that, for a blank-name PIN, sends `DATA DELETE USERINFO PIN=<pin>` ([`app/Models/AdmsDeviceCommand.php:450-452`](../../app/Models/AdmsDeviceCommand.php)) followed by a clean `DATA UPDATE` and a reboot. Note ADR-0011 ALT-003 ([`:86-89`](../../docs/adr/adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)) correctly rejects doing this on **every** sync because delete purges on-chip templates — scope it to blank-name remediation with re-enrollment only.
4. **Tighten admin guidance.** In `EnrollBiometricAction` (or its modal helper text) tell operators that a keypad name must be entered or a reboot must follow; the enrollment code currently queues the command and logs success without any display-refresh step ([`app/Filament/Admin/Actions/EnrollBiometricAction.php:345-378`](../../app/Filament/Admin/Actions/EnrollBiometricAction.php)).

### Test recommendation (TDD)
- Add a feature test in `tests/Feature/AdmsProtocolTest.php`: queue a `user_add`, call `GET /api/iclock/getrequest?SN=…`, and assert the **body contains** `C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={memberName}\tPrivilege=0` and CRLF end. This closes the wire-level name gap identified in §6.4.
- Add a feature test asserting that acknowledging that `user_add` with `Return=0` queues **one** `reboot` command with wire string `C:{id}:CONTROL DEVICE 03000000` (and a second acknowledgement does not queue a duplicate). Reuse the in-flight guard tests in `tests/Feature/AdmsRebootDeviceCommandTest.php`.
- Extend `tests/Feature/AdmsProtocolSanitizerTest.php` so the `USERINFO` assertion set also covers the name-over-wire case, keeping the existing negative assertions ([`tests/Feature/AdmsProtocolSanitizerTest.php:71-127`](../../tests/Feature/AdmsProtocolSanitizerTest.php)).

Run with `composer test -- --filter=AdmsProtocol` / `--filter=AdmsRebootDeviceCommandTest` (PHPUnit uses `gym_app_test`; never run destructive commands against `gym_app`).

---

## 8. One-line answer

The software **is** writing the name to the MB20 (query proves it, `Return=0`); the LCD is blank because the ZLM60 TFT GUI process caches users in RAM and never reloads after an ADMS-background SQLite write until the device reboots — with keypad fingerprint enrollment independently writing `Name=""`. **Fix = automatically queue a `CONTROL DEVICE 03000000` reboot after a successful `user_add` (plus a keypad-blank remediation path), and stop changing the USERINFO wire format for this symptom.**
