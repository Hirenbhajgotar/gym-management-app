---
name: laravel-intelligent-refactor
description: >
  Intelligent refactoring skill for Laravel/PHP codebases using DDD, SOLID, DRY principles.
  Use when: user asks to refactor code, improve architecture, extract services/actions/DTOs,
  reduce duplication, or modernize legacy Laravel code. ALWAYS ask clarifying questions
  before making changes. NEVER guess intent.
license: MIT
compatibility: opencode, laravel-boost
metadata:
  author: opencode-community
  version: "2.0"
  language: php
  framework: laravel
  patterns: [ddd, solid, dry, action-classes, services, dtos]
---


# Laravel Intelligent Refactor Skill



## Philosophy


> **Refactoring without tests is reckless. Refactoring without understanding is destructive.**
> **Zero false positives. Every issue must be reproducible, actionable, and verifiable.**

This skill enforces a 6-phase deterministic refactoring protocol with 8-dimension validation. Every refactor must:
1. **Understand** the intent before touching code
2. **Map** the entire codebase impact zone (via @code-explorer)
3. **Assess** test coverage and 8-dimension risk
4. **Plan** meticulously with atomic steps addressing all dimensions
5. **Execute** with LSP precision and AST-grep safety
6. **Verify** after every single change across all 8 dimensions


## The 9 Refactor Dimensions


Every refactor MUST validate these categories. Load `laravel-code-review` skill for detailed review framework and `laravel-code-extraction` skill for extraction patterns.

| # | Dimension | Key Checks |

|---|-----------|------------|
| 1 | **BUSINESS LOGIC** | Domain rules, invariants, state transitions, auth alignment |
| 2 | **USER EXPERIENCE** | Error messages, validation clarity, API consistency, async feedback |
| 3 | **RACE CONDITIONS** | DB locking, atomic ops, queue uniqueness (`withoutOverlapping`), cache locks |
| 4 | **IDEMPOTENCY** | Duplicate handling, `updateOrCreate`, unique constraints, retry safety |
| 5 | **RUNTIME ERRORS** | Null safety (`??`, `optional()`), type safety, API error handling, timeouts |
| 6 | **BUGS & ANTI-PATTERNS** | God classes, N+1 queries, dead code, DRY violations, tight coupling |
| 7 | **SECURITY** | SQL injection (parameter binding), auth (Policies/Gates), mass assignment (`$fillable`), secrets |
| 8 | **CODE QUALITY** | Type declarations, PSR compliance, method length, complexity, naming |


### Dimension Integration in 6-Phase Protocol


- **Phase 1 (Analysis)**: @code-explorer gathers context + runs 9-dimension pre-audit + identifies extraction opportunities
- **Phase 4 (Plan)**: Each atomic step addresses specific dimension issues + extraction plan
- **Phase 5 (Execute)**: After each step, verify relevant dimensions + extraction quality
- **Phase 6 (Final)**: Full 9-dimension re-review on ALL modified files + verify Laravel best practices


## When to Activate


Activate this skill when the user mentions:
- "refactor", "clean up", "improve", "restructure", "modernize"
- Extracting logic into Services, Actions, DTOs, Value Objects
- Reducing duplication, applying DRY
- Domain-Driven Design, SOLID principles
- Laravel architecture improvements
- Code smell fixes (fat controllers, god classes, etc.)


## Before Any Action: Intent Gate (MANDATORY)



### Step 0.1: Parse Request Type


| Signal | Classification | Action |
|--------|----------------|--------|
| Specific file/symbol provided | Explicit | Proceed to analysis |
| "Refactor X to Y" | Clear transformation | Proceed to analysis |
| "Improve", "Clean up", "Better way" | Open-ended | **MUST ask**: "What specific improvement?" |
| Ambiguous scope | Uncertain | **MUST ask**: "Which modules/files?" |
| Missing context | Incomplete | **MUST ask**: "What's the desired outcome?" |


### Step 0.2: Validate Understanding (ASK USER)


Before proceeding, confirm ALL of these are clear:
- [ ] Target file(s)/module clearly identified
- [ ] Desired outcome understood (extract service? reduce duplication? DDD migration?)
- [ ] Scope defined (single file / module / project-wide)
- [ ] Success criteria can be articulated

