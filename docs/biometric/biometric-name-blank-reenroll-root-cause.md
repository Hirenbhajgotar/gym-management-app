# Biometric name blanks after software re-enroll — root cause (device 6 / EUF7262500413)

## Scope of sources

Investigation used only: application source code, `storage/logs/laravel.log`, read-only SELECT queries against `adms_device_commands`, tests, git history, and `CHANGELOG.md`. No design-record documents were consulted. The working tree was not modified (only this findings file was added).

## Symptom and the decisive new test flow

Previous symptom: a member is enrolled, `biometrics:query-user` returns a name, a fingerprint is added, the terminal shows the numeric ID but the punch-in display name is blank.

New flow reported by the operator (device 6, serial `EUF7262500413`, PIN / member id `9`):

1. Ran `php artisan biometrics:reboot-device` (`app/Console/Commands/AdmsRebootDeviceCommand.php:15`) — **name still blank**.
2. Manually edited the name on the terminal keypad to `abctt` — **name appeared at punch-in**.
3. Re-enrolled from the software via `GymMemberResource` (`app/Filament/Admin/Resources/GymMemberResource.php:449`) — **name blank again**.

The decisive facts are: the reboot was genuinely executed, the keypad save genuinely wrote the name, and the software re-enroll actively destroyed the display name again.

## Timestamped log timeline (all times 2026-11-17 IST)

| Time | `laravel.log` | Event |
|---|---|---|
| 18:02:46 | — | DB row id **9** `user_query` `{"Pin":"9"}` created (acknowledged, `USERINFO`, return `0`) |
| 18:27:08 | — | DB row id **10** `reboot` created |
| 18:27:31 | `:69` | Command 10 sent: `C:10:CONTROL DEVICE 03000000`, `user_table=USERINFO` |
| 18:27:38 | `:70-73` | Device acked `ID=10&Return=0&CMD=CONTROL DEVICE`; row 10 `acknowledged` |
| 18:27:46, 18:28:40/41 | `:74-77`, `:83-87` | Duplicate acks for id 10, warned `devicecmd received for non-SENT command` (harmless duplicates — row already acknowledged) |
| 18:28:27 | `:79-80` | Device handshake after reboot (`pushver=2.4.1`, `PushOptionsFlag=1`) |
| 18:28:33 | `:81-82` | `OPTIONS` received: `~Platform=ZLM60_TFT`, `FWVersion=ZLM60-NF28VA-Ver2.0.12` — device re-registered, so the reboot **did execute** |
| 18:29:07–18:29:33 | `:96`, `:102` | `OPLOG 4` keypad operation records for PIN 0 (no member id) |
| 18:29:57 | `:106` | `OPLOG 70` for PIN 9 — operator activity on the member record |
| 18:30:05 | `:113` | `OPLOG 36` for PIN 9 |
| 18:30:17 | `:110` | Device's own user-record dump: `USER PIN=9\tName=Abctt\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000\tExpires=0\tStartDatetime=0\tEndDatetime=0\tValidCount=0` — the keypad-saved name is present in *all* device fields |
| 18:30:31 | `:115-118` | Punch processed; punch-in displays the name (operator report) |
| 18:30:47 / 18:30:55 | DB id **11**, `:120` | `user_query` `{"Pin":"9"}` sent: `C:11:DATA QUERY USERINFO PIN=9` |
| 18:31:04 | `:122` | Same full record dumped again: `USER PIN=9\tName=Abctt\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000...` |
| 18:31:10 | `:125` | `FP PIN=9\tFID=4 ...` — fingerprint template present on device |
| 18:31:18 | `:127-130` | Query command 11 acked, return `0` |
| 18:32:35 | `:138`, DB id **12** created | `Biometric enrollment initiated {"gym_id":1,"device_id":6,"member_id":9,"template_id":4,"operator_id":2}` — the software re-enroll from `GymMemberResource` |
| 18:32:47 | `:139` | Command 12 sent: `C:12:DATA UPDATE USERINFO PIN=9\tName=Stefan\tPrivilege=0` |
| 18:32:57 | `:140-143` | Command 12 acked, return `0`; row 12 `acknowledged` |
| 18:33:12 / 18:33:27 | `:149` | `OPLOG 4` again (PIN 0) following the re-enroll |
| after 18:33:27 | `:144-159` | Only empty polls. **No `USER PIN=9\tName=...` record dump follows** — the terminal display name is blank (operator report) |

### Read-only DB confirmation (`adms_device_commands`, device 6)

