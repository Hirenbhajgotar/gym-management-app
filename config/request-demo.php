<?php

declare(strict_types=1);

/**
 * @return array{
 *     rate_limit_max_attempts: int,
 *     rate_limit_decay_seconds: int,
 *     force_contact_email: ?string,
 *     dns_check_enabled: bool,
 *     dns_cache_ttl_seconds: int,
 *     phone_countries: array<int, string>,
 *     whatsapp_message: string,
 *     testimonials: array<int, array<string, mixed>>,
 *     faqs: array<int, array<string, string>>,
 * }
 */
return [
    'rate_limit_max_attempts' => (int) env('REQUEST_DEMO_RATE_LIMIT_MAX', 3),

    'rate_limit_decay_seconds' => (int) env('REQUEST_DEMO_RATE_LIMIT_DECAY', 3600),

    'force_contact_email' => env('REQUEST_DEMO_FORCE_CONTACT_EMAIL'),

    'dns_check_enabled' => (bool) env('REQUEST_DEMO_DNS_CHECK_ENABLED', true),

    'dns_cache_ttl_seconds' => (int) env('REQUEST_DEMO_DNS_CACHE_TTL', 86400),
    'dns_negative_cache_ttl_seconds' => (int) env('REQUEST_DEMO_DNS_NEGATIVE_CACHE_TTL', 300),

    'phone_countries' => ['US', 'IN', 'PK'],

    'whatsapp_message' => 'Hi FluxFitness Team! I want to know more about the gym software.',

    'metrics' => [
        'min_tenants_threshold' => (int) env('REQUEST_DEMO_MIN_TENANTS_THRESHOLD', 47),
        'active_gyms_count' => env('REQUEST_DEMO_ACTIVE_GYMS_COUNT', '47+'),
        'monthly_dues_processed' => env('REQUEST_DEMO_MONTHLY_DUES_PROCESSED', '₹42.3 Lakh+'),
    ],

    'testimonials' => [
        [
            'topic' => 'Fee Renewals & UPI',
            'initials' => 'VS',
            'avatar_color' => 'from-emerald-500/40 to-cyan-500/40',
            'name' => 'Vikram S.',
            'role' => 'Gym Owner & Managing Partner',
            'location' => 'Pune, Maharashtra',
            'rating' => 5,
            'metric_badge' => '+34% On-Time Renewals',
            'metric_icon' => 'trending_up',
            'badge_color' => 'emerald',
            'tag' => 'Commercial Strength Gym',
            'quote' => 'Earlier, reception staff felt awkward calling members for expired fees, so members would workout unpaid for 2–3 weeks. With automated WhatsApp reminders 3 days before expiry with a direct UPI QR, 85% of our members now pay on their phone before walking in.',
            'highlight' => '85% of our members now pay on their phone before walking in',
            'stat_1_title' => 'Gym Scale',
            'stat_1_value' => '420+ Active Members',
            'stat_1_sub' => 'Zero manual register follow-up',
            'stat_2_title' => 'Access Setup',
            'stat_2_value' => 'Biometric Fingerprint Sync',
            'stat_2_sub' => 'Instant real-time check-in sync',
        ],
        [
            'topic' => 'Studio & Class Bookings',
            'initials' => 'AD',
            'avatar_color' => 'from-cyan-500/40 to-[#cafd00]/40',
            'name' => 'Ananya D.',
            'role' => 'Fitness Studio & Crossfit Director',
            'location' => 'Bengaluru, Karnataka',
            'rating' => 5,
            'metric_badge' => 'Saved 18 Hours / Week',
            'metric_icon' => 'timer',
            'badge_color' => 'cyan',
            'tag' => 'Studio & Crossfit Model',
            'quote' => 'Managing 8 daily batch slots and calculating trainer session commissions was consuming my entire Sunday on Excel. Now members book their slots on their phone, capacity is capped automatically, and trainers see their verified commission breakdown with zero disputes.',
            'highlight' => 'trainers see their verified commission breakdown with zero disputes',
            'stat_1_title' => 'Class Scheduling',
            'stat_1_value' => '280 Active Members',
            'stat_1_sub' => 'Automated slot capacity caps',
            'stat_2_title' => 'Trainer Payouts',
            'stat_2_value' => 'Session Commission Logs',
            'stat_2_sub' => 'Zero commission disputes',
        ],
        [
            'topic' => 'Turnstile Gate Sync',
            'initials' => 'RR',
            'avatar_color' => 'from-[#cafd00]/40 to-emerald-500/40',
            'name' => 'Rajesh R.',
            'role' => 'Multi-Location Gym Franchise Owner',
            'location' => 'Delhi NCR',
            'rating' => 5,
            'metric_badge' => 'Zero Unpaid Gate Entries',
            'metric_icon' => 'lock',
            'badge_color' => 'lime',
            'tag' => 'Multi-Branch Franchise Model',
            'quote' => 'Managing multiple gym branches was stressful because unpaid walk-ins were rampant during peak morning rush. Syncing our tripod turnstiles with FluxFitness solved it instantly—the gate denies entry in 0.2 seconds if a membership is expired, and I see live collections across all branches on my phone.',
            'highlight' => 'the gate denies entry in 0.2 seconds if a membership is expired',
            'stat_1_title' => 'Multi-Branch Scale',
            'stat_1_value' => '1,100+ Total Members',
            'stat_1_sub' => 'Centralized owner cloud analytics',
            'stat_2_title' => 'Access Control',
            'stat_2_value' => 'Tripod Turnstile Gates',
            'stat_2_sub' => '0.2s facial & RFID pass sync',
        ],
        [
            'topic' => 'Data Privacy & Migration',
            'initials' => 'KM',
            'avatar_color' => 'from-emerald-500/40 to-cyan-500/40',
            'name' => 'Kavita M.',
            'role' => 'Fitness Center Director',
            'location' => 'Mumbai, Maharashtra',
            'rating' => 5,
            'metric_badge' => '100% Privacy & Zero Downtime',
            'metric_icon' => 'security',
            'badge_color' => 'emerald',
            'tag' => 'Strength & Conditioning Center',
            'quote' => 'My biggest hesitation in switching software was our 4-year-old spreadsheet with 600 active members having mixed expiry dates. The FluxFitness team cleaned and imported our entire ledger in one evening with zero gym downtime, and trainer contact-number masking protects our client data.',
            'highlight' => 'team cleaned and imported our entire ledger in one evening with zero gym downtime',
            'stat_1_title' => 'Data Security',
            'stat_1_value' => 'Masked Contact Records',
            'stat_1_sub' => 'Zero client poaching risk',
            'stat_2_title' => 'Migration Speed',
            'stat_2_value' => '24-Hour Excel Onboarding',
            'stat_2_sub' => 'Zero gym operations interruption',
        ],
    ],

    'faqs' => [
        [
            'question' => 'Do I need to buy new biometric machines or turnstiles?',
            'answer' => 'No. FluxFitness integrates directly with standard biometric devices you already own (eSSL, ZKTeco, Realtime, BioMax) and tripod turnstiles via local LAN or cloud sync without requiring expensive proprietary hardware.',
            'tag' => 'Hardware Compatibility',
        ],
        [
            'question' => 'How long does moving our member data from Excel take?',
            'answer' => 'Less than 24 hours. Simply share your existing member spreadsheet or database export. Our data team cleans, maps active memberships, remaining validity days, and past payment ledgers with 100% confidentiality and zero gym downtime.',
            'tag' => 'Free Data Migration',
        ],
        [
            'question' => 'What actually happens in the 20-minute 1-on-1 demo walkthrough?',
            'answer' => 'Our gym specialist will show you a live tailored walkthrough on your screen: how WhatsApp fee reminders send UPI QR links, how biometric gates deny expired members in 0.2s, and how the mobile owner cockpit tracks daily collections.',
            'tag' => 'Live Walkthrough',
        ],
        [
            'question' => 'Can front-desk staff or trainers steal my members\' phone numbers?',
            'answer' => 'No. FluxFitness enforces strict role-based data isolation with phone number masking. Desk staff and personal trainers cannot view or export full contact numbers, preventing private dealings and client poaching.',
            'tag' => 'Data Privacy & Security',
        ],
        [
            'question' => 'How do automated WhatsApp renewal reminders and UPI payments work?',
            'answer' => 'Polite automated alerts are sent to members 3 days and 1 day before expiration with a dynamic UPI QR code. When members scan and pay, their membership updates instantly and an auto-receipt is dispatched via WhatsApp.',
            'tag' => 'WhatsApp Automation',
        ],
        [
            'question' => 'Is there any commitment, credit card, or hidden fee after the demo?',
            'answer' => 'None at all. The 1-on-1 demo is 100% free with zero obligation. You also receive a 14-day full unrestricted trial to test the software on your gym floor with real members.',
            'tag' => 'Zero Risk Guarantee',
        ],
    ],
];
