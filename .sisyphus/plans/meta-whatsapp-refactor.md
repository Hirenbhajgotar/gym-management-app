# MetaWhatsAppProvider Refactoring - Composition Over Implementation

## TL;DR

> **Quick Summary**: Refactor the 1,215-line `MetaWhatsAppProvider` fat provider into a thin orchestrator + focused services using the composition pattern from `TwilioWhatsAppProvider`.
> 
> **Deliverables**: 
> - `App\Services\Meta\MetaContext.php` - Shared state/configuration
> - `App\Services\Meta\MetaApiClient.php` - HTTP client for Meta Graph API
> - `App\Services\Meta\MetaMessagingService.php` → MessagingInterface
> - `App\Services\Meta\MetaTemplateService.php` → TemplateManagementInterface
> - `App\Services\Meta\MetaCreditService.php` → CreditManagementInterface
> - `App\Services\Meta\MetaWabaService.php` → WabaSetupInterface
> - `App\Services\Meta\MetaEmbeddedSignupService.php` → EmbeddedSignupInterface
> - Refactored `App\Providers\MetaWhatsAppProvider.php` - Thin orchestrator (~100 lines)
>
> **Estimated Effort**: Medium
> **Parallel Execution**: YES - 3 waves
> **Critical Path**: MetaContext → MetaApiClient → Services → Provider refactoring

- [ ] 4. Create MetaMessagingService

  **What to do**:
  - Create `App\Services\Meta\MetaMessagingService.php`
  - Implement `MessagingInterface` (sendMessage, sendTemplateMessage, sendTestMessage, sendBroadcast, getMessageStatus)
  - Inject MetaContext and MetaCreditService in constructor
  - Use MetaApiClient for HTTP requests (not direct Http facade)
  - Extract logic from MetaWhatsAppProvider:131-345 (messaging methods)
  - Handle all Meta API specific payload formatting (text messages, template messages)

  **Must NOT do**:
  - Do NOT put HTTP calls directly (use MetaApiClient)
  - Do NOT implement credit checking (that's in MetaCreditService)
  - Do NOT modify `MessagingInterface`

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Reason**: Implements full interface with multiple methods, extracts complex logic from 200+ lines, uses Eloquent models (WhatsAppTemplate, WhatsAppBroadcast, etc.)
  > **Skills**: `laravel-best-practices`, `laravel-specialist`, `laravel-boost` (for models, Http facade)
  > **Skills Evaluated but Omitted**: `fluxui-development` (no UI), `volt-development` (no Volt)

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 5, 6, 7)
  - **Blocks**: Task 8 (provider refactoring needs messaging service)
  - **Blocked By**: Tasks 1, 2, 3 (needs context, API client, credits)

  **References** (CRITICAL - Be Exhaustive):

  **Pattern References** (existing code to follow):
  - `app/Services/Twilio/TwilioMessagingService.php:1-340` - Exact pattern (constructor injection, interface implementation, error handling)
  - `app/Interfaces/MessagingInterface.php:1-87` - Interface to implement

  **Current Logic to Extract**:
  - `app/Providers/MetaWhatsAppProvider.php:131-203` - sendMessage() logic
  - `app/Providers/MetaWhatsAppProvider.php:208-284` - sendTemplateMessage() logic
  - `app/Providers/MetaWhatsAppProvider.php:289-292` - sendTestMessage() logic
  - `app/Providers/MetaWhatsAppProvider.php:297-345` - sendBroadcast() logic
  - `app/Providers/MetaWhatsAppProvider.php:350-362` - getMessageStatus() logic

  **Model References** (types to use):
  - `app/Models/WhatsAppTemplate.php` - Template queries, incrementUsage()
  - `app/Models/WhatsAppBroadcast.php` - Broadcast operations
  - `app/Models/WhatsAppBroadcastRecipient.php` - Recipient marking
  - `app/Models/WhatsAppUsageLog.php` - Usage logging
  - `app/Models/GymMember.php` - Member data for variables

  **Helper References**:
  - `app/Services/WhatsAppVariableResolver.php` - Template variable resolution
  - `app/Services/WhatsAppTemplateService.php` - Template creation/submit

  **WHY Each Reference Matters**:
  - TwilioMessagingService: Shows exact pattern (readonly properties, dependency injection, return types)
  - MetaWhatsAppProvider:131-345: Contains ALL messaging logic to extract
  - WhatsAppTemplate model: Shows how to query templates, check status, increment usage
  - WhatsAppVariableResolver: Needed for prepareVariables() in sendBroadcast()

  **Acceptance Criteria**:

  > **AGENT-EXECUTABLE VERIFICATION ONLY** - No human action permitted.

  **Verification**:
  - [ ] `docker-compose exec app php -l app/Services/Meta/MetaMessagingService.php` → No syntax errors
  - [ ] Class implements `MessagingInterface`
  - [ ] Methods: `sendMessage()`, `sendTemplateMessage()`, `sendTestMessage()`, `sendBroadcast()`, `getMessageStatus()`
  - [ ] Injects MetaContext, MetaCreditService via constructor (readonly properties)
  - [ ] Uses MetaApiClient (not direct Http) for API calls

  **QA Scenarios (MANDATORY - task is INCOMPLETE without these)**:

  ```
  Scenario: Send text message via Meta API (Happy path)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: Valid Meta access token, phone number ID configured
    Steps:
      1. Create service: `$service = new \App\Services\Meta\MetaMessagingService($context, $creditService)`
      2. Mock Http: Use Http::fake() to simulate Meta API success
      3. Call: `->sendMessage('+1234567890', 'Hello from gym!')`
      4. Assert: `success` is true, `message_id` exists
    Expected Result: `['success' => true, 'message_id' => '...', 'status' => 'sent']`
    Failure Indicators: Exception thrown, success=false without error
    Evidence: .sisyphus/evidence/task-4-send-message.txt

  Scenario: Template not found (Failure/edge case)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: Template 'nonexistent' does not exist in database
    Steps:
      1. Call: `->sendTemplateMessage('+1234567890', 'nonexistent', [])`
      2. Assert: `success` is false
      3. Assert: `error` contains "not found or not approved"
    Expected Result: `['success' => false, 'error' => "Template 'nonexistent' not found..."]`
    Evidence: .sisyphus/evidence/task-4-template-notfound.txt
  ```

  **Evidence to Capture:**
  - [ ] Tinker output for happy path and failure scenarios
  - [ ] Mock HTTP responses for Meta API

  **Commit**: YES
  - Message: `feat(services): add Meta messaging service with API integration`
  - Files: `app/Services/Meta/MetaMessagingService.php`
  - Pre-commit: `vendor/bin/pint app/Services/Meta/MetaMessagingService.php`

