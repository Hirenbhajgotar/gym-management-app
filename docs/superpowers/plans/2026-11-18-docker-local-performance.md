# Docker Local Performance (Option A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make local Docker pages and Livewire/Filament actions load in roughly 0.2–0.4s with no multi-second swings, by caching Filament component discovery and removing OPcache timestamp revalidation across the Windows bind mount.

**Architecture:** Keep the `.:/var/www/html` bind mount for editability, but disable OPcache timestamp checks (`validate_timestamps=0`) so requests stop `stat`ing ~1,500 files across the Windows→Docker bridge. Warm Filament's component cache and Blade view cache in the container entrypoint so the ~8.7s per-request component discovery happens once at startup. Provide an explicit reset script for the cases where stale bytecode is expected. No application logic, dependency, or route changes.

**Tech Stack:** PHP 8.3 / Laravel 12 / Filament v3.3.48 / Docker Compose v2 on Windows (Git Bash), nginx + php-fpm, OPcache,laravel-boost not required.

**Spec:** `docs/superpowers/specs/2026-11-18-docker-local-performance-design.md`

## Global Constraints

- Windows host + Docker Desktop bind mount `.:/var/www/html` is the root cause; do not remove it.
- Exact OPcache values: `validate_timestamps=0`, `revalidate_freq=0`, `memory_consumption=256`, `interned_strings_buffer=16`, `max_accelerated_files=20000`, `save_comments=1`.
- **Never** add `config:cache` to the entrypoint. Tests run in the same container; a config cache built from local `.env` would override the forced `phpunit.xml` testing values and risks resolving `gym_app` instead of `gym_app_test`.
- **Never** add `route:cache`: `routes/web.php` and `routes/api.php` contain closure routes.
- Keep the existing `chown -R www-data:www-data ...` and `php artisan storage:link` lines in the entrypoint — removing them risks breaking storage writes.
- No new Composer/NPM dependencies; no changes to `composer.json` `require`/`require-dev`.
- Reuse the existing `docker/` directory; no new top-level directories.
- Test-database safety: never run `migrate:fresh`, `db:seed`, `migrate:reset` etc. without `--env=testing`; prefer the `composer test:*` scripts.
- Compose CLI is v2 (`docker compose`), not v1 (`docker-compose`).

## Review Focus

- **A config cache appearing at `bootstrap/cache/config.php` after a container start** → tests must still resolve `gym_app_test`. Pinned in Task 2 by asserting the file is absent and running `TestDatabaseNameResolutionTest`.
- **Stale PHP bytecode after a developer edits a file** → the reset script must make the new code visible. Pinned in Task 3 with a create → edit → reset → re-fetch probe.
- **A newly added Filament resource/page/widget not appearing after `filament:optimize` was already cached** → reset must rebuild the component cache. Pinned in Task 3 by asserting `filament:about` reports components cached after reset.
- **Container restart silently dropping the warm caches** → entrypoint must re-warm on every start. Pinned in Task 2 by restarting and re-checking `filament:about`.
- **`DEBUGBAR_ENABLED=false` in `.env` silently not applying because `APP_ENV=testing` loads `.env.testing`** → pinned in Task 3 Step 3 by requiring the README note that states this explicitly; no task changes test config in this plan.

---

### Task 1: OPcache configuration and image wiring

**Files:**
- Create: `docker/php/opcache.ini`
- Modify: `Dockerfile` (add a `COPY` line after `WORKDIR /var/www/html` and before the `composer install` layer)

**Interfaces:**
- Consumes: nothing.
- Produces: a rebuilt `gym-app` image whose FPM reads `opcache.ini` from `/usr/local/etc/php/conf.d/zz-opcache.ini`.

- [ ] **Step 1: Create `docker/php/opcache.ini` with exactly these directives**

```ini
opcache.enable=1
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.save_comments=1
```

Do not add `opcache.preload` or a JIT buffer — neither is part of this design.

- [ ] **Step 2: Wire it into the image**

In `Dockerfile`, immediately after `WORKDIR /var/www/html`, add:

```dockerfile
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
```

Keep it before the `COPY composer.json composer.lock ./` line so changing this small file does not invalidate the dependency layers.

- [ ] **Step 3: Rebuild and recreate only the app container**

Run: `docker compose up -d --build app && docker compose restart app`
Expected: `gym-app` recreated; `gym-web` keeps running.

- [ ] **Step 4: Verify the FPM/CLI directives took effect**

Run:

