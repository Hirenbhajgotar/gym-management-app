# WhatsApp Template Setup Guide

## Overview

WhatsApp Business API requires **pre-approved message templates** for business-initiated conversations. This guide explains how to set up templates for your gym's WhatsApp integration.

---

## Understanding WhatsApp Templates

### What are WhatsApp Templates?

WhatsApp templates are pre-approved message formats that businesses can use to initiate conversations with customers. They are required for:

- Welcome messages after setup
- Booking confirmations
- Payment reminders
- Birthday greetings
- Promotional messages

### Template vs. Sandbox Mode

| Mode | Message Type | Approval Required | Use Case |
|------|-------------|-------------------|----------|
| **Sandbox** | Free-form text | No | Testing & development |
| **Production** | Templates only | Yes | Live business messages |

---

## Quick Setup

### Option 1: Use Sandbox Mode (Recommended for Testing)

```env
TWILIO_USE_SANDBOX=true
TWILIO_SANDBOX_NUMBER=+14155238886
```

**Pros:**
- ✅ No template approval needed
- ✅ Send any message format
- ✅ Instant setup
- ✅ Perfect for testing

**Cons:**
- ❌ Recipients must "join" sandbox first
- ❌ Not suitable for production
- ❌ Limited to test numbers

### Option 2: Use Production with Templates

```env
TWILIO_USE_SANDBOX=false
TWILIO_WELCOME_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
```

**Pros:**
- ✅ Professional business messages
- ✅ No recipient opt-in required
- ✅ Production-ready
- ✅ Verified business account

**Cons:**
- ❌ Requires template approval
- ❌ Template creation takes time
- ❌ Limited message formats

---

## Creating WhatsApp Templates

### Step 1: Access Twilio Console

