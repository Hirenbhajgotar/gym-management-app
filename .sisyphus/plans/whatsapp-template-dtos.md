# WhatsApp Template DTO Implementation Plan

## TL;DR

> **Quick Summary**: Create 9 PHP 8.2 readonly Data Transfer Object (DTO) classes across `App\DTOs\WhatsAppTemplates\` to replace raw `array` parameters and `array{...}` return shapes in all WhatsApp template management services (TwilioTemplateService, MetaTemplateService, WhatsAppTemplateService, WhatsAppTemplateManager). No external packages — native PHP 8.2 readonly classes with typed properties.
>
> **Deliverables**:
> - 2 input DTOs: `CreateTemplateData`, `UpdateTemplateData`
> - 5 result DTOs: `TemplateSubmissionResult`, `TemplateCollectionResult`, `TemplateMasterSyncResult`, `TemplateFetchResult`, `TemplateDbSyncResult`
> - 2 value objects: `TemplateVariable`, `TemplatePlaceholder`
> - Updates to 4 services + 2 interfaces + all consumers (Filament, commands, notifications)
>
> **Estimated Effort**: Medium (9 DTO files + ~15 consumer updates)
> **Parallel Execution**: YES — 4 waves
> **Critical Path**: DTO definitions → Interface signatures → Service implementations → Consumer updates

---

## Context

### Original Request
"Create a plan to create DTOs for WhatsAppTemplate services. Activate 'laravel-specialist' and 'laravel-best-practices'."

### Interview Summary
**Key Decisions**:
- **Approach**: Native PHP 8.2 readonly classes (no spatie/data-transfer-object — deprecated; no spatie/laravel-data — not in composer.json)
- **Namespace**: `App\DTOs\WhatsAppTemplates\`
- **Naming**: Input DTOs use `{Purpose}Data` (e.g., `CreateTemplateData`), Result DTOs use `{Purpose}Result` (e.g., `TemplateSubmissionResult`), Value objects use entity name (e.g., `TemplateVariable`)
- **Pattern**: Constructor property promotion + `toArray()` for backward compatibility with existing array consumers
- **Scope**: Template management ONLY (exclude messaging, credits, phone numbers, WABA setup)
- **Provider-neutral**: Shared DTOs for both Twilio and Meta (they share the same TemplateManagementInterface)
- **No automated tests**: Agent-executed QA verification only
- **Backward compat**: `toArray()` on all result DTOs so existing `$result['key']` consumers still work

**Research Findings**:
- No existing DTOs anywhere in the codebase — greenfield
- `TemplateManagementInterface` is the key contract (4 methods) using raw arrays
- Twilio returns `sid` (string), Meta returns `template_id` (string) — result DTOs handle both
- Template body uses `{{1}}`, `{{2}}` variable placeholders processed by `TemplateVariableHelper`
- WhatsAppTemplateManager (legacy, 221 lines) is a separate consumer using Twilio Client directly

---

## Work Objectives

### Core Objective
Implement typed Data Transfer Objects across all WhatsApp template services, replacing raw array parameters and return shapes with PHP 8.2 readonly classes for type-safe data exchange.

### Concrete Deliverables
- 9 DTO classes in `app/DTOs/WhatsAppTemplates/`
- Updated `TemplateManagementInterface` with DTO type hints
- Updated service method signatures in all 4 services
- Updated consumers (Filament pages, console commands, notifications)
- `toArray()` bridge on result DTOs for backward compatibility

### Must Have
- All 6 array return shapes replaced with typed DTOs
- All `array $data` parameters replaced with typed input DTOs
- Backward-compatible `toArray()` on all result DTOs
- No breaking changes to public API (Filament consumers use `toArray()` bridge)
- `$data['key']` → `$dto->key` access pattern throughout services

### Must NOT Have (Guardrails)
- No renaming of existing methods (interface contract stability)
- No removal of backward-compat code paths (bridge, not replace)
- No changes to non-template services (messaging, credits, phone numbers, WABA)
- No external DTO packages added to composer.json
- No moving to new namespaces (do NOT restructure services)

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed. No exceptions.

### Test Decision
- **Automated tests**: NO (user chose agent QA only)
- **Agent-Executed QA**: Every task includes concrete scenarios with exact commands

### QA Policy
Every task MUST include agent-executed QA scenarios:
- **Files exist**: `php -l` syntax check on each DTO file
- **Method signatures**: `grep` to verify updated type hints in interfaces and services
- **Construction**: Run `php artisan tinker` to instantiate each DTO and verify property access
- **Consumer bridge**: Verify Filament pages/commands still work with `toArray()` output

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation — value objects + input DTOs):
├── Task 1: Create TemplateVariable value object
├── Task 2: Create TemplatePlaceholder value object
├── Task 3: Create CreateTemplateData input DTO
├── Task 4: Create UpdateTemplateData input DTO

Wave 2 (Result DTOs — interface-facing):
├── Task 5: Create TemplateSubmissionResult DTO
├── Task 6: Create TemplateCollectionResult DTO
├── Task 7: Create TemplateMasterSyncResult DTO

Wave 3 (Manager DTOs + Interfaces):
├── Task 8: Create TemplateFetchResult DTO
├── Task 9: Create TemplateDbSyncResult DTO
├── Task 10: Update TemplateManagementInterface with DTO types
├── Task 11: Update WhatsAppProviderInterface PHPDoc with DTO types

Wave 4 (Service implementations):
├── Task 12: Update TwilioTemplateService method signatures
├── Task 13: Update MetaTemplateService method signatures
├── Task 14: Update WhatsAppTemplateService method signatures
├── Task 15: Update WhatsAppTemplateManager method signatures
├── Task 16: Update WhatsAppTemplateManager consumers (SyncCommand, Notification)

Wave FINAL (Verification):
├── Task F1: Plan compliance audit (oracle)
├── Task F2: QA execution + evidence capture
└── Task F3: Scope fidelity check

Critical Path: Task 3 → Task 5 → Task 10 → Task 12 → F1-F3
```

