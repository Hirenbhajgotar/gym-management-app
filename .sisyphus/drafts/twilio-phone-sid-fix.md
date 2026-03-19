# Draft: Fix phone_number_sid Storage Issue

## Problem Identified
- `phone_number_sid` stores different types of Twilio SIDs (Channel Sender vs Incoming Phone Number)
- Cleanup code incorrectly uses the stored SID for different API calls
- Need to validate existing stored SIDs and fix the data model

## Requirements
- Add validation to verify stored phone_number_sid is correct
- Fix data model to store both SID types separately
- Fix fallback logic in registration methods
- Create a validation/sync command to check existing data

## Technical Approach
1. Add new column `channel_sender_sid` to WhatsAppPhoneNumber model
2. Keep `phone_number_sid` for IncomingPhoneNumber SID only
3. Update all registration methods to store correct SIDs
4. Create artisan command to validate and fix existing records

## Scope
- IN: Fix TwilioAccountManager.php, WhatsAppPhoneNumber.php model, add migration
- OUT: Changes to other Twilio services unless directly related
