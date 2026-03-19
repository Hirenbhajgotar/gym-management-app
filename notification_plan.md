## Notification System Improvement Plan

We need to enhance the existing notification system in our gym management SaaS platform to make it more scalable, user-friendly, and maintainable.

### Core Requirement

Each tenant (gym owner) should have the ability to toggle notification settings.
WhatsApp notifications should only be sent to gym members if the tenant has enabled the corresponding notification switch.

### Scope of Improvements

#### 1. Update to new enhance whatsapp implementation

* Update to new enhance whatsapp implementation (WhatsAppAccount, WhatsAppTemplate, WhatsAppPhoneNumber, WhatsAppMessageCredit, WhatsAppMessagePackage, WhatsAppBroadcast, WhatsAppBroadcastRecipient, WhatsAppCreditTransaction, WhatsAppUsageLog)
* Some of the notifications are using `app\Models\NotificationSetting.php`, there is new whatsapp model `app\Models\WhatsAppAccount.php` and other whatsapp related models.
* We need to use the new whatsapp implementation for all the notifications.
* Check whole workflow and replace old whatsapp implementation with new whatsapp implementation.

#### 2. Notification Settings (Tenant Control)

* Allow gym owners (tenants) to enable or disable WhatsApp notifications from the settings panel.
* If the notification toggle is OFF, no WhatsApp notifications should be sent to end users (gym members).
* Improve the overall UI/UX of the notification settings page to make it more intuitive and user-friendly.
* Update the settings page located at:
  `app/Filament/Admin/Pages/GymSettings.php`

#### 3. Refactor Existing Notification System

* An old notification system is already implemented; review and refactor it for better performance and maintainability.
* Ensure the new system is modular, scalable, and easy to manage.
* Maintain backward compatibility where necessary.

#### 4. WhatsApp Integration Updates

* Review the entire workflow and replace it with the updated and optimized WhatsApp implementation (we have already optimized whatsapp implementation).
* Ensure proper handling of tenant-based WhatsApp configuration and message sending.

#### 5. Review Existing Commands, Jobs, and Notifications

There are already commands, queued jobs, and notification classes created for sending notifications.

* Review and optimize them.
* Ensure they follow the updated notification logic and tenant-based toggles.
* Ensure they follow the optimized WhatsApp implementation.

#### 6. Files to Review and Improve

Update and refactor the following components:

* @app/Services/NotificationChannelResolver.php
* @app/Notifications/WhatsAppChannel.php
* @app/Filament/Admin/Pages/GymSettings.php
* @app/Models/GymWhatsAppBalance.php
* @app/Console/Commands/BirthdayNotificationCommand.php
* @app/Notifications/BirthdayNotification.php
* @app/Console/Commands/CheckWhatsAppBalance.php
* @app/Jobs/CheckWhatsAppBalanceJob.php
* @app/Notifications/LowWhatsAppBalanceNotification.php
* @app/Console/Commands/PendingLeadNotificationCommand.php
* @app/Notifications/PendingLeadNotification.php
* @app/Console/Commands/SendFollowUpRemindersCommand.php
* @app/Notifications/FollowUpReminderNotification.php
* @app/Console/Commands/SendMembershipExpiryAlerts.php
* @app/Console/Commands/SendSubscriptionExpiryAlerts.php
* @app/Notifications/PlanExpiryNotification.php
* @app/Notifications/SubscriptionExpired.php
* @app/Notifications/TrialEndingNotification.php

### Goal

* Create a robust, scalable, and tenant-aware notification system.
* Provide a clean and modern UI/UX for notification settings.
* Ensure WhatsApp notifications are sent only when enabled by the tenant.
* Improve code structure, performance, and maintainability across the notification workflow.
* List notifications, commands, jobs, and services that not used.