- [ ] 5. Create MetaTemplateService

  **What to do**:
  - Create `App\Services\Meta\MetaTemplateService.php`
  - Implement `TemplateManagementInterface` (createTemplate, submitTemplate, syncTemplateStatus, getTemplates)
  - Inject MetaContext in constructor
  - Use MetaApiClient for Meta API calls (submit to Meta, sync status)
  - Extract logic from MetaWhatsAppProvider:419-539 (template methods)
  - Keep WhatsAppTemplateService usage for local template creation (already exists)

  **Must NOT do**:
  - Do NOT implement local template creation (use existing WhatsAppTemplateService)
  - Do NOT put HTTP calls directly (use MetaApiClient)
  - Do NOT modify `TemplateManagementInterface`

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Reason**: Implements interface, extracts template logic from 120+ lines, interacts with Meta API and local database.
  > **Skills**: `laravel-best-practices`, `laravel-specialist`, `laravel-boost` (for models, API docs)
  > **Skills Evaluated but Omitted**: `stitch-ui-design` (no UI design)

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 6, 7)
  - **Blocks**: Task 8 (provider refactoring needs template service)
  - **Blocked By**: Tasks 1, 2 (needs context, API client)

  **References** (CRITICAL - Be Exhaustive):

  **Pattern References** (existing code to follow):
  - `app/Providers/MetaWhatsAppProvider.php:419-539` - Current template logic to extract
  - `app/Interfaces/TemplateManagementInterface.php` - Interface to implement

  **Current Logic to Extract**:
  - `app/Providers/MetaWhatsAppProvider.php:419-424` - createTemplate() (delegates to WhatsAppTemplateService)
  - `app/Providers/MetaWhatsAppProvider.php:429-470` - submitTemplate() to Meta
  - `app/Providers/MetaWhatsAppProvider.php:475-505` - syncTemplateStatus() from Meta
  - `app/Providers/MetaWhatsAppProvider.php:510-539` - getTemplates() from Meta

  **Existing Service Reference**:
  - `app/Services/WhatsAppTemplateService.php` - Already handles local template creation

  **Model References**:
  - `app/Models/WhatsAppTemplate.php` - Template model (meta_template_id, status, etc.)

  **WHY Each Reference Matters**:
  - MetaWhatsAppProvider:419-539: Contains ALL template logic to extract
  - TemplateManagementInterface: Defines the contract to implement
  - WhatsAppTemplateService: Shows how to reuse existing service for local operations

  **Acceptance Criteria**:

  **Verification**:
  - [ ] `docker-compose exec app php -l app/Services/Meta/MetaTemplateService.php` → No syntax errors
  - [ ] Class implements `TemplateManagementInterface`
  - [ ] Methods: `createTemplate()`, `submitTemplate()`, `syncTemplateStatus()`, `getTemplates()`
  - [ ] Uses MetaApiClient for Meta API operations

  **QA Scenarios (MANDATORY)**:

  ```
  Scenario: Submit template to Meta (Happy path)
    Tool: Bash (docker-compose exec app php artisan test)
    Preconditions: Template exists with approved status
    Steps:
      1. Run: `php artisan test --compact --filter=test_submit_template`
      2. Assert: API called with correct payload
      3. Assert: Template status updated to 'pending'
    Expected Result: Test passes, Meta API called correctly
    Evidence: .sisyphus/evidence/task-5-submit-template.txt

  Scenario: Sync template status from Meta (Happy path)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: Template has meta_template_id set
    Steps:
      1. Mock Http: Meta API returns APPROVED status
      2. Call: `->syncTemplateStatus($template)`
      3. Assert: Template status changed to 'approved'
    Expected Result: Status updated correctly
    Evidence: .sisyphus/evidence/task-5-sync-status.txt
  ```

  **Evidence to Capture:**
  - [ ] Test output for template operations
  - [ ] Tinker output for sync status

  **Commit**: YES
  - Message: `feat(services): add Meta template service for Meta API integration`
  - Files: `app/Services/Meta/MetaTemplateService.php`
  - Pre-commit: `vendor/bin/pint app/Services/Meta/MetaTemplateService.php`

- [ ] 6. Create MetaWabaService

  **What to do**:
  - Create `App\Services\Meta\MetaWabaService.php`
  - Implement `WabaSetupInterface` (createWaba, connectIndividualWaba, connectManualWaba, verifyWabaConnection)
  - Inject MetaContext in constructor
  - Use MetaApiClient for Meta API calls (verify WABA, get business info)
  - Extract logic from MetaWhatsAppProvider:597-742 (WABA setup), 794-923 (verification)
  - Handle DB::transaction for connectManualWaba (preserve existing logic)

  **Must NOT do**:
  - Do NOT put HTTP calls directly (use MetaApiClient)
  - Do NOT modify `WabaSetupInterface`
  - Do NOT change WhatsAppAccount/WhatsAppPhoneNumber model structure

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Reason**: Implements interface, extracts 300+ lines of WABA logic, handles complex DB transactions, Meta API integration.
  > **Skills**: `laravel-best-practices`, `laravel-specialist`, `laravel-boost` (for DB transactions, models)
  > **Skills Evaluated but Omitted**: `phpunit-best-practices` (can use if writing tests)

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 5, 7)
  - **Blocks**: Task 8 (provider refactoring needs WABA service)
  - **Blocked By**: Tasks 1, 2 (needs context, API client)

  **References** (CRITICAL - Be Exhaustive):

  **Pattern References** (existing code to follow):
  - `app/Providers/MetaWhatsAppProvider.php:597-742` - WABA connection logic
  - `app/Providers/MetaWhatsAppProvider.php:744-785` - Helper methods (normalizePhoneNumber, etc.)
  - `app/Providers/MetaWhatsAppProvider.php:794-923` - verifyWabaConnection() logic
  - `app/Interfaces/WabaSetupInterface.php:1-112` - Interface to implement

  **Current Logic to Extract**:
  - `app/Providers/MetaWhatsAppProvider.php:597-604` - createWaba()
  - `app/Providers/MetaWhatsAppProvider.php:609-625` - connectIndividualWaba()
  - `app/Providers/MetaWhatsAppProvider.php:630-742` - connectManualWaba() (with DB::transaction)
  - `app/Providers/MetaWhatsAppProvider.php:794-923` - verifyWabaConnection()
  - `app/Providers/MetaWhatsAppProvider.php:747-785` - Helper methods

  **Model References**:
  - `app/Models/WhatsAppAccount.php` - Account updates (waba_id, meta_access_token, etc.)
  - `app/Models/WhatsAppPhoneNumber.php` - Phone number creation/update
  - `app/Models/WabaSetupSession.php` - Session management

  **Enum References**:
  - `app/Enums/WabaModeEnum.php` - WABA mode (INDIVIDUAL, MANUAL, SHARED)
  - `app/Enums/WabaStatusEnum.php` - WABA status (ACTIVE, PENDING, etc.)
  - `app/Enums/WhatsAppAccountVerificationStatusEnum.php` - Verification status
  - `app/Enums/WhatsAppPhoneNumberTypesEnum.php` - Phone type (BUSINESS, etc.)

  **WHY Each Reference Matters**:
  - MetaWhatsAppProvider:597-923: Contains ALL WABA logic to extract (300+ lines)
  - WabaSetupInterface: Defines the contract to implement
  - WhatsAppAccount model: Shows fields to update (waba_id, waba_mode, meta_access_token, etc.)
  - Helper methods (normalizePhoneNumber, etc.): Need to be extracted to service or kept as private helpers

  **Acceptance Criteria**:

  **Verification**:
  - [ ] `docker-compose exec app php -l app/Services/Meta/MetaWabaService.php` → No syntax errors
  - [ ] Class implements `WabaSetupInterface`
  - [ ] Methods: `createWaba()`, `connectIndividualWaba()`, `connectManualWaba()`, `verifyWabaConnection()`
  - [ ] Uses MetaApiClient for Meta API operations
  - [ ] connectManualWaba() uses DB::transaction (preserve existing logic)

  **QA Scenarios (MANDATORY)**:

  ```
  Scenario: Verify WABA connection (Happy path)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: Valid WABA ID, phone number ID, access token
    Steps:
      1. Mock Http: Meta API returns verified phone number details
      2. Call: `->verifyWabaConnection($wabaId, $phoneNumberId, $token)`
      3. Assert: `success` is true, `verified` is true
      4. Assert: `business_name`, `waba_status` returned
    Expected Result: `['success' => true, 'verified' => true, ...]`
    Failure Indicators: Success=false, missing required fields
    Evidence: .sisyphus/evidence/task-6-verify-waba.txt

  Scenario: Missing access token (Failure/edge case)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: No access token provided
    Steps:
      1. Call: `->verifyWabaConnection('waba123', 'phone123', null)`
      2. Assert: `success` is false
      3. Assert: `error` contains "access token is required"
    Expected Result: `['success' => false, 'error' => 'Meta access token is required...']`
    Evidence: .sisyphus/evidence/task-6-no-token.txt
  ```

  **Evidence to Capture:**
  - [ ] Tinker output for verification scenarios
  - [ ] Mock HTTP responses for Meta API

  **Commit**: YES
  - Message: `feat(services): add Meta WABA service for WABA setup and verification`
  - Files: `app/Services/Meta/MetaWabaService.php`
  - Pre-commit: `vendor/bin/pint app/Services/Meta/MetaWabaService.php`