---

## TODOs

- [ ] 1. Create `TemplateVariable` value object

  **What to do**:
  - Create `app/DTOs/WhatsAppTemplates/TemplateVariable.php`
  - PHP 8.2 readonly class with constructor property promotion
  - Properties: `public readonly string $name`, `public readonly string $format`, `public readonly string $example`
  - Add `toArray(): array` method returning `['name' => $this->name, 'format' => $this->format, 'example' => $this->example]`
  - Add `fromArray(array $data): static` named constructor (factory method)
  - Used by `TemplateVariableHelper::extractVariables()` return values

  **Must NOT do**:
  - No validation logic (value object, not validator)
  - No database interaction

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3, 4)
  - **Blocks**: None (independent foundation)
  - **Blocked By**: None

  **References**:
  - `app/Services/Support/TemplateVariableHelper.php:extractVariables()` — Returns array of `{name, format, example}` — this DTO replaces that array shape
  - No existing DTOs to reference (greenfield)

  **Acceptance Criteria**:
  - [ ] File exists: `app/DTOs/WhatsAppTemplates/TemplateVariable.php`
  - [ ] `php -l app/DTOs/WhatsAppTemplates/TemplateVariable.php` → No syntax errors

  **QA Scenarios**:
  ```
  Scenario: DTO instantiation and property access
    Tool: Bash (php -l + tinker)
    Steps:
      1. `php -l app/DTOs/WhatsAppTemplates/TemplateVariable.php`
      2. Via tinker: `$v = new \App\DTOs\WhatsAppTemplates\TemplateVariable(name: 'name', format: 'string', example: 'John');` then `$v->name`
    Expected Result: Syntax clean, property access returns expected value
    Evidence: .sisyphus/evidence/task-1-syntax.txt
  ```

  **Commit**: YES (groups with 2-4)
  - Message: `feat(dto): add template value objects and input DTOs`
  - Files: `app/DTOs/WhatsAppTemplates/TemplateVariable.php`

- [ ] 2. Create `TemplatePlaceholder` value object

  **What to do**:
  - Create `app/DTOs/WhatsAppTemplates/TemplatePlaceholder.php`
  - PHP 8.2 readonly class with constructor property promotion
  - Properties: `public readonly string $placeholder`, `public readonly string $name`, `public readonly string $example`, `public readonly string $type`, `public readonly string $source`
  - Add `toArray(): array` method returning array of all properties
  - Add `fromArray(array $data): static` named constructor
  - Used by `TemplateVariableHelper::generateDefaultPlaceholders()` return items

  **Must NOT do**:
  - No database interaction
  - No validation logic

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3, 4)
  - **Blocks**: None
  - **Blocked By**: None

  **References**:
  - `app/Services/Support/TemplateVariableHelper.php:generateDefaultPlaceholders()` — Returns array of `{placeholder, name, example, type, source}` items

  **Acceptance Criteria**:
  - [ ] File exists: `app/DTOs/WhatsAppTemplates/TemplatePlaceholder.php`
  - [ ] `php -l` → No syntax errors

  **QA Scenarios**:
  ```
  Scenario: DTO construction from array
    Tool: Bash (php -l + tinker)
    Steps:
      1. `php -l app/DTOs/WhatsAppTemplates/TemplatePlaceholder.php`
      2. Via tinker: `\App\DTOs\WhatsAppTemplates\TemplatePlaceholder::fromArray(['placeholder' => '{{1}}', 'name' => 'Name', 'example' => 'John', 'type' => 'text', 'source' => 'member'])`
    Expected Result: Syntax clean, factory returns typed object
    Evidence: .sisyphus/evidence/task-2-syntax.txt
  ```

  **Commit**: YES (groups with 1)
  - Message: (same as Task 1)
  - Files: `app/DTOs/WhatsAppTemplates/TemplatePlaceholder.php`

