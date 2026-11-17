# Biometric display name blank after software enrolment — fresh, code-and-log-only findings

Investigated: 2026-11-17 (system date). Device: eSSL MB20, serial `EUF7262500413`, `biometric_devices.id=6`, platform `ZLM60_TFT`, firmware `ZLM60-NF28VA-Ver2.0.12` (`storage/logs/laravel.log:82`). Member/PIN 9, fingerprint `FID=4` (`storage/logs/laravel.log:14`, `storage/logs/laravel.log:125`).

Scope (one line): only application source (`app/`, `config/`, `routes/`), tests (`tests/`), `storage/logs/laravel.log`, and read-only SQL via laravel-boost `database-query` were used as evidence; no documentation file was used.

## Bottom line (not a root-cause claim)

The application **does** put `Name=Stefan` on the wire and the device **does** ACK it (`storage/logs/laravel.log:139`, `storage/logs/laravel.log:140-143`; DB `adms_device_commands.id=12`, `status=acknowledged`, `device_return_code='0'`). Nothing in the application source can blank a name after serialization: every cdata handler only logs and returns `OK` (`app/Http/Controllers/Api/AdmsController.php:354-371`, `app/Http/Controllers/Api/AdmsController.php:380-396`, `app/Http/Controllers/Api/AdmsController.php:405-521`), and no code path writes a user record to the device from ingested data (grep of `app/` for `user_add|user_update|user_delete|command_payload` shows producers only in enrolment/propagation/reconciliation/erasure, all of which build a payload locally). The failure is therefore at or after the device's parsing of the 3-field `USERINFO` update. The ranked hypotheses below are falsifiable and are **not** resolved by the current log, because **no device roster query was issued after the failing write** (`storage/logs/laravel.log:144-199` contains only polls).

## Timestamped evidence timeline

All log references are `storage/logs/laravel.log:<line>`; DB facts are from read-only SELECTs and cite the row id.

| Time (2026-11-17) | Event | Evidence |
|---|---|---|
| 17:55:58 | Earlier software `user_add` for PIN 9 / "Stefan" created | DB `adms_device_commands.id=8`, payload `{"Pin":"9","Name":"Stefan"}`, `sent_at=17:56:24`, `acknowledged_at=17:56:36` |
| 18:00:15 | Device pushes `OPLOG 4` (user 0) | `:1-2` |
| 18:02:46 / 18:02:51 | `user_query` id 9 queued and sent as `C:9:DATA QUERY USERINFO PIN=9` | DB `adms_device_commands.id=9`; `:9` |
| 18:02:57 | Device returns native record `USER PIN=9\tName=Stefan\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000\tExpires=0\tStartDatetime=0\tEndDatetime=0\tValidCount=0` | `:10-11` |
| 18:03:13 | ACK for id 9: `ID=9&Return=0&CMD=DATA` | `:16-19` |
| 18:27:08 / 18:27:31 | Reboot id 10 sent as `C:10:CONTROL DEVICE 03000000` | DB `adms_device_commands.id=10`; `:69` |
| 18:27:38 | Reboot ACK `Return=0` | `:70-73` |
| 18:28:27–18:28:33 | Device re-registers: handshake with `pushver`, options, `OPTIONS` push (`UserExtFmt=`, `SupportUserPic=`) | `:79-82` |
| 18:30:10 / 18:30:17 | Manual keypad edit produces `OPLOG 70` for user 9 then `USER PIN=9\tName=Abctt\t...`; brief reports name now visible at punch-in | `:106-111` |
| 18:30:47 / 18:30:55 | `user_query` id 11 sent | DB `adms_device_commands.id=11`; `:120` |
| 18:31:04 | Device still reports `USER PIN=9\tName=Abctt\t...` | `:121-122` |
| 18:31:18 | ACK id 11 `Return=0` | `:127-130` |
| 18:32:35 | Re-enrolment: "Biometric enrollment initiated", member 9 / template 4 / device 6 | `:138`; DB `biometric_templates.id=4` (`gym_member_id=9`, `template_ref=NULL`, `enrolled_by_device_id=6`) |
| 18:32:47 | `user_add` id 12 sent as exactly `C:12:DATA UPDATE USERINFO PIN=9\tName=Stefan\tPrivilege=0` | `:139`; DB `adms_device_commands.id=12`, payload `{"Pin":"9","Name":"Stefan"}`, `user_table=USERINFO` |
| 18:32:57 | ACK id 12 `ID=12&Return=0&CMD=DATA`; app marks acknowledged | `:140-143`; DB `adms_device_commands.id=12` |
| 18:33:27 | Only `OPLOG 4` (user 0) arrives; **no** `USER PIN=9 ...` roster dump follows the write | `:148-149` |
| 18:33–18:59 | Only "no pending commands" polls; no further query of PIN 9 | `:144-199` |
| Final state | Member 9 name in DB is clean ASCII `Stefan` (`HEX=53746566616E`, `LENGTH=6`); template 4 is `synced`, `is_active=1`; command 12 is `acknowledged` | DB SELECT (read-only): `gym_members.id=9`, `biometric_templates.id=4`, `adms_device_commands.id=12` |