**If ANY is unclear, ASK this exact template:**

```
I want to make sure I understand the refactoring goal correctly.

**What I understood**: [your interpretation]
**What I'm unsure about**: [specific ambiguity]

Options I see:
1. [Option A] - [implications]
2. [Option B] - [implications]

**My recommendation**: [suggestion with reasoning]

Should I proceed with [recommendation], or would you prefer differently?
```


### Step 0.3: Create Initial Todos


**IMMEDIATELY** after understanding the request, create todos:

```
TodoWrite([
  {"id": "phase-0", "content": "PHASE 0: Intent Gate - validate understanding", "status": "completed", "priority": "high"},
  {"id": "phase-1", "content": "PHASE 1: Codebase Analysis - explore target and dependencies", "status": "pending", "priority": "high"},
  {"id": "phase-2", "content": "PHASE 2: Build Codemap - map impact zones and patterns", "status": "pending", "priority": "high"},
  {"id": "phase-3", "content": "PHASE 3: Test Assessment - analyze coverage and verification strategy", "status": "pending", "priority": "high"},
  {"id": "phase-4", "content": "PHASE 4: Plan Generation - create detailed atomic refactoring plan", "status": "pending", "priority": "high"},
  {"id": "phase-5", "content": "PHASE 5: Execute Refactoring - step-by-step with continuous verification", "status": "pending", "priority": "high"},
  {"id": "phase-6", "content": "PHASE 6: Final Verification - full regression check", "status": "pending", "priority": "high"}
])
```

---


## PHASE 1: Codebase Analysis (PARALLEL EXPLORATION)


**Mark `phase-1` as `in_progress`.**


### 1.1: Launch @code-explorer (BACKGROUND)


The orchestrator (or this agent if running standalone) calls `@code-explorer` subagents in background to gather full context BEFORE any refactoring.

Invoke ALL of these by sending `@code-explorer` messages (NOT `call_omo_agent` which may resolve to built-in `@explore`):

```
// Agent 1: Target Discovery
Send: @code-explorer Find all occurrences, definitions, and usages of [TARGET].
Report: file paths, line numbers, class hierarchy, method signatures.

// Agent 2: Dependency Graph
Send: @code-explorer Find all code that imports, uses, or depends on [TARGET].
Report: dependency chains, import graphs, DI container bindings.

// Agent 3: Pattern Discovery
Send: @code-explorer Find similar code patterns to [TARGET] in the codebase.
Report: analogous implementations, established Laravel conventions, existing Services/Actions/DTOs.

// Agent 4: Test Discovery
Send: @code-explorer Find all test files related to [TARGET].
Report: test file paths, test case names, feature/unit test distinction, coverage indicators.

// Agent 5: Architecture Context
Send: @code-explorer Find architectural patterns and module organization around [TARGET].
Report: module boundaries, layer structure (Controller/Service/Repository/Model), design patterns.

// Agent 6: 9-Dimension Pre-Audit + Extraction (CRITICAL)
Send: @code-explorer Review [TARGET] across 9 dimensions:
1. BUSINESS_LOGIC - Domain rules, state transitions, calculations
2. UX - Error messages, validation, API consistency
3. RACE_CONDITIONS - Locking, atomic ops, concurrent access
4. IDEMPOTENCY - Duplicate handling, retry safety
5. RUNTIME_ERRORS - Null safety, type safety, API errors
6. BUGS_ANTI_PATTERNS - God classes, N+1, dead code
7. SECURITY - SQL injection, auth, mass assignment
8. CODE_QUALITY - Types, PSR, complexity, naming
9. CODE_EXTRACTION - What to extract: Services, Actions, DTOs, Traits, Enums, Value Objects, Repositories, Form Requests, Policies, Events, Listeners, Notifications

Also check LARAVEL BEST PRACTICES:
- DB transactions for multi-step ops
- Cache::lock() for race conditions
- Eager loading for relationships
- Chunked/cursor for large datasets
- Form Requests for validation
- Policies for authorization
- env() only in config files
- Type declarations on all methods
- Structured logging with context

Report: Specific issues with file paths, line numbers, severity (CRITICAL/HIGH/MEDIUM/LOW).
Report: Extraction opportunities with recommended locations.
```


