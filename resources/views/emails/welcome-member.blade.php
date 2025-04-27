@component('mail::message')
# Hello {{ $memberName }}!

Welcome to {{ $gymName }}! We're excited to have you as a member.

You can now access our facilities and services.

**Membership Details:**
- Plan: {{ $planName }}
- Start Date: {{ $startDate }}
- End Date: {{ $endDate }}

Thank you for choosing us!

@endcomponent