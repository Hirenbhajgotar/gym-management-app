---
title: "ADR-0006: Biometric ADMS Legacy Hardware Endpoint, ASPX Routing, and Transport Compatibility"
status: "Accepted"
date: "2026-10-30"
authors: "Lead Architect, Hardware Integration Engineer, DevOps Engineer"
tags: ["architecture", "decision", "biometrics", "adms", "hardware-integration", "routing", "essl", "zkteco", "nginx"]
supersedes: ""
superseded_by: ""
---

# ADR-0006: Biometric ADMS Legacy Hardware Endpoint, ASPX Routing, and Transport Compatibility

## Status

**Accepted**

## Context

In production deployments of commercial biometric access control terminals (primarily manufactured by eSSL, ZKTeco, and OEM variants running `iClock Proxy/1.09`, `pushver=2.4.1`), devices communicate with central servers using the **ADMS (Automatic Data Master Server)** push protocol over HTTP/HTTPS.

Standard Laravel application routing establishes API boundaries under the `/api` prefix via `Application::configure()->withRouting(apiPrefix: 'api')`, mapping the push endpoints to:
- `POST|GET /api/iclock/cdata`
- `GET /api/iclock/getrequest`
- `POST /api/iclock/devicecmd`

However, real-world deployment on physical hardware terminals revealed three critical incompatibilities:

1. **Path-Restricted Embedded Hardware Firmware**:
   Budget and standalone terminals (such as eSSL K90, MB-20, and SilkBio) do not expose a configurable "Web Directory" or "Path" input in their on-device GUI (**Menu > Comm. > Cloud Server Setting**). Devices only prompt for a server IP/domain and port, and their embedded firmware hardcodes all outbound HTTP requests directly to root paths: `/iclock/cdata`, `/iclock/getrequest`, and `/iclock/devicecmd`. When pointed at the gym application server, all requests failed with HTTP 404 (Not Found).
2. **Legacy ASP.NET Extension Hardcoding (`.aspx`)**:
   Certain firmware builds designed originally for legacy Windows-based attendance suites (e.g., ZKTime.Net, BioTime 7.0/8.0) automatically append `.aspx` suffixes to protocol URIs:
   `GET /iclock/cdata.aspx?SN={serial}&...`
   These requests bypass standard REST route patterns, resulting in unhandled 404 errors.
3. **HTTP 301 Redirect Failures in Embedded Network Stacks**:
   Embedded microcontrollers running lightweight HTTP client stacks (such as LwIP or proprietary RTOS network layers) lack HTTP redirect handling. When web servers enforce HTTPS redirection (`return 301 https://$host$request_uri;`) on port 80, terminals treat the 301 response as a fatal transport error and abort communication. Furthermore, because these terminals lack hardware TLS/SSL certificate handling, setting the server port to 443 causes the terminal to transmit plain HTTP traffic to an HTTPS listener, yielding `400 Bad Request: The plain HTTP request was sent to HTTPS port`.

A robust architectural solution was needed to provide dual-path ingestion, ASP.NET suffix tolerance, and plain HTTP transport bypass without sacrificing security, rate limiting, or web interface protection.

## Decision

We implemented a defense-in-depth routing and reverse proxy architecture supporting both modern REST and legacy firmware implementations:

