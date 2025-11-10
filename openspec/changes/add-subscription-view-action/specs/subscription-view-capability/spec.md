# Subscription View Capability

## ADDED Requirements

#### Scenario: User views subscription details
Given a user is viewing the subscriptions relation manager for a user
When they click the view action on a subscription row
Then a modal opens displaying the subscription details in read-only mode
And the modal shows all form fields including plan, billing cycle, status, trial end date, and notes

#### Scenario: View action is available alongside edit and delete
Given a user is viewing the subscriptions relation manager
When they see the actions column for any subscription
Then they see view, edit, and delete action buttons
And the view action appears first in the actions list