<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>FadaWebs | Google Authentication</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #f5f7fb;
            color: #172033;
            font-family: Arial, sans-serif;
        }

        .container {
            width: 100%;
            max-width: 480px;
            padding: 40px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, .08);
        }

        h1 {
            margin-bottom: 12px;
            color: #102c25;
            font-size: 28px;
        }

        .subtitle {
            margin-bottom: 28px;
            color: #687386;
            line-height: 1.6;
        }

        .google-btn {
            display: block;
            width: 100%;
            padding: 16px;
            border: 1px solid #dce1e8;
            border-radius: 9px;
            background: #fff;
            color: #253044;
            font-size: 15px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
        }

        .google-btn:hover {
            background: #f5f8f6;
        }

        #result {
            display: none;
            margin-top: 22px;
            padding: 16px;
            border-radius: 9px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        #result.success {
            display: block;
            color: #176b3a;
            background: #eaf8ef;
        }

        #result.error {
            display: block;
            color: #b42318;
            background: #fff0f0;
        }

        #result.loading {
            display: block;
            color: #344054;
            background: #f2f4f7;
        }

        .note {
            margin-top: 25px;
            color: #8a94a6;
            font-size: 12px;
            line-height: 1.6;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Welcome to FadaWebs</h1>

    <p class="subtitle">
        Sign in securely with your Google account.
    </p>

    <a
        href="{{ url('/auth/google/redirect') }}"
        class="google-btn"
        id="google-login"
    >
        Continue with Google
    </a>

    <div id="result"></div>

    <p class="note">
        New Google registrations create a normal user account.
        No investor or administrator role is automatically assigned.
    </p>

</div>

<script>
    const resultBox = document.getElementById('result');

    function showResult(message, type) {
        resultBox.className = type;
        resultBox.textContent = message;
    }

    async function exchangeGoogleCode() {
        const params = new URLSearchParams(window.location.search);

        const code = params.get('code');
        const error = params.get('error');

        if (error) {
            const messages = {
                google_auth_failed: 'Google authentication failed. Please try again.',
                google_email_not_verified: 'Your Google email could not be verified.',
                account_inactive: 'Your account is currently inactive.'
            };

            showResult(
                messages[error] || 'Authentication failed.',
                'error'
            );

            return;
        }

        if (!code) {
            return;
        }

        // Remove the temporary code from the visible URL.
        window.history.replaceState(
            {},
            document.title,
            window.location.pathname
        );

        showResult('Completing your sign-in...', 'loading');

        try {
            const response = await fetch('/api/v1/auth/google/exchange', {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },

                body: JSON.stringify({
                    code: code
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message || 'Unable to complete Google sign-in.'
                );
            }

            // Local testing only: keep the token in this browser tab's
            // session storage. Your production frontend must handle tokens
            // according to its own authentication/security architecture.
            sessionStorage.setItem(
                'auth_token',
                data.data.token
            );

            const user = data.data.user;

            showResult(
                'Google authentication successful! Welcome, ' +
                user.first_name +
                '. Your Sanctum token has been issued.',
                'success'
            );

            document.getElementById('google-login').style.display = 'none';

        } catch (error) {
            showResult(error.message, 'error');
        }
    }

    exchangeGoogleCode();
</script>

</body>
</html>