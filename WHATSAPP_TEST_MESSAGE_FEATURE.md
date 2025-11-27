# WhatsApp Test Message Feature

## Overview

This document describes the automatic and manual test message functionality that confirms WhatsApp integration is working correctly for gym owners.

## Features

### 1. Automatic Test Message on Verification

When a gym owner completes WhatsApp number verification, the system automatically sends a welcome test message to confirm the integration is working.

### 2. Manual Test Message Button

Gym owners can send test messages anytime from the WhatsApp Setup page to verify their integration is still functioning properly.

---

## Automatic Test Message Flow

### When It Happens
- **Trigger**: Immediately after successful WhatsApp number verification
- **Location**: During the verification process in `WhatsAppSetup.php`
- **Method**: `TwilioAccountManager::completeWhatsAppVerification()`

### Message Content

The automatic test message includes:

```
🎉 *WhatsApp Integration Active!*

Hello from [Gym Name]! 👋

Your WhatsApp Business integration is now successfully set up and ready to use.

*What's Next?*
✅ Members will receive booking confirmations
✅ Payment reminders will be sent automatically
✅ Class updates will reach members instantly
✅ Birthday greetings will be sent on special days

This is a test message to confirm everything is working perfectly.

_Sent at: [Timestamp]_
```

### User Feedback

After verification completes, the user sees:

```
🎉 Verification Successful!
Your WhatsApp integration is now active. You can start sending notifications. 
A welcome test message has been sent to your WhatsApp number.
```

---

## Manual Test Message Flow

### How to Send

1. Navigate to **WhatsApp Setup** page
2. Ensure account status is "Active"
3. Click **"Send Test Message"** button
4. Confirm the action in the modal
5. Check WhatsApp for the test message

### Button Configuration

```php
public function testMessageAction(): Action
{
    return Action::make("testMessage")
        ->label("Send Test Message")
        ->icon("heroicon-o-paper-airplane")
        ->color("info")
        ->size("xl")
        ->outlined()
        ->requiresConfirmation()
        ->modalHeading("Send Test Message")
        ->modalDescription("This will send a test WhatsApp message to verify your integration is working.")
        ->action(fn() => $this->sendTestMessage());
}
```

### Success Response

```
✅ Test Message Sent!
A test message has been sent to your WhatsApp number (+1234567890). 
Please check your WhatsApp to confirm receipt.
```

### Error Response

```
❌ Test Message Failed
[Error message details]
```

---

## Implementation Details

### File: `TwilioAccountManager.php`

#### Method: `sendTestWhatsAppMessage()`

**Purpose**: Sends a comprehensive test message to the gym owner's WhatsApp number

**Parameters**:
- `NotificationSetting $settings` - The notification settings containing Twilio credentials

**Returns**:
```php
[
    "success" => true|false,
    "message_id" => "SM...",
    "status" => "queued|sent|...",
    "to" => "+1234567890",
    "error" => "Error message" // Only on failure
]
```

**Key Features**:
1. Personalized with gym name
2. Comprehensive feature overview
3. Timestamp included
4. Full error handling
5. Detailed logging

**Code Flow**:
```php
1. Validate Twilio credentials exist
2. Create Twilio client
3. Get from number (sandbox or production)
4. Build personalized message with gym name
5. Send message via Twilio
6. Log success/failure
7. Return result array
```

### File: `WhatsAppSetup.php`

#### Method: `verifyWhatsAppNumber()`

**Enhanced Features**:
- Detects if test message was sent during verification
- Shows enhanced notification with test message status
- Provides clear feedback to user

**Code**:
```php
if ($result["success"]) {
    $testMessageSent = $result["test_message_sent"] ?? false;
    
    $bodyMessage = "Your WhatsApp integration is now active...";
    
    if ($testMessageSent) {
        $bodyMessage .= " A welcome test message has been sent to your WhatsApp number.";
    }
    
    Notification::make()
        ->title("🎉 Verification Successful!")
        ->body($bodyMessage)
        ->success()
        ->send();
}
```

#### Method: `sendTestMessage()`

**Purpose**: Manually send test message from UI

**Features**:
- Validates settings exist
- Calls `TwilioAccountManager::sendTestWhatsAppMessage()`
- Shows detailed success/error notifications
- Logs all attempts

**Success Handling**:
```php
Notification::make()
    ->title("✅ Test Message Sent!")
    ->body("A test message has been sent to your WhatsApp number...")
    ->success()
    ->duration(6000)
    ->send();
```

---

## Logging

### Automatic Test Message Logs

**On Success**:
```php
Log::info("WhatsApp verification completed", [
    "gym_id" => $tenantId,
    "verified_number" => "+1234567890",
    "test_message_sent" => true,
    "test_message_id" => "SM...",
]);

Log::info("Test WhatsApp message sent successfully", [
    "gym_id" => "123",
    "message_id" => "SM...",
    "to" => "+1234567890",
    "status" => "queued",
]);
```

**On Failure**:
```php
Log::error("Twilio WhatsApp test message error", [
    "gym_id" => "123",
    "error" => "Error message",
    "code" => 21211,
]);
```

### Manual Test Message Logs

**On Success**:
```php
Log::info("Manual test message sent from WhatsApp Setup page", [
    "gym_id" => "123",
    "message_id" => "SM...",
    "to" => "+1234567890",
]);
```

**On Failure**:
```php
Log::error("Manual test message failed from WhatsApp Setup page", [
    "gym_id" => "123",
    "error" => "Error message",
]);
```

---

## UI/UX Flow

### Step 1: Create Account
```
[Create WhatsApp Account Button]
↓
Account created, verification code sent
```

