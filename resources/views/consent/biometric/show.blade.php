<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Consent — {{ $gym->name }}</title>
    <style>
        body { font-family: sans-serif; max-width: 640px; margin: 40px auto; padding: 20px; color: #111; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .gym-name { color: #666; font-size: 15px; margin-bottom: 32px; }
        .section { margin-bottom: 24px; }
        .section h2 { font-size: 16px; margin-bottom: 8px; }
        .section ul { padding-left: 20px; color: #444; line-height: 1.8; }
        .consent-box {
            border: 1px solid #ddd; border-radius: 8px;
            padding: 20px; margin: 32px 0; background: #f9f9f9;
        }
        .consent-box label { display: flex; align-items: flex-start; gap: 12px; cursor: pointer; }
        .consent-box input[type="checkbox"] { margin-top: 3px; width: 18px; height: 18px; flex-shrink: 0; }
        button {
            background: #1a1a1a; color: white; border: none;
            padding: 12px 28px; border-radius: 6px; font-size: 16px;
            cursor: pointer; width: 100%;
        }
        button:disabled { background: #aaa; cursor: not-allowed; }
        .member-name { font-weight: 500; }
        footer { margin-top: 40px; color: #999; font-size: 13px; }
    </style>
</head>
<body>

    <h1>Biometric Data Consent</h1>
    <p class="gym-name">{{ $gym->name }}</p>

    <p>Dear <span class="member-name">{{ $gymMember->name }}</span>,</p>

    <div class="section">
        <h2>Why we collect biometric data</h2>
        <ul>
            <li>Gym check-in — fast and contactless</li>
            <li>Door access control — secure entry</li>
        </ul>
    </div>

    <div class="section">
        <h2>What we collect</h2>
        <ul>
            <li>Face scan and/or fingerprint</li>
            <li>Check-in timestamps</li>
        </ul>
    </div>

    <div class="section">
        <h2>How we protect your data</h2>
        <ul>
            <li>All biometric data is encrypted at rest</li>
            <li>Data is never sold to third parties</li>
            <li>Only used for gym access purposes</li>
        </ul>
    </div>

    <div class="section">
        <h2>Your rights</h2>
        <ul>
            <li>Withdraw consent at any time — contact reception</li>
            <li>Request deletion of all biometric data</li>
            <li>Opt out without affecting your gym membership</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('biometric.consent.store', request()->query()) }}">
        @csrf

        <input type="hidden" name="gym_member_id" value="{{ $gymMember->id }}">
        <input type="hidden" name="gym_id" value="{{ $gymMember->gym_id }}">

        <div class="consent-box">
            <label>
                <input
                    type="checkbox"
                    name="consented"
                    id="consent-checkbox"
                    value="1"
                    onchange="document.getElementById('submit-btn').disabled = !this.checked"
                >
                <span>
                    I have read and understood the above information.
                    I consent to {{ $gym->name }} collecting and processing
                    my biometric data for gym access purposes.
                    I understand I can withdraw this consent at any time.
                </span>
            </label>
        </div>

        @error('consented')
            <p style="color: red; margin-bottom: 12px;">{{ $message }}</p>
        @enderror

        <button type="submit" id="submit-btn" disabled>
            Give Consent
        </button>
    </form>

    <footer>
        Consent version {{ $consentVersion }} &middot;
        {{ $gym->name }} &middot;
        {{ now()->format('Y') }}
    </footer>

</body>
</html>
