---
phase: 5
plan: 1
wave: 1
gap_closure: false
---

# Plan 5.1: Biometric Device Availability Gate & Welcome Mail Integration

## Objective
Implement conditional inclusion of the biometric attendance notice and secure 7-day signed consent link in the member welcome email (`WelcomeNotification` and `WelcomeMemberMail`). The biometric notice and consent call-to-action will be rendered ONLY when the member's gym has at least one active biometric device available (`status = Active` and `is_enabled = true`) and the member has not yet granted active biometric consent.

## Context
Load these files for context:
- [.gsd/SPEC.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/SPEC.md)
- [.gsd/REQUIREMENTS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/REQUIREMENTS.md)
- [app/Models/Gym.php](file:///c:/xampp/htdocs/projects/gym-app/app/Models/Gym.php)
- [app/Models/BiometricDevice.php](file:///c:/xampp/htdocs/projects/gym-app/app/Models/BiometricDevice.php)
- [app/Notifications/Member/WelcomeNotification.php](file:///c:/xampp/htdocs/projects/gym-app/app/Notifications/Member/WelcomeNotification.php)
- [app/Mail/WelcomeMemberMail.php](file:///c:/xampp/htdocs/projects/gym-app/app/Mail/WelcomeMemberMail.php)
- [resources/views/emails/welcome-member.blade.php](file:///c:/xampp/htdocs/projects/gym-app/resources/views/emails/welcome-member.blade.php)

## Tasks

<task type="auto">
  <name>Add biometricDevices relationship and hasActiveBiometricDevice helper to Gym model</name>
  <files>app/Models/Gym.php</files>
  <action>
    - In `app/Models/Gym.php`:
      - Add relationship `biometricDevices(): HasMany`:
        ```php
        /**
         * @return HasMany<BiometricDevice, $this>
         */
        public function biometricDevices(): HasMany
        {
            return $this->hasMany(BiometricDevice::class);
        }
        ```
      - Add helper method `hasActiveBiometricDevice(): bool`:
        ```php
        public function hasActiveBiometricDevice(): bool
        {
            return $this->biometricDevices()->active()->exists();
        }
        ```
      - Update class docblock with `@property-read Collection<int, BiometricDevice> $biometricDevices` and `@method bool hasActiveBiometricDevice()`.
  </action>
  <verify>docker compose exec app php -l app/Models/Gym.php</verify>
  <done>Gym model exposes typed biometricDevices() relation and hasActiveBiometricDevice() helper.</done>
</task>

<task type="auto">
  <name>Integrate conditional biometric attendance notice into WelcomeNotification and WelcomeMemberMail</name>
  <files>app/Notifications/Member/WelcomeNotification.php, app/Mail/WelcomeMemberMail.php, resources/views/emails/welcome-member.blade.php</files>
  <action>
    1. In `app/Notifications/Member/WelcomeNotification.php`:
       - Add helper `shouldIncludeBiometricNotice(): bool`:
         Checks `$this->gym->hasActiveBiometricDevice() && ! $this->member->hasActiveBiometricConsent()`.
       - In `toMail(object $notifiable): MailMessage`:
         If `shouldIncludeBiometricNotice()` returns true:
           - Generate signed 7-day consent URL:
             `URL::temporarySignedRoute('biometric.consent.show', now()->addDays(7), ['gym_member_id' => $this->member->id, 'gym_id' => $this->gym->id])`
           - Add notice lines and action button to `MailMessage`:
             - `line('**Biometric Attendance & Access**')`
             - `line("{$this->gym->name} uses biometric devices for check-in and facility access. Before we can enroll your biometric attendance, your consent is required under data protection regulations.")`
             - `action('Submit Biometric Consent', $consentUrl)`
             - `line('This consent link is valid for 7 days.')`
    2. In `app/Mail/WelcomeMemberMail.php`:
       - Add `shouldIncludeBiometricNotice(): bool` check on member's gym and consent status.
       - If true, compute `consentUrl` using 7-day signed route and pass `includeBiometricNotice` and `consentUrl` to markdown view parameters.
    3. In `resources/views/emails/welcome-member.blade.php`:
       - Add conditional block `@if(!empty($includeBiometricNotice) && !empty($consentUrl))` rendering biometric notice, `@component('mail::button')` linking to `$consentUrl`, and 7-day expiration notice.
  </action>
  <verify>docker compose exec app php -l app/Notifications/Member/WelcomeNotification.php && docker compose exec app php -l app/Mail/WelcomeMemberMail.php</verify>
  <done>WelcomeNotification and WelcomeMemberMail render biometric notice only when active device exists and member lacks consent.</done>
</task>

<task type="auto">
  <name>Format code with Pint</name>
  <files>app/Models/Gym.php, app/Notifications/Member/WelcomeNotification.php, app/Mail/WelcomeMemberMail.php</files>
  <action>
    Run `vendor/bin/pint --dirty` to ensure strict PSR-12 code formatting standards.
  </action>
  <verify>docker compose exec app vendor/bin/pint --dirty</verify>
  <done>All modified files conform to Pint code style standards.</done>
</task>

## Must-Haves
- [ ] Gym model provides hasActiveBiometricDevice() checking active and enabled devices
- [ ] WelcomeNotification conditionally includes biometric attendance notice and signed consent link
- [ ] WelcomeMemberMail conditionally includes biometric notice and button in markdown template
- [ ] Biometric notice is omitted when gym has no active devices or member already consented
- [ ] Signed consent link expires in 7 days

## Success Criteria
- [ ] Clean syntax checks on all modified files
- [ ] Pint formatting clean