- [ ] 3. Create `CreateTemplateData` input DTO

  **What to do**:
  - Create `app/DTOs/WhatsAppTemplates/CreateTemplateData.php`
  - PHP 8.2 readonly class with constructor property promotion
  - Properties: `public readonly string $name`, `public readonly string $display_name`, `public readonly string $body`, `public readonly string $category`, `public readonly ?array $variable_placeholder = null`, `public readonly ?bool $is_default = null`
  - Add `fromArray(array $data): self` named constructor mapping snake_case keys to constructor params
  - This replaces `array $data` parameter in `WhatsAppTemplateService::createTemplate()` and `TemplateManagementInterface::createTemplate()`
  - The `$fillable` fields in WhatsAppTemplate model guide which properties belong here: name, display_name, body, category, variable_placeholder, is_default

  **Must NOT do**:
  - No validation rules (Form Request is separate concern)
  - No database interaction
  - Do NOT include system fields: twilio_template_sid, meta_template_id, status, is_active, rejection_reason, usage_count, last_used_at

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 4)
  - **Blocks**: Task 10, Task 12, Task 13, Task 14
  - **Blocked By**: None

  **References**:
  - `app/Models/WhatsAppTemplate.php` — `$fillable` property shows which fields are mass-assignable: `['gym_id', 'name', 'display_name', 'body', 'category', 'variables', 'variable_count', 'variable_placeholder', 'status', 'is_active']`
  - `app/Services/WhatsAppTemplateService.php:createTemplate()` — Current `array $data` parameter usage
  - `app/Interfaces/TemplateManagementInterface.php:createTemplate()` — Interface signature to update

  **Acceptance Criteria**:
  - [ ] File exists: `app/DTOs/WhatsAppTemplates/CreateTemplateData.php`
  - [ ] `php -l` → No syntax errors
  - [ ] `tinker` instantiation with all required fields works

  **QA Scenarios**:
  ```
  Scenario: Full construction and fromArray factory
    Tool: Bash (php -l + tinker)
    Steps:
      1. `php -l app/DTOs/WhatsAppTemplates/CreateTemplateData.php`
      2. Via tinker: `new \App\DTOs\WhatsAppTemplates\CreateTemplateData(name: 'welcome_msg', display_name: 'Welcome', body: 'Hello {{1}}', category: 'utility')`
      3. Via tinker: `\App\DTOs\WhatsAppTemplates\CreateTemplateData::fromArray(['name' => 'welcome_msg', 'display_name' => 'Welcome', 'body' => 'Hello {{1}}', 'category' => 'utility'])`
    Expected Result: Both construction methods work, all properties accessible
    Evidence: .sisyphus/evidence/task-3-construction.txt
  ```

  **Commit**: YES (groups with 1, 2, 4)
  - Message: (same as Task 1)
  - Files: `app/DTOs/WhatsAppTemplates/CreateTemplateData.php`

- [ ] 4. Create `UpdateTemplateData` input DTO

  **What to do**:
  - Create `app/DTOs/WhatsAppTemplates/UpdateTemplateData.php`
  - PHP 8.2 readonly class with ALL properties optional
  - Properties: `public readonly ?string $name = null`, `public readonly ?string $display_name = null`, `public readonly ?string $body = null`, `public readonly ?string $category = null`, `public readonly ?array $variable_placeholder = null`, `public readonly ?bool $is_default = null`
  - Add `fromArray(array $data): self` named constructor
  - This replaces `array $data` parameter in `WhatsAppTemplateService::updateTemplate()`
  - All properties nullable since update is partial

  **Must NOT do**:
  - No validation rules
  - No merging with existing model data (caller handles that)

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 3)
  - **Blocks**: Task 12, Task 13, Task 14
  - **Blocked By**: None

  **References**:
  - `app/Services/WhatsAppTemplateService.php:updateTemplate()` — Current `array $data` parameter usage
  - CreateTemplateData (Task 3) — Mirror structure but all-optional

  **Acceptance Criteria**:
  - [ ] File exists: `app/DTOs/WhatsAppTemplates/UpdateTemplateData.php`
  - [ ] `php -l` → No syntax errors
  - [ ] Instantiation with partial fields works (only name, no others)

  **QA Scenarios**:
  ```
  Scenario: Partial construction (update with single field)
    Tool: Bash (php -l + tinker)
    Steps:
      1. `php -l app/DTOs/WhatsAppTemplates/UpdateTemplateData.php`
      2. Via tinker: `new \App\DTOs\WhatsAppTemplates\UpdateTemplateData(display_name: 'New Name')`
    Expected Result: Only display_name is set, others null
    Evidence: .sisyphus/evidence/task-4-partial.txt
  ```

  **Commit**: YES (groups with 1, 2, 3)
  - Message: (same as Task 1-3)
  - Files: `app/DTOs/WhatsAppTemplates/UpdateTemplateData.php`

- [ ] 5. Create `TemplateSubmissionResult` DTO

  **What to do**:
  - Create `app/DTOs/WhatsAppTemplates/TemplateSubmissionResult.php`
  - PHP 8.2 readonly class with constructor property promotion
  - Properties: `public readonly bool $success`, `public readonly ?string $status = null`, `public readonly ?string $sid = null` (Twilio), `public readonly ?string $templateId = null` (Meta), `public readonly ?string $error = null`
  - Add `toArray(): array` returning all non-null properties in snake_case keys: `['success' => bool, 'status' => ?string, 'sid' => ?string, 'template_id' => ?string, 'error' => ?string]`
  - Add `fromTwilioResponse(array $response): self` factory (maps 'sid' key)
  - Add `fromMetaResponse(string $templateId, string $status): self` factory
  - This replaces `array{success: bool, status?: string, sid?: string, template_id?: string, error?: string}` return from `submitTemplate()`

  **Must NOT do**:
  - Do NOT add HTTP/client logic — pure data transfer only
  - Do NOT rename `sid`/`templateId` — these are provider-specific identifiers

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 6, 7)
  - **Blocks**: Task 10, Task 12, Task 13, Task 14
  - **Blocked By**: None (independent of value objects)

  **References**:
  - `app/Interfaces/TemplateManagementInterface.php:submitTemplate()` — Current `array` return type
  - `app/Services/Twilio/TwilioTemplateService.php:submitTemplate()` — Returns `['success' => bool, 'status' => string, 'sid' => string]` or `['success' => false, 'error' => string]`
  - `app/Services/Meta/MetaTemplateService.php:submitTemplate()` — Returns `['success' => bool, 'status' => WhatsAppTemplateStatusEnum, 'template_id' => string]` or `['success' => false, 'error' => string]`

  **Acceptance Criteria**:
  - [ ] File exists: `app/DTOs/WhatsAppTemplates/TemplateSubmissionResult.php`
  - [ ] `php -l` → No syntax errors
  - [ ] `toArray()` produces exact snake_case keys that existing consumers expect

  **QA Scenarios**:
  ```
  Scenario: Success result construction
    Tool: Bash (php -l + tinker)
    Steps:
      1. `php -l app/DTOs/WhatsAppTemplates/TemplateSubmissionResult.php`
      2. Via tinker: `new \App\DTOs\WhatsAppTemplates\TemplateSubmissionResult(success: true, status: 'PENDING', sid: 'SM123')`
      3. Call `->toArray()` and verify keys: success, status, sid
    Expected Result: Array has ['success' => true, 'status' => 'PENDING', 'sid' => 'SM123']
    Evidence: .sisyphus/evidence/task-5-success.txt

  Scenario: Error result construction
    Tool: Bash (tinker)
    Steps:
      1. Via tinker: `new \App\DTOs\WhatsAppTemplates\TemplateSubmissionResult(success: false, error: 'Invalid body')`
      2. Call `->toArray()` and verify error key present
    Expected Result: Array has ['success' => false, 'error' => 'Invalid body']
    Evidence: .sisyphus/evidence/task-5-error.txt
  ```

  **Commit**: YES (groups with 6, 7)
  - Message: `feat(dto): add template result DTOs`
  - Files: `app/DTOs/WhatsAppTemplates/TemplateSubmissionResult.php`