| id | type | payload | user_table | status | return | created / sent / ack |
|---|---|---|---|---|---|---|
| 9 | `user_query` | `{"Pin":"9"}` | USERINFO | acknowledged | `0` | 18:02:46 / 18:02:51 / 18:03:13 |
| 10 | `reboot` | `null` | USERINFO | acknowledged | `0` | 18:27:08 / 18:27:31 / 18:27:38 |
| 11 | `user_query` | `{"Pin":"9"}` | USERINFO | acknowledged | `0` | 18:30:47 / 18:30:55 / 18:31:18 |
| 12 | `user_add` | `{"Pin":"9","Name":"Stefan"}` | USERINFO | acknowledged | `0` | 18:32:35 / 18:32:47 / 18:32:57 |

Only one write command (`user_add` id 12) was queued; there is no `user_delete` and no `user_update` in this window.

## Exact re-enroll code path

1. `app/Filament/Admin/Resources/GymMemberResource.php:447-449` — the table row action group `Biometric Actions` contains `EnrollBiometricAction::make()`. `EditGymMember` also exposes the same helper as a header action via `makeForHeader()` (`app/Filament/Admin/Actions/EnrollBiometricAction.php:68-97`); the logged event shows the table-row/page action on member 9, but both funnel into the same `handle()`.
2. `app/Filament/Admin/Actions/EnrollBiometricAction.php:57-60` — `->action()` calls `self::handle($record, $data)`.
3. `EnrollBiometricAction::handle()` (`:199-428`) — after authorization and consent checks, inside one transaction:
   - `AdmsDeviceCommand::cancelDeleteCommands($device->id, $pins)` at `:299` — **cancels** pending `user_delete`; it does not queue one.
   - Supersedes older templates at `:308-314` (app-side DB only).
   - Creates the placeholder `BiometricTemplate` at `:325-335`.
   - Queues exactly **one** `AdmsDeviceCommand` at `:345-355`: `command_type = user_add`, `command_payload = ['Pin' => (string) $member->id, 'Name' => $member->name]`. For member 9 this produces `{"Pin":"9","Name":"Stefan"}` — matching DB row 12.
   - Cancels other in-flight `user_add`s at `:358`.
   - Emits the activity + `Log::info('Biometric enrollment initiated', ...)` at `:361-378` — matching `laravel.log:138`.
   - No template-push command, no reboot, no `user_delete` is queued by this path.
4. `app/Models/AdmsDeviceCommand.php:350-376` — `toAdmsCommandString()` resolves `user_table` (snapshotted at queue time in the `creating` hook at `:185-194`) and, for PIN-addressed commands, calls `userCommandString()`.
5. `app/Models/AdmsDeviceCommand.php:421-432` — `serializedName()` reads `$payload['Name'] ?? $payload['name'] ?? $payload['NAME'] ?? ''` and replaces control characters with spaces; `"Stefan"` survives intact.
6. `app/Models/AdmsDeviceCommand.php:458-467` — the USERINFO branch emits `C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tPrivilege=%d`, producing the exact wire string logged at `laravel.log:139`.
7. `app/Models/AdmsDeviceCommand.php:769-771` — on ack, a successful `user_add` calls `reconcileUserAdd()` (`:883-972`), which promotes the app-side template to `Synced`. That is why the app reports success while the terminal display remains blank: acknowledgement only proves the command was received (`AdmsDeviceCommand.php:710-775`, `laravel.log:143`), not that the LCD display name was populated.

## Root cause

### 1. "Missing / empty Name in payload" is ruled out

The payload is `{"Pin":"9","Name":"Stefan"}` (DB row 12) and the wire string contains `Name=Stefan` (`laravel.log:139`). `serializedName()` (`AdmsDeviceCommand.php:421-432`) handles the exact key used by the producer (`EnrollBiometricAction.php:351`). So this is **not** a null/blank-name or key-casing bug.

### 2. The `user_delete` + re-add hypothesis is ruled out

`EnrollBiometricAction::handle()` never queues a delete on this path (`:299` *cancels* deletes; the only queued command is the `user_add` at `:345-355`). DB rows 9–12 contain no `user_delete`. A fingerprint template (`FP PIN=9 FID=4`, `laravel.log:125`) coexists with the user record, so the credential was not erased and re-created.

### 3. Why the reboot did not fix it

