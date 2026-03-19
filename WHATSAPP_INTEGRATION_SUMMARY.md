# WhatsApp Integration - Implementation Summary

## Overview
This document summarizes the WhatsApp messaging integration for the Gym Management SaaS platform using Twilio's WhatsApp Business API with a multi-tenant architecture.

## Architecture

### Billing Model
- **Type**: Fixed Packages (no expiration)
- **Model**: Full Reseller
- **Number Ownership**: Numbers purchased at master account level, assigned to tenant subaccounts
- **Message Costs**: Platform pays Twilio ₹0.15/message, tenants prepay via credit packages
- **Auto-topup**: Disabled (manual only)

### Broadcast Rules
- **Max Recipients**: 1000 per broadcast
- **Frequency**: 2 broadcasts per day
- **Min Gap**: 1 hour between broadcasts
- **Target**: Active members only

### Templates
- **Count**: 8 default utility templates
- **Verification**: Auto-submitted to Meta during setup
- **Category**: Utility only (to avoid recategorization to Marketing)

### Welcome Credits
- **Amount**: 50 free message credits
- **Eligibility**: New gym owners only
- **Expiration**: No expiration

## Database Schema

### Core Tables

#### `whatsapp_accounts`
Stores Twilio subaccount information for each gym.
- `gym_id` - Foreign key to gyms table
- `twilio_account_sid` - Twilio subaccount SID
- `twilio_auth_token` - Encrypted auth token
- `waba_id` - WhatsApp Business Account ID
- `business_id` - Meta Business ID
- `status` - pending/active/suspended/disconnected
- `phone_number_count` - Number of phone numbers

#### `whatsapp_phone_numbers`
Phone numbers assigned to each gym's WABA.
- `whatsapp_account_id` - Foreign key to whatsapp_accounts
- `phone_number` - E.164 format
- `formatted_number` - Display format
- `twilio_phone_number_sid` - Twilio phone number SID
- `verification_status` - pending/verified/failed
- `quality_rating` - green/yellow/red
- `template_burst_limit` - Max messages per burst (default 1000)

#### `whatsapp_templates`
Message templates for each gym.
- `gym_id` - Foreign key to gyms table
- `name` - Template identifier
- `display_name` - Human-readable name
- `body` - Template body with {{1}}, {{2}} placeholders
- `category` - utility/marketing/authentication
- `variables` - JSON array of variable definitions
- `variable_count` - Number of variables in template
- `twilio_template_sid` - Twilio Content SID
- `meta_template_id` - Meta template ID
- `status` - draft/pending/approved/rejected/disabled
- `rejection_reason` - Reason if rejected
- `is_default` - System default template
- `is_active` - Can be used for messaging
- `usage_count` - Number of times used
- `last_used_at` - Last usage timestamp

#### `whatsapp_packages`
Credit packages available for purchase.
- `name` - Package name
- `description` - Package description
- `message_count` - Number of message credits
- `selling_price` - Price charged to gym owner
- `twilio_number_cost` - Cost of WhatsApp number (if included)
- `twilio_msg_cost_per_unit` - Twilio cost per message (₹0.15)
- `validity_days` - Package validity (365 = no expiration)
- `sort_order` - Display order
- `is_active` - Available for purchase
- `is_popular` - Mark as popular/recommended
- `includes_number` - Includes dedicated WhatsApp number
- `features` - JSON array of package features

#### `whatsapp_message_credits`
Credit balance for each gym.
- `gym_id` - Foreign key to gyms table
- `total_credits` - Total credits purchased
- `used_credits` - Credits consumed
- `remaining_credits` - Available credits
- `cost_per_message` - Twilio cost per message
- `selling_price_per_msg` - Avg selling price per message
- `welcome_credits_used` - Welcome credits consumed
- `welcome_credits_claimed` - Has claimed welcome credits
- `last_used_at` - Last message sent timestamp