## Exact code path (source only)

1. `GET /api/iclock/getrequest` → `AdmsController::getRequest()` (`routes/api.php:267-268`, `app/Http/Controllers/Api/AdmsController.php:99`).
2. Heartbeat update, then atomic lease `AdmsDeviceCommand::leaseNext($device->id, ...)` (`app/Http/Controllers/Api/AdmsController.php:112-118`; implementation `app/Models/AdmsDeviceCommand.php:522-584`).
3. `$command->toAdmsCommandString()` (`app/Http/Controllers/Api/AdmsController.php:131`; `app/Models/AdmsDeviceCommand.php:350-376`). PIN via `serializedPin()` (`:383-394`), name via `serializedName()` (`:421-432`: prefers `Name`, then `name`, then `NAME`, then `''`; replaces `\x00-\x1F\x7F` with a space).
4. PIN-addressed branch selects schema from the snapshotted `user_table` (`:373-375`); `USERINFO` branch emits `C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tPrivilege=%d` (`:458-467`). The legacy branch emits `... DATA UPDATE user Pin=%s\tName=%s\tPri=0` (`:470-477`).
5. Empty-string guard releases the lease (`:133-147`), then `Log::info('ADMS: Command sent to device', ...)` (`:149-157`) — this is the line that produced `storage/logs/laravel.log:139`.
6. Response body is `rtrim($commandString) . "\r\n"` with `Content-Type: text/plain; charset=utf-8` (`app/Http/Controllers/Api/AdmsController.php:159`, `:573-576`). `rtrim()` strips only trailing whitespace; the name is followed by `\tPrivilege=0`, so it cannot strip the name (`app/Models/AdmsDeviceCommand.php:461-467`).
7. ACK path: `deviceCmd()` parses `ID`/`Return` and calls `markAcknowledged()` (`app/Http/Controllers/Api/AdmsController.php:181-226`). `markAcknowledged()` treats code `'0'` as success (`:710-720`, `:747-775`) and, for `user_add`, calls `reconcileUserAdd()` (`:769-771`, `:883-972`), which promotes the template to `Synced`/active (`:960-970`). No command is queued by this path.
8. Inbound data never writes to the device: `cdata` dispatch (`app/Http/Controllers/Api/AdmsController.php:63-93`), `handleDeviceDataPush()` for `USERINFO|USER|BIODATA|FINGERTMP|OPTIONS` (`:80-82`, `:354-371`), `handleOperlog()` including `OPLOG 4` (`:76-78`, `:380-396`), and `handleAttlog()` (`:405-521`) all log and return `OK`. `AdmsEventService::resolveMember()` resolves attendance only, via `template_ref` then strict positive member id (`app/Biometrics/Services/AdmsEventService.php:140-175`); it never writes a device record.
9. All `user_add` producers build the same two-key payload locally: `EnrollBiometricAction` (`app/Filament/Admin/Actions/EnrollBiometricAction.php:345-355`), `BiometricTemplatePropagator` (`app/Biometrics/Services/BiometricTemplatePropagator.php:105-115`, persisted-failure variant `:271-274`), `ReconcileBiometricEnrollmentsCommand` (`app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php:225-235`). `user_delete` producers carry `Pin` only (`app/Biometrics/Services/BiometricDeProvisioner.php:616-617`). `user_update` has **no producer** — it appears only as a presentation label / consent check (`app/Models/AdmsDeviceCommand.php:481`, `app/Filament/Admin/Resources/AdmsDeviceCommandResource.php:137,195`, `app/Models/AdmsDeviceCommand.php:1258-1263`).
10. Schema selection: global default `USERINFO` (`config/biometrics.php:77`; `app/Enums/AdmsUserTableEnum.php:23-26,63-74`), per-device override read from encrypted `provider_config['adms_user_table']` (`app\Models\BiometricDevice.php:305-309`; editable at `app/Filament/Admin/Resources/BiometricDeviceResource.php:557-567`). Command 12 snapshotted `USERINFO` (DB `adms_device_commands.id=12`).