### 1.2: Direct Tool Exploration (While Agents Run)


Use these tools directly:

**LSP Tools:**
```php
// Find definitions
LspGotoDefinition(filePath, line, character)

// Find ALL references across workspace
LspFindReferences(filePath, line, character, includeDeclaration=true)

// Get file structure
LspDocumentSymbols(filePath)
LspWorkspaceSymbols(filePath, query="[target_symbol]")

// Get diagnostics baseline
lsp_diagnostics(filePath)
```

**AST-Grep for PHP:**
```bash

# Find class structures

ast-grep --lang php --pattern 'class $NAME { $$$ }' --json


# Find method patterns

ast-grep --lang php --pattern 'function $NAME($$$) { $$$ }' --json


# Find dependency injections

ast-grep --lang php --pattern '__construct($$$)' --json


# DRY RUN preview (ALWAYS)

ast-grep --lang php --pattern '[old]' --rewrite '[new]' --dry-run
```

**Grep for Text Patterns:**
```bash
grep -rn "class ImportWhatsAppTemplatesJob" app/
grep -rn "MetaTemplateService|TwilioTemplateService" app/
grep -rn "use App\\Services" app/Jobs/
```

**Laravel-Boost MCP Tools:**
```bash

# Use laravel-boost tools if available

# - analyze:architecture

# - analyze:dependencies

# - generate:service

# - generate:action

# - generate:dto

```


### 1.3: Collect Results


Gather all subagent outputs and tool results.

**Mark `phase-1` as `completed`.**

---


## PHASE 2: Build Codemap (DEPENDENCY MAPPING)


**Mark `phase-2` as `in_progress`.**


### 2.1: Construct Definitive Codemap


Build this exact structure:

```markdown

## CODEMAP: [TARGET]



### Core Files (Direct Impact)

- `path/to/file.php:L10-L50` - Primary definition
- `path/to/file2.php:L25` - Key usage


### Dependency Graph

```
[TARGET]
├── imports from:
│   ├── App\Services\X (constructor injection)
│   └── App\Models\Y (direct use)
├── imported by:
│   ├── App\Http\Controllers\Z
│   └── App\Jobs\W
└── used by:
    ├── routes/web.php (route binding)
    └── config/services.php (config reference)
```


### Impact Zones

| Zone | Risk Level | Files Affected | Test Coverage |
|------|------------|----------------|---------------|
| Core | HIGH | 3 files | 85% covered |
| Consumers | MEDIUM | 8 files | 70% covered |
| Edge | LOW | 2 files | 50% covered |


### Established Patterns

- Pattern A: [description] - used in N places
- Pattern B: [description] - established convention
```


### 2.2: Identify Refactoring Constraints


Based on codemap, document:
- **MUST follow**: [existing patterns identified]
- **MUST NOT break**: [critical dependencies, public APIs, route bindings]
- **Safe to change**: [isolated code zones]
- **Requires migration**: [breaking changes impact]

**Mark `phase-2` as `completed`.**

---


## PHASE 3: Test Assessment (VERIFICATION STRATEGY)


**Mark `phase-3` as `in_progress`.**


### 3.1: Detect Test Infrastructure


```bash

# Laravel test detection

ls -la phpunit.xml phpunit.xml.dist
ls -la tests/Feature tests/Unit
ls -la .env.testing


# Check test commands

php artisan test --list-tests 2>/dev/null || true
php artisan test --filter=[TARGET] 2>/dev/null || true
```


### 3.2: Analyze Test Coverage


```
Send: @code-explorer Analyze test coverage for [TARGET]:
1. Which test files cover this code?
2. What test cases exist (happy path, edge cases, exceptions)?
3. Are there integration/feature tests?
4. What edge cases are tested?
5. Estimated coverage percentage?
```


### 3.3: Determine Verification Strategy


| Coverage Level | Strategy |
|----------------|----------|
| HIGH (>80%) | Run existing tests after each step |
| MEDIUM (50-80%) | Run tests + add safety assertions |
| LOW (<50%) | **PAUSE**: Propose adding tests first |
| NONE | **BLOCK**: Refuse aggressive refactoring |

