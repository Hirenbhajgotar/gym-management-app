# Design for Subscription View Action

## Architecture
The change leverages Filament's built-in ViewAction for relation managers, which automatically displays the form fields in read-only mode within a modal.

## Implementation Approach
- Add `Tables\Actions\ViewAction::make()` to the actions array in the SubscriptionRelationManager table method
- Enhance the existing form method to display comprehensive subscription details in read-only mode for super-admin users
- Include all subscription fields and related information for complete visibility
- No additional configuration needed as Filament handles the modal display automatically

## Form Fields for Super-Admin View

### Subscription Details Section
- **Plan**: Select field showing plan name (read-only)
- **Billing Cycle**: Select field (monthly/yearly)
- **Status**: Select field with status enum
- **Price**: Text input showing decimal price
- **Currency**: Select field with currency enum
- **Trial End Date**: DateTime picker (optional)
- **Current Period Start**: DateTime display (read-only)
- **Current Period End**: DateTime display (read-only)
- **Subscription End Date**: DateTime display (read-only, nullable)
- **Cancelled At**: DateTime display (read-only, nullable)
- **Grace Period Ends At**: DateTime display (read-only, nullable)
- **Notes**: Rich editor for subscription notes

### Metadata & Audit Section
- **Metadata**: JSON display for additional subscription data
- **Created At**: DateTime display (read-only)
- **Updated At**: DateTime display (read-only)

### Related Information Section
- **Subscription Features**: Display list of associated features with limits
- **User Information**: Display related user details
- **Plan Details**: Display complete plan information

## Trade-offs
- Comprehensive view provides complete subscription visibility for super-admin
- Modal-based viewing may be less detailed than a full page view, but appropriate for relation manager context
- Enhanced form includes all model fields and relationships for administrative oversight
- Maintains existing edit and delete functionality alongside detailed view capability