## Hypotheses ruled out by this evidence

- **Name empty / mangled on the wire** — ruled out: `:139` contains `Name=Stefan`; DB `adms_device_commands.id=12` payload contains `"Name":"Stefan"`.
- **Hidden characters or charset corruption in the member name** — ruled out: DB `gym_members.id=9` name is `53746566616E` (6 clean ASCII bytes); `serializedName()` replaces control bytes (`app/Models/AdmsDeviceCommand.php:423-431`); response is declared UTF-8 (`app/Http/Controllers/Api/AdmsController.php:575-576`).
- **Inbound `USER`/`USERINFO`/`OPERLOG`/`OPLOG 4` push blanking the display** — ruled out: every handler only logs and returns `OK` (`app/Http/Controllers/Api/AdmsController.php:354-396`); no device-write call exists in the cdata path.
- **Wrong wire table for this device (`USERINFO` vs `user`)** — *mostly* ruled out: `DATA QUERY USERINFO PIN=9` (`:9`) returned a native `USER PIN=9 ...` record (`:11`), showing this firmware maps the `USERINFO` token to the same user table it dumps as `USER`. It cannot be fully excluded without the schema-swap experiment below.
- **PIN/fingerprint identity mismatch** — ruled out for the *device* display: device records bind `FP PIN=9 FID=4` (`:14`, `:125`) and `USER PIN=9` (`:11`, `:110`, `:122`); app-side PIN resolution is attendance-only (`app/Biometrics/Services/AdmsEventService.php:140-175`).
- **Stale firmware memory needing a reboot** — ruled out as a sufficient cause: reboot id 10 was sent and ACKed (`:69-73`) and the device re-registered (`:79-82`), yet the brief reports the name still blank.
- **Dual `Name=`/`NAME=` token variant** — already attempted by the operator per the task brief; not re-proposed here.

## Ranked, falsifiable hypotheses

### H1 — The device's punch-time display name is a different field from the `USER.Name` roster token the app writes (rank 1)