#### `whatsapp_credit_transactions`
Transaction history for credits.
- `gym_id` - Foreign key to gyms table
- `whatsapp_message_credit_id` - Foreign key to message credits
- `whatsapp_package_id` - Foreign key to package (if purchase)
- `type` - purchase/usage/refund/adjustment/bonus
- `credits` - Credit amount (negative for usage)
- `balance_before` - Balance before transaction
- `balance_after` - Balance after transaction
- `amount` - Money amount (for purchases)
- `payment_reference` - Razorpay payment ID
- `description` - Transaction description
- `admin_id` - Admin who made adjustment (if applicable)

#### `whatsapp_broadcasts`
Bulk message campaigns.
- `gym_id` - Foreign key to gyms table
- `whatsapp_template_id` - Template used
- `name` - Campaign name
- `message_content` - Preview of message
- `template_params` - JSON params for template variables
- `status` - draft/scheduled/sending/completed/failed/cancelled
- `total_recipients` - Total recipients
- `sent_count` - Successfully sent
- `delivered_count` - Successfully delivered
- `failed_count` - Failed to send
- `pending_count` - Pending send
- `scheduled_at` - Scheduled send time
- `started_at` - Actual start time
- `completed_at` - Completion time
- `failure_reason` - Reason if failed

#### `whatsapp_broadcast_recipients`
Individual recipients for each broadcast.
- `whatsapp_broadcast_id` - Foreign key to broadcasts
- `member_id` - Foreign key to gym_members
- `phone_number` - Recipient phone number
- `member_name` - Recipient name
- `status` - pending/sending/sent/delivered/read/failed
- `twilio_message_sid` - Twilio message SID
- `error_message` - Error if failed
- `sent_at` - Sent timestamp
- `delivered_at` - Delivered timestamp

#### `whatsapp_usage_logs`
Detailed message logs.
- `gym_id` - Foreign key to gyms table
- `member_id` - Foreign key to gym_members
- `whatsapp_phone_number_id` - Phone number used
- `whatsapp_template_id` - Template used
- `phone_number` - Recipient phone
- `twilio_message_sid` - Twilio message SID
- `template_name` - Template identifier
- `direction` - outbound/inbound
- `status` - queued/sending/sent/delivered/failed/undelivered
- `credits_used` - Credits deducted
- `message_body` - Message content
- `error_message` - Error if failed
- `twilio_cost` - Actual Twilio cost
- `sent_at` - Sent timestamp
- `delivered_at` - Delivered timestamp

## Models

All models are located in `app/Models/`:

- **WhatsAppAccount** - Twilio subaccount management
- **WhatsAppPhoneNumber** - Phone number management
- **WhatsappTemplate** - Template management (note: lowercase 'a')
- **WhatsAppPackage** - Credit packages
- **WhatsAppMessageCredit** - Credit balance
- **WhatsAppCreditTransaction** - Transaction history
- **WhatsAppBroadcast** - Broadcast campaigns
- **WhatsAppBroadcastRecipient** - Broadcast recipients
- **WhatsAppUsageLog** - Message logs
- **WhatsAppMessagePackage** - Legacy model (points to whatsapp_packages table)

## Services

### WhatsAppBillingService (`app/Services/WhatsAppBillingService.php`)
Handles credit management and billing operations.

**Key Methods:**
- `purchasePackage($gymId, $packageId, $paymentReference, $userId)` - Process package purchase
- `addWelcomeCredits($gymId)` - Grant 50 free welcome credits
- `hasClaimedWelcomeCredits($gymId)` - Check if welcome credits claimed
- `useCredits($gymId, $credits, $description)` - Deduct credits for usage
- `refundCredits($gymId, $credits, $reason, $adminId)` - Refund credits
- `adjustCredits($gymId, $credits, $description, $adminId)` - Manual adjustment
- `getCreditBalance($gymId)` - Get current balance
- `getTransactionHistory($gymId, $limit)` - Get transaction history
- `getUsageReport($gymId, $startDate, $endDate)` - Generate usage report

### WhatsAppTemplateService (`app/Services/WhatsAppTemplateService.php`)
Manages WhatsApp message templates.

**Key Methods:**
- `getDefaultTemplates()` - Returns 8 default utility templates
- `createTemplate($gymId, $templateData)` - Create new template
- `submitForApproval($templateId)` - Submit to Meta for approval
- `syncTemplateStatus($templateId)` - Sync approval status from Meta
- `getApprovedTemplates($gymId)` - Get all approved templates
- `deleteTemplate($templateId)` - Delete template