**If coverage is LOW or NONE, ask user:**

```
Test coverage for [TARGET] is [LEVEL].

**Risk Assessment**: Refactoring without adequate tests is dangerous.

Options:
1. Add tests first, then refactor (RECOMMENDED)
2. Proceed with extra caution, manual verification required
3. Abort refactoring

Which approach do you prefer?
```


### 3.4: Document Verification Plan


```markdown

## VERIFICATION PLAN



### Test Commands

- Unit: `php artisan test --filter=[TARGET]`
- Feature: `php artisan test tests/Feature/`
- Static Analysis: `phpstan analyse` / `psalm`
- Lint: `pint --test`


### Verification Checkpoints

After each refactoring step:
1. lsp_diagnostics -> zero new errors
2. Run test command -> all pass
3. Static analysis -> clean
4. Type check (if using PHPStan/Psalm) -> clean


### Regression Indicators

- [Specific test that must pass]
- [Behavior that must be preserved]
- [API contract that must not change]
```

**Mark `phase-3` as `completed`.**

---


## PHASE 4: Plan Generation


**Mark `phase-4` as `in_progress`.**


### 4.1: Create Detailed Refactoring Plan


Use `@plan` agent or create plan manually:

```markdown

## Refactoring Plan: [TARGET]



### Goal

[User's original request]


### Codemap Summary

[Insert from Phase 2]


### Test Coverage

[Insert from Phase 3]


### Constraints

- MUST follow existing patterns: [list]
- MUST NOT break: [critical paths]
- MUST run tests after each step


### Atomic Steps

1. **[Step 1]**: [description] -> [files affected]
2. **[Step 2]**: [description] -> [files affected]
3. ...


### Rollback Strategy

- Each step is independently reversible via git
- Before/after snapshots for each step
```


### 4.2: Address 9-Dimension Issues + Extraction Plan


From the @code-explorer pre-audit (Phase 1, Agent 6), create specific refactoring steps for each dimension AND extraction opportunities:

```markdown

## Dimension Issue Resolution Plan



### BUSINESS_LOGIC

- [ ] Fix: [specific issue] -> [file:line]
- [ ] Verify: [how to verify]


### RACE_CONDITIONS

- [ ] Fix: [specific issue] -> [file:line]
- [ ] Verify: [how to verify]


### IDEMPOTENCY

- [ ] Fix: [specific issue] -> [file:line]
- [ ] Verify: [how to verify]


### RUNTIME_ERRORS

- [ ] Fix: [specific issue] -> [file:line]
- [ ] Verify: [how to verify]


### BUGS_ANTI_PATTERNS

- [ ] Fix: [specific issue] -> [file:line]
- [ ] Verify: [how to verify]


### SECURITY

- [ ] Fix: [specific issue] -> [file:line]
- [ ] Verify: [how to verify]


### CODE_QUALITY

- [ ] Fix: [specific issue] -> [file:line]
- [ ] Verify: [how to verify]
```


### 4.3: Extraction Plan


Using `laravel-code-extraction` skill, plan extractions:

```markdown

## Extraction Plan



### Services to Extract

- [ ] `App\Services\[Name]Service` from [file:lines] - [reason]


### Actions to Extract

- [ ] `App\Actions\[Name]Action` from [file:lines] - [reason]


### DTOs to Extract

- [ ] `App\DTOs\[Name]DTO` from [file:lines] - [reason]


### Value Objects to Extract

- [ ] `App\ValueObjects\[Name]` from [file:lines] - [reason]


### Traits to Extract

- [ ] `App\Traits\Has[Name]` from [file:lines] - [reason]


### Enums to Extract

- [ ] `App\Enums\[Name]Enum` from [file:lines] - [reason]


### Form Requests to Extract

- [ ] `App\Http\Requests\[Name]Request` from [file:lines] - [reason]


### Policies to Extract

- [ ] `App\Policies\[Name]Policy` from [file:lines] - [reason]


### Laravel Best Practices to Apply

- [ ] Add DB transactions to [method] in [file]
- [ ] Add Cache::lock() to [method] in [file]
- [ ] Add eager loading to [query] in [file]
- [ ] Extract validation to Form Request [name]
- [ ] Extract auth to Policy [name]
- [ ] Add type declarations to [methods]
- [ ] Add structured logging to [operations]
```


