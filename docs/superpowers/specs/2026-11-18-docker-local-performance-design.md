# Make the Docker local environment fast (Option A)

Date: 2026-11-18
Status: awaiting approval

## Intent / why

Local pages, Livewire/Filament button clicks and the general dev loop are much slower
than the live site. The user uses Docker Desktop on Windows.

Measured on the running stack (`GET /login`, Debugbar timings):

- Cold request: 7–21s. Warm request: anywhere from 0.2s to 7s (very inconsistent).
- ~8.7s of each request was `Filament\FilamentServiceProvider::boot()` doing component
  discovery from scratch (panel resources / pages / widgets). No DB queries were involved.
- The 1–7s swings came from OPcache revalidating ~1,500 PHP file timestamps across the
  Windows → Docker bind mount (`.:/var/www/html`) on every request.
- Debugbar added ~70ms boot and a ~55KB HTML payload per response, and its AJAX handler
  ran on every Livewire update.
- `docker-entrypoint.sh` ran `config:clear` + `cache:clear` on every start.

After running `php artisan filament:optimize` once, repeated requests became ~0.2s when
OPcache was warm — confirming the diagnosis.

## Goals

- Pages and Livewire/Filament actions load consistently in roughly 0.2–0.4s.
- No multi-second swings between requests.
- Keep the bind mount so source edits still appear on the host immediately.
- Changes must be reversible and reviewable; no application logic changes.

## Non-goals

- Changing PHP/Laravel/Filament dependency versions.
- Converting route closures to controllers so `route:cache` can be enabled.
- Reworking `composer run dev` / Vite HMR (the Vite port is not exposed today, but this
  does not affect page load speed — assets are already built into `public/build`).
- Making the PHPUnit suite itself fast (tracked separately below, needs explicit approval).
- Any change to the production/live deployment path (`.scripts/deploy.sh` already runs
  `filament:optimize`).

## Anti-goals

- Cache something that makes tests read local config and accidentally target `gym_app`
  instead of `gym_app_test`. The DB-safety guard in `tests/TestCase.php` would fail the
  run, but the risk is unacceptable.
- A "fix" that silently serves stale Filament components or Blade views after a developer
  adds a new resource or edits a view, with no obvious way to refresh.

## Constraints

- Windows host + Docker Desktop bind mount is the root of the stat-cost problem.
- `routes/web.php` and `routes/api.php` contain closures → `route:cache` cannot be used.
- Tests run in the same container via CLI, where `opcache.enable_cli = false` and Filament
  deliberately skips its component cache (`! app()->runningInConsole()`).
- Existing folder structure must be reused: `docker/` already exists.
- No new dependencies; no changes to `composer.json` requirements.

## Approaches considered

| Approach | Pages/buttons | Edit workflow | Verdict |
|---|---|---|---|
| A — OPcache timestamps off + cached Filament components + reset command | fastest, most consistent (~0.2s) | one reset command after PHP/Blade edits | **chosen** |
| B — `opcache.revalidate_freq=30`, keep auto-refresh | ~0.3–0.5s | edits appear within 30s | rejected: still pays stat cost periodically |
| C — copy source into image, no bind mount | fastest | rebuild image for every edit | rejected: worst developer experience |

A was chosen. The deterministic wins (Filament component cache, OPcache tuning, Debugbar
default) are shared by all three; the distinction is the timestamp-validation tradeoff.

## Decisions

### 1. OPcache tuning (new file `docker/php/opcache.ini`)

```
opcache.enable=1
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.save_comments=1
```

- `validate_timestamps=0` removes the per-request `stat` storm — this is the core fix.
- Memory/file limits raised because `vendor/` alone has ~30,000 PHP files; the current
  128MB / 10,000 files is too small and causes eviction/recompile churn.
- `save_comments=1` is kept explicitly (Laravel relies on attributes/metadata).
- JIT buffer stays at its default 0 (effectively off); no need to change it.
- Copied into the image in the `Dockerfile` as
  `COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini`.

### 2. Warm the caches at startup (`docker-entrypoint.sh`)

Remove `php artisan cache:clear`. Keep `php artisan config:clear` as a safety net (it
also guarantees no config cache can leak into the test run).

Add (failures tolerated, startup-only cost):
```
php artisan filament:optimize || true
php artisan view:cache || true
```

**Do NOT add `config:cache`.** Tests run in this same container; a config cache built
from local `.env` would make `php artisan test` read local values instead of the forced
`phpunit.xml` testing values. The existing `config:clear` is kept as a safety net.

`route:cache` is also excluded because of the closure routes.

Keep the existing `chown -R` and `storage:link` — removing them risks breaking writes.
The chown is not in the request path, so it does not affect page speed.

### 3. Reset workflow (`scripts/opcache-reset.sh`)

Because `validate_timestamps=0`, PHP and Blade changes need an explicit refresh:

```bash
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose exec -T app php artisan view:clear
docker compose exec -T app php artisan filament:optimize
docker compose restart app
```

- `view:clear` removes compiled Blade files.
- `filament:optimize` re-discovers newly added panels/resources/pages/widgets.
- `docker compose restart app` destroys the FPM master and its OPcache SHM, so the next
  request recompiles from the bind mount. No public reset endpoint, no token to secure.
- Documented in the README next to the Docker setup block.

### 4. Debugbar default (`.env`, `.env.example`)

Set `DEBUGBAR_ENABLED=false` for the local Docker environment and document how to flip it
back on temporarily when debugging. This removes ~70ms boot plus the 55KB payload on every
page and Livewire update.

This is my call, not something the user asked for explicitly — easy to revert if they want
Debugbar always on.

### 5. Test-speed pass (separate, needs explicit go-ahead)

Not included in the main change unless approved. The concrete, low-risk win:

- Add `<env name="DEBUGBAR_ENABLED" value="false" force="true"/>` to `phpunit.xml`
  (Debugbar currently boots in tests because `APP_DEBUG=true`). With ~200 test files using
  `RefreshDatabase`, that is ~70ms × many app boots.

Further test speedups (e.g. SQLite in-memory instead of MySQL, or `--parallel`) change test
semantics and need their own investigation; not promised here.

## Files to change

- `Dockerfile` — copy the OPcache ini.
- `docker/php/opcache.ini` — new.
- `docker-entrypoint.sh` — warm caches, stop clearing them.
- `.env`, `.env.example` — `DEBUGBAR_ENABLED=false`.
- `scripts/opcache-reset.sh` — new, executable.
- `README.md` — reset command + short explanation.
- `phpunit.xml` — only if the test-speed pass is approved.

## How to verify

- `docker compose up -d --build` (one rebuild for the new ini).
- `curl -s -o /dev/null -w '%{time_total}\n' http://localhost:8000/login` × 5 → expect
  ~0.2–0.4s with no multi-second outliers.
- Confirm `opcache_get_configuration()` reports `validate_timestamps=false` and
  `memory_consumption=256`.
- Confirm `filament:about` reports panel components and Blade icons as cached.
- Edit a Blade view and a PHP file → run `scripts/opcache-reset.sh` → confirm the change
  appears.
- Run the existing test suite (`composer test`) to confirm the DB-safety guard and all
  tests still pass.

## What is left to the builder (decided later, not blocking)

- Whether to add a `queue` service to `docker-compose.yml` so `composer run dev` is no
  longer needed for queue workers.
- Whether to expose Vite's port for HMR (currently not exposed).