- [ ] 6. Create `TemplateCollectionResult` DTO

  **What to do**:
  - Create `app/DTOs/WhatsAppTemplates/TemplateCollectionResult.php`
  - PHP 8.2 readonly class with constructor property promotion
  - Properties: `public readonly bool $success`, `public readonly array $templates` (array of arrays from API), `public readonly ?int $count = null`, `public readonly ?string $error = null`
  - Add `toArray(): array` returning `['success' => bool, 'templates' => array, 'count' => ?int, 'error' => ?string]`
  - Templates property stores raw provider API response arrays (not typed DTOs — too many provider-specific field variations)
  - This replaces `array{success: bool, templates?: array, count?: int, error?: string}` return from `getTemplates()`

  **Must NOT do**:
  - Do NOT type the templates array items — provider APIs return different field shapes
  - Do NOT transform/filter template data — pure passthrough

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 5, 7)
  - **Blocks**: Task 10, Task 12, Task 13, Task 14
  - **Blocked By**: None

  **References**:
  - `app/Interfaces/TemplateManagementInterface.php:getTemplates()` — Current `array` return type
  - `app/Services/Twilio/TwilioTemplateService.php:getTemplates()` — Returns `['success' => bool, 'templates' => [...], 'count' => int]`
  - `app/Services/Meta/MetaTemplateService.php:getTemplates()` — Same shape

  **Acceptance Criteria**:
  - [ ] File exists: `app/DTOs/WhatsAppTemplates/TemplateCollectionResult.php`
  - [ ] `php -l` → No syntax errors
  - [ ] `toArray()` preserves array keys for backward compatibility

  **QA Scenarios**:
  ```
  Scenario: Collection with templates
    Tool: Bash (php -l + tinker)
    Steps:
      1. `php -l app/DTOs/WhatsAppTemplates/TemplateCollectionResult.php`
      2. Via tinker: `new \App\DTOs\WhatsAppTemplates\TemplateCollectionResult(success: true, templates: [['name' => 'welcome']], count: 1)`
    Expected Result: ->toArray()['templates'][0]['name'] === 'welcome'
    Evidence: .sisyphus/evidence/task-6-collection.txt
  ```

  **Commit**: YES (groups with 5, 7)
  - Message: (same as Task 5)
  - Files: `app/DTOs/WhatsAppTemplates/TemplateCollectionResult.php`

- [ ] 7. Create `TemplateMasterSyncResult` DTO

  **What to do**:
  - Create `app/DTOs/WhatsAppTemplates/TemplateMasterSyncResult.php`
  - PHP 8.2 readonly class with constructor property promotion
  - Properties: `public readonly bool $success`, `public readonly ?int $syncedCount = null`, `public readonly ?int $errorCount = null`, `public readonly array $errors = []`, `public readonly ?string $error = null`
  - Add `toArray(): array` returning snake_case keys: `['success' => bool, 'synced_count' => ?int, 'error_count' => ?int, 'errors' => array, 'error' => ?string]`
  - Add `fromTwilioResponse(int $syncedCount, int $errorCount, array $errors): self` factory for success case
  - Add `fromError(string $error): self` factory for failure case
  - This replaces the Twilio-specific `syncTemplatesFromTwilioMasterAccount()` return

  **Must NOT do**:
  - Keep naming provider-neutral despite being Twilio-only
  - Do NOT add Twilio-specific imports

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 5, 6)
  - **Blocks**: Task 11** (or actually this is used by SuperAdmin Filament page — blocks consumer update in Task 16)
  - **Blocked By**: None

  **References**:
  - `app/Services/Twilio/TwilioTemplateService.php:syncTemplatesFromTwilioMasterAccount()` — Returns `['success' => bool, 'synced_count' => int, 'error_count' => int, 'errors' => array]`
  - `app/Filament/SuperAdmin/Resources/WhatsAppTemplateResource/Pages/ListWhatsAppTemplates.php` — The sync action reads these result keys

  **Acceptance Criteria**:
  - [ ] File exists: `app/DTOs/WhatsAppTemplates/TemplateMasterSyncResult.php`
  - [ ] `php -l` → No syntax errors
  - [ ] `fromTwilioResponse()` and `fromError()` factories work correctly

  **QA Scenarios**:
  ```
  Scenario: Success factory
    Tool: Bash (php -l + tinker)
    Steps:
      1. `php -l app/DTOs/WhatsAppTemplates/TemplateMasterSyncResult.php`
      2. Via tinker: `\App\DTOs\WhatsAppTemplates\TemplateMasterSyncResult::fromTwilioResponse(syncedCount: 5, errorCount: 1, errors: ['Template X failed'])`
    Expected Result: ->syncedCount === 5, ->errorCount === 1
    Evidence: .sisyphus/evidence/task-7-sync-success.txt

  Scenario: Error factory
    Tool: Bash (tinker)
    Steps:
      1. Via tinker: `\App\DTOs\WhatsAppTemplates\TemplateMasterSyncResult::fromError('API unavailable')`
    Expected Result: ->success === false, ->error === 'API unavailable'
    Evidence: .sisyphus/evidence/task-7-sync-error.txt
  ```

  **Commit**: YES (groups with 5, 6)
  - Message: (same as Task 5)
  - Files: `app/DTOs/WhatsAppTemplates/TemplateMasterSyncResult.php`

