# Request Demo WhatsApp Template Support

## Overview

Enhanced the `RequestDemo.php` WhatsApp notification system to support both sandbox (free-form) and production (template-based) modes, ensuring compliance with WhatsApp Business API requirements for business-initiated messages.

---

## Problem Statement

The demo request notification in `RequestDemo.php` was sending free-form WhatsApp messages to super-admin, which:

- ✅ Works in **sandbox mode** (testing)
- ❌ Fails in **production mode** (WhatsApp Business API requires pre-approved templates)

WhatsApp Business API enforces that all business-initiated conversations must use pre-approved message templates with `contentSid` and `contentVariables`.

---

## Solution Implemented

Updated the system tenant WhatsApp service (`TwilioWhatsAppService`) to automatically detect and handle both modes:

### Sandbox Mode (Testing)
- Uses free-form message body
- No template approval needed
- Perfect for development and testing

### Production Mode (Live)
- Uses approved WhatsApp template
- Sends via `contentSid` and `contentVariables`
- Complies with WhatsApp Business API requirements

---

## Implementation Details

### File: `app/Services/TwilioWhatsAppService.php`

#### Method: `sendSystemMessage()` - Enhanced

**What Changed:**

1. **Added Mode Detection**
   ```php
   if ($this->useSandbox) {
       // Sandbox: Use free-form message
       $messageParams["body"] = $message;
   } else {
       // Production: Use template
       $messageParams["contentSid"] = $templateSid;
       $messageParams["contentVariables"] = json_encode(["1" => $message]);
   }
   ```

2. **Template Lookup**
   - Checks `TWILIO_DEMO_REQUEST_TEMPLATE_SID` from config
   - Returns helpful error if template not found
   - Logs template usage for debugging

3. **Enhanced Logging**
   ```php
   Log::info("System WhatsApp message sent successfully", [
       "message_id" => $twilioMessage->sid,
       "to" => $to,
       "status" => $twilioMessage->status,
       "mode" => $this->useSandbox ? "sandbox" : "production",
       "uses_template" => !$this->useSandbox,
   ]);
   ```

4. **Better Error Messages**
   ```
   No approved WhatsApp template found for demo requests. Please:
   1. Create and approve a 'demo_request' template in Twilio console, OR
   2. Set TWILIO_DEMO_REQUEST_TEMPLATE_SID in your .env file, OR
   3. Use sandbox mode (TWILIO_USE_SANDBOX=true) for testing.
   ```

---

## Configuration

### For Testing (Sandbox Mode)

```env
# Sandbox Configuration
TWILIO_USE_SANDBOX=true
TWILIO_SANDBOX_NUMBER=+14155238886
SYSTEM_TENANT_ID=system

# Demo request works with free-form messages
# No template needed!
```

### For Production (Live)

```env
# Production Configuration
TWILIO_USE_SANDBOX=false
TWILIO_DEMO_REQUEST_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
SYSTEM_TENANT_ID=system

# Demo request uses approved template
```

### Config File: `config/services.php`

```php
'twilio' => [
    'master_sid' => env('TWILIO_ACCOUNT_SID'),
    'master_token' => env('TWILIO_AUTH_TOKEN'),
    'use_sandbox' => env('TWILIO_USE_SANDBOX', true),
    'sandbox_number' => env('TWILIO_SANDBOX_NUMBER', '+14155238886'),
    
    // Template SIDs
    'welcome_template_sid' => env('TWILIO_WELCOME_TEMPLATE_SID'),
    'demo_request_template_sid' => env('TWILIO_DEMO_REQUEST_TEMPLATE_SID'),
    'birthday_greeting_template_sid' => env('TWILIO_BIRTHDAY_GREETING_TEMPLATE_SID'),
],
```

---

## WhatsApp Template Creation

### Template Name
`demo_request`

### Template Type
`WhatsApp`

### Category
`UTILITY` (for business notifications)

### Language
`English (en)`

### Template Body

```
🔔 *New Demo Request*

{{1}}

⏰ Please respond to this request as soon as possible.
```

### Variables
- `{{1}}` - Complete formatted demo request details (name, email, phone, company, etc.)

### Steps to Create

1. **Login to Twilio Console**
   - Navigate to: https://console.twilio.com
   - Go to: Messaging → Content Templates

2. **Create New Template**
   - Click "Create new Template"
   - Name: `demo_request`
   - Type: WhatsApp
   - Category: UTILITY

3. **Add Template Body**
   - Copy the template body above
   - Add variable `{{1}}` for demo request details

4. **Submit for Approval**
   - Review template
   - Submit to WhatsApp for approval
   - Wait 24-48 hours for approval

5. **Configure in Application**
   - Copy the Content SID (starts with `HX`)
   - Add to `.env`: `TWILIO_DEMO_REQUEST_TEMPLATE_SID=HXxxxxx`

---

## How It Works

### User Flow

