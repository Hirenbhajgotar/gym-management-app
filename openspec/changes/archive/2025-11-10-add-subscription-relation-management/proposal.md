# Add Subscription Relation Management

## Why
Super admins need a comprehensive way to manage user subscriptions directly from the user management interface. Currently, subscription management is scattered across form fields and table actions, making it difficult to view subscription history or perform detailed subscription operations.

## What Changes
- Add a SubscriptionRelationManager to the UserResource
- Configure table columns for subscription details (plan, status, dates, etc.)
- Add form configuration for creating and editing subscriptions
- Integrate the relation manager into the UserResource relations

## Impact
- Affected specs: subscription-relation-management
- Affected code: app/Filament/SuperAdmin/Resources/UserResource.php and new relation manager class
- No breaking changes to existing functionality