- [ ] 8. Create `TemplateFetchResult` DTO

  **What to do**:
  - Create `app/DTOs/WhatsAppTemplates/TemplateFetchResult.php`
  - PHP 8.2 readonly class with constructor property promotion
  - Properties: `public readonly bool $success`, `public readonly ?array $templates = null`, `public readonly ?int $count = null`, `public readonly ?string $error = null`
  - Add `toArray(): array` — snake_case keys matching current `WhatsAppTemplateManager::fetchTemplates()` return
  - This replaces `array{success: bool, error?: string, templates?: array, count?: int}` return

  **Must NOT do**:
  - Do NOT type templates array items (provider-specific shapes)

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 9, 10, 11)
  - **Blocks**: Task 15 (WhatsAppTemplateManager update)
  - **Blocked By**: None

  **References**:
  - `app/Services/WhatsAppTemplateManager.php:fetchTemplates()` — Returns raw array
  - TemplateCollectionResult (Task 6) — Similar but for Manager's own fetch mechanism

  **Acceptance Criteria**:
  - [ ] File exists: `app/DTOs/WhatsAppTemplates/TemplateFetchResult.php`
  - [ ] `php -l` → No syntax errors

  **QA Scenarios**:
  ```
  Scenario: Basic construction
    Tool: Bash (php -l + tinker)
    Steps:
      1. `php -l app/DTOs/WhatsAppTemplates/TemplateFetchResult.php`
      2. Via tinker: `new \App\DTOs\WhatsAppTemplates\TemplateFetchResult(success: true, templates: [], count: 0)`
    Expected Result: ->success === true, ->count === 0
    Evidence: .sisyphus/evidence/task-8-fetch.txt
  ```

  **Commit**: YES (groups with 9)
  - Message: `feat(dto): add manager result DTOs`
  - Files: `app/DTOs/WhatsAppTemplates/TemplateFetchResult.php`

- [ ] 9. Create `TemplateDbSyncResult` DTO

  **What to do**:
  - Create `app/DTOs/WhatsAppTemplates/TemplateDbSyncResult.php`
  - PHP 8.2 readonly class with constructor property promotion
  - Properties: `public readonly bool $success`, `public readonly ?int $synced = null`, `public readonly ?string $message = null`, `public readonly ?string $error = null`
  - Add `toArray(): array` — snake_case keys matching current `WhatsAppTemplateManager::syncTemplatesToDatabase()` return
  - This replaces `array{success: bool, synced?: int, message?: string, error?: string}` return

  **Must NOT do**:
  - Do NOT merge with TemplateMasterSyncResult — they serve different callers

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 8, 10, 11)
  - **Blocks**: Task 15 (WhatsAppTemplateManager update), Task 16 (consumer update)
  - **Blocked By**: None

  **References**:
  - `app/Services/WhatsAppTemplateManager.php:syncTemplatesToDatabase()` — Raw array return
  - `app/Console/Commands/SyncWhatsAppTemplates.php` — Consumer that reads `$result['success']`, `$result['synced']`, `$result['error']`

  **Acceptance Criteria**:
  - [ ] File exists: `app/DTOs/WhatsAppTemplates/TemplateDbSyncResult.php`
  - [ ] `php -l` → No syntax errors

  **QA Scenarios**:
  ```
  Scenario: Sync result
    Tool: Bash (php -l + tinker)
    Steps:
      1. `php -l app/DTOs/WhatsAppTemplates/TemplateDbSyncResult.php`
      2. Via tinker: `new \App\DTOs\WhatsAppTemplates\TemplateDbSyncResult(success: true, synced: 10, message: 'Synced 10 templates')`
    Expected Result: ->toArray()['synced'] === 10, ->toArray()['message'] === 'Synced 10 templates'
    Evidence: .sisyphus/evidence/task-9-dbsync.txt
  ```

  **Commit**: YES (groups with 8)
  - Message: (same as Task 8)
  - Files: `app/DTOs/WhatsAppTemplates/TemplateDbSyncResult.php`