- [ ] 7. Create MetaEmbeddedSignupService

  **What to do**:
  - Create `App\Services\Meta\MetaEmbeddedSignupService.php`
  - Implement `EmbeddedSignupInterface` (startEmbeddedSignup, getEmbeddedSignupLoginUrl, handleEmbeddedSignupCallback)
  - Inject MetaContext in constructor
  - Extract logic from MetaWhatsAppProvider:1014-1127 (start signup), 1050-1127 (handle callback)
  - Extract helper methods: getMetaAppConfig(), exchangeCodeForToken(), debugToken()
  - Use MetaApiClient where applicable (or Http facade for OAuth endpoints)

  **Must NOT do**:
  - Do NOT modify `EmbeddedSignupInterface`
  - Do NOT change WabaSetupSession model structure
  - Do NOT put OAuth logic directly in provider

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Reason**: Implements interface, extracts OAuth flow (200+ lines), handles token exchange, debug token, session management.
  > **Skills**: `laravel-best-practices`, `laravel-specialist`, `laravel-boost` (for config, Http facade, sessions)
  > **Skills Evaluated but Omitted**: `seo-audit` (no SEO work)

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 5, 6)
  - **Blocks**: Task 8 (provider refactoring needs embedded signup service)
  - **Blocked By**: Tasks 1, 2 (needs context, API client)

  **References** (CRITICAL - Be Exhaustive):

  **Pattern References** (existing code to follow):
  - `app/Providers/MetaWhatsAppProvider.php:1014-1048` - startEmbeddedSignup() and getMetaAppConfig()
  - `app/Providers/MetaWhatsAppProvider.php:1050-1127` - handleEmbeddedSignupCallback()
  - `app/Providers/MetaWhatsAppProvider.php:1132-1163` - exchangeCodeForToken()
  - `app/Providers/MetaWhatsAppProvider.php:1168-1214` - debugToken()
  - `app/Interfaces/EmbeddedSignupInterface.php:1-50` - Interface to implement

  **Current Logic to Extract**:
  - `app/Providers/MetaWhatsAppProvider.php:1017-1026` - getMetaAppConfig() (config values)
  - `app/Providers/MetaWhatsAppProvider.php:1033-1048` - startEmbeddedSignup() (create session, return URL)
  - `app/Providers/MetaWhatsAppProvider.php:1053-1056` - getEmbeddedSignupLoginUrl() (alias)
  - `app/Providers/MetaWhatsAppProvider.php:1064-1127` - handleEmbeddedSignupCallback() (exchange code, debug token)
  - `app/Providers/MetaWhatsAppProvider.php:1132-1163` - exchangeCodeForToken() (OAuth token exchange)
  - `app/Providers/MetaWhatsAppProvider.php:1168-1214` - debugToken() (extract WABA ID from token)

  **Model References**:
  - `app/Models/WabaSetupSession.php` - Session creation, marking methods (markAsExpired, markAsCompleted, etc.)

  **Config References**:
  - `config/services.php` - Twilio/Meta config (app_id, app_secret, api_version, redirect_uri, config_id)

  **WHY Each Reference Matters**:
  - MetaWhatsAppProvider:1014-1214: Contains ALL embedded signup logic to extract (200+ lines)
  - EmbeddedSignupInterface: Defines the contract to implement
  - WabaSetupSession model: Shows session methods to use (createForGym, markAsExpired, etc.)
  - getMetaAppConfig(): Shows config keys needed (services.twilio.meta.*)

  **Acceptance Criteria**:

  **Verification**:
  - [ ] `docker-compose exec app php -l app/Services/Meta/MetaEmbeddedSignupService.php` → No syntax errors
  - [ ] Class implements `EmbeddedSignupInterface`
  - [ ] Methods: `startEmbeddedSignup()`, `getEmbeddedSignupLoginUrl()`, `handleEmbeddedSignupCallback()`
  - [ ] Helper methods: `getMetaAppConfig()`, `exchangeCodeForToken()`, `debugToken()`

  **QA Scenarios (MANDATORY)**:

  ```
  Scenario: Start embedded signup (Happy path)
    Tool: Bash (docker-compose exec app php artisan test)
    Preconditions: Config has meta app_id, app_secret, etc.
    Steps:
      1. Run: `php artisan test --compact --filter=test_start_embedded_signup`
      2. Assert: Returns valid Facebook OAuth URL
      3. Assert: URL contains client_id, redirect_uri, state (session_id)
    Expected Result: URL starts with 'https://www.facebook.com/', contains required params
    Evidence: .sisyphus/evidence/task-7-start-signup.txt

  Scenario: Invalid session in callback (Failure/edge case)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: No WabaSetupSession with given state
    Steps:
      1. Call: `->handleEmbeddedSignupCallback('invalid_code', 'invalid_state')`
      2. Assert: `success` is false
      3. Assert: `error` contains "Invalid session"
    Expected Result: `['success' => false, 'error' => 'Invalid session']`
    Evidence: .sisyphus/evidence/task-7-invalid-session.txt
  ```

  **Evidence to Capture:**
  - [ ] Test output for signup flow
  - [ ] Tinker output for callback handling

  **Commit**: YES
  - Message: `feat(services): add Meta embedded signup service for OAuth flow`
  - Files: `app/Services/Meta/MetaEmbeddedSignupService.php`
  - Pre-commit: `vendor/bin/pint app/Services/Meta/MetaEmbeddedSignupService.php`