1. Log in to [Twilio Console](https://console.twilio.com)
2. Navigate to **Messaging** → **Content Templates**
3. Click **Create new Template**

### Step 2: Create Welcome Message Template

**Template Name:** `welcome_message`

**Template Type:** `WhatsApp`

**Category:** `UTILITY` (for account updates) or `MARKETING` (for promotions)

**Language:** `English (en)`

**Template Body:**
```
🎉 *WhatsApp Integration Active!*

Hello from {{1}}! 👋

Your WhatsApp Business integration is now successfully set up and ready to use.

*What's Next?*
✅ Members will receive booking confirmations
✅ Payment reminders will be sent automatically
✅ Class updates will reach members instantly
✅ Birthday greetings will be sent on special days

This is a test message to confirm everything is working perfectly.

_Sent at: {{2}}_
```

**Variables:**
- `{{1}}` - Gym Name
- `{{2}}` - Timestamp

### Step 3: Submit for Approval

1. Review your template
2. Click **Submit for Approval**
3. Wait for WhatsApp approval (usually 24-48 hours)

### Step 4: Configure in Application

Once approved, copy the **Content SID** (starts with `HX`)

**Add to `.env` file:**
```env
TWILIO_WELCOME_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
```

**Or add to database:**
```php
use App\Models\WhatsAppTemplate;

WhatsAppTemplate::create([
    'gym_id' => $gymId,
    'template_name' => 'welcome_message',
    'template_sid' => 'HXxxxxxxxxxxxxxxxxxxxxx',
    'language' => 'en',
    'variables' => ['gym_name', 'timestamp'],
    'status' => 'approved',
]);
```

---

## Template Requirements & Best Practices

### WhatsApp Template Guidelines

1. **No Personalization in Template Body**
   - ❌ Can't use dynamic content directly
   - ✅ Use variables like {{1}}, {{2}}

2. **Template Categories**
   - **UTILITY**: Account updates, OTPs, confirmations
   - **MARKETING**: Promotions, offers (limited to 1 per 24h)
   - **AUTHENTICATION**: OTP codes only

3. **Approval Process**
   - Templates reviewed by WhatsApp
   - Takes 24-48 hours typically
   - Rejection possible if violates policies

4. **Character Limits**
   - Body: 1024 characters
   - Header: 60 characters
   - Footer: 60 characters

### Best Practices

✅ **Do:**
- Keep messages professional
- Use clear, actionable language
- Include business name
- Add opt-out information
- Use variables for personalization

❌ **Don't:**
- Include promotional content in UTILITY templates
- Use excessive emojis
- Add URLs without approval
- Send spam or unsolicited messages

---

## Template Examples

### 1. Welcome Message Template

```
🎉 Welcome to {{1}}!

Your WhatsApp notifications are now active.

You'll receive:
• Booking confirmations
• Payment reminders
• Class updates
• Special offers

Sent: {{2}}
```

**Variables:**
- {{1}} = Gym Name
- {{2}} = Timestamp

---

### 2. Booking Confirmation Template

```
✅ Booking Confirmed!

Hi {{1}},

Your class booking is confirmed:

📅 Class: {{2}}
🕐 Time: {{3}}
📍 Location: {{4}}

See you there! 💪

- {{5}}
```

**Variables:**
- {{1}} = Member Name
- {{2}} = Class Name
- {{3}} = Time
- {{4}} = Location
- {{5}} = Gym Name

---

### 3. Payment Reminder Template

```
💳 Payment Reminder

Hi {{1}},

Your membership payment of ₹{{2}} is due on {{3}}.

Pay now to avoid service interruption.

Thank you!
- {{4}}
```

**Variables:**
- {{1}} = Member Name
- {{2}} = Amount
- {{3}} = Due Date
- {{4}} = Gym Name

---

### 4. Birthday Greeting Template

```
🎉 Happy Birthday {{1}}! 🎂

Wishing you a fantastic year ahead!

Here's a special gift: {{2}}

Valid until: {{3}}

Enjoy your special day! 🎈

- Team {{4}}
```

**Variables:**
- {{1}} = Member Name
- {{2}} = Special Offer
- {{3}} = Expiry Date
- {{4}} = Gym Name

---

### 5. Demo Request Template (For System Notifications)

```
🔔 *New Demo Request*

{{1}}

⏰ Please respond to this request as soon as possible.
```

**Variables:**
- {{1}} = Complete formatted demo request details

**Note:** This template is used for system-level notifications when demo requests are submitted through the website.

**Configuration:**
```env
TWILIO_DEMO_REQUEST_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
```

---

## Configuration Reference

### Environment Variables

```env
# Twilio Configuration
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token

# Mode Selection
TWILIO_USE_SANDBOX=false  # true for testing, false for production

# Sandbox Configuration
TWILIO_SANDBOX_NUMBER=+14155238886

# Template SIDs (Production Mode)
TWILIO_WELCOME_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
TWILIO_DEMO_REQUEST_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
TWILIO_BOOKING_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
TWILIO_PAYMENT_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
TWILIO_BIRTHDAY_GREETING_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx
```

### Config File (`config/services.php`)

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
    'booking_confirmation_template_sid' => env('TWILIO_BOOKING_TEMPLATE_SID'),
    'payment_reminder_template_sid' => env('TWILIO_PAYMENT_TEMPLATE_SID'),
],
```

---

## Testing Templates

### Test in Sandbox Mode

```bash
# Set to sandbox mode
TWILIO_USE_SANDBOX=true

# Send test message
php artisan whatsapp:test-system +1234567890 --message="Test message"
```

### Test in Production Mode

```bash
# Set to production mode
TWILIO_USE_SANDBOX=false

# Configure template
TWILIO_WELCOME_TEMPLATE_SID=HXxxxxxxxxxxxxxxxxxxxxx

# Send test message (will use template)
# From WhatsApp Setup page: Click "Send Test Message"
```

### Verify Template Usage

Check logs for template confirmation:
```
[INFO] Using WhatsApp template for test message
{
    "gym_id": "123",
    "template_sid": "HXxxxxxxxxxxxxxxxxxxxxx",
    "source": "config"
}
```

---

## Troubleshooting

### Issue: Template Not Found

**Error:**
```
No approved WhatsApp template found. Please:
1. Create and approve a 'welcome_message' template in Twilio console, OR
2. Set TWILIO_WELCOME_TEMPLATE_SID in your .env file, OR
3. Use sandbox mode (TWILIO_USE_SANDBOX=true) for testing.
```

**Solution:**
1. Create template in Twilio console
2. Wait for approval
3. Add `TWILIO_WELCOME_TEMPLATE_SID` to `.env`

---

### Issue: Template Rejected

**Common Reasons:**
- Contains prohibited content
- Too promotional for UTILITY category
- Missing required information
- Violates WhatsApp policies

**Solution:**
1. Review WhatsApp's [Message Template Guidelines](https://developers.facebook.com/docs/whatsapp/message-templates/guidelines)
2. Modify template to comply
3. Resubmit for approval

---

### Issue: Variables Not Rendering

**Error:** Template shows `{{1}}` instead of gym name

**Solution:**
```php
// Correct variable format
$messageParams["contentVariables"] = json_encode([
    "1" => $gymName,    // Variable 1
    "2" => $timestamp,  // Variable 2
]);