```bash
docker compose exec app php -r '$c=opcache_get_configuration()["directives"]; $want=["opcache.validate_timestamps"=>false,"opcache.revalidate_freq"=>0,"opcache.memory_consumption"=>256,"opcache.interned_strings_buffer"=>16,"opcache.max_accelerated_files"=>20000,"opcache.save_comments"=>true]; foreach($want as $k=>$v){ if($c[$k]!==$v){ fwrite(STDERR,"$k=".var_export($c[$k],true)." expected ".var_export($v,true)."\n"); exit(1);} } echo "opcache ok\n";'
```

Expected: `opcache ok`, exit code 0.

- [ ] **Step 5: Commit**

```bash
git add Dockerfile docker/php/opcache.ini
git commit -m "perf: disable OPcache timestamp checks and raise cache limits"
```

---

### Task 2: Warm caches in the entrypoint

**Files:**
- Modify: `docker-entrypoint.sh` (replace the two `config:clear` / `cache:clear` lines)

**Interfaces:**
- Consumes: rebuilt image from Task 1.
- Produces: on every container start, Filament panel component cache + Blade icon cache + compiled Blade views are warm, and no `bootstrap/cache/config.php` is created.

- [ ] **Step 1: Replace the cache-clearing block**

Current lines in `docker-entrypoint.sh`:

```bash
# Clear caches (optional, for safety)
php artisan config:clear
php artisan cache:clear
```

Replace with:

```bash
# Warm the caches that are expensive to build per request.
# config:cache and route:cache are intentionally NOT used: tests run in this
# same container and need the forced phpunit.xml config, and the routes use closures.
php artisan filament:optimize || true
php artisan view:cache || true
php artisan config:clear || true
```

Keep the existing `chown -R` and `php artisan storage:link --quiet || true` lines unchanged, and keep `exec php-fpm` as the last line.

- [ ] **Step 2: Restart and confirm the entrypoint succeeded**

Run: `docker compose restart app && sleep 3 && docker compose logs --tail=20 app`
Expected: no fatal errors; look for the filament caching output.

- [ ] **Step 3: Assert caches are warm and config is not cached**

Run:

```bash
docker compose exec app php artisan filament:about
docker compose exec app sh -c 'test ! -f /var/www/html/bootstrap/cache/config.php && echo "no config cache"'
docker compose exec app sh -c 'ls /var/www/html/bootstrap/cache/filament/panels/*.php | wc -l'
```

Expected: `Panel Components .... CACHED`, `Blade Icons ......... CACHED`, `no config cache`, and a panel count of at least 1 (currently 3: admin, staff, super-admin).

- [ ] **Step 4: Confirm storage writes still work (the `chown` was preserved)**

Run: `docker compose exec app sh -c 'touch /var/www/html/storage/framework/.write-check && rm /var/www/html/storage/framework/.write-check && echo writable'`
Expected: `writable`.

- [ ] **Step 5: Confirm the DB-safety guard still passes**

Run: `docker compose exec app composer test --filter=TestDatabaseNameResolutionTest`
Expected: PASS, with no failure mentioning `gym_app` as the resolved test database.

- [ ] **Step 6: Commit**

```bash
git add docker-entrypoint.sh
git commit -m "perf: warm Filament and Blade caches at container start"
```

---

### Task 3: Reset workflow, Debugbar default, and documentation

**Files:**
- Create: `scripts/opcache-reset.sh` (set executable)
- Modify: `.env` (add `DEBUGBAR_ENABLED=false`)
- Modify: `.env.example` (add `DEBUGBAR_ENABLED=false` with a short comment)
- Modify: `README.md` (add a note under the Docker Setup block, around line 188)

**Interfaces:**
- Consumes: Task 2's warmed caches.
- Produces: `scripts/opcache-reset.sh`, callable from the repo root on Windows/Git Bash, which clears compiled Blade views, rebuilds Filament's component cache, and restarts the app container to drop FPM's OPcache SHM.

- [ ] **Step 1: Create `scripts/opcache-reset.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

echo 'Clearing compiled Blade views...'
docker compose exec -T app php artisan view:clear

echo 'Rebuilding Filament component cache...'
docker compose exec -T app php artisan filament:optimize

echo 'Restarting app container to reset OPcache...'
docker compose restart app

echo 'Done. PHP and Blade changes are now visible.'
```

Then run `chmod +x scripts/opcache-reset.sh`.

- [ ] **Step 2: Set the Debugbar default**

Add to `.env` and `.env.example`:

```
DEBUGBAR_ENABLED=false
```

In `.env.example`, precede it with:

```
# Set to true temporarily when you need the Debugbar during local development.
```

Do not modify `config/debugbar.php` — its `env('DEBUGBAR_ENABLED', null)` already handles this.

- [ ] **Step 3: Document the reset workflow in README.md**

Insert after the `docker-compose exec app composer run dev` block in the Docker Setup section:

````markdown
> **PHP or Blade changes not showing up?** OPcache timestamp checks are disabled locally
> for speed, so run this from the repo root after editing PHP/views:
>
> ```bash
> ./scripts/opcache-reset.sh
> ```
>
> Debugbar is off by default locally (`DEBUGBAR_ENABLED=false` in `.env`) to keep every
> page and Livewire update fast. Set it to `true` temporarily when you need to debug.
> Tests use `.env.testing` (loaded because `APP_ENV=testing`), so the `.env` value does
> not apply there.
````

- [ ] **Step 4: Prove the reset script works with a temporary probe**

Run these in order from the repo root:

```bash
printf '<?php echo "v1";' > public/__reset_check.php
curl -s http://localhost:8000/__reset_check.php
curl -s http://localhost:8000/__reset_check.php
printf '<?php echo "v2";' > public/__reset_check.php
curl -s http://localhost:8000/__reset_check.php
./scripts/opcache-reset.sh
curl -s http://localhost:8000/__reset_check.php
rm -f public/__reset_check.php
```

Expected: first two outputs `v1`; the third still `v1` (proving OPcache is serving stale bytecode); the output after the reset script is `v2`. Then run `docker compose exec app php artisan filament:about` and confirm panel components are still reported as cached. The probe file must be deleted at the end — confirm with `git status --short` that no `__reset_check.php` remains.

- [ ] **Step 5: Commit**

```bash
git add scripts/opcache-reset.sh .env.example README.md
git commit -m "perf: add OPcache reset script and document fast local workflow"
```

Note: `.env` is gitignored — do not `git add` it. Its change is local-only.

---

### Task 4: End-to-end verification

**Files:** none (verification only). If a measurement fails, return to the owning task above.

**Interfaces:**
- Consumes: all of Tasks 1–3.
- Produces: recorded timings confirming the goal.

- [ ] **Step 1: Confirm no diagnostic probe files remain**

Run: `git status --short`
Expected: only the intended file changes; no `__perf_probe.php`, `__boot_probe.php`, `__reset.php`, `__ocstatus.php`, `__reset_check.php`, or `__login_probe.html`.

- [ ] **Step 2: Measure 5 sequential page loads**

Run: `for i in 1 2 3 4 5; do curl -s -o /dev/null -w "login $i: %{time_total}s\n" http://localhost:8000/login; done`
Expected: every value below 1.0s after the first request; no multi-second outliers. The previous baseline was 7–21s cold and 0.2–7s warm.

- [ ] **Step 3: Confirm OPcache is actually serving the app files**

Run:

```bash
docker compose exec app php -r '$s=opcache_get_status(false); echo "cached=".$s["opcache_statistics"]["num_cached_scripts"]." hits=".$s["opcache_statistics"]["hits"]."\n";'
```

Expected: `cached` in the thousands and `hits` increasing after Step 2.

- [ ] **Step 4: Confirm no regression in the test suite**

Run: `docker compose exec app composer test --filter=TestDatabaseNameResolutionTest`
Expected: PASS. Then ask the user whether to run the full suite (`docker compose exec app composer test`) — per project rules, do not run it unprompted.

- [ ] **Step 5: Report the before/after numbers to the user**

Include the five timings from Step 2 and confirm: no `config:cache`, no `route:cache`, Debugbar off locally, reset script verified.

---

## Deferred (not in this plan; needs explicit approval)

- Disable Debugbar specifically in tests: add `<env name="DEBUGBAR_ENABLED" value="false" force="true"/>` to `phpunit.xml`, or add `DEBUGBAR_ENABLED=false` to `.env.testing`. The `.env` value in Task 3 does **not** apply to tests because `APP_ENV=testing` makes Laravel load `.env.testing`.
- Further test-suite speedups (SQLite `:memory:` vs MySQL, `php artisan test --parallel`) change test semantics and need separate investigation.
- Add a dedicated `queue` service to `docker-compose.yml` so `composer run dev` is unnecessary.
- Expose Vite's port 5173 for HMR; currently not exposed and not needed for page load speed because assets are built into `public/build`.