```
1. User submits demo request form
   ↓
2. RequestDemo component receives submission
   ↓
3. Creates RequestContact record
   ↓
4. Calls sendWhatsAppNotification()
   ↓
5. Creates TwilioWhatsAppService with system tenant
   ↓
6. Service detects system tenant → routes to sendSystemMessage()
   ↓
7. Checks mode: sandbox or production
   ↓
8a. SANDBOX: Sends free-form message with body
    OR
8b. PRODUCTION: Sends with contentSid and contentVariables
   ↓
9. Admin receives WhatsApp notification
   ↓
10. Logs success/failure with mode information
```

### Code Flow in RequestDemo.php

```php
// 1. Format message
$message = "🔔 *New Demo Request*\n\n";
$message .= "*Contact Details:*\n";
$message .= "• Name: {$requestContact->name}\n";
// ... more details

// 2. Use system tenant
$tenantId = config("app.system_tenant_id", "system");

// 3. Create service
$whatsappService = new TwilioWhatsAppService($tenantId);

// 4. Send message (automatically handles mode)
$result = $whatsappService->sendMessage($adminPhone, $message);
```

### Behind the Scenes

```php
// In TwilioWhatsAppService
public function sendMessage($to, $message, $templateData = null)
{
    // Detect system tenant
    if ($this->isSystemTenant()) {
        return $this->sendSystemMessage($to, $message, $templateData);
    }
    // ... regular tenant handling
}

private function sendSystemMessage($to, $message, $templateData = null)
{
    $client = $this->getClient();
    
    // Mode detection
    if ($this->useSandbox) {
        // Sandbox: free-form
        $params = ["from" => "whatsapp:$from", "body" => $message];
    } else {
        // Production: template
        $templateSid = config('services.twilio.demo_request_template_sid');
        $params = [
            "from" => "whatsapp:$from",
            "contentSid" => $templateSid,
            "contentVariables" => json_encode(["1" => $message])
        ];
    }
    
    return $client->messages->create("whatsapp:$to", $params);
}
```

---

## Testing

### Test in Sandbox Mode

```bash
# 1. Set environment
TWILIO_USE_SANDBOX=true
SYSTEM_TENANT_ID=system

# 2. Submit demo request through website
# Visit: https://yourapp.com/request-demo

# 3. Fill form and submit

# 4. Check logs
tail -f storage/logs/laravel.log | grep "WhatsApp"

# Expected log:
# System WhatsApp message sent successfully
# {"mode": "sandbox", "uses_template": false}
```

### Test in Production Mode

```bash
# 1. Set environment
TWILIO_USE_SANDBOX=false
TWILIO_DEMO_REQUEST_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
SYSTEM_TENANT_ID=system

# 2. Submit demo request through website

# 3. Check logs
tail -f storage/logs/laravel.log | grep "WhatsApp"

# Expected log:
# Using WhatsApp template for system message
# {"template_sid": "HXxxxxx"}
# System WhatsApp message sent successfully
# {"mode": "production", "uses_template": true}
```

---

## Response Structure

### Success Response

**Sandbox Mode:**
```php
[
    "success" => true,
    "message_id" => "SMxxxxxxxxxxxxxxxxxxxxx",
    "status" => "queued",
    "from" => "+14155238886",
    "system_message" => true,
    "mode" => "sandbox",
]
```

**Production Mode:**
```php
[
    "success" => true,
    "message_id" => "SMxxxxxxxxxxxxxxxxxxxxx",
    "status" => "queued",
    "from" => "+14155238886",
    "system_message" => true,
    "mode" => "production",
]
```

### Error Response (Production without Template)

```php
[
    "success" => false,
    "error" => "No approved WhatsApp template found for demo requests. Please:...",
    "requires_template" => true,
    "mode" => "production",
]
```

---

## Logging

### Success Logs

```
[INFO] System WhatsApp message sent successfully
{
    "message_id": "SMxxxxxxxxxxxxxxxxxxxxx",
    "to": "+1234567890",
    "status": "queued",
    "mode": "sandbox",
    "uses_template": false
}
```

```
[INFO] Using WhatsApp template for system message
{
    "template_sid": "HXxxxxxxxxxxxxxxxxxxxxx",
    "to": "+1234567890"
}

[INFO] System WhatsApp message sent successfully
{
    "message_id": "SMxxxxxxxxxxxxxxxxxxxxx",
    "to": "+1234567890",
    "status": "queued",
    "mode": "production",
    "uses_template": true
}
```

### Error Logs

```
[ERROR] System WhatsApp message error
{
    "to": "+1234567890",
    "error": "No approved WhatsApp template found...",
    "code": null,
    "mode": "production"
}
```

---

## Files Modified

1. **`app/Services/TwilioWhatsAppService.php`**
   - Added `Log` facade import
   - Enhanced `sendSystemMessage()` with mode detection
   - Added template support for production mode
   - Improved error handling and logging
   - Added mode tracking in responses

