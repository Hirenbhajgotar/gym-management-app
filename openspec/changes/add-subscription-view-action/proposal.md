# Add Subscription View Action

## Summary
Add a comprehensive view action to the SubscriptionRelationManager to allow super-admin users to view complete subscription information in a read-only modal, including all subscription fields, metadata, audit information, and related data.

## Motivation
Super-admin users need complete visibility into subscription details for administrative oversight, troubleshooting, and audit purposes. Currently, only edit and delete actions are available, limiting the ability to review comprehensive subscription information.

## Impact
- Adds comprehensive view capability for subscriptions in the UserResource relation manager for super-admin users
- Provides complete subscription visibility including pricing, periods, metadata, and audit trails
- Improves administrative oversight and troubleshooting capabilities
- Maintains existing edit and delete functionality
- Displays all subscription-related information in an organized, read-only format

## Dependencies
None

## Related Changes
None