- **Evidence:** a software write had already produced `USER PIN=9\tName=Stefan\t...` (`:11`, following DB command id 8 sent at 17:56:24) while the reported symptom is a blank LCD name; a *keypad* edit produced `USER PIN=9\tName=Abctt\t...` (`:110`) and the name became visible. The two paths therefore differ in more than the string value. The app can only emit the roster form (`app/Models/AdmsDeviceCommand.php:461-467`).
- **Prediction / falsifier:** If H1 is the cause, then a `user_query` after the next software write will show `Name=Stefan` in the log while the LCD stays blank, and a write that mirrors the keypad record will make it visible; adding more `Name` token spellings to the same 3-field update will **not** help. If the post-write query instead shows `Name=` empty, H1 is refuted (the write really blanked the stored name).
- **Minimal experiment:** queue the read-only roster query after a fresh enrol and compare the returned `USER ... Name=...` body to the LCD. Commands:
  ```bash
  docker-compose exec app php artisan biometrics:query-user --device-id=6 --pin=9
  docker-compose exec app tail -n 200 storage/logs/laravel.log
  ```
  Look for `ADMS: Command sent to device` with `user_query`, then `ADMS: OPERLOG received from device` containing `USER PIN=9` (`:11`, `:110`, `:122` are the known-good shapes).

### H2 — The 3-field update is a full-record replacement on this firmware; omitted native fields reset the display-name cache (rank 2)

- **Evidence:** the wire sends only `PIN`, `Name`, `Privilege` (`:139`; `app/Models/AdmsDeviceCommand.php:461-467`), while the device's native record carries 10 fields including `Passwd=`, `Card=`, `Grp=1`, `TZ=...`, `Expires=`, `StartDatetime=`, `EndDatetime=`, `ValidCount=` (`:110`, `:122`). The unit test enforces the *absence* of `Passwd=`, `Card=`, `Grp=`, `TZ=` (`tests/Unit/Biometrics/AdmsDeviceCommandWireFormatTest.php:45-48`) and of `Pri=` (`:44`), so the app can never send the native field set the device itself uses. `Privilege=` (app) vs `Pri=` (device) is a direct token-shape mismatch (`:139` vs `:110`).
- **Prediction / falsifier:** If H2 is the cause, then adding the native field set to the update will preserve/show the name, and the current write will blank it even with `Name=Stefan`; any existing card would also be cleared by the current write and would reappear in the next dump. If a post-write query shows `Name=Stefan` **and** all native fields intact, H2 is refuted.
- **Minimal experiment:** temporarily change only the `USERINFO` `sprintf()` at `app/Models/AdmsDeviceCommand.php:461-467` to emit the native field set/tokens (`Pri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000\tExpires=0\tStartDatetime=0\tEndDatetime=0\tValidCount=0` in the order seen at `:110`), re-enrol from the same Filament action that produced `:138`, then run the two commands in H1 and compare the new `USER PIN=9 ...` dump. I did **not** modify source. A unit test mirroring `tests/Unit/Biometrics/AdmsDeviceCommandWireFormatTest.php:24-49` can assert the new exact string before the device run.

### H3 — `Return=0` acknowledges frame receipt only; the device silently ignored the name field, and app-side reconciliation masks it (rank 3)

- **Evidence:** the ACK body is `ID=12&Return=0&CMD=DATA` and names only `DATA`, not `UPDATE`/`USERINFO`/`Name` (`:140-142`; same shape at `:16-18`, `:127-129`). The app converts any `'0'` to success (`app/Models/AdmsDeviceCommand.php:720`, `:747-759`) and promotes the template to `Synced`/active (`:769-771`, `:960-970`), so DB `adms_device_commands.id=12` (`acknowledged`) and `biometric_templates.id=4` (`synced`, `is_active=1`) report success regardless of field-level application.
- **Prediction / falsifier:** If H3 is the cause, the post-write roster query returns the pre-write (blank or `Abctt`) name despite `Return=0`; a firmware that surfaced a field-level rejection would make the command `FAILED`. If the query returns `Name=Stefan`, the field *was* applied and H3 is refuted as the direct cause (H1/H2 may still hold).
- **Minimal experiment:** same read-only query as H1, plus a DB state check to expose the app/device divergence:
  ```bash
  docker-compose exec app php artisan biometrics:query-user --device-id=6 --pin=9
  docker-compose exec app php artisan tinker --execute="echo json_encode(App\Models\AdmsDeviceCommand::find(12)->only(['status','device_return_code'])); echo ' | '.json_encode(App\Models\BiometricTemplate::find(4)->only(['sync_status','is_active','hardware_confirmed_at']));"
  docker-compose exec app tail -n 200 storage/logs/laravel.log
  ```

