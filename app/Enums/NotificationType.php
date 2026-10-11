<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case BIRTHDAY = 'birthday_notifications';
    case MEMBERSHIP_EXPIRY = 'membership_expiry_notifications';
    case ATTENDANCE_REMINDER = 'attendance_reminders';
    case CLASS_REMINDER = 'class_reminders';
    case PAYMENT_REMINDER = 'payment_reminders';
    case FOLLOWUP_REMINDER = 'followup_reminders';
    case LEAD_REMINDER = 'lead_reminders_notifications';
    case WELCOME = 'welcome_notifications';
    case PROMOTIONAL = 'promotional_notifications';
    case BIOMETRIC_CONSENT = 'biometric_consent_notifications';

    public function getLabel(): string
    {
        return match ($this) {
            self::BIRTHDAY => 'Birthday Notifications',
            self::MEMBERSHIP_EXPIRY => 'Membership Expiry Notifications',
            self::ATTENDANCE_REMINDER => 'Attendance Reminders',
            self::CLASS_REMINDER => 'Class Reminders',
            self::PAYMENT_REMINDER => 'Payment Reminders',
            self::FOLLOWUP_REMINDER => 'Follow-up Reminders',
            self::LEAD_REMINDER => 'Lead Reminders',
            self::WELCOME => 'Welcome Notifications',
            self::PROMOTIONAL => 'Promotional Notifications',
            self::BIOMETRIC_CONSENT => 'Biometric Consent Notifications',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::BIRTHDAY => 'Send birthday wishes to members',
            self::MEMBERSHIP_EXPIRY => 'Send notifications before and after membership expiry',
            self::ATTENDANCE_REMINDER => 'Send reminders for regular attendance',
            self::CLASS_REMINDER => 'Send reminders for upcoming classes',
            self::PAYMENT_REMINDER => 'Send payment reminders for pending dues',
            self::FOLLOWUP_REMINDER => 'Send follow-up reminders for new leads',
            self::LEAD_REMINDER => 'Send reminders for pending leads that need attention',
            self::WELCOME => 'Send welcome message to new members',
            self::PROMOTIONAL => 'Send promotional messages to members',
            self::BIOMETRIC_CONSENT => 'Send biometric consent requests to members',
        };
    }
}