### Step 2: Verify Number
```
[Enter Verification Code]
[Verify Number Button]
↓
Verification successful
↓
✅ Test message automatically sent
↓
Notification: "Welcome test message sent to your number"
```

### Step 3: Active State
```
[Account Status: Active]
[Send Test Message Button] ← Manual testing available anytime
```

---

## Error Handling

### Common Errors

**1. Missing Credentials**
```
Error: "Twilio credentials are not configured."
Solution: Ensure settings have twilio_account_sid and twilio_auth_token
```

**2. Invalid From Number**
```
Error: "Unable to create record: The 'From' number... is not a valid..."
Solution: Check sandbox configuration or verify business number
```

**3. Unverified To Number (Sandbox Mode)**
```
Error: "Attempt to send to unverified number"
Solution: User must join Twilio sandbox by sending "join [keyword]"
```

**4. Insufficient Balance**
```
Error: "Account balance is insufficient"
Solution: Add credits to Twilio account or switch to sandbox mode
```

### Error Response Structure

```php
[
    "success" => false,
    "error" => "Human-readable error message",
    "code" => 21211, // Twilio error code (if applicable)
]
```

---

## Testing

### Manual Testing Steps

1. **Setup Phase**:
   - Create WhatsApp account
   - Enter verification code
   - Confirm test message received automatically

2. **Active Phase**:
   - Click "Send Test Message" button
   - Confirm modal
   - Verify message received
   - Check logs for message ID

3. **Sandbox Mode Testing**:
   ```env
   TWILIO_USE_SANDBOX=true
   TWILIO_SANDBOX_NUMBER=+14155238886
   ```
   - Join sandbox: Send "join [keyword]" to sandbox number
   - Complete verification
   - Receive test message

4. **Production Mode Testing**:
   ```env
   TWILIO_USE_SANDBOX=false
   ```
   - Use verified WhatsApp Business number
   - Complete verification
   - Receive test message from business number

### Automated Testing

```php
public function test_sends_test_message_after_verification()
{
    // Mock Twilio client
    $twilioMock = Mockery::mock(Client::class);
    
    // Complete verification
    $result = $accountManager->completeWhatsAppVerification($gymId, $code);
    
    // Assert test message was sent
    $this->assertTrue($result['test_message_sent']);
    $this->assertNotNull($result['test_message_id']);
}

public function test_manual_test_message_sends_successfully()
{
    $accountManager = new TwilioAccountManager();
    $result = $accountManager->sendTestWhatsAppMessage($settings);
    
    $this->assertTrue($result['success']);
    $this->assertNotEmpty($result['message_id']);
}
```

---

## Configuration

### Required Environment Variables

```env
# Twilio Master Account (for subaccount management)
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token

# Sandbox Configuration (for testing)
TWILIO_USE_SANDBOX=true
TWILIO_SANDBOX_NUMBER=+14155238886
```

### Database Requirements

- `notification_settings` table with columns:
  - `gym_id`
  - `twilio_account_sid`
  - `twilio_auth_token`
  - `whatsapp_business_number`
  - `verification_status`
  - `verified_at`

---

## Security Considerations

1. **Credential Protection**: Twilio credentials stored encrypted in database
2. **Rate Limiting**: Consider adding rate limits on manual test messages
3. **Audit Trail**: All test messages logged with gym_id and timestamp
4. **Authorization**: Only gym owners can send test messages to their own number

---

## Best Practices

### For Developers

1. **Always log test message results** for debugging
2. **Handle both sandbox and production modes** gracefully
3. **Provide clear error messages** to users
4. **Include timestamps** in test messages
5. **Personalize messages** with gym name

### For Users

1. **Test immediately** after verification
2. **Keep phone nearby** when verifying
3. **Join sandbox** if testing (sandbox mode)
4. **Contact support** if messages not received
5. **Check spam/blocked** numbers if issues persist

---

## Troubleshooting

### Message Not Received

**Check:**
1. Phone has internet connection
2. WhatsApp is installed and logged in
3. Number format correct (include country code)
4. In sandbox mode, joined sandbox
5. Number not blocked on Twilio account

**View Logs:**
```bash
# Check application logs
tail -f storage/logs/laravel.log | grep "WhatsApp"

# Check Twilio logs
# Visit: https://console.twilio.com/us1/monitor/logs/whatsapp
```

### Verification Successful But No Test Message

**Possible Causes:**
1. Test message sending failed silently
2. Twilio credentials invalid
3. From number misconfigured

**Solution:**
```php
// Check test_message_sent flag in response
if ($result['success'] && !$result['test_message_sent']) {
    // Test message failed, check logs
    Log::warning("Verification succeeded but test message failed");
}
```

---

## Future Enhancements

1. **Template Support**: Use WhatsApp templates for test messages
2. **Multiple Test Messages**: Different test message types (booking, payment, etc.)
3. **Scheduled Tests**: Periodic automatic testing
4. **Analytics**: Track test message delivery rates
5. **Retry Logic**: Automatic retry on failure

---

## Related Documentation

- [WHATSAPP_SYSTEM_TENANT_SUPPORT.md](WHATSAPP_SYSTEM_TENANT_SUPPORT.md)
- [REQUEST_DEMO_NOTIFICATIONS_UPDATE.md](REQUEST_DEMO_NOTIFICATIONS_UPDATE.md)
- Twilio WhatsApp API: https://www.twilio.com/docs/whatsapp

---

## Support

For issues with test messages:

1. Check logs: `storage/logs/laravel.log`
2. Verify Twilio console: https://console.twilio.com
3. Check notification_settings table
4. Review Twilio error codes: https://www.twilio.com/docs/api/errors

## Change History

- **2024**: Initial implementation with automatic and manual test messages
- Enhanced message content with gym personalization
- Added comprehensive logging
- Improved error handling and user feedback