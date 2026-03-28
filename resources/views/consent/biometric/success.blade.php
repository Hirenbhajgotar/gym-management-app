<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consent Recorded — {{ $gym->name }}</title>
    <style>
        body { font-family: sans-serif; max-width: 640px; margin: 40px auto;
               padding: 20px; text-align: center; color: #111; }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 22px; }
        p { color: #555; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="icon">✓</div>
    <h1>Consent Recorded</h1>
    <p>
        Thank you, <strong>{{ $gymMember->name }}</strong>.<br>
        Your biometric consent has been recorded for {{ $gym->name }}.
    </p>
    <p>
        You can now proceed with biometric enrollment at the gym.<br>
        To withdraw consent at any time, please contact the reception.
    </p>
</body>
</html>