**Default Templates:**
1. **welcome_message** - Welcome new members
2. **membership_expiry** - Membership expiring soon
3. **payment_reminder** - Payment due reminder
4. **payment_confirmation** - Payment received
5. **attendance_milestone** - Attendance achievement
6. **class_reminder** - Upcoming class reminder
7. **birthday_wish** - Birthday wishes
8. **renewal_reminder** - Membership renewal

### TwilioWhatsAppService (`app/Services/TwilioWhatsAppService.php`)
Handles Twilio WhatsApp API integration.

**Key Methods:**
- `sendTemplateMessage($to, $templateName, $params)` - Send template-based message
- `sendBroadcast($templateId, $recipients, $params)` - Send bulk messages
- `getMessageStatus($messageSid)` - Check message delivery status
- `validatePhoneNumber($phone)` - Validate phone format

### RazorpayService (`app/Services/RazorpayService.php`)
Extended to handle WhatsApp package purchases.

**Updates:**
- Added WhatsApp package payment type handling in `createOrder()`
- Stores package metadata in payment record

### RazorpayWebhookController (`app/Http/Controllers/Api/RazorpayWebhookController.php`)
Extended to process WhatsApp payments.

**New Method:**
- `processWhatsAppPackagePayment($payment)` - Process package purchase from webhook
  - Validates payment data
  - Calls `WhatsAppBillingService::purchasePackage()`
  - Adds credits to gym account
  - Creates transaction record

## Admin Pages

### WhatsAppSetup (`app/Filament/Admin/Pages/WhatsAppSetup.php`)
9-step wizard for setting up WhatsApp for a gym:

1. **Gym Selection** - Select gym to configure
2. **Twilio Subaccount** - Create/link Twilio subaccount
3. **WABA Creation** - Create WhatsApp Business Account
4. **Phone Number** - Purchase/assign WhatsApp number
5. **Business Profile** - Configure business details
6. **Templates** - Create/approve message templates
7. **Credits** - Add welcome credits (50 free)
8. **Testing** - Send test message
9. **Review** - Final review and activation

## Tenant Pages

### Dashboard (`app/Filament/Admin/Pages/WhatsApp/Dashboard.php`)
WhatsApp dashboard for gym owners.

**Features:**
- Credit balance display (total, remaining, used, percentage)
- Monthly usage stats (messages sent, delivered, failed, delivery rate)
- Recent messages list
- Send test message action

**View:** `resources/views/filament/tenant/pages/whatsapp-dashboard.blade.php`

### Broadcast (`app/Filament/Admin/Pages/WhatsApp/Broadcast.php`)
Bulk messaging interface.

**Features:**
- Template selection
- Recipient selection (active members only)
- Variable parameter input
- Preview before send
- Broadcast history
- Delivery status tracking

**View:** `resources/views/filament/tenant/pages/whatsapp-broadcast.blade.php`

### PurchaseCredits (`app/Filament/Admin/Pages/WhatsApp/PurchaseCredits.php`)
Credit purchase page for gym owners.

**Features:**
- Display available packages
- Claim welcome credits (50 free, one-time)
- Current balance display
- Razorpay payment integration
- Package features comparison
- Cost per message calculation

**View:** `resources/views/filament/tenant/pages/whatsapp-purchase-credits.blade.php`

## Default Packages

Seeded via `database/seeders/WhatsAppMessagePackagesSeeder.php`:

1. **Starter Pack** - ₹199
   - 100 message credits
   - ₹1.99 per message
   - Perfect for small gyms

2. **Growth Pack** - ₹749 (Most Popular)
   - 500 message credits
   - ₹1.50 per message
   - Priority support

3. **Professional Pack** - ₹1,299
   - 1,000 message credits
   - ₹1.30 per message
   - Priority support + Usage analytics

4. **Business Pack** - ₹3,499
   - 3,000 message credits
   - ₹1.17 per message
   - Priority support + Analytics + Account manager

5. **Enterprise Pack** - ₹4,999
   - 5,000 message credits
   - Dedicated WhatsApp number included
   - ₹1.00 per message
   - Full support + Custom integrations