### 4.4: Apply Laravel Architecture Patterns


When planning, ALWAYS consider these Laravel/PHP patterns:


#### A. Service Extraction

- If business logic exists in Controller/Job/Command -> extract to `App\Services\`
- Use constructor injection, never `new Service()` in controllers
- Single Responsibility: one service per domain concept


#### B. Action Classes

- For single-purpose operations -> `App\Actions\`
- Implement `__invoke()` for callable actions
- Keep stateless, inject dependencies via constructor


#### C. DTOs (Data Transfer Objects)

- For complex data passing -> `App\DTOs\`
- Use readonly properties (PHP 8.1+)
- Add static `fromArray()` / `fromRequest()` factories


#### D. Value Objects

- For domain concepts with validation -> `App\ValueObjects\`
- Immutable, self-validating
- Example: `PhoneNumber`, `Money`, `EmailAddress`


#### E. Repository Pattern (when needed)

- For complex query logic -> `App\Repositories\`
- Interface + Eloquent implementation
- Only when query complexity justifies abstraction


#### F. Reuse Existing Classes

- BEFORE creating new classes, check:
  - `App\Services\*` -- does a similar service exist?
  - `App\Actions\*` -- does a similar action exist?
  - `App\DTOs\*` -- does a similar DTO exist?
  - Traits in `App\Traits\`
  - Base classes and abstract classes
- **DRY Principle**: If logic exists elsewhere, refactor to reuse, don't duplicate


### 4.3: Register Granular Todos


```
TodoWrite([
  {"id": "refactor-1", "content": "Step 1: [description]", "status": "pending", "priority": "high"},
  {"id": "verify-1", "content": "Verify Step 1: run tests", "status": "pending", "priority": "high"},
  {"id": "refactor-2", "content": "Step 2: [description]", "status": "pending", "priority": "medium"},
  {"id": "verify-2", "content": "Verify Step 2: run tests", "status": "pending", "priority": "medium"}
])
```

**Mark `phase-4` as `completed`.**

---


## PHASE 5: Execute Refactoring (DETERMINISTIC EXECUTION)


**Mark `phase-5` as `in_progress`.**


### 5.1: Execution Protocol


For EACH step:


#### Pre-Step

1. Mark step todo as `in_progress`
2. Read current file state
3. Verify `lsp_diagnostics` baseline is clean
4. **Git checkpoint**: `git add -A && git commit -m "checkpoint: before [step description]"`


#### Execute Step

Use appropriate tool:

**For Symbol Renames:**
```php
lsp_prepare_rename(filePath, line, character)
lsp_rename(filePath, line, character, newName)
```

**For Structural Transformations:**
```bash

# AST-grep DRY RUN first

ast-grep --lang php --pattern '[old]' --rewrite '[new]' --dry-run


# If preview good, execute

ast-grep --lang php --pattern '[old]' --rewrite '[new]'
```

**For Laravel Class Generation:**
```bash

# Prefer artisan generators

php artisan make:service [Name]        # if laravel-boost provides

php artisan make:action [Name]         # if laravel-boost provides

php artisan make:dto [Name]            # if laravel-boost provides



# Or use laravel-boost MCP tools

# generate:service, generate:action, generate:dto

```

**For Manual Edits:**
Use `edit()` tool with precise oldString/newString.


#### Post-Step Verification (MANDATORY)


```php
// 1. Check diagnostics
lsp_diagnostics(filePath)

// 2. Run tests
bash("php artisan test --filter=[related]")

// 3. Static analysis
bash("phpstan analyse [files] --level=8")