### H4 — Per-device wire schema override is needed on this firmware (mostly ruled out, rank 4)

- **Evidence:** the code supports a per-device `adms_user_table` override (`app\Models\BiometricDevice.php:305-309`, UI at `app/Filament/Admin/Resources/BiometricDeviceResource.php:557-567`), command 12 used the global `USERINFO` default (DB `adms_device_commands.id=12`; `config/biometrics.php:77`), and the alternative serializer would emit `C:{id}:DATA UPDATE user Pin=9\tName=Stefan\tPri=0` (`app/Models/AdmsDeviceCommand.php:470-477`). Against it: `DATA QUERY USERINFO PIN=9` already returned the device's native `USER` record (`:9` → `:11`).
- **Prediction / falsifier:** If H4 is the cause, setting device 6 to `user` will change the logged wire string to the `user`/`Pin`/`Pri=0` form and make the display show the name; if the wire changes but the display stays blank, H4 is refuted.
- **Minimal experiment:** Filament Admin → Biometric Devices → edit device 6 → set "ADMS User Table Format" to `user` (`app/Filament/Admin/Resources/BiometricDeviceResource.php:557-567`), re-enrol from `GymMemberResource`, then:
  ```bash
  docker-compose exec app tail -n 200 storage/logs/laravel.log
  ```
  Verify the logged `command_string` and the subsequent `USER PIN=9` dump. Revert the selector to blank afterwards (`:560-566`).

### H5 — A protocol-level refresh/reload after the write is missing (plain reboot already shown insufficient), rank 5

- **Evidence:** no code queues any follow-up after `user_add`; `markAcknowledged()` only reconciles local template state (`app/Models/AdmsDeviceCommand.php:767-771`, `:883-972`). The only device-level "reload" command is `reboot` (`app/Console/Commands/AdmsRebootDeviceCommand.php:15-19`), and an unknown command type would fall through to `C:{id}:INFO` (`app/Models/AdmsDeviceCommand.php:365`), so the app cannot emit a distinct refresh verb. Reboot id 10 was already ACKed without fixing the display (`:69-82`).
- **Prediction / falsifier:** If H5 is the cause, a distinct refresh/reload command issued *after* the user write will make the name visible, while another plain reboot will not. If a post-write query already shows the correct name and only the LCD is stale, H5 gains weight; if the device has no such verb for this firmware, H5 is refuted.
- **Minimal experiment:** after a fresh enrol, re-run `biometrics:query-user` (H1 command) to confirm stored state, then add a temporary serializer branch at `app/Models/AdmsDeviceCommand.php:358-366` for a refresh verb and queue it via a temporary artisan command modelled on `app/Console/Commands/AdmsRebootDeviceCommand.php:13-19`; observe the new `command_string` in `laravel.log` and the LCD. I did **not** modify source.

## Exact safe commands (appendix)

Read-only / observation only. Do **not** run `migrate:fresh`, `db:seed`, `migrate:reset`, `migrate:rollback`, or any unqualified `php artisan` DB command — those would target `gym_app`. The following only enqueue ADMS commands or read logs/DB through the existing app.

```bash
# 1. Ask the device for its current PIN 9 roster record (queues a user_query only)
docker-compose exec app php artisan biometrics:query-user --device-id=6 --pin=9

# 2. Read the resulting wire string and the device's USER record dump
docker-compose exec app tail -n 200 storage/logs/laravel.log

# 3. Confirm app-side state after the failing write
docker-compose exec app php artisan tinker --execute="echo json_encode(App\Models\AdmsDeviceCommand::find(12)->only(['status','device_return_code','command_payload','user_table']));"

# 4. (Already done historically — do NOT repeat unless intentionally testing) reboot
# docker-compose exec app php artisan biometrics:reboot-device --device-id=6
```