### 1. Dual-Path Ingestion via Laravel 12 `then:` Routing
- Retained the existing `/api/iclock/*` routes in [`routes/api.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/api.php) to preserve full backward compatibility with configurable terminals and webhook integrations.
- Registered native non-prefixed `/iclock/*` routes directly within [`bootstrap/app.php`](file:///c:/xampp/htdocs/projects/gym-app/bootstrap/app.php) using the Laravel 12 `withRouting(then: ...)` closure.
- Routes registered in the `then:` closure execute outside the `web` cookie and session middleware group, ensuring pure stateless IoT communication without CSRF token overhead.
- Applied identical security policies: all native `/iclock/*` endpoints are guarded by `'throttle:biometric-api'` and `\App\Http\Middleware\AuthenticateAdmsDevice::class`.

### 2. ASPX Suffix Route Parity
- Defined explicit `.aspx` endpoint aliases across both native and API prefixes:
  - `GET|POST /iclock/cdata.aspx` and `/api/iclock/cdata.aspx`
  - `GET /iclock/getrequest.aspx` and `/api/iclock/getrequest.aspx`
  - `POST /iclock/devicecmd.aspx` and `/api/iclock/devicecmd.aspx`
- Mapped all `.aspx` endpoints to the same controller methods in [`AdmsController`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/AdmsController.php) (`cdata`, `getRequest`, and `deviceCmd`).
- Configured dedicated route names (`adms.native.cdata.aspx`, etc.) to prevent namespace collisions in Laravel's routing table.

### 3. Nginx Port 80 Plaintext Transport Bypass
- Formulated and documented reverse proxy specifications for production Nginx configurations:
  - In the port 80 HTTP server block (`listen 80;`), requests targeted at `location /iclock/` and `location /api/iclock/` are intercepted before HTTPS redirection and dispatched directly to the PHP FastCGI process (`try_files $uri $uri/ /index.php?$query_string;`).
  - Standard user and administrative traffic to `location /` continues to be redirected to HTTPS (`return 301 https://$host$request_uri;`).

```
┌────────────────────────────────────────────────────────────────────────┐
│                   PHYSICAL TERMINAL (eSSL / ZKTeco)                    │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ Plain HTTP (Port 80)
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                          NGINX REVERSE PROXY                           │
│  ├── /iclock/*, /api/iclock/*  ──► Bypass 301 ──► FastCGI PHP-FPM     │
│  └── All other traffic (/)     ──► 301 Redirect to https://$host...    │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                         LARAVEL ROUTING LAYER                          │
│  ├── /api/iclock/cdata[.aspx]       (routes/api.php)                   │
│  └── /iclock/cdata[.aspx]           (bootstrap/app.php `then:`)        │
│                                   │                                    │
│               Guarded by AuthenticateAdmsDevice                        │
│               Guarded by throttle:biometric-api                        │
│                                   ▼                                    │
│                         AdmsController Handlers                        │
└────────────────────────────────────────────────────────────────────────┘
```

## Consequences

### Positive

- **POS-001**: **Universal Hardware Compatibility**: Full interoperability across legacy firmware (requesting `/iclock/*.aspx` on port 80) and modern firmware (supporting `/api` or custom paths).
- **POS-002**: **Zero Breaking Changes**: Existing integrations, automated tests, and webhook consumers relying on `/api/iclock/*` remain completely unaffected.
- **POS-003**: **Stateless Performance & Security**: Bypassing the `web` middleware group eliminates session locks, CSRF verification errors, and cookie allocation for high-frequency IoT polling.
- **POS-004**: **Preserved HTTPS Boundary**: Mandatory HTTPS redirection is maintained for human users, browsers, and administrative portals, confining plaintext HTTP strictly to biometric protocol endpoints.

### Negative

- **NEG-001**: **Plaintext Wire Transit**: Biometric attendance logs and polling commands transmitted over port 80 are not TLS-encrypted in transit. Physical hardware limitations preclude SSL handshakes on budget devices; mitigation relies on PBKDF2 communication key authentication and closed local gym networks.
- **NEG-002**: **Dual Route Maintenance**: Adding new ADMS protocol endpoints requires registering definitions in both `routes/api.php` and `bootstrap/app.php`.

## Alternatives Considered

### Nginx-Only URL Rewriting (`rewrite ^/iclock/(.*)(\.aspx)?$ /api/iclock/$1 break;`)

- **ALT-001**: **Description**: Solely rewrite requests at the Nginx web server layer, transforming legacy URLs to `/api/iclock/*` before routing to PHP-FPM, leaving application code untouched.
- **ALT-002**: **Rejection Reason**: Coupling routing exclusively to external web server configuration prevents local verification via PHPUnit/Pest feature tests, creates deployment discrepancies across environments (Docker, staging, production, local artisan serve), and obscures actual endpoint capabilities from the application codebase.

### Global Prefix Removal (`apiPrefix: ''`)

- **ALT-003**: **Description**: Remove the global `apiPrefix: 'api'` in `bootstrap/app.php` so all API routes are natively accessible at the domain root.
- **ALT-004**: **Rejection Reason**: Destabilizes the entire REST API namespace, creating route collisions with web routes, administrative Filament panels, and versioned public endpoints (`/v1/...`).

## Implementation Notes

- **IMP-001**: Native non-prefixed routes are defined using `Route::prefix('iclock')` inside the `then:` closure of `Application::configure()->withRouting()` in `bootstrap/app.php`.
- **IMP-002**: Automated test `test_adms_endpoints_accessible_without_api_prefix()` in `tests/Feature/AdmsProtocolTest.php` validates that `cdata`, `getrequest`, and `devicecmd` respond with HTTP 200 and execute state transitions both with and without the `.aspx` extension.
- **IMP-003**: Production Nginx virtual hosts must specify FastCGI parameters (`include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;`) inside the port 80 block.

## References

- **REF-001**: [ADR-0004: Member Archival Lifecycle, Cryptographic Biometric Credential Erasure, and Access Revocation Architecture](docs/adr/adr-0004-member-archival-and-biometric-erasure-lifecycle.md)
- **REF-002**: [ADR-0005: Biometric ADMS Communication Key Hardware Standardization and Sanitization Architecture](docs/adr/adr-0005-biometric-adms-communication-key-hardware-standardization.md)
- **REF-003**: [Feature Specification: Biometric ADMS Push Protocol](docs/biometric/feature/biometric-adms-push-protocol.md)