## Razorpay Payment Flow

1. **User selects package** on PurchaseCredits page
2. **Frontend calls** `initiatePayment()` method
3. **Backend creates** Razorpay order via `RazorpayService::createOrder()`
   - Stores gym_id, package_id, user_id in metadata
   - Creates Payment record with status 'pending'
4. **Razorpay modal** opens for payment
5. **User completes** payment
6. **Frontend receives** payment success callback
7. **Frontend calls** `handlePaymentSuccess()` with payment_id and signature
8. **Backend verifies** payment via `RazorpayService::verifyPayment()`
9. **Webhook received** from Razorpay (async)
10. **Webhook handler** calls `processWhatsAppPackagePayment()`
11. **Credits added** via `WhatsAppBillingService::purchasePackage()`
12. **Transaction logged** in whatsapp_credit_transactions table

## Credit Deduction Flow

1. **User sends** message/broadcast
2. **TwilioWhatsAppService** checks credit balance
3. **If sufficient**, deducts credits via `WhatsAppBillingService::useCredits()`
4. **Sends message** via Twilio API
5. **Logs usage** in whatsapp_usage_logs table
6. **Updates balance** in whatsapp_message_credits table
7. **Creates transaction** in whatsapp_credit_transactions table

## Welcome Credits Flow

1. **Gym completes** WhatsApp setup (Step 7 of wizard)
2. **Admin clicks** "Add Welcome Credits" button
3. **Backend calls** `WhatsAppBillingService::addWelcomeCredits()`
4. **Checks** if already claimed via `welcome_credits_claimed` flag
5. **If not claimed**, adds 50 credits
6. **Sets flag** `welcome_credits_claimed = true`
7. **Creates transaction** with type 'purchase', amount 0

**OR**

1. **Gym owner** visits PurchaseCredits page
2. **Sees banner** "50 Free Welcome Credits!"
3. **Clicks** "Claim Now" button
4. **Backend calls** `claimWelcomeCredits()` method
5. **Same flow** as above

## Testing

### Manual Testing Checklist

#### Setup Flow
- [ ] Create Twilio subaccount via admin wizard
- [ ] Create WABA
- [ ] Assign phone number
- [ ] Configure business profile
- [ ] Submit default templates
- [ ] Add welcome credits
- [ ] Send test message

#### Credit Purchase Flow
- [ ] View available packages
- [ ] Select package
- [ ] Complete Razorpay payment
- [ ] Verify credits added
- [ ] Check transaction history
- [ ] Verify webhook processing

#### Messaging Flow
- [ ] Send single message
- [ ] Verify credit deduction
- [ ] Check message status
- [ ] Send broadcast to multiple members
- [ ] Verify broadcast limits (1000 max)
- [ ] Test rate limiting (2/day, 1 hour gap)

#### Welcome Credits
- [ ] Claim welcome credits (new gym)
- [ ] Verify 50 credits added
- [ ] Attempt to claim again (should fail)
- [ ] Verify welcome_credits_claimed flag

### Unit Tests (To Be Created)
- `WhatsAppBillingServiceTest`
- `WhatsAppTemplateServiceTest`
- `TwilioWhatsAppServiceTest`
- `RazorpayWebhookControllerTest`

## Pending Tasks

### High Priority
1. **Complete TwilioAccountManager ISV methods**
   - `createSubaccount($gymId)` - Create Twilio subaccount
   - `createWABA($subaccountSid, $gymData)` - Create WABA
   - `purchasePhoneNumber($subaccountSid)` - Purchase WhatsApp number
   - `assignNumberToWABA($phoneSid, $wabaSid)` - Assign number
   - `submitTemplate($templateData)` - Submit template to Meta

2. **Create Admin Filament Resources**
   - WhatsAppAccountResource - Manage gym WhatsApp accounts
   - WhatsAppTemplateResource - Review/approve templates
   - WhatsAppPackageResource - Manage credit packages
   - WhatsAppTransactionResource - View all transactions
   - WhatsAppUsageLogResource - View message logs