- The opcode is correct: `reboot` serializes to `C:%d:CONTROL DEVICE 03000000` (`AdmsDeviceCommand.php:360`), which is exactly what was sent (`laravel.log:69`). The earlier `REBOOT`/`Restart` application-layer forms are gone.
- The command was acknowledged, not swallowed (`laravel.log:70-73`, DB row 10 return `0`), and the device re-registered with `OPTIONS` (`laravel.log:79-82`), so the in-flight guard at `AdmsRebootDeviceCommand.php:65-75` did not block the needed reboot.
- Rebooting only forces the terminal's GUI process to reload its records from flash into display RAM (`CHANGELOG.md:24`). The flash record's display-name field had never been written by the software `user_add` (see §4), so the reboot simply reloaded the same blank value. **A reboot cannot repair an incomplete write.** This is why "just reboot after enrollment" is not a fix.
- The later re-enroll did overwrite the good keypad value, but that happened *after* the reboot and *after* the manual save (`laravel.log:138-139` vs `:110/:122`), so it explains the final blank state, not the still-blank state immediately after the reboot.

### 4. Why the manual keypad save worked, and why re-enroll blanks it — the token-shape mismatch

Compare the two writes:

- **Manual keypad save** produced a device record with the complete native field set: `USER PIN=9\tName=Abctt\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000\tExpires=0\tStartDatetime=0\tEndDatetime=0\tValidCount=0` (`laravel.log:110`, repeated `:122`). The device wrote all of its own user-record fields, including the one its LCD/punch-in display reads.
- **Software re-enroll** sent only `C:12:DATA UPDATE USERINFO PIN=9\tName=Stefan\tPrivilege=0` (`laravel.log:139`, produced by `AdmsDeviceCommand.php:461-467`). It omits the uppercase `NAME=` token (absent since `9730a982`), omits `Pri=`, and uses `Privilege=`.

The repository's own hardware telemetry documents that this exact omission breaks the display name on this device family:

- `CHANGELOG.md:58` — the dual-token format "**Fixes blank LCD display names on eSSL MB20 and Linux/ZEM terminals (`ZLM60_TFT`) that strictly require uppercase `NAME=`**". Device 6 reports `~Platform=ZLM60_TFT` (`laravel.log:82`).
- `CHANGELOG.md:59` — "Confirmed via live hardware telemetry that eSSL MB20 **drops mixed-case `Name=` and clears previously stored names if `NAME=` is absent**."
- `CHANGELOG.md:61` — MB20 "strictly rejects lowercase `user` table schema (`return_code: -1004`), mandating `USERINFO`" — so the app's `USERINFO` table key (`laravel.log:139`, DB row 12 `user_table=USERINFO`) is correct; the `USER` label in the device's own dump (`laravel.log:110`) is the device's internal record format, not a signal to switch schema.
- Git history establishes the exact commit ordering (verified with `git show`):
  - `09fca465` (Nov 5) changed the USERINFO wire to `Name=%s\tNAME=%s\tPri=0\tPrivilege=0` — dual NAME, message "**Add uppercase NAME alongside mixed-case Name / Restore display names on eSSL MB20 terminals**".
  - `9730a982` (Nov 7) changed it to `Name=%s\tPri=%d\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000` — this commit **removed the uppercase `NAME=` token and `Privilege=`**, with message "**MB20 dropped names with `Privilege=` token / Use `Pri` and native access control fields**".
  - `fe053f37` (Nov 17 11:16, HEAD~1) changed it from that native full struct to the current `Name=%s\tPrivilege=%d` — it removed `Pri=`, `Passwd=`, `Card=`, `Grp=`, `TZ=`; it did **not** remove `NAME=`, which had already been gone since `9730a982`. Its message claims "**Empty tokens wiped member names on terminals / Use Privilege= instead of legacy Pri= / Drop speculative Grp= and TZ=**".
  - `git blame -L 44,62 CHANGELOG.md` confirms the contradiction is baked into the file: lines 47-51 (clean format canonical, empty tokens wipe names) come from `fe053f37`; lines 56-61 (dual `NAME=` required, MB20 drops mixed-case `Name=` if `NAME=` absent) come from `09fca465`. Both claim live hardware proof.

The current HEAD format differs from **both** historically hardware-validated formats. `fe053f37` ("use clean USERINFO wire format", 2026-11-17 11:16 IST, hours before this test; HEAD `b3ede4e7` contains it) changed the serializer from the native full struct (`9730a982`) to `Name=%s\tPrivilege=%d` only, removing `Pri=`, `Passwd=`, `Card=`, `Grp=` and `TZ=`. It did not remove `NAME=` — that token had already been dropped two commits earlier by `9730a982`. The resulting current format is therefore a token set that was never hardware-validated by any of the earlier "worked" commits: it contains exactly the `Privilege=` token that `9730a982` said makes MB20 drop names, and lacks the uppercase `NAME=` token that `09fca465` said ZLM60_TFT requires for the LCD display. The operator's 18:32 test (`laravel.log:139`, blank display afterwards) is a live confirmation that this unvalidated format fails. `Return=0` (`laravel.log:143`) only means the terminal accepted the command string, not that it applied the name to its display field.