Re-enrolment that produces the failing command is a UI action on `GymMemberResource` → `EnrollBiometricAction` (`app/Filament/Admin/Actions/EnrollBiometricAction.php:345-355`), the same path that logged `storage/logs/laravel.log:138-139`.

## Empirical A/B loop results (2026-11-17 19:04–19:32 IST)

A tight feedback loop was built: queue a candidate `user_add`, let the device poll it, read the device's own roster back with `DATA QUERY USERINFO`, and have the operator observe the physical LCD. Every candidate below was delivered, ACKed, and the LCD was checked by the operator.

| # | Time | Wire candidate (PIN 9 unless noted) | ACK | Device roster dump | LCD |
|---|---|---|---|---|---|
| 1 | 19:04 | `Name=Stefan\tPrivilege=0` (current code) | `0` (`laravel.log:207-211`) | `Name=Stefan` (`laravel.log:214`) | **blank** |
| 2 | 19:14 | `Name=Stefan\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=...` (full native struct) | `0` (`laravel.log:271-275`) | `Name=Stefan` | **blank** |
| 3 | 19:20 | `Name=Stefan` only (name as final token) | `0` (`laravel.log:315-319`) | `Name=Stefan` | **blank** |
| 4 | 19:24 | brand-new PIN 99, `Name=ZTEST99` | `0` (`laravel.log:349-353`) | `Name=ZTEST99` (`laravel.log:356`) | **blank** |
| 5 | 19:26 | lowercase `DATA UPDATE user Pin=98 ...` | `-1004` (`laravel.log:372-376`) | n/a | n/a |
| 6 | 19:30 | uppercase `DATA UPDATE USER PIN=97 ...` | `-1004` (`laravel.log:390-394`) | n/a | n/a |
| 7 | 20:06 | dual-token `Name=Stefan\tNAME=Stefan\tPri=0\tPrivilege=0` | `0` (`laravel.log:479-483`) | `Name=Stefan` (`laravel.log:486`) | **blank** |

Test user PIN 99 was removed afterwards (`laravel.log:400-407`). All temporary serializer instrumentation was reverted; `git diff -- app/` is empty and `AdmsDeviceCommandWireFormatTest` still passes 10/10.

### Conclusion from the loop

H1 is confirmed in its strongest form: the device's ADMS roster contains the correct name after every accepted write, but the TFT GUI never renders a name written over ADMS — **not even for a brand-new user created entirely over ADMS** (row 4). This rules out H2 (field-set shape), H4 (schema override), and H5 (stale cache / missing refresh): a newly added user is necessarily loaded into any GUI list, and its name still rendered blank. `Return=0` is receipt-only (H3) and the app's `Synced` promotion (`app/Models/AdmsDeviceCommand.php:720,769-771`) therefore overstates what happened.

This is a firmware limitation of this ZLM60_TFT build: the display-name store is written only by the local keypad path. No `DATA UPDATE` variant available in this protocol populates it.

### Actionable outcome

- **No wire-format code change can fix this symptom.** Do not keep changing `userCommandString()`.
- **Operational fix:** after any software enrollment / re-enrollment, the operator must enter the name on the terminal keypad — that is the only observed path that writes the GUI name. `biometrics:query-user` will show the roster name but cannot confirm the LCD.
- **App-side honesty fix (recommended):** stop treating `Return=0` on `user_add` as "name applied". Surface an explicit operator instruction after enrollment that the keypad name entry is still required, so reception staff do not assume the display is set. A regression test can assert the wire string, but it cannot assert the LCD — that limitation should be documented rather than papered over with a false-green test.
- **Firmware route:** if names must be set remotely, this requires a device/firmware capability beyond the current ADMS `DATA UPDATE USERINFO` command set; obtain vendor support for the GUI-name write before attempting further protocol experiments.