// Incorrect (missing json_encode)
$messageParams["contentVariables"] = [
    "1" => $gymName,  // ❌ Won't work
];
```

---

### Issue: Sandbox Mode Not Working

**Error:** "Attempt to send to unverified number"

**Solution:**
1. Recipient must join sandbox first
2. Send message to sandbox number: `join [your-sandbox-keyword]`
3. Example: Send "join fitness-gym" to +14155238886
4. Then try test message again

---

## Template Management

### Syncing Templates to Database

```php
use App\Services\WhatsAppTemplateManager;

$manager = new WhatsAppTemplateManager($gymId);
$result = $manager->syncTemplatesToDatabase();

if ($result['success']) {
    echo "Synced {$result['synced']} templates";
}
```

### Getting Template from Database

```php
$template = $manager->getTemplate('welcome_message');

if ($template) {
    echo "Template SID: " . $template->template_sid;
}
```

### Artisan Command

```bash
# Sync templates for all gyms
php artisan app:sync-whats-app-templates
```

---

## Migration from Sandbox to Production

### Step 1: Create Templates

1. Create all required templates in Twilio
2. Submit for approval
3. Wait for approval (24-48 hours)

### Step 2: Update Configuration

```env
# Before (Sandbox)
TWILIO_USE_SANDBOX=true

# After (Production)
TWILIO_USE_SANDBOX=false
TWILIO_WELCOME_TEMPLATE_SID=HXxxxxx
TWILIO_BOOKING_TEMPLATE_SID=HXxxxxx
TWILIO_PAYMENT_TEMPLATE_SID=HXxxxxx
```

### Step 3: Test Production Mode

1. Navigate to WhatsApp Setup
2. Click "Send Test Message"
3. Verify template message received
4. Check logs for template usage

### Step 4: Enable for All Users

Update configuration to production mode:
```bash
php artisan config:clear
php artisan cache:clear
```

---

## Cost Considerations

### Sandbox Mode
- **Cost:** Free
- **Limitation:** Testing only

### Production Mode
- **Template Messages:** ~$0.005 per message
- **Session Messages:** Free within 24-hour window
- **Marketing Messages:** Higher rate, limited frequency

---

## Resources

### Official Documentation
- [Twilio WhatsApp API](https://www.twilio.com/docs/whatsapp)
- [WhatsApp Message Templates](https://www.twilio.com/docs/whatsapp/tutorial/send-whatsapp-notification-messages-templates)
- [WhatsApp Business Policy](https://www.whatsapp.com/legal/business-policy/)

### Related Files
- `app/Services/TwilioAccountManager.php` - Test message implementation
- `app/Services/WhatsAppTemplateManager.php` - Template management
- `app/Services/TwilioWhatsAppService.php` - Message sending service (includes system tenant support)
- `app/Livewire/Frontend/RequestDemo.php` - Demo request notifications (uses system tenant)

---

## FAQ

**Q: Do I need templates for testing?**
A: No, use sandbox mode (TWILIO_USE_SANDBOX=true) for testing.

**Q: How long does template approval take?**
A: Usually 24-48 hours, but can be longer.

**Q: Can I edit approved templates?**
A: No, you need to create a new template and get it approved.

**Q: How many templates can I create?**
A: Unlimited, but each needs approval.

**Q: What happens if template is rejected?**
A: Review rejection reason, modify template, and resubmit.

**Q: Can I use emojis in templates?**
A: Yes, but use them sparingly and professionally.

**Q: Do I need different templates for different gyms?**
A: No, use variables to personalize the same template.

---

## Support

For template-related issues:

1. Check Twilio console for template status
2. Review WhatsApp Business Policy
3. Check application logs for errors
4. Contact Twilio support if needed

Template approval issues: https://www.twilio.com/console/messaging/content-templates