- [ ] 8. Refactor MetaWhatsAppProvider to Thin Orchestrator

  **What to do**:
  - Refactor `app/Providers/MetaWhatsAppProvider.php` from 1,215 lines to ~100 lines
  - Convert to thin orchestrator pattern (matching TwilioWhatsAppProvider:31-248)
  - Add lazy-loaded service accessors (messaging(), templates(), credits(), waba(), embeddedSignup())
  - All public methods delegate to appropriate services
  - Keep only: constructor, service accessors, and delegation methods
  - Remove ALL business logic (now in services)

  **Must NOT do**:
  - Do NOT keep business logic in provider (must be in services)
  - Do NOT add HTTP calls directly (use MetaApiClient via services)
  - Do NOT change method signatures (must match interfaces)
  - Do NOT change return types or error handling patterns

  **Recommended Agent Profile**:
  > **Category**: `deep`
  > **Reason**: Complex refactoring requiring understanding of all extracted services, interface contracts, and delegation pattern. Must ensure zero functionality change.
  > **Skills**: `laravel-best-practices`, `laravel-specialist`, `laravel-boost` (for models, interfaces)
  > **Skills Evaluated but Omitted**: `frontend-design` (no UI work), `stitch-ui-design` (no design)

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on all services)
  - **Parallel Group**: Wave 3 (after Tasks 4, 5, 6, 7 complete)
  - **Blocks**: Tasks 9, 10 (Pint, tests)
  - **Blocked By**: Tasks 4, 5, 6, 7 (all services must exist)

  **References** (CRITICAL - Be Exhaustive):

  **Pattern References** (existing code to follow):
  - `app/Providers/TwilioWhatsAppProvider.php:31-248` - EXACT pattern to follow (thin orchestrator, lazy-loaded services, delegation)
  - `app/Providers/TwilioWhatsAppProvider.php:53-71` - Service accessor pattern (lazy loading with `??=`)

  **Service References** (new services to delegate to):
  - `app/Services/Meta/MetaMessagingService.php` - For sendMessage(), sendTemplateMessage(), sendTestMessage(), sendBroadcast(), getMessageStatus()
  - `app/Services/Meta/MetaTemplateService.php` - For createTemplate(), submitTemplate(), syncTemplateStatus(), getTemplates()
  - `app/Services/Meta/MetaCreditService.php` - For getCreditBalance(), getMonthlyUsage()
  - `app/Services/Meta/MetaWabaService.php` - For createWaba(), connectIndividualWaba(), connectManualWaba(), verifyWabaConnection()
  - `app/Services/Meta/MetaEmbeddedSignupService.php` - For startEmbeddedSignup(), getEmbeddedSignupLoginUrl(), handleEmbeddedSignupCallback()

  **Interface References** (contracts to maintain):
  - `app/Interfaces/MessagingInterface.php` - Methods: sendMessage, sendTemplateMessage, sendTestMessage, sendBroadcast, getMessageStatus
  - `app/Interfaces/TemplateManagementInterface.php` - Methods: createTemplate, submitTemplate, syncTemplateStatus, getTemplates
  - `app/Interfaces/CreditManagementInterface.php` - Methods: getCreditBalance, getMonthlyUsage
  - `app/Interfaces/WabaSetupInterface.php` - Methods: createWaba, connectIndividualWaba, connectManualWaba, verifyWabaConnection
  - `app/Interfaces/EmbeddedSignupInterface.php` - Methods: startEmbeddedSignup, getEmbeddedSignupLoginUrl, handleEmbeddedSignupCallback
  - `app/Interfaces/TestMessagingInterface.php` - Methods: sendTestWhatsAppMessage

  **WHY Each Reference Matters**:
  - TwilioWhatsAppProvider:31-248: Shows exact target pattern (248 lines with delegation)
  - TwilioWhatsAppProvider:53-71: Shows service accessor pattern (lazy loading with `??=`)
  - MetaMessagingService: Must delegate messaging methods to this service
  - MetaWabaService: Must delegate WABA setup methods to this service
  - MetaEmbeddedSignupService: Must delegate OAuth flow methods to this service
  - All Interface files: Must maintain exact method signatures for backward compatibility

  **Acceptance Criteria**:

  > **AGENT-EXECUTABLE VERIFICATION ONLY** - No human action permitted.
  > Every criterion MUST be verifiable by running a command or using a tool.

  **Verification**:
  - [ ] `docker-compose exec app php -l app/Providers/MetaWhatsAppProvider.php` → No syntax errors
  - [ ] File reduced from 1,215 lines to ~100 lines (target: under 150 lines)
  - [ ] ALL public methods delegate to services (no business logic in provider)
  - [ ] Service accessors use lazy loading pattern: `return $this->service ??= new Service($this->context, ...)`
  - [ ] Constructor only initializes MetaContext (matching TwilioWhatsAppProvider:44-47)
  - [ ] Implements all 6 interfaces (check with `implements` keyword)

  **QA Scenarios (MANDATORY - task is INCOMPLETE without these)**:

  > **Minimum: 1 happy path + 1 failure/edge case per task.**

  ```
  Scenario: Provider delegates sendMessage to service (Happy path)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: MetaContext configured, MetaMessagingService exists
    Steps:
      1. Create provider: `$provider = app(\App\Providers\MetaWhatsAppProvider::class, ['gymId' => 1])`
      2. Mock MetaMessagingService::sendMessage() to return ['success' => true]
      3. Call: `$provider->sendMessage('+1234567890', 'test')`
      4. Assert: Returns ['success' => true]
    Expected Result: Delegation works, returns service result
    Failure Indicators: Business logic in provider, direct HTTP calls
    Evidence: .sisyphus/evidence/task-8-delegation.txt

  Scenario: Provider reduced in size (Happy path)
    Tool: Bash (wc -l app/Providers/MetaWhatsAppProvider.php)
    Preconditions: Refactoring complete
    Steps:
      1. Run: `wc -l app/Providers/MetaWhatsAppProvider.php`
      2. Assert: Line count < 150 (target: ~100 lines)
      3. Compare to original: 1,215 lines
    Expected Result: File is ~92% smaller
    Failure Indicators: File still has 500+ lines (business logic not extracted)
    Evidence: .sisyphus/evidence/task-8-size.txt

  Scenario: Service accessor lazy loading (Happy path)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: Provider instantiated
    Steps:
      1. Create provider: `$provider = new \App\Providers\MetaWhatsAppProvider(1)`
      2. Check: services are null initially (private properties)
      3. Call: `$provider->sendMessage(...)` triggers service creation
      4. Assert: Service is now cached (same instance returned)
    Expected Result: Lazy loading works (service created on first use)
    Evidence: .sisyphus/evidence/task-8-lazy-loading.txt
  ```

  **Evidence to Capture:**
  - [ ] Line count before/after refactoring
  - [ ] Tinker output showing delegation works
  - [ ] Proof that services are lazy-loaded

  **Commit**: YES
  - Message: `refactor(provider): slim down MetaWhatsAppProvider to orchestrator`
  - Files: `app/Providers/MetaWhatsAppProvider.php`
  - Pre-commit: `vendor/bin/pint app/Providers/MetaWhatsAppProvider.php`

