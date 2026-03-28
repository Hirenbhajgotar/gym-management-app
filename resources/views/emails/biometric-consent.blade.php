<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">

    <h2>{{ $gym->name }}</h2>
    <p>Dear {{ $gymMember->name }},</p>

    <p>
        {{ $gym->name }} uses biometric technology for gym check-in and door access.
        Before we can enroll your biometric data, we need your consent.
    </p>

    <p>
        Please click the button below to review and give your consent.
        This link is valid for {{ $expiresIn }}.
    </p>

    <a href="{{ $consentUrl }}"
       style="background: #1a1a1a; color: white; padding: 12px 24px;
              text-decoration: none; border-radius: 6px; display: inline-block;">
        Review and Give Consent
    </a>

    <p style="margin-top: 24px; color: #666; font-size: 14px;">
        If you did not join {{ $gym->name }}, please ignore this email.
        <br>
        Questions? Contact us at {{ $gym->email ?? 'the gym reception' }}.
    </p>

</body>
</html>
