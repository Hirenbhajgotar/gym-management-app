<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Already Consented — {{ $gym->name }}</title>
    <style>
        body { font-family: sans-serif; max-width: 640px; margin: 40px auto;
               padding: 20px; text-align: center; color: #111; }
        h1 { font-size: 22px; }
        p { color: #555; }
    </style>
</head>
<body>
    <h1>Consent Already Recorded</h1>
    <p>
        <strong>{{ $gymMember->name }}</strong>, your biometric consent
        for {{ $gym->name }} has already been recorded.
    </p>
    <p>
        To withdraw consent, please contact the gym reception.
    </p>
</body>
</html>