- [ ] 9. Run Pint Formatter

  **What to do**:
  - Run `vendor/bin/pint --dirty` to format all changed files
  - Fix any formatting issues in new services and refactored provider
  - Run on entire `app/Services/Meta/` directory

  **Must NOT do**:
  - Do NOT run `vendor/bin/pint --test` (use `pint` only to fix)
  - Do NOT format files outside scope (Twilio services, interfaces)

  **Recommended Agent Profile**:
  > **Category**: `quick`
  > **Reason**: Straightforward Pint formatting run, no logic changes.
  > **Skills**: `laravel-best-practices` (Pint rule), `laravel-specialist`
  > **Skills Evaluated but Omitted**: All others (no relevance to formatting)

  **Parallelization**:
  - **Can Run In Parallel**: NO (needs refactored provider)
  - **Parallel Group**: Wave 3
  - **Blocks**: Task 10 (tests should run after formatting)
  - **Blocked By**: Task 8 (provider must be refactored first)

  **References** (CRITICAL - Be Exhaustive):

  **Rule References**:
  - `pint/core rules` - Laravel Pint Code Formatter (must run before finalizing)
  - `AGENTS.md` - Pint command: `vendor/bin/pint --dirty`

  **Files to Format**:
  - `app/Services/Meta/MetaContext.php`
  - `app/Services/Meta/MetaApiClient.php`
  - `app/Services/Meta/MetaMessagingService.php`
  - `app/Services/Meta/MetaTemplateService.php`
  - `app/Services/Meta/MetaCreditService.php`
  - `app/Services/Meta/MetaWabaService.php`
  - `app/Services/Meta/MetaEmbeddedSignupService.php`
  - `app/Providers/MetaWhatsAppProvider.php`

  **WHY Each Reference Matters**:
  - Pint rules: Mandates formatting before finalizing
  - All Meta service files: Must be formatted to project standards
  - MetaWhatsAppProvider: Must be formatted after refactoring

  **Acceptance Criteria**:

  **Verification**:
  - [ ] `docker-compose exec app vendor/bin/pint --dirty` → No errors
  - [ ] All changed files pass Pint formatting
  - [ ] No syntax errors introduced by formatting

  **QA Scenarios (MANDATORY)**:

  ```
  Scenario: Pint formatting passes (Happy path)
    Tool: Bash (docker-compose exec app vendor/bin/pint --dirty)
    Preconditions: All Meta services and provider created
    Steps:
      1. Run: `vendor/bin/pint --dirty`
      2. Assert: Exit code 0 (no errors)
      3. Assert: No output (no files modified)
    Expected Result: All files correctly formatted
    Failure Indicators: Pint errors, files modified by Pint
    Evidence: .sisyphus/evidence/task-9-pint.txt
  ```

  **Evidence to Capture:**
  - [ ] Pint command output (should show no errors)

  **Commit**: NO (formatting is part of other commits)

- [ ] 10. Run Tests to Verify Functionality

  **What to do**:
  - Run existing tests to verify zero functionality breakage
  - Run: `docker-compose exec app php artisan test --compact --env=testing`
  - Focus on WhatsApp-related tests
  - Verify MetaWhatsAppProvider still works via WhatsAppProviderFactory

  **Must NOT do**:
  - Do NOT modify existing tests (unless explicitly requested)
  - Do NOT create new test database (use existing gym_app_test)
  - Do NOT run tests on main database

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Reason**: Running test suite to verify backward compatibility, checking WhatsApp functionality.
  > **Skills**: `laravel-best-practices` (test enforcement), `laravel-specialist` (PHPUnit), `laravel-boost` (test commands)
  > **Skills Evaluated but Omitted**: `phpunit-best-practices` (can use if needed)

  **Parallelization**:
  - **Can Run In Parallel**: NO (needs formatted code)
  - **Parallel Group**: Wave 3
  - **Blocks**: Final Verification Wave
  - **Blocked By**: Tasks 8, 9 (provider refactored and formatted)

  **References** (CRITICAL - Be Exhaustive):

  **Rule References**:
  - `phpunit/core rules` - PHPUnit testing (run minimal tests, filter by name)
  - `tests rules` - Test Enforcement (every change must be programmatically tested)
  - `AGENTS.md` - Test command: `php artisan test --compact`

  **Test References**:
  - `tests/Feature/WhatsApp/` - Existing WhatsApp tests to run
  - `php artisan test --compact --filter=WhatsApp` - Filter for WhatsApp tests

  **WHY Each Reference Matters**:
  - PHPUnit rules: How to run tests (filter, compact mode)
  - Tests rules: Verification that functionality is preserved
  - Existing tests: Baseline to compare against

  **Acceptance Criteria**:

  **Verification**:
  - [ ] `docker-compose exec app php artisan test --compact --env=testing` → All tests pass
  - [ ] No tests broken by refactoring
  - [ ] MetaWhatsAppProvider works via WhatsAppProviderFactory

  **QA Scenarios (MANDATORY)**:

  ```
  Scenario: All WhatsApp tests pass (Happy path)
    Tool: Bash (docker-compose exec app php artisan test)
    Preconditions: Refactoring complete, Pint run
    Steps:
      1. Run: `php artisan test --compact --filter=WhatsApp --env=testing`
      2. Assert: All tests pass (0 failures)
      3. Check: No deprecation warnings related to refactoring
    Expected Result: 100% pass rate
    Failure Indicators: Failed tests, errors related to MetaWhatsAppProvider
    Evidence: .sisyphus/evidence/task-10-tests.txt

  Scenario: Provider factory creates Meta provider (Happy path)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: Config set to use Meta provider
    Steps:
      1. Run: `config(['services.twilio.provider' => 'waba'])`
      2. Call: `app(\App\Providers\WhatsAppProviderFactory::class)->create(1)`
      3. Assert: Returns MetaWhatsAppProvider instance
      4. Assert: Can call methods (sendMessage, etc.)
    Expected Result: Factory returns working Meta provider
    Evidence: .sisyphus/evidence/task-10-factory.txt
  ```

  **Evidence to Capture:**
  - [ ] Test output (pass/fail counts)
  - [ ] Tinker output showing factory works

  **Commit**: NO (verification step, not a commit)

---


### Original Request
Refactor `App\Providers\MetaWhatsAppProvider.php` from fat service (1,215 lines) to composition pattern using services, following the `TwilioWhatsAppProvider` pattern.

### Interview Summary
**Key Discussions**:
- User wants composition over implementation (matching Twilio pattern)
- Reference implementation: `TwilioWhatsAppProvider.php` (248 lines) + `App\Services\Twilio\*` services
- MUST NOT change working functionality or interfaces
- MUST activate `laravel-best-practices` and `laravel-specialist` skills

**Research Findings**:
- Twilio pattern: Provider (orchestrator) → Context (shared state) → Services (focused implementation)
- Services implement specific interfaces (MessagingInterface, CreditManagementInterface, etc.)
- Services are lazy-loaded via accessor methods in provider
- Error handling: return arrays with `success`, `error`, `data` keys

### Reference Pattern (Twilio)
```
TwilioWhatsAppProvider (248 lines)
├── TwilioContext (shared: gymId, account, client management)
├── TwilioMessagingService → MessagingInterface (sendMessage, sendTemplateMessage, etc.)
├── TwilioAccountService → AccountManagementInterface (createSubAccount, getAccountStatus, etc.)
├── TwilioCreditService → CreditManagementInterface (getCreditBalance, getMonthlyUsage, etc.)
└── TwilioPhoneService → PhoneNumberManagementInterface (getAvailableNumbers, purchaseNumber, etc.)
```