// 4. Lint check
bash("pint --test [files]")
```


#### Step Completion

- If ALL pass -> Mark step todo as `completed`
- If ANY fails -> **STOP AND FIX** (see Failure Recovery)


### 5.2: Failure Recovery Protocol


If verification fails:

1. **STOP** immediately
2. **REVERT**: `git checkout -- [files]` or `git reset --hard HEAD`
3. **DIAGNOSE**: Check error messages, LSP diagnostics, test failures
4. **OPTIONS**:
   - Fix issue and retry
   - Skip step (if optional)
   - Consult `@oracle` agent for help
   - Ask user for guidance

**NEVER proceed to next step with broken tests.**


### 5.3: Commit Checkpoints


After each logical group:
```bash
git add [changed-files]
git commit -m "refactor(scope): description

- What changed
- Why it changed
- Tests: [status]"
```

**Mark `phase-5` as `completed`.**

---


## PHASE 6: Final Verification (REGRESSION CHECK)


**Mark `phase-6` as `in_progress`.**


### 6.1: Full Test Suite

```bash
php artisan test
```


### 6.2: Static Analysis

```bash
phpstan analyse app/ --level=max

# or psalm

```


### 6.3: Lint Check

```bash
pint --test
```


### 6.4: Architecture Validation (Laravel-Boost)

```bash

# If laravel-boost tools available:

# analyze:architecture --check-cycles

# analyze:dependencies --verify-layers

```


### 6.5: Final Diagnostics

```php
for (file of changedFiles) {
  lsp_diagnostics(file)  // Must all be clean
}
```


### 6.6: 9-Dimension Final Review + Extraction Validation


Run full 9-dimension review on ALL modified files. Compare with Phase 1 pre-audit:

```markdown

## 8-Dimension Review: Before vs After


| Dimension | Issues Before | Issues After | Status |
|-----------|---------------|--------------|--------|
| Business Logic | [count] | [count] | PASS / NEEDS_FIX |
| UX | [count] | [count] | PASS / NEEDS_FIX |
| Race Conditions | [count] | [count] | PASS / NEEDS_FIX |
| Idempotency | [count] | [count] | PASS / NEEDS_FIX |
| Runtime Errors | [count] | [count] | PASS / NEEDS_FIX |
| Bugs & Anti-Patterns | [count] | [count] | PASS / NEEDS_FIX |
| Security | [count] | [count] | PASS / NEEDS_FIX |
| Code Quality | [count] | [count] | PASS / NEEDS_FIX |
| Code Extraction | [count] | [count] | PASS / NEEDS_FIX |
```

**If ANY dimension shows "NEEDS_FIX", STOP and fix before completing.**


### 6.7: Generate Summary


```markdown

## Refactoring Complete



### What Changed

- [List of architectural improvements]
- [List of extractions performed]


### Files Modified

- `path/to/file.php` - [what changed]
- `app/Services/NewService.php` - [new service]
- `app/Actions/NewAction.php` - [new action]
- `app/DTOs/NewDTO.php` - [new DTO]


### Patterns Applied

- Service Extraction: [details]
- Action Classes: [details]
- DTOs: [details]
- Value Objects: [details]
- Trait Extraction: [details]
- Repository Extraction: [details]
- Enum Extraction: [details]
- Form Request Extraction: [details]
- Policy Extraction: [details]
- DRY Compliance: [details]


### 9-Dimension Results

| Dimension | Status |
|-----------|--------|
| Business Logic | PASS |
| UX | PASS |
| Race Conditions | PASS |
| Idempotency | PASS |
| Runtime Errors | PASS |
| Bugs & Anti-Patterns | PASS |
| Security | PASS |
| Code Quality | PASS |
| Code Extraction | PASS |


### Laravel Best Practices Applied

| Practice | Status |
|----------|--------|
| DB Transactions | PASS |
| Cache::lock() | PASS |
| Eager Loading | PASS |
| Form Requests | PASS |
| Policies | PASS |
| Type Declarations | PASS |
| Structured Logging | PASS |


### Verification Results

- Tests: PASSED (X/Y passing)
- PHPStan: CLEAN
- Pint: CLEAN
- Architecture: VALIDATED
- 9-Dimension Review: ALL PASS
- Laravel Best Practices: ALL PASS


### No Regressions Detected

