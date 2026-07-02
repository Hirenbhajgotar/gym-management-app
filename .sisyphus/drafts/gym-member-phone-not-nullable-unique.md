# Draft: GymMember - Phone Not Nullable/Unique, Email Nullable

## Requirements (confirmed)
- Make `phone` NOT NULL, UNIQUE, INDEXED in the database
- Make `email` NULLABLE (members use phone for authentication, email is just contact info)
- Future: GymMember model will be authenticatable via phone
- Update Filament resource: phone=required field, email=optional field
- Update import/export files accordingly

## Files to Modify

### 1. Migration (NEW)
- `database/migrations/YYYY_MM_DD_HHMMSS_update_gym_members_email_phone.php`
  - `$table->string('email')->nullable()->change();`
  - `$table->string('phone')->nullable(false)->unique()->index()->change();`

### 2. Model (`app/Models/GymMember.php`)
- PHPDoc: `@property string $phone` (remove `|null`), `@property string|null $email` (add `|null`)
- No fillable changes needed (both already in `$fillable`)

### 3. Filament Resource (`app/Filament/Admin/Resources/GymMemberResource.php`)
- **Email field**: Remove `->required()`, add `->nullable()`
- **Phone field**: Add `->unique(GymMember::class, 'phone', ignoreRecord: true)` validation
- Existing `filled($record->email)` checks on consent actions are already correct

### 4. Import (`app/Imports/GymMemberImport.php`)
- Rules: email → `['nullable', 'string', 'email', 'max:255', 'unique:gym_members,email']`
- Rules: phone → `['required', 'max:20']` (remove nullable)
- Create call: `'phone' => $row['phone']` (remove `?? null`), `'email' => $row['email'] ?? null`

### 5. Import (`app/Imports/GymMembersImport.php`)
- Rules: email → `'nullable|email|unique:gym_members,email'`
- Rules: phone → `['required', 'max:255', new ValidPhone()]`
- Model creation: `'phone' => $row['phone']` (remove `?? null`), `'email' => $row['email'] ?? null`

### 6. Factory (`database/factories/GymMemberFactory.php`)
- `'phone' => fake()->unique()->phoneNumber()` (add `->unique()`)

### 7. Tests
- `CreateGymMemberTest.php`: Already includes both fields, verify passes
- `BiometricConsentTest.php`: Already sets explicit email, verify passes

### Files NOT needing changes (handled)
- **Exporter** (`GymMemberExporter.php`): Just exports values, null-safe
- **FraudDetector**: Already guards `if (!$gymMember->email) return false;`
- **Observer**: Already checks `filled($gymMember->email)` before sending biometric email
- **SendBiometricConsentEmail**: Already guards `blank($this->gymMember->email)`
- **Notifications**: Already handle null phone with null coalescing (`??`)
- **WhatsApp Broadcast**: Already filters `whereNotNull('phone')`
- **SendBroadcastJob**: Loads selective columns, works fine

## Open Questions
- What happens to existing records with null phone numbers? (Need default or cleanup before migration)
- Should we also add phone-based authentication route at the same time?
