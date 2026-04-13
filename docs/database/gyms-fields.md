---
table: gyms
last_updated: 2026-04-13
author: docs-agent
related_features:
  - ../features/qr-code-attendance.md
---

# `gyms` — Field Reference

## Purpose

The `gyms` table is the central tenant model representing each gym location in the multi-tenant system. It stores gym details, configuration, and QR code tokens for the QR attendance feature.

## Field Reference

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO | auto | — | Primary key |
| `name` | varchar(255) | NO | — | GymCreator | Gym name |
| `slug` | varchar(255) | NO | — | GymCreator | URL-friendly identifier (unique) |
| `logo` | varchar(255) | YES | null | GymCreator | Path to gym logo image |
| `qr_code_token` | varchar(64) | YES | null | QRCodeManager | Token for check-in QR code URL |
| `qr_code_checkout_token` | varchar(64) | YES | null | QRCodeManager | Token for check-out QR code URL |
| `tenant_id` | bigint unsigned | YES | null | System | Foreign key to tenants (if multi-tenant) |
| `is_active` | tinyint(1) | NO | 1 | GymCreator | Whether gym is active |
| `address` | text | YES | null | GymCreator | Gym address |
| `phone` | varchar(50) | YES | null | GymCreator | Gym phone number |
| `email` | varchar(255) | YES | null | GymCreator | Gym email |
| `created_at` | timestamp | NO | — | — | Creation timestamp |
| `updated_at` | timestamp | NO | — | Last update timestamp |

> **Nullable fields note:** Columns marked nullable are optional in specific flows. The QR code tokens (`qr_code_token`, `qr_code_checkout_token`) are nullable and generated on-demand when gym owners generate QR codes from the admin panel.

## Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `PRIMARY` | `id` | PRIMARY | — |
| `gyms_slug_unique` | `slug` | UNIQUE | Ensure unique gym URLs |
| `gyms_qr_code_token_unique` | `qr_code_token` | UNIQUE | Ensure QR tokens are unique (when not null) |
| `gyms_qr_code_checkout_token_unique` | `qr_code_checkout_token` | UNIQUE | Ensure checkout QR tokens unique (when not null) |

## Relationships

| Relationship | Type | Foreign Key | Related Model |
|---|---|---|---|
| `members()` | hasMany | gym_id | GymMember |
| `staff()` | hasMany | gym_id | Staff |
| `trainers()` | hasMany | gym_id | Trainer |
| `attendances()` | hasMany | gym_id | Attendances |
| `membershipPlans()` | hasMany | gym_id | MembershipPlan |

## Usage by Feature

| Feature | Reads | Writes | Notes |
|---|---|---|---|
| [QR Code Attendance](../features/qr-code-attendance.md) | `id, qr_code_token, qr_code_checkout_token` | `qr_code_token, qr_code_checkout_token` | Used for generating and validating QR code URLs |
| [Biometric Check-in Flow](../features/biometric-checkin-flow.md) | `id, name` | — | Used for gym identification in biometric attendance |
| [Door Access Control](../features/door-access-control.md) | `id, name` | — | Used for gym context in door events |

## Migration History

| Migration | Change |
|---|---|
| `2026_04_08_120000_add_qr_code_token_to_gyms_table` | Added `qr_code_token` column for check-in QR code |
| `2026_04_13_000000_add_qr_code_checkout_token_to_gyms_table` | Added `qr_code_checkout_token` column for check-out QR code |