2. **`config/services.php`**
   - Added `demo_request_template_sid` configuration
   - Added `welcome_template_sid` configuration

---

## Benefits

### For Developers
- ✅ Automatic mode detection
- ✅ No code changes needed between environments
- ✅ Clear error messages
- ✅ Comprehensive logging

### For Business
- ✅ Complies with WhatsApp Business API requirements
- ✅ Production-ready for live deployments
- ✅ Professional approved templates
- ✅ No message delivery failures

### For Testing
- ✅ Easy testing with sandbox mode
- ✅ No template approval needed for testing
- ✅ Quick iteration during development

---

## Migration Guide

### From Sandbox to Production

1. **Create Template in Twilio**
   - Create `demo_request` template
   - Submit for approval
   - Wait for WhatsApp approval

2. **Get Template SID**
   - Copy Content SID from Twilio console
   - Starts with `HX`

3. **Update Environment**
   ```env
   # Before
   TWILIO_USE_SANDBOX=true
   
   # After
   TWILIO_USE_SANDBOX=false
   TWILIO_DEMO_REQUEST_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
   ```

4. **Test Production Mode**
   - Submit demo request
   - Verify message received
   - Check logs for template usage

5. **Deploy**
   ```bash
   php artisan config:cache
   php artisan cache:clear
   ```

---

## Troubleshooting

### Issue: Template Not Found Error

**Error:**
```
No approved WhatsApp template found for demo requests.
```

**Solution:**
1. Create template in Twilio console
2. Wait for approval (24-48 hours)
3. Add `TWILIO_DEMO_REQUEST_TEMPLATE_SID` to `.env`
4. Clear config cache: `php artisan config:clear`

---

### Issue: Message Not Received (Sandbox)

**Error:** Admin doesn't receive message

**Solution:**
1. Admin must join Twilio sandbox first
2. Send "join [keyword]" to `+14155238886`
3. Then demo requests will be received

---

### Issue: Template Variables Not Rendering

**Error:** Message shows `{{1}}` instead of actual content

**Solution:**
```php
// Correct: Use json_encode
$messageParams["contentVariables"] = json_encode([
    "1" => $message
]);

// Wrong: Don't forget json_encode
$messageParams["contentVariables"] = ["1" => $message]; // ❌
```

---

### Issue: Wrong Mode Being Used

**Check Configuration:**
```bash
# Check current mode
php artisan tinker
>>> config('services.twilio.use_sandbox')
=> true  // or false

# Clear cache if needed
php artisan config:clear
php artisan cache:clear
```

---

## Security Considerations

1. **Rate Limiting** - Already implemented in `RequestDemo.php`
   - 3 requests per hour per IP
   - Prevents spam and abuse

2. **Validation** - All form inputs validated
   - Name, email, phone validation
   - Prevents injection attacks

3. **Logging** - All messages logged
   - Audit trail for compliance
   - Debugging support

4. **Credentials** - Master account credentials used
   - Stored in environment variables
   - Never exposed in logs or responses

---

## Performance Considerations

- **Async Processing** - Consider moving to queue for high volume
- **Error Handling** - Graceful failure, user still sees success
- **Caching** - Template SIDs cached in config
- **Timeout** - Twilio API calls have timeout protection

---

## Cost Implications

### Sandbox Mode
- **Cost:** Free
- **Use Case:** Testing only

### Production Mode
- **Cost:** ~$0.005 per message
- **Use Case:** Live demo request notifications
- **Volume:** Depends on demo request submissions

---

## Future Enhancements

1. **Queue Processing** - Move to background job
2. **Template Versioning** - Support multiple template versions
3. **Multi-language** - Support templates in different languages
4. **Rich Media** - Add images/buttons to templates
5. **Analytics** - Track delivery and read rates

---

## Related Documentation

- [WHATSAPP_TEMPLATE_SETUP_GUIDE.md](WHATSAPP_TEMPLATE_SETUP_GUIDE.md) - Complete template guide
- [WHATSAPP_SYSTEM_TENANT_SUPPORT.md](WHATSAPP_SYSTEM_TENANT_SUPPORT.md) - System tenant documentation
- [WHATSAPP_TEST_MESSAGE_SUMMARY.md](WHATSAPP_TEST_MESSAGE_SUMMARY.md) - Test message feature
- [Twilio WhatsApp API](https://www.twilio.com/docs/whatsapp) - Official documentation

---

## Summary

The `RequestDemo.php` WhatsApp notification now supports both sandbox and production modes:

- ✅ **Sandbox Mode** - Free-form messages for testing
- ✅ **Production Mode** - Template-based messages for compliance
- ✅ **Automatic Detection** - No code changes needed
- ✅ **Comprehensive Logging** - Full audit trail
- ✅ **Error Handling** - Helpful error messages
- ✅ **Ready for Production** - WhatsApp Business API compliant

**Result:** Demo request notifications work seamlessly in both development and production environments with full WhatsApp Business API compliance.