3. **Add Broadcast Rate Limiting**
   - Implement 2 broadcasts/day limit
   - Enforce 1 hour gap between broadcasts
   - Store last broadcast timestamp
   - Validate before allowing new broadcast

4. **Template Approval Workflow**
   - Auto-submit default templates during setup
   - Poll Meta API for approval status
   - Update template status in database
   - Notify gym owner when approved/rejected

### Medium Priority
5. **Add Message Scheduling**
   - Schedule broadcasts for future date/time
   - Queue processing for scheduled messages
   - Cancel scheduled broadcasts

6. **Enhanced Analytics**
   - Delivery rate trends
   - Credit usage predictions
   - Template performance metrics
   - Member engagement tracking

7. **Low Balance Alerts**
   - Notify when credits < threshold
   - Suggest package upgrade
   - Email/SMS alerts

### Low Priority
8. **Template Builder UI**
   - Visual template editor
   - Variable insertion
   - Preview with sample data

9. **Webhook Handling for Message Status**
   - Receive Twilio status callbacks
   - Update message delivery status
   - Track read receipts (if available)

10. **Export Features**
    - Export usage reports (CSV/PDF)
    - Export transaction history
    - Export broadcast reports

## Configuration

### Environment Variables Required

```env
# Twilio Master Account
TWILIO_ACCOUNT_SID=your_master_account_sid
TWILIO_AUTH_TOKEN=your_master_auth_token
TWILIO_API_KEY=your_api_key
TWILIO_API_SECRET=your_api_secret

# Razorpay
RAZORPAY_KEY_ID=your_razorpay_key_id
RAZORPAY_KEY_SECRET=your_razorpay_key_secret
RAZORPAY_WEBHOOK_SECRET=your_webhook_secret
```

### Package Pricing Calculation

**Cost Breakdown (per package):**
- Twilio cost = `(message_count × ₹0.15) + twilio_number_cost`
- Profit = `selling_price - Twilio cost`
- Margin % = `(profit / selling_price) × 100`

**Example (Growth Pack):**
- Twilio cost = (500 × ₹0.15) + ₹0 = ₹75
- Profit = ₹749 - ₹75 = ₹674
- Margin = (₹674 / ₹749) × 100 = 89.99%

## Security Considerations

1. **Twilio Credentials**
   - Store auth tokens encrypted in database
   - Use API keys for authentication
   - Rotate credentials regularly

2. **Razorpay Webhooks**
   - Verify webhook signatures
   - Use HTTPS only
   - Implement idempotency checks

3. **Phone Number Validation**
   - Validate E.164 format
   - Check for duplicates
   - Verify against DND registry

4. **Rate Limiting**
   - Enforce broadcast limits
   - Prevent credit manipulation
   - Monitor for abuse

## Support & Troubleshooting

### Common Issues

**Credits not added after payment**
- Check webhook logs
- Verify Razorpay signature
- Check payment status in database
- Manually trigger webhook processing

**Template not approved**
- Check Meta approval status
- Review rejection reason
- Modify template content
- Resubmit for approval

**Messages not delivered**
- Check credit balance
- Verify phone number format
- Check Twilio message status
- Review error logs

**Broadcast failed**
- Check recipient count (max 1000)
- Verify template is approved
- Check rate limits
- Review error message

## Monitoring

### Key Metrics to Track

1. **Financial**
   - Total revenue from package sales
   - Average package price
   - Credit purchase frequency
   - Refund rate

2. **Usage**
   - Messages sent per day
   - Delivery rate
   - Template usage distribution
   - Broadcast frequency

3. **Performance**
   - Message delivery time
   - Template approval time
   - Payment success rate
   - Webhook processing time

4. **Customer**
   - Active gyms using WhatsApp
   - Average credits per gym
   - Welcome credits claim rate
   - Customer satisfaction

## Changelog

### Version 1.0.0 (2026-02-12)
- Initial implementation
- Database schema created
- Core models and services implemented
- Razorpay payment integration
- Admin setup wizard
- Tenant dashboard and broadcast pages
- Credit purchase flow
- Welcome credits system
- Default template library
- Package seeding

---

**Last Updated:** February 12, 2026  
**Version:** 1.0.0  
**Status:** Development Complete - Testing Pending
