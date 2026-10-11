---
phase: 1
plan: 1
wave: 1
---

# Plan 1.1: Biometric Consent NotificationType & WhatsApp Template Setup

## Objective
Establish the foundational notification type and WhatsApp template infrastructure for biometric consent. This includes registering `NotificationType::BIOMETRIC_CONSENT`, seeding the default template definition, and adding support for biometric consent link resolution in `WhatsAppVariableResolver`.

## Context
- [.gsd/SPEC.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/SPEC.md)
- [.gsd/REQUIREMENTS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/REQUIREMENTS.md)
- [.gsd/DECISIONS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/DECISIONS.md)
- [app/Enums/NotificationType.php](file:///c:/xampp/htdocs/projects/gym-app/app/Enums/NotificationType.php)
- [database/seeders/WhatsAppDefaultTemplatesSeeder.php](file:///c:/xampp/htdocs/projects/gym-app/database/seeders/WhatsAppDefaultTemplatesSeeder.php)
- [app/Services/WhatsAppVariableResolver.php](file:///c:/xampp/htdocs/projects/gym-app/app/Services/WhatsAppVariableResolver.php)

## Tasks

<task type="auto">
  <name>Add BIOMETRIC_CONSENT to NotificationType enum</name>
  <files>app/Enums/NotificationType.php</files>
  <action>
    - Add case `BIOMETRIC_CONSENT = 'biometric_consent_notifications'` to `App\Enums\NotificationType`.
    - Update `getLabel()` method to return `'Biometric Consent Notifications'`.
    - Update `getDescription()` method to return `'Send biometric consent requests to members'`.
    - Ensure strict typing and follow existing enum pattern.
  </action>
  <verify>php -r "require 'vendor/autoload.php'; echo \App\Enums\NotificationType::BIOMETRIC_CONSENT->value;"</verify>
  <done>NotificationType::BIOMETRIC_CONSENT is defined with valid value, label, and description.</done>
</task>

<task type="auto">
  <name>Register default biometric consent template in seeder and variable resolver</name>
  <files>database/seeders/WhatsAppDefaultTemplatesSeeder.php, app/Services/WhatsAppVariableResolver.php</files>
  <action>
    - In `WhatsAppDefaultTemplatesSeeder.php`, add a default template array for `NotificationType::BIOMETRIC_CONSENT->value`:
      - display_name: 'Biometric Consent Request'
      - category: 'utility'
      - body: "Hello {{1}},\n\n{{2}} requires your consent for biometric check-in. Please review and provide your consent here:\n{{3}}\n\nThis link is valid for 7 days."
      - variables: variable_1 (Member Name), variable_2 (Gym Name), variable_3 (Consent Link)
      - variable_placeholder: mapping 1 to member.name, 2 to gym.name, 3 to biometric.consent_url
      - status: 'approved', is_default: true
    - In `WhatsAppVariableResolver.php`:
      - Add `'biometric.consent_url'` data source under group `'Biometric'`.
      - In `resolveSource()`, resolve `'biometric.consent_url'` from `$context['consent_url'] ?? null`.
  </action>
  <verify>php -r "require 'vendor/autoload.php'; \$sources = \App\Services\WhatsAppVariableResolver::getAvailableDataSources(); echo isset(\$sources['biometric.consent_url']) ? 'OK' : 'MISSING';"</verify>
  <done>Default biometric consent WhatsApp template is registered in seeder and 'biometric.consent_url' is resolvable in WhatsAppVariableResolver.</done>
</task>

## Success Criteria
- [ ] NotificationType::BIOMETRIC_CONSENT exists and returns expected values
- [ ] WhatsAppDefaultTemplatesSeeder contains the default biometric consent template definition
- [ ] WhatsAppVariableResolver supports 'biometric.consent_url' data source