- [ ] 10. Update `TemplateManagementInterface` with DTO type hints

  **What to do**:
  - Update `app/Interfaces/TemplateManagementInterface.php`:
    - `createTemplate(int $gymId, array $data): WhatsAppTemplate` → change `array $data` to `CreateTemplateData $data`
    - `submitTemplate(WhatsAppTemplate $template): array` → `TemplateSubmissionResult`
    - `getTemplates(int $gymId): array` → `TemplateCollectionResult`
  - Keep `syncTemplateStatus(WhatsAppTemplate $template): void` unchanged
  - Add `use App\DTOs\WhatsAppTemplates\{CreateTemplateData, TemplateSubmissionResult, TemplateCollectionResult};`
  - Update PHPDoc `@return` annotations

  **Must NOT do**:
  - Do NOT rename methods or change parameter order
  - Do NOT remove `WhatsAppTemplate $template` from submitTemplate — DTO is only for return

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES (with Tasks 8, 9, 11)
  - **Parallel Group**: Wave 3 (with Tasks 8, 9, 11)
  - **Blocks**: Tasks 12, 13, 14
  - **Blocked By**: Tasks 3, 5, 6

  **References**:
  - `app/Interfaces/TemplateManagementInterface.php` — The file to edit
  - Tasks 3, 5, 6 — The DTOs being referenced

  **Acceptance Criteria**:
  - [ ] Interface updated with DTO type hints
  - [ ] `php -l` → No syntax errors

  **QA Scenarios**:
  ```
  Scenario: Verify updated interface signatures
    Tool: Bash (grep + php -l)
    Steps:
      1. `php -l app/Interfaces/TemplateManagementInterface.php`
      2. `grep 'CreateTemplateData' app/Interfaces/TemplateManagementInterface.php`
      3. `grep 'TemplateSubmissionResult' app/Interfaces/TemplateManagementInterface.php`
      4. `grep 'TemplateCollectionResult' app/Interfaces/TemplateManagementInterface.php`
    Expected Result: All 3 DTOs referenced in the interface file
    Evidence: .sisyphus/evidence/task-10-interface.txt
  ```

  **Commit**: YES (groups with 11)
  - Message: `feat(dto): update interfaces with DTO type hints`
  - Files: `app/Interfaces/TemplateManagementInterface.php`