All existing tests pass. No new errors introduced. No new issues in any dimension.
```

**Mark `phase-6` as `completed`.**

---


## CRITICAL RULES



### NEVER DO

- Skip lsp_diagnostics check after changes
- Proceed with failing tests
- Make changes without understanding impact
- Use `@ts-ignore` equivalent (`// phpcs:ignore` without reason)
- Delete tests to make them pass
- Commit broken code
- Refactor without understanding existing patterns
- Create new classes when existing ones can be reused
- Ignore Laravel conventions (PSR-4 autoloading, namespace standards)
- Skip 9-dimension review at any phase
- Skip extraction analysis for monolithic files
- Report issues without verification (zero false positives)
- Ignore race conditions or idempotency issues
- Ignore Laravel best practices (transactions, locks, eager loading)


### ALWAYS DO

- Understand before changing
- Preview before applying (ast-grep dry-run)
- Verify after every change
- Follow existing codebase patterns
- Keep todos updated in real-time
- Commit at logical checkpoints
- Report issues immediately
- Reuse existing Services, Actions, DTOs when possible
- Apply SOLID: Single Responsibility, Open/Closed, Liskov, Interface Segregation, Dependency Inversion
- Apply DRY: Extract shared logic to traits, base classes, or services
- Validate ALL 9 dimensions after each significant change
- Use @code-explorer for background analysis before refactoring
- Load laravel-code-review skill for 9-dimension framework
- Load laravel-code-extraction skill for extraction patterns
- Apply Laravel best practices: transactions, locks, eager loading, Form Requests, Policies


### ABORT CONDITIONS

If any occur, **STOP and consult user**:
- Test coverage is zero for target code
- Changes would break public API (routes, published events, external consumers)
- Refactoring scope is unclear after asking
- 3 consecutive verification failures
- User-defined constraints violated
- Circular dependency would be introduced

---


## Laravel-Specific Patterns Reference



### Service Class Template

```php
<?php

namespace App\Services;

class WhatsAppTemplateService
{
    public function __construct(
        private readonly MetaTemplateService $metaService,
        private readonly TwilioTemplateService $twilioService,
    ) {}

    public function import(): void
    {
        // Business logic
    }
}
```


### Action Class Template

```php
<?php

namespace App\Actions;

class ImportWhatsAppTemplatesAction
{
    public function __construct(
        private readonly WhatsAppTemplateService $service,
    ) {}

    public function __invoke(): void
    {
        // Single-purpose operation
    }
}
```


### DTO Template

```php
<?php

namespace App\DTOs;

readonly class WhatsAppTemplateDTO
{
    public function __construct(
        public string $name,
        public string $language,
        public array $components,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            language: $data['language'],
            components: $data['components'] ?? [],
        );
    }
}
```


### Value Object Template

```php
<?php

namespace App\ValueObjects;

final class TemplateLanguage
{
    private function __construct(
        private readonly string $code,
    ) {
        if (!preg_match('/^[a-z]{2}_[A-Z]{2}$/', $code)) {
            throw new \InvalidArgumentException('Invalid language code format');
        }
    }

    public static function from(string $code): self
    {
        return new self($code);
    }

    public function code(): string
    {
        return $this->code;
    }
}
```

---


## Tool Integration Guide



### LSP Tools (Priority: HIGH)

- `LspGotoDefinition` -- Understand before changing
- `LspFindReferences` -- Map all usages before modification
- `lsp_prepare_rename` -> `lsp_rename` -- Safe symbol renames
- `lsp_diagnostics` -- Continuous verification


### AST-Grep (Priority: HIGH)

- `ast-grep --lang php --pattern '...' --dry-run` -- Preview first
- `ast-grep --lang php --pattern '...' --rewrite '...'` -- Execute
- Use for structural transformations across multiple files


### Laravel-Boost MCP (Priority: HIGH)

- `analyze:architecture` -- Verify layer compliance
- `analyze:dependencies` -- Check coupling
- `generate:service` -- Create service scaffolding
- `generate:action` -- Create action scaffolding
- `generate:dto` -- Create DTO scaffolding


### Static Analysis (Priority: MEDIUM)

- `phpstan analyse` -- Type safety
- `psalm` -- Advanced type analysis
- `pint` -- Code style


### Testing (Priority: HIGH)

- `php artisan test` -- Full suite
- `php artisan test --filter=` -- Targeted
- `php artisan test --coverage` -- Coverage report