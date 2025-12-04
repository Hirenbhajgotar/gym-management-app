# WhatsApp System Tenant Support

## Overview

This document describes the implementation of WhatsApp messaging support for system-level users (super-admin) who do not belong to any tenant.

## Problem Statement

Super-admin users are not part of any tenant/gym, so they don't have:
- A `NotificationSetting` record with Twilio credentials
- A `GymWhatsAppBalance` record for message tracking
- A verified business WhatsApp number

When attempting to send WhatsApp messages to super-admin (e.g., demo request notifications), the system would fail with "No Twilio settings found" error.

## Solution

We've modified the WhatsApp services to detect and handle system tenant separately:

### 1. TwilioWhatsAppService Changes

**Added System Tenant Detection:**
```php
private function isSystemTenant(): bool
{
    return $this->tenantId === config("app.system_tenant_id", "system") ||
           $this->tenantId === "system";
}
```

**Modified getClient() Method:**
- System tenant now uses master account credentials directly
- Bypasses the need for tenant-specific `NotificationSetting` records

**Added sendSystemMessage() Method:**
- Private method specifically for system-level messages
- Skips verification status checks
- Skips balance checks
- Always uses sandbox/master account configuration
- Returns additional `system_message` flag in response

**Modified sendMessage() Method:**
- Detects system tenant at the beginning
- Routes to `sendSystemMessage()` for system tenant
- Continues normal flow for regular tenants

**Modified sendTemplateMessage() Method:**
- System tenant explicitly blocked from using templates
- Templates require verified business accounts

**Modified getAccountBalance() Method:**
- System tenant returns master account balance
- Returns additional `system_account` flag

### 2. WhatsAppTemplateManager Changes

**Added System Tenant Detection:**
- Same `isSystemTenant()` helper method

**Modified All Template Methods:**
- `fetchTemplates()`: Returns error for system tenant
- `syncTemplatesToDatabase()`: Returns error for system tenant
- `getTemplate()`: Returns null for system tenant

Templates are not supported for system tenant since they require a verified WhatsApp Business account.

## Configuration

### Required Config

In `config/app.php` or `.env`:
```php
// config/app.php
'system_tenant_id' => env('SYSTEM_TENANT_ID', 'system'),
```

Or in `.env`:
```
SYSTEM_TENANT_ID=system
```

### Twilio Configuration

System messages use the master account configuration from `config/services.php`:
```php
'twilio' => [
    'master_sid' => env('TWILIO_ACCOUNT_SID'),
    'master_token' => env('TWILIO_AUTH_TOKEN'),
    'use_sandbox' => env('TWILIO_USE_SANDBOX', true),
    'sandbox_number' => env('TWILIO_SANDBOX_NUMBER', '+14155238886'),
],
```

## Usage Examples

### Sending System Message (Demo Request Notification)

```php
// In RequestDemo.php or similar system-level notifications
$tenantId = config("app.system_tenant_id", "system");
$whatsappService = new TwilioWhatsAppService($tenantId);

$result = $whatsappService->sendMessage($adminPhone, $message);

if ($result["success"]) {
    // Message sent successfully
    // $result["system_message"] will be true for system messages
    Log::info("System WhatsApp sent", [
        "message_id" => $result["message_id"],
        "is_system" => $result["system_message"] ?? false,
    ]);
}
```

### Response Structure

**For System Messages:**
```php
[
    "success" => true,
    "message_id" => "SM...",
    "status" => "queued",
    "from" => "+14155238886", // Sandbox number
    "system_message" => true, // Indicates this was sent via system tenant
    "data" => [...] // Full Twilio response
]
```

**For Regular Tenant Messages:**
```php
[
    "success" => true,
    "message_id" => "SM...",
    "status" => "queued",
    "from" => "+1234567890", // Tenant's verified number
    "data" => [...] // Full Twilio response
]
```

## Limitations for System Tenant

1. **No Template Messages**: System tenant cannot use WhatsApp templates as they require a verified business account.

2. **Sandbox Mode**: System messages typically use Twilio sandbox mode, which requires recipients to opt-in first.

3. **No Balance Tracking**: System messages don't decrement from any gym's balance.

4. **No Verification Required**: System tenant bypasses all verification checks.

## Testing

### Manual Testing

1. Set up Twilio sandbox credentials in `.env`:
```env
TWILIO_ACCOUNT_SID=your_master_sid
TWILIO_AUTH_TOKEN=your_master_token
TWILIO_USE_SANDBOX=true
TWILIO_SANDBOX_NUMBER=+14155238886
SYSTEM_TENANT_ID=system
```

2. Send a test message:
```php
$service = new TwilioWhatsAppService('system');
$result = $service->sendMessage('+1234567890', 'Test message');
```

3. Verify the response includes `system_message => true`.

### Unit Testing

```php
public function test_system_tenant_sends_message_without_settings()
{
    $service = new TwilioWhatsAppService('system');
    $result = $service->sendMessage('+1234567890', 'Test message');
    
    $this->assertTrue($result['success']);
    $this->assertTrue($result['system_message']);
}

public function test_system_tenant_cannot_use_templates()
{
    $service = new TwilioWhatsAppService('system');
    
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Template messages are not available for system tenant');
    
    $service->sendTemplateMessage('+1234567890', 'template_sid', []);
}
```

## Migration Path

No database migrations required. This is a code-only change that adds support for system-level messaging.

## Files Modified

1. `app/Services/TwilioWhatsAppService.php`
   - Added `isSystemTenant()` method
   - Modified `getClient()` to handle system tenant
   - Added `sendSystemMessage()` method
   - Modified `sendMessage()` to route system messages
   - Modified `sendTemplateMessage()` to block system tenant
   - Modified `getAccountBalance()` to handle system tenant

2. `app/Services/WhatsAppTemplateManager.php`
   - Added `isSystemTenant()` method
   - Modified all template methods to check and handle system tenant

## Security Considerations

1. **Master Credentials**: System messages use master account credentials, which are more privileged. Ensure proper access control to prevent abuse.

2. **No Balance Limits**: System messages bypass balance checks. Monitor usage to prevent unexpected costs.

3. **Audit Logging**: All system messages should be logged for audit purposes (already implemented in `RequestDemo.php`).

## Future Enhancements

1. Consider adding a dedicated system message balance/quota
2. Add system-specific rate limiting
3. Create a dedicated configuration for system WhatsApp number (non-sandbox)
4. Add system message analytics/reporting

## Support

For issues or questions:
1. Check Twilio sandbox status and credentials
2. Verify `SYSTEM_TENANT_ID` configuration
3. Review logs for detailed error messages
4. Ensure recipient has joined the Twilio sandbox (for sandbox mode)