- [ ] 11. Update `WhatsAppProviderInterface` PHPDoc with DTO types

  **What to do**:
  - Update `app/Interfaces/WhatsAppProviderInterface.php` template section (lines ~226-260)
  - Update `@return` PHPDoc annotations to reference DTO classes
  - Add `use App\DTOs\WhatsAppTemplates\{TemplateSubmissionResult, TemplateCollectionResult};`

  **Must NOT do**:
  - PHPDoc update only — method signatures unchanged

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES (with Tasks 8, 9, 10)
  - **Parallel Group**: Wave 3 (with Tasks 8, 9, 10)
  - **Blocks**: None
  - **Blocked By**: Tasks 5, 6

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php` — Lines ~226-260

  **Acceptance Criteria**:
  - [ ] PHPDoc updated with DTO type references
  - [ ] `php -l` → No syntax errors

  **QA Scenarios**:
  ```
  Scenario: Verify PHPDoc references
    Tool: Bash (grep)
    Steps:
      1. `grep 'TemplateSubmissionResult' app/Interfaces/WhatsAppProviderInterface.php`
      2. `grep 'TemplateCollectionResult' app/Interfaces/WhatsAppProviderInterface.php`
    Expected Result: Both DTOs referenced in PHPDoc blocks
    Evidence: .sisyphus/evidence/task-11-provider-interface.txt
  ```

  **Commit**: YES (groups with 10)
  - Message: (same as Task 10)
  - Files: `app/Interfaces/WhatsAppProviderInterface.php`

- [ ] 12. Update `TwilioTemplateService` method signatures

  **What to do**:
  - Update `app/Services/Twilio/TwilioTemplateService.php`:
    - `submitTemplate()` return type: `array` → `TemplateSubmissionResult`
    - `getTemplates()` return type: `array` → `TemplateCollectionResult`
    - `syncTemplatesFromTwilioMasterAccount()` return type: `array` → `TemplateMasterSyncResult`
  - In each method, construct the DTO at the return point using the factory/constructor
  - E.g., `return new TemplateSubmissionResult(success: true, status: 'PENDING', sid: $sid);`
  - E.g., `return TemplateMasterSyncResult::fromError('API error');`
  - Add `use App\DTOs\WhatsAppTemplates\{TemplateSubmissionResult, TemplateCollectionResult, TemplateMasterSyncResult};` imports

  **Must NOT do**:
  - Do NOT change any business logic — only wrap returns in DTOs
  - Do NOT remove `toArray()` calls at consumer boundaries (yet — that's Task 16)

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES (with Tasks 13, 14, 15, 16)
  - **Parallel Group**: Wave 4 (with Tasks 13, 14, 15, 16)
  - **Blocks**: None (leaf task)
  - **Blocked By**: Tasks 5, 6, 7, 10

  **References**:
  - `app/Services/Twilio/TwilioTemplateService.php` — The service file
  - Tasks 5 (TemplateSubmissionResult), 6 (TemplateCollectionResult), 7 (TemplateMasterSyncResult) — The DTOs

  **Acceptance Criteria**:
  - [ ] All 3 return types updated
  - [ ] `php -l app/Services/Twilio/TwilioTemplateService.php` → No syntax errors
  - [ ] `vendor/bin/pint --dirty --format agent` passes

  **QA Scenarios**:
  ```
  Scenario: Verify DTO return types
    Tool: Bash (grep + php -l)
    Steps:
      1. `grep ': TemplateSubmissionResult' app/Services/Twilio/TwilioTemplateService.php`
      2. `grep ': TemplateCollectionResult' app/Services/Twilio/TwilioTemplateService.php`
      3. `grep ': TemplateMasterSyncResult' app/Services/Twilio/TwilioTemplateService.php`
    Expected Result: All 3 DTOs appear as return types
    Evidence: .sisyphus/evidence/task-12-twilio-types.txt
  ```

  **Commit**: YES (groups with 13, 14)
  - Message: `feat(dto): update services with DTO signatures`
  - Files: `app/Services/Twilio/TwilioTemplateService.php`

- [ ] 13. Update `MetaTemplateService` method signatures

  **What to do**:
  - Update `app/Services/Meta/MetaTemplateService.php`:
    - `submitTemplate()` return type: `array` → `TemplateSubmissionResult`
    - `getTemplates()` return type: `array` → `TemplateCollectionResult`
  - Construct DTO at return points
  - Meta returns `template_id` not `sid` — use `templateId` parameter: `new TemplateSubmissionResult(success: true, status: 'APPROVED', templateId: $metaTemplateId)`
  - Add `use App\DTOs\WhatsAppTemplates\{TemplateSubmissionResult, TemplateCollectionResult};` imports

  **Must NOT do**:
  - Do NOT change any business logic

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES (with Tasks 12, 14, 15, 16)
  - **Parallel Group**: Wave 4 (with Tasks 12, 14, 15, 16)
  - **Blocks**: None
  - **Blocked By**: Tasks 5, 6, 10

  **References**:
  - `app/Services/Meta/MetaTemplateService.php` — The service file
  - Tasks 5, 6 — The DTOs

  **Acceptance Criteria**:
  - [ ] `php -l app/Services/Meta/MetaTemplateService.php` → No syntax errors
  - [ ] `vendor/bin/pint --dirty --format agent` passes

  **QA Scenarios**:
  ```
  Scenario: Verify DTO return types
    Tool: Bash (grep + php -l)
    Steps:
      1. `grep ': TemplateSubmissionResult' app/Services/Meta/MetaTemplateService.php`
      2. `grep ': TemplateCollectionResult' app/Services/Meta/MetaTemplateService.php`
    Expected Result: Both DTOs appear as return types
    Evidence: .sisyphus/evidence/task-13-meta-types.txt
  ```

  **Commit**: YES (groups with 12, 14)
  - Message: (same as Task 12)
  - Files: `app/Services/Meta/MetaTemplateService.php`

- [ ] 14. Update `WhatsAppTemplateService` method signatures

  **What to do**:
  - Update `app/Services/WhatsAppTemplateService.php`:
    - `createTemplate(int $gymId, array $data): WhatsAppTemplate` → `createTemplate(int $gymId, CreateTemplateData $data): WhatsAppTemplate`
    - `updateTemplate(WhatsAppTemplate $template, array $data): ?WhatsAppTemplate` → `updateTemplate(WhatsAppTemplate $template, UpdateTemplateData $data): ?WhatsAppTemplate`
  - Inside the methods, call `$data->toArray()` where the original `$data` array was used (backward compat with model `create()`/`update()`)
  - E.g., `$data->toArray()` replaces the raw `$data` array for mass assignment
  - Add `use App\DTOs\WhatsAppTemplates\{CreateTemplateData, UpdateTemplateData};` imports

  **Must NOT do**:
  - Do NOT change `WhatsAppTemplateService::submitForVerification()` return type — this method is deprecated/delegation layer

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES (with Tasks 12, 13, 15, 16)
  - **Parallel Group**: Wave 4 (with Tasks 12, 13, 15, 16)
  - **Blocks**: None
  - **Blocked By**: Tasks 3, 4, 10

  **References**:
  - `app/Services/WhatsAppTemplateService.php` — The base service
  - Tasks 3 (CreateTemplateData), 4 (UpdateTemplateData) — Input DTOs
  - `app/Models/WhatsAppTemplate.php` — Model `$fillable` fields matched by CreateTemplateData

  **Acceptance Criteria**:
  - [ ] `php -l app/Services/WhatsAppTemplateService.php` → No syntax errors
  - [ ] `vendor/bin/pint --dirty --format agent` passes

  **QA Scenarios**:
  ```
  Scenario: Verify DTO parameters
    Tool: Bash (grep + php -l)
    Steps:
      1. `grep 'CreateTemplateData' app/Services/WhatsAppTemplateService.php`
      2. `grep 'UpdateTemplateData' app/Services/WhatsAppTemplateService.php`
    Expected Result: Both DTOs appear as parameter types
    Evidence: .sisyphus/evidence/task-14-service-params.txt
  ```

  **Commit**: YES (groups with 12, 13)
  - Message: (same as Task 12)
  - Files: `app/Services/WhatsAppTemplateService.php`

- [ ] 15. Update `WhatsAppTemplateManager` method signatures

  **What to do**:
  - Update `app/Services/WhatsAppTemplateManager.php`:
    - `fetchTemplates()` return type: `array` → `TemplateFetchResult`
    - `syncTemplatesToDatabase()` return type: `array` → `TemplateDbSyncResult`
  - Construct DTO at return points
  - Add `use App\DTOs\WhatsAppTemplates\{TemplateFetchResult, TemplateDbSyncResult};` imports

  **Must NOT do**:
  - Do NOT refactor WhatsAppTemplateManager architecture

  **Recommended Agent Profile**:
  - Category: `quick`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES (with Tasks 12, 13, 14, 16)
  - **Parallel Group**: Wave 4 (with Tasks 12, 13, 14, 16)
  - **Blocks**: None
  - **Blocked By**: Tasks 8, 9

  **References**:
  - `app/Services/WhatsAppTemplateManager.php` — The legacy manager
  - Tasks 8 (TemplateFetchResult), 9 (TemplateDbSyncResult) — The DTOs

  **Acceptance Criteria**:
  - [ ] `php -l app/Services/WhatsAppTemplateManager.php` → No syntax errors

  **QA Scenarios**:
  ```
  Scenario: Verify DTO return types
    Tool: Bash (grep + php -l)
    Steps:
      1. `grep ': TemplateFetchResult' app/Services/WhatsAppTemplateManager.php`
      2. `grep ': TemplateDbSyncResult' app/Services/WhatsAppTemplateManager.php`
    Expected Result: Both DTOs appear as return types
    Evidence: .sisyphus/evidence/task-15-manager-types.txt
  ```

  **Commit**: YES
  - Message: `feat(dto): update WhatsAppTemplateManager with DTOs`
  - Files: `app/Services/WhatsAppTemplateManager.php`

- [ ] 16. Update consumers that handle array results

  **What to do**:
  - Update consumers to call `->toArray()` on DTO results where they access array keys:
    1. `app/Filament/SuperAdmin/Resources/WhatsAppTemplateResource/Pages/ListWhatsAppTemplates.php` — The sync action reads `$result['success']`, `$result['synced_count']`, etc. Wrap service calls with `->toArray()`
    2. `app/Filament/Admin/Resources/WhatsAppTemplateResource.php` — submit/resubmit actions that read `$result['success']`, `$result['error']`. Call `->toArray()` on DTO returns
    3. `app/Filament/Admin/Resources/WhatsAppTemplateResource/Pages/EditWhatsAppTemplate.php` — Same submit/resubmit pattern
    4. `app/Console/Commands/SyncWhatsAppTemplates.php` — Reads `$result['success']`, `$result['synced']` from syncTemplatesToDatabase(). Call `->toArray()`
    5. `app/Notifications/BaseTenantNotification.php` — Uses WhatsAppTemplateManager. Verify compatibility
    6. `app/Services/WhatsAppTemplateService.php:submitForVerification()` — If it bridges to submitTemplate(), convert DTO back to array via `->toArray()`
    7. `app/Filament/Admin/WhatsAppSetup/Steps/TemplatesStep.php` — If it calls any template methods, ensure DTO compatibility

  **Strategy**: The `toArray()` bridge on every result DTO means consumers continue to work with `$result['key']` pattern. For each consumer, either:
   - **A** (preferred): Update to `$dto->key` property access and remove `->toArray()` call
   - **B** (minimal change): Call `->toArray()` on the DTO and pass to existing code unchanged

  **Must NOT do**:
  - Do NOT break existing notification/command flow
  - Prefer strategy A for new code, B for existing untested paths

  **Recommended Agent Profile**:
  - Category: `unspecified-high`
  - Skills: `['laravel-best-practices']`

  **Parallelization**:
  - **Can Run In Parallel**: YES (with Tasks 12, 13, 14, 15)
  - **Parallel Group**: Wave 4 (with Tasks 12, 13, 14, 15)
  - **Blocks**: None
  - **Blocked By**: Tasks 5-9, 10

  **References**:
  - All result DTOs (Tasks 5-9) — toArray() bridge on each
  - Filament resources listed above — The actual consumer files

  **Acceptance Criteria**:
  - [ ] All consumers updated to work with DTOs via `toArray()` bridge or direct property access
  - [ ] `php -l` on all touched consumer files passes

  **QA Scenarios**:
  ```
  Scenario: Consumer backward compatibility
    Tool: Bash (grep)
    Steps:
      1. `grep -rn '\$result\[' app/Filament/SuperAdmin/Resources/WhatsAppTemplateResource/Pages/ListWhatsAppTemplates.php`
      2. Check that DTO results flow correctly through toArray()
    Expected Result: No broken array access
    Evidence: .sisyphus/evidence/task-16-consumers.txt
  ```

  **Commit**: YES
  - Message: `feat(dto): update consumers with DTO compatibility`
  - Files: (all touched consumer files)

---

## Final Verification Wave (MANDATORY)

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists. For each "Must NOT Have": search codebase for forbidden patterns. Check evidence files exist. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **QA Execution** — `unspecified-high`
  Run `php -l` on every DTO file. Verify each DTO can be instantiated via tinker. Run `grep` to confirm updated type hints in interfaces and services. Run `vendor/bin/pint --dirty --format agent`. Run `php -l` on all modified PHP files.

- [ ] F3. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff. Verify 1:1 — everything built, nothing beyond scope built. Check "Must NOT do" compliance. Detect cross-task contamination.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | VERDICT`

---

## Commit Strategy

- **1-4**: `feat(dto): add template value objects and input DTOs`
- **5-7**: `feat(dto): add template result DTOs`
- **8-9**: `feat(dto): add manager result DTOs`
- **10-11**: `feat(dto): update interfaces with DTO type hints`
- **12-16**: `feat(dto): update services and consumers with DTOs`
- **F**: `chore: final verification`

---

## Success Criteria

### Verification Commands
```bash
php -l app/DTOs/WhatsAppTemplates/*.php
# Expected: No syntax errors detected

grep -n 'CreateTemplateData\|UpdateTemplateData' app/Services/WhatsAppTemplateService.php
# Expected: Method signatures use DTO types

grep -n 'TemplateSubmissionResult\|TemplateCollectionResult' app/Interfaces/TemplateManagementInterface.php
# Expected: Return types use DTOs
```

### Final Checklist
- [ ] 9 DTO files created with PHP 8.2 readonly classes
- [ ] All 4 services updated with DTO type hints
- [ ] Both interfaces updated
- [ ] All consumers updated (Filament, commands, notifications)
- [ ] `toArray()` bridge on all result DTOs
- [ ] All PHP syntax checks pass
- [ ] Pint formatting clean