**Root cause: the serializer regressed to an unvalidated token set.** The `user_add` wire string emitted by `app/Models/AdmsDeviceCommand.php:461-467` (`Name=Stefan\tPrivilege=0`, `laravel.log:139`) differs from both hardware-validated formats — `09fca465`'s dual-token `Name + NAME + Pri + Privilege` and `9730a982`'s native `Name + Pri + Passwd + Card + Grp + TZ` — and does not populate the device's display-name field. This is *not* because the name is absent: the payload is `{"Pin":"9","Name":"Stefan"}` (DB row 12) and `Name=Stefan` is on the wire. The command is acknowledged (`Return=0`), which proves receipt only, not display population; the display name is left blank, overwriting the good keypad-written value.

### 5. Why `biometrics:query-user` returns a name

`biometrics:query-user` (`app/Console/Commands/AdmsQueryUserCommand.php:12-19`) queues `DATA QUERY USERINFO PIN={pin}` (`AdmsDeviceCommand.php:363`). In this log the query was issued at 18:30:55 (`laravel.log:120`, DB row 11) **after** the keypad save, and the device dumped its record containing `Name=Abctt` (`laravel.log:122`). So query-user reflected the keypad-written value. More generally, the flash/queryable record and the LCD display-name field are different things on this firmware — `CHANGELOG.md:58` explicitly describes "blank LCD display names" and `CHANGELOG.md:59` distinguishes the stored `Name=` from the uppercase `NAME=` needed for the display. That is how a queryable name can coexist with a blank punch-in display (the earlier symptom). Limitation: this log contains no query after command 12, so the post-re-enroll queryable value was not directly observed.

### 6. Role of the `OPLOG 4` events

`OPLOG 4` appears at `laravel.log:96`, `:102` (around the manual save) and `:149` (after re-enroll), with `OPLOG 70` (`:106`) and `OPLOG 36` (`:113`) for PIN 9. These are device-native operational/audit records pushed to `/iclock/cdata?table=OPERLOG` and are evidence of local keypad activity. The app **does not interpret them**: `AdmsController::handleOperlog()` (`app/Http/Controllers/Api/AdmsController.php:380-396`) only logs the raw body and returns `OK`. They are corroborating telemetry, not a cause and not something the app acts on.

## Concrete fix

### A. Code fix (must)

Restore the hardware-required tokens in the USERINFO branch of `userCommandString()` — `app/Models/AdmsDeviceCommand.php:458-467`. Replace the body with the dual-token format documented at `CHANGELOG.md:57` and emitted by commit `09fca465`:

```php
        if ($wireTable === 'USERINFO') {
            // ZLM60_TFT / eSSL MB20 LCD display names require the uppercase
            // NAME= token; mixed-case Name= and Pri= keep the queryable record
            // and standard ZKTeco firmware compatible. Passwd=/Card= stay
            // omitted so existing terminal credentials are not cleared.
            return sprintf(
                "C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tNAME=%s\tPri=0\tPrivilege=0",
                $this->id,
                $pin,
                $name,
                $name
            );
        }
```

Also:
- Update the schema docblock at `app/Models/AdmsDeviceCommand.php:435-443` and the format description at `:333-349` (they currently describe the clean format).
- Remove the now-unused `$privilege = 0;` at `app/Models/AdmsDeviceCommand.php:448` so Larastan does not flag it.
- This single change covers both `user_add` and `user_update` (both flow through the same branch at `:458-467`), so re-enroll and any later name edit are both fixed.

Resulting wire string for this incident: `C:12:DATA UPDATE USERINFO PIN=9\tName=Stefan\tNAME=Stefan\tPri=0\tPrivilege=0` — `USERINFO` schema (required), `NAME=` for the LCD display (`CHANGELOG.md:58-59`), `Pri=0` for MB20 (`9730a982`), no `Passwd=`/`Card=` so credentials are preserved (`CHANGELOG.md:60`).

### A2. Mandatory A/B hardware verification before rollout

The repository's own CHANGELOG contradicts itself on which format is correct (see Risks §1), so code review alone cannot settle this — verification on the physical ZLM60_TFT (device 6) is mandatory:

1. **Candidate A (recommended first):** the dual-token format above (from `09fca465`, `CHANGELOG.md:57`). It is the closest validated format and the only one carrying both `NAME=` (for the LCD display) and `Pri=` (for MB20), without the empty `Passwd=`/`Card=` tokens that `fe053f37` warns wipe names.
2. After the re-enroll is delivered, run `php artisan biometrics:query-user --device-id=6 --pin=9` (`app/Console/Commands/AdmsQueryUserCommand.php:12-19`) and confirm **both**: (a) the queryable flash record's name, and (b) the physical LCD/punch-in display name at the terminal. An ACK alone is not sufficient evidence.
3. **Candidate B (only if A still blanks the display):** restore the full native struct from `9730a982` — `C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tPri=%d\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000` — and repeat the same two checks. `CHANGELOG.md:49` warns this form wiped names, which is why it is the fallback and why hardware verification must decide between them.

### B. Tests to update / add

- `tests/Unit/Biometrics/AdmsDeviceCommandWireFormatTest.php:14-19` — update the spec docblock (it currently asserts "no duplicate or all-caps `NAME=`" and `Privilege=0` only).
- `tests/Unit/Biometrics/AdmsDeviceCommandWireFormatTest.php:39`, `:43-48`, `:67` — change expected strings to the dual-token format; convert the negative assertion on `NAME=` (`:43`) into a positive one (`assertStringContainsString('NAME=Hiren', ...)`) and assert `Pri=0` is present; keep the negative assertions for `Passwd=`, `Card=`, `Grp=`, `TZ=`.
- `tests/Feature/AdmsProtocolSanitizerTest.php:83`, `:101`, `:124` — update the expected clean-format strings; `:35-36` (sanitization) stays valid.
- **New regression test**: call `EnrollBiometricAction::handle()` for a member named e.g. `Stefan` against device 6, fetch the queued `user_add`, and assert `toAdmsCommandString()` contains `NAME=Stefan` and `Pri=0` and starts with `DATA UPDATE USERINFO PIN=9`. This directly guards the operator's flow (`EnrollBiometricAction.php:345-355` → `AdmsDeviceCommand.php:461-467`).

Run the affected tests on `gym_app_test` only: `docker-compose exec app composer test --filter=AdmsDeviceCommandWireFormatTest` (see `AGENTS.md` DB safety rules). Do not run destructive commands against `gym_app`.

### C. Immediate operational workaround (no deploy)

After a software re-enroll, edit the name at the terminal keypad (the flow that worked at `laravel.log:110/:122`), or queue a `user_add` carrying the dual-token wire string directly. Do **not** rely on a reboot — it reloads the same blank record and cannot populate the display name (`CHANGELOG.md:24`).

## Risks, contradictions, and residual uncertainty

1. **The repository contradicts itself, and the commit ordering matters.** `git blame -L 44,62 CHANGELOG.md` shows the "clean format is canonical / empty tokens wipe names" entry (`CHANGELOG.md:47-51`) was added by `fe053f37` (Nov 17), while "uppercase `NAME=` required / MB20 drops mixed-case `Name=` when `NAME=` is absent" (`CHANGELOG.md:56-61`) was added by `09fca465` (Nov 5). Both claim live hardware proof. In between, `9730a982` (Nov 7) removed `NAME=` and `Privilege=` in favour of the native full struct, explicitly because "MB20 dropped names with `Privilege=` token". The live evidence in this incident (`laravel.log:110/:122` working vs `:139` failing) shows the current `fe053f37` format fails, but does not by itself arbitrate between the two earlier validated formats — hence the mandatory A/B test in §A2 before rollout.
2. **Firmware internals are not observable from this repo.** The claim that `NAME=` populates the LCD display field is inferred from the documented hardware telemetry (`CHANGELOG.md:58-59`) plus the log evidence, not from device source. Candidate A and Candidate B in §A2 are both hypotheses drawn from historically validated commits; neither is proven for this exact firmware build (`~Platform=ZLM60_TFT`, `FWVersion=ZLM60-NF28VA-Ver2.0.12`, `laravel.log:82`). Hardware verification decides.
3. **No post-re-enroll device dump.** The log ends at `:159` without a `DATA QUERY USERINFO` after command 12, so whether the queryable record name is also blanked (vs only the LCD display) is not directly proven. Adding a query after re-enroll in a follow-up test would close this gap.
4. **`Return=0` is misleading.** Acknowledgement (`AdmsDeviceCommand.php:749-771`, `laravel.log:143`) and template promotion to `Synced` (`:883-972`) both occurred, so the app reports success. Any monitoring that equates ACK with "display name applied" will miss this class of bug.
5. **Unrelated noise, not causes:** the "non-SENT command" warnings (`laravel.log:77`, `:86`) are duplicate acks after the reboot was already acknowledged; `OPLOG` records are inert (`AdmsController.php:380-396`). Neither contributes to the blank name.