---

## Work Objectives

### Core Objective
Transform `MetaWhatsAppProvider` from a 1,215-line fat provider into a thin orchestrator (~100 lines) with focused services (~400-500 lines total) following the Twilio composition pattern.

### Concrete Deliverables
- `App\Services\Meta\MetaContext.php` - Shared context (gymId, account, apiVersion, access token)
- `App\Services\Meta\MetaApiClient.php` - HTTP client wrapper for Meta Graph API
- `App\Services\Meta\MetaMessagingService.php` - Messaging operations (implements MessagingInterface)
- `App\Services\Meta\MetaTemplateService.php` - Template operations (implements TemplateManagementInterface)
- `App\Services\Meta\MetaCreditService.php` - Credit management (implements CreditManagementInterface)
- `App\Services\Meta\MetaWabaService.php` - WABA setup/verification (implements WabaSetupInterface)
- `App\Services\Meta\MetaEmbeddedSignupService.php` - OAuth flow (implements EmbeddedSignupInterface)
- Refactored `App\Providers\MetaWhatsAppProvider.php` - Thin orchestrator with delegation

### Definition of Done
- [ ] All existing interfaces unchanged (MessagingInterface, TemplateManagementInterface, etc.)
- [ ] MetaWhatsAppProvider reduced from 1,215 to ~100 lines
- [ ] All business logic extracted to focused services
- [ ] Services follow Twilio service pattern (constructor injection, interface implementation)
- [ ] All existing functionality preserved (verified by running tests)
- [ ] Pint formatting passes (`vendor/bin/pint --dirty`)

### Must Have
- Composition pattern matching TwilioWhatsAppProvider
- Services in `App\Services\Meta\` directory
- Lazy-loaded services via accessor methods in provider
- Error handling consistent with existing pattern (return arrays)
- Use `laravel-best-practices` and `laravel-specialist` skills

### Must NOT Have (Guardrails)
- NO changes to existing interfaces
- NO changes to models (WhatsAppAccount, WhatsAppTemplate, etc.)
- NO changes to Twilio services
- NO functionality changes (only structural refactoring)
- NO direct HTTP calls in services (must use MetaApiClient)
- NO business logic remaining in provider after refactoring

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** - ALL verification is agent-executed.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: Tests-after (TDD not required for refactoring)
- **Framework**: PHPUnit (php artisan make:test --phpunit)
- **Note**: This is a structural refactoring - run existing tests to verify no functionality breakage

### QA Policy
Every task MUST include agent-executed QA scenarios.

- **API Services**: Use Bash (curl) to test Meta API endpoints
- **Provider**: Use Bash (php artisan tinker) to verify delegation works
- **Evidence saved to**: `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`

---

## Execution Strategy

### Parallel Execution Waves

> Maximize throughput by grouping independent tasks into parallel waves.
> Target: 5-8 tasks per wave. Fewer than 3 per wave = under-splitting.

```
Wave 1 (Start Immediately - Foundation):
├── Task 1: Create MetaContext [quick]
├── Task 2: Create MetaApiClient [quick]
└── Task 3: Create MetaCreditService [unspecified-high]

Wave 2 (After Wave 1 - Core Services):
├── Task 4: Create MetaMessagingService (depends: 1, 2, 3) [unspecified-high]
├── Task 5: Create MetaTemplateService (depends: 1, 2) [unspecified-high]
├── Task 6: Create MetaWabaService (depends: 1, 2) [unspecified-high]
└── Task 7: Create MetaEmbeddedSignupService (depends: 1, 2) [unspecified-high]

Wave 3 (After Wave 2 - Orchestrator + Cleanup):
├── Task 8: Refactor MetaWhatsAppProvider (depends: 4, 5, 6, 7) [deep]
├── Task 9: Run Pint formatter (depends: 8) [quick]
└── Task 10: Run tests to verify (depends: 8) [unspecified-high]

FINAL (After ALL tasks - 4 parallel reviews):
├── F1: Plan compliance audit (oracle)
├── F2: Code quality review (unspecified-high)
├── F3: Real manual QA (unspecified-high)
└── F4: Scope fidelity check (deep)
-> Present results -> Get explicit user okay

Critical Path: Task 1 → Task 2 → Task 4/5/6/7 → Task 8 → Task 9/10 → F1-F4 → user okay
Parallel Speedup: ~70% faster than sequential
Max Concurrent: 4 (Waves 1-2)
```

### Dependency Matrix

| Task | Depends On | Blocks | Parallel Group |
|------|------------|--------|----------------|
| 1 (MetaContext) | - | 4, 5, 6, 7, 8 | Wave 1 |
| 2 (MetaApiClient) | - | 4, 5, 6, 7 | Wave 1 |
| 3 (MetaCreditService) | - | 4 | Wave 1 |
| 4 (MetaMessagingService) | 1, 2, 3 | 8 | Wave 2 |
| 5 (MetaTemplateService) | 1, 2 | 8 | Wave 2 |
| 6 (MetaWabaService) | 1, 2 | 8 | Wave 2 |
| 7 (MetaEmbeddedSignupService) | 1, 2 | 8 | Wave 2 |
| 8 (Refactor Provider) | 4, 5, 6, 7 | 9, 10 | Wave 3 |
| 9 (Pint) | 8 | F1-F4 | Wave 3 |
| 10 (Tests) | 8 | F1-F4 | Wave 3 |

### Agent Dispatch Summary

- **1**: **3** - T1 (MetaContext) → `quick`, T2 (MetaApiClient) → `quick`, T3 (MetaCreditService) → `unspecified-high`
- **2**: **4** - T4 (MetaMessagingService) → `unspecified-high`, T5 (MetaTemplateService) → `unspecified-high`, T6 (MetaWabaService) → `unspecified-high`, T7 (MetaEmbeddedSignupService) → `unspecified-high`
- **3**: **3** - T8 (Refactor Provider) → `deep`, T9 (Pint) → `quick`, T10 (Tests) → `unspecified-high`
- **FINAL**: **4** - F1 → `oracle`, F2 → `unspecified-high`, F3 → `unspecified-high`, F4 → `deep`

---

## TODOs

> Implementation + Test = ONE Task. Never separate.
> EVERY task MUST have: Recommended Agent Profile + Parallelization info + QA Scenarios.

---

## Final Verification Wave

> 4 review agents run in PARALLEL. ALL must APPROVE. Present consolidated results to user and get explicit "okay" before completing.
>
> **Do NOT auto-proceed after verification. Wait for user's explicit approval before marking work complete.**
> **Never mark F1-F4 as checked before getting user's okay.** Rejection or user feedback -> fix -> re-run -> present again -> wait for okay.

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read file). For each "Must NOT Have": search codebase for forbidden patterns — reject with file:line if found. Check evidence files exist in .sisyphus/evidence/. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `vendor/bin/pint --dirty` + `php artisan test --compact`. Review all changed files for: `as any`/`@ts-ignore`, empty catches, console.log in prod, commented-out code, unused imports. Check AI slop: excessive comments, over-abstraction, generic names (data/result/item/temp).
  Output: `Pint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high` (+ `laravel-boost`)
  Start from clean state. Execute provider via tinker: `app(WhatsAppProviderFactory::class)->create($gymId)`. Test: sendMessage returns array, getCreditBalance works, template operations work. Save to `.sisyphus/evidence/final-qa/`.
  Output: `Provider [PASS/FAIL] | Services [N/N pass] | Integration [PASS/FAIL] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff (git log/diff). Verify 1:1 — everything in spec was built (no missing), nothing beyond spec was built (no creep). Check "Must NOT do" compliance. Detect cross-task contamination.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Commit Strategy

- **1**: `refactor(providers): extract Meta services to App\Services\Meta\` - MetaContext.php, MetaApiClient.php, MetaCreditService.php, `vendor/bin/pint`
- **2**: `feat(services): add Meta messaging and template services` - MetaMessagingService.php, MetaTemplateService.php, `vendor/bin/pint`
- **3**: `feat(services): add Meta WABA and embedded signup services` - MetaWabaService.php, MetaEmbeddedSignupService.php, `vendor/bin/pint`
- **4**: `refactor(provider): slim down MetaWhatsAppProvider to orchestrator` - MetaWhatsAppProvider.php, `vendor/bin/pint`

---

## Success Criteria

### Verification Commands
```bash
# Pint formatting
docker-compose exec app vendor/bin/pint --dirty

# Run tests (use test database)
docker-compose exec app php artisan test --compact --env=testing

# Verify provider still works
docker-compose exec app php artisan tinker --execute="app(\App\Providers\WhatsAppProviderFactory::class)->create(1)->sendMessage('+1234567890', 'test')"
```

### Final Checklist
- [ ] All "Must Have" present (composition pattern, services in App\Services\Meta\, etc.)
- [ ] All "Must NOT Have" absent (no interface changes, no model changes)
- [ ] All tests pass
- [ ] Pint formatting passes
- [ ] MetaWhatsAppProvider reduced from 1,215 to ~100 lines
- [ ] Provider delegates to services (no business logic in provider)

---

## Task Details

- [ ] 1. Create MetaContext Service

  **What to do**:
  - Create `App\Services\Meta\MetaContext.php`
  - Implement shared state: `gymId`, `account`, `apiVersion`, `accessToken`
  - Lazy-load WhatsAppAccount with phone number relationship
  - Add getter methods: `getAccount()`, `getAccessToken()`, `getApiBaseUrl()`, `getWabaId()`
  - Match TwilioContext pattern (constructor injection, private properties, getter methods)

  **Must NOT do**:
  - Do NOT add HTTP client code (that goes in MetaApiClient)
  - Do NOT add business logic (that goes in services)
  - Do NOT modify existing models

  **Recommended Agent Profile**:
  > **Category**: `quick`
  > **Reason**: Straightforward PHP class with getters, no complex logic, follows existing TwilioContext pattern.
  > **Skills**: `laravel-best-practices`, `laravel-specialist`
  > **Skills Evaluated but Omitted**: `fluxui-development` (no UI), `tailwindcss-development` (no styling)

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3)
  - **Blocks**: Tasks 4, 5, 6, 7, 8 (services depend on context)
  - **Blocked By**: None (can start immediately)

  **References** (CRITICAL - Be Exhaustive):

  **Pattern References** (existing code to follow):
  - `app/Services/Twilio/TwilioContext.php:1-156` - Exact pattern to follow (constructor, lazy loading, getters)
  - `app/Providers/MetaWhatsAppProvider.php:41-76` - Current logic to extract (getAccessToken, getWabaId, getApiBaseUrl)

  **Model References** (types to use):
  - `app/Models/WhatsAppAccount.php` - WhatsAppAccount model (hasMany phoneNumbers)
  - `app/Models/WhatsAppPhoneNumber.php` - WhatsAppPhoneNumber model (waba_phone_number_id)

  **WHY Each Reference Matters**:
  - TwilioContext: Shows exact pattern (readonly properties, private clients, getter methods)
  - MetaWhatsAppProvider lines 41-76: Contains logic to extract (apiVersion from config, account lazy loading)

  **Acceptance Criteria**:

  > **AGENT-EXECUTABLE VERIFICATION ONLY** - No human action permitted.
  > Every criterion MUST be verifiable by running a command or using a tool.

  **Verification**:
  - [ ] `docker-compose exec app php -l app/Services/Meta/MetaContext.php` → No syntax errors
  - [ ] File exists at `app/Services/Meta/MetaContext.php`
  - [ ] Class has properties: `gymId` (int), `account` (WhatsAppAccount|null), `apiVersion` (string)
  - [ ] Methods exist: `getAccount()`, `getAccessToken()`, `getApiBaseUrl()`, `getWabaId()`

  **QA Scenarios (MANDATORY - task is INCOMPLETE without these)**:

  > **Minimum: 1 happy path + 1 failure/edge case per task.**

  ```
  Scenario: Context loads account correctly (Happy path)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: WhatsAppAccount exists for gym_id=1
    Steps:
      1. Run: `app(\App\Services\Meta\MetaContext::class, ['gymId' => 1])`
      2. Call: `->getAccount()`
      3. Assert: returns WhatsAppAccount instance or null
    Expected Result: Account object returned (or null if not exists)
    Failure Indicators: Exception thrown, wrong type returned
    Evidence: .sisyphus/evidence/task-1-context-load.account

  Scenario: API base URL generated correctly (Happy path)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: None
    Steps:
      1. Run: `$context = app(\App\Services\Meta\MetaContext::class, ['gymId' => 1])`
      2. Call: `->getApiBaseUrl()`
      3. Assert: returns string starting with 'https://graph.facebook.com/'
    Expected Result: Returns 'https://graph.facebook.com/v25.0' (or configured version)
    Evidence: .sisyphus/evidence/task-1-api-url.txt

  Scenario: Missing access token handling (Failure/edge case)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: Account exists but meta_access_token is null
    Steps:
      1. Ensure account has no token: `WhatsAppAccount::find(1)->update(['meta_access_token' => null])`
      2. Run: `$context = app(\App\Services\Meta\MetaContext::class, ['gymId' => 1])`
      3. Call: `->getAccessToken()`
      4. Assert: returns null
    Expected Result: null (graceful handling)
    Failure Indicators: Exception thrown, returns non-null value
    Evidence: .sisyphus/evidence/task-1-no-token.txt
  ```

  **Evidence to Capture:**
  - [ ] Each evidence file named: task-{N}-{scenario-slug}.{ext}
  - [ ] Tinker output for context tests
  - [ ] API URL output

  **Commit**: YES (group with Task 2)
  - Message: `refactor(providers): extract MetaContext shared state`
  - Files: `app/Services/Meta/MetaContext.php`
  - Pre-commit: `vendor/bin/pint app/Services/Meta/MetaContext.php`

- [ ] 2. Create MetaApiClient Service

  **What to do**:
  - Create `App\Services\Meta\MetaApiClient.php`
  - Implement `makeRequest(string $method, string $endpoint, array $data = []): array`
  - Use Laravel Http facade with token from MetaContext
  - Handle success/failure responses (match existing pattern in MetaWhatsAppProvider:81-122)
  - Add error logging via Log facade

  **Must NOT do**:
  - Do NOT implement business logic (only HTTP transport)
  - Do NOT handle token management (that's in MetaContext)
  - Do NOT add API endpoints (that goes in services)

  **Recommended Agent Profile**:
  > **Category**: `quick`
  > **Reason**: HTTP client wrapper with error handling, follows existing makeRequest() pattern.
  > **Skills**: `laravel-best-practices`, `laravel-specialist`
  > **Skills Evaluated but Omitted**: `fluxui-development` (no UI work)

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3)
  - **Blocks**: Tasks 4, 5, 6, 7 (services need HTTP client)
  - **Blocked By**: Task 1 (needs MetaContext for token/URL)

  **References** (CRITICAL - Be Exhaustive):

  **Pattern References** (existing code to follow):
  - `app/Providers/MetaWhatsAppProvider.php:81-122` - Current makeRequest() logic to extract
  - `app/Providers/MetaWhatsAppProvider.php:57-60` - getApiBaseUrl() pattern

  **API References** (external knowledge):
  - Meta Graph API documentation: https://developers.facebook.com/docs/graph-api/overview/
  - Laravel Http facade: https://laravel.com/docs/12.x/http-client

  **WHY Each Reference Matters**:
  - MetaWhatsAppProvider:81-122: Contains exact HTTP call pattern with token, error handling, logging
  - MetaWhatsAppProvider:57-60: Shows how API base URL is constructed

  **Acceptance Criteria**:

  > **AGENT-EXECUTABLE VERIFICATION ONLY**

  **Verification**:
  - [ ] `docker-compose exec app php -l app/Services/Meta/MetaApiClient.php` → No syntax errors
  - [ ] Class accepts MetaContext in constructor
  - [ ] Method `makeRequest()` returns array with `success`, `data`/`error` keys
  - [ ] Failed requests return `success => false` with error message

  **QA Scenarios (MANDATORY)**:

  ```
  Scenario: Successful API request (Happy path)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: Valid Meta access token configured
    Steps:
      1. Create context: `$context = app(\App\Services\Meta\MetaContext::class, ['gymId' => 1])`
      2. Create client: `$client = new \App\Services\Meta\MetaApiClient($context)`
      3. Mock Http: Use `Http::fake()` to simulate success response
      4. Call: `->makeRequest('GET', '/test')`
      5. Assert: `success` is true, `data` exists
    Expected Result: `['success' => true, 'data' => [...]]`
    Evidence: .sisyphus/evidence/task-2-api-success.txt

  Scenario: Failed API request (Failure/edge case)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: None
    Steps:
      1. Create client with context
      2. Mock Http: `Http::fake(['*' => Http::response([], 400)])`
      3. Call: `->makeRequest('GET', '/invalid')`
      4. Assert: `success` is false, `error` message exists
    Expected Result: `['success' => false, 'error' => '...']]`
    Evidence: .sisyphus/evidence/task-2-api-fail.txt
  ```

  **Evidence to Capture:**
  - [ ] Tinker output for success/failure scenarios
  - [ ] Http fake mocking code snippets

  **Commit**: YES (group with Task 1)
  - Message: `refactor(providers): extract MetaContext and MetaApiClient`
  - Files: `app/Services/Meta/MetaContext.php`, `app/Services/Meta/MetaApiClient.php`
  - Pre-commit: `vendor/bin/pint app/Services/Meta/`

- [ ] 3. Create MetaCreditService

  **What to do**:
  - Create `App\Services\Meta\MetaCreditService.php`
  - Implement `CreditManagementInterface` (getCreditBalance, getMonthlyUsage, checkAndDeduct)
  - Inject MetaContext in constructor
  - Use WhatsAppMessageCredit model for balance queries
  - Use WhatsAppUsageLog model for usage tracking
  - Match TwilioCreditService pattern (dependency injection, interface implementation)

  **Must NOT do**:
  - Do NOT add HTTP calls (use MetaApiClient via context if needed)
  - Do NOT modify interfaces
  - Do NOT add messaging logic

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Reason**: Implements interface with multiple methods, uses Eloquent models, follows TwilioCreditService pattern.
  > **Skills**: `laravel-best-practices`, `laravel-specialist`, `laravel-boost` (for models)
  > **Skills Evaluated but Omitted**: `volt-development` (no Livewire)

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2)
  - **Blocks**: Task 4 (MetaMessagingService may use credits)
  - **Blocked By**: Task 1 (needs MetaContext)

  **References** (CRITICAL - Be Exhaustive):

  **Pattern References** (existing code to follow):
  - `app/Services/Twilio/TwilioCreditService.php:1-86` - Exact pattern (constructor injection, interface implementation)
  - `app/Interfaces/CreditManagementInterface.php` - Interface to implement

  **Model References** (models to use):
  - `app/Models/WhatsAppMessageCredit.php` - Credit balance queries
  - `app/Models/WhatsAppUsageLog.php` - Usage logging

  **Current Logic to Extract**:
  - `app/Providers/MetaWhatsAppProvider.php:548-588` - getCreditBalance()
  - `app/Providers/MetaWhatsAppProvider.php:959-972` - checkAndDeductCredits()
  - `app/Providers/MetaWhatsAppProvider.php:977-989` - logUsage()

  **WHY Each Reference Matters**:
  - TwilioCreditService: Shows exact pattern (readonly properties, dependency injection)
  - MetaWhatsAppProvider:548-588: Contains credit logic to extract
  - WhatsAppMessageCredit model: Shows methods like `canSendMessage()`, `getWelcomeCreditsRemaining()`

  **Acceptance Criteria**:

  **Verification**:
  - [ ] `docker-compose exec app php -l app/Services/Meta/MetaCreditService.php` → No syntax errors
  - [ ] Class implements `CreditManagementInterface`
  - [ ] Methods: `getCreditBalance()`, `getMonthlyUsage()`, `checkAndDeduct()`
  - [ ] Injects MetaContext via constructor

  **QA Scenarios (MANDATORY)**:

  ```
  Scenario: Get credit balance (Happy path)
    Tool: Bash (docker-compose exec app php artisan test)
    Preconditions: WhatsAppMessageCredit exists for gym_id=1
    Steps:
      1. Run: `php artisan test --compact --filter=test_credit_balance`
      2. Assert: returns array with total_credits, used_credits, remaining_credits
    Expected Result: Array with keys: total_credits, used_credits, remaining_credits, can_send
    Evidence: .sisyphus/evidence/task-3-credit-balance.txt

  Scenario: Insufficient credits (Failure/edge case)
    Tool: Bash (docker-compose exec app php artisan tinker)
    Preconditions: Credits exhausted or no credit record
    Steps:
      1. Call: `checkAndDeduct(1)` with no credits
      2. Assert: throws Exception with 'Insufficient message credits'
    Expected Result: Exception thrown
    Evidence: .sisyphus/evidence/task-3-insufficient-credits.txt
  ```

  **Evidence to Capture:**
  - [ ] Test output for credit balance
  - [ ] Exception message for insufficient credits

  **Commit**: YES
  - Message: `feat(services): add MetaCreditService for credit management`
  - Files: `app/Services/Meta/MetaCreditService.php`
  - Pre-commit: `vendor/bin/pint app/Services/Meta/MetaCreditService.php`

---

