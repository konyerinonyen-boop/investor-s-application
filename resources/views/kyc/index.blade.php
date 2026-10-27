
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity Verification | FadaWebs</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f4f6fa;
            color: #172033;
            font-family: Arial, sans-serif;
        }

        .page {
            width: min(760px, 94%);
            margin: 50px auto;
        }

        .brand {
            font-size: 23px;
            font-weight: 800;
            margin-bottom: 24px;
        }

        .card {
            background: white;
            padding: 30px;
            border: 1px solid #e4e8ef;
            border-radius: 16px;
            box-shadow: 0 10px 35px rgba(20, 30, 55, .05);
        }

        h1 { margin-top: 0; font-size: 27px; }
        p { line-height: 1.6; color: #667085; }

        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            font-size: 15px;
        }

        button {
            margin-top: 20px;
            padding: 13px 18px;
            width: 100%;
            background: #1746a2;
            color: white;
            border: 0;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
        }

        button:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .hidden { display: none; }

        .message {
            margin-top: 18px;
            padding: 12px;
            border-radius: 8px;
            background: #f0f4ff;
            color: #1746a2;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 24px 0;
        }

        .step {
            background: #f7f8fc;
            padding: 15px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.5;
        }

        #sumsub-websdk-container {
            margin-top: 24px;
            min-height: 100px;
        }

        @media (max-width: 520px) {
            .page { margin: 24px auto; }
            .card { padding: 20px; }
            .steps { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main class="page">
    <div class="brand">FadaWebs</div>

    <section class="card">
        <h1>Identity verification</h1>

        <p>
            Verify your identity securely to continue using your
            investor account. Your verification will be processed
            through our identity verification provider, Sumsub.
        </p>

        <div class="steps">
            <div class="step">
                <strong>01. Identity</strong>
                <br>Provide the requested identity document.
            </div>

            <div class="step">
                <strong>02. Selfie</strong>
                <br>Complete any required selfie or liveness check.
            </div>

            <div class="step">
                <strong>03. Review</strong>
                <br>Wait for your verification result.
            </div>
        </div>

        <div id="login-section">
            <h2>Sign in to continue</h2>

            <p>
                Sign in with your existing investor account.
                Your password is sent only to your Laravel login API.
            </p>

            <form id="login-form">
                <label for="email">Email address</label>
                <input
                    id="email"
                    type="email"
                    autocomplete="username"
                    required
                >

                <label for="password">Password</label>
                <input
                    id="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >

                <button id="login-button" type="submit">
                    Sign in
                </button>
            </form>
        </div>

        <div id="verification-section" class="hidden">
            <p id="account-message"></p>
            <p id="kyc-status"></p>

            <button id="start-button" type="button">
                Start identity verification
            </button>

            <button id="refresh-button" type="button">
                Refresh KYC status
            </button>

            <button id="logout-button" type="button">
                Sign out
            </button>
        </div>

        <div id="message" class="message hidden" role="status"
             aria-live="polite"></div>

        <div id="sumsub-websdk-container"></div>
    </section>
</main>

<script src="https://static.sumsub.com/idensic/static/sns-websdk-builder.js"></script>

<script>
(() => {
    'use strict';

    const loginSection = document.getElementById('login-section');
    const verificationSection = document.getElementById('verification-section');
    const loginForm = document.getElementById('login-form');
    const loginButton = document.getElementById('login-button');
    const startButton = document.getElementById('start-button');
    const refreshButton = document.getElementById('refresh-button');
    const logoutButton = document.getElementById('logout-button');
    const messageBox = document.getElementById('message');
    const statusBox = document.getElementById('kyc-status');
    const accountMessage = document.getElementById('account-message');
    const sdkContainer = document.getElementById('sumsub-websdk-container');

    // Keep the application's bearer token in memory only.
    // The token is not saved in localStorage or sessionStorage.
    let apiToken = null;
    let sdkInstance = null;

    function showMessage(message) {
        messageBox.textContent = message;
        messageBox.classList.remove('hidden');
    }

    function clearMessage() {
        messageBox.textContent = '';
        messageBox.classList.add('hidden');
    }

    async function apiRequest(url, options = {}) {
        const headers = new Headers(options.headers || {});

        headers.set('Accept', 'application/json');

        if (apiToken) {
            headers.set('Authorization', `Bearer ${apiToken}`);
        }

        if (options.body) {
            headers.set('Content-Type', 'application/json');
        }

        const response = await fetch(url, {
            ...options,
            headers,
            credentials: 'same-origin'
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 401) {
                apiToken = null;
                loginSection.classList.remove('hidden');
                verificationSection.classList.add('hidden');
            }

            if (response.status === 403) {
                throw new Error(
                    result.message ||
                    'Access denied. Your account may need email verification.'
                );
            }

            throw new Error(
                result.message || `Request failed (${response.status}).`
            );
        }

        return result;
    }

    async function refreshKycStatus() {
        const result = await apiRequest('/api/v1/kyc', {
            method: 'GET'
        });

        const data = result.data || {};
        const profile = data.profile || {};

        statusBox.textContent =
            `KYC status: ${data.kyc_status || profile.status || 'not started'}` +
            ` | Identity: ${profile.identity_verification_status || 'not started'}` +
            ` | Bank: ${profile.bank_verification_status || 'not started'}`;

        if (data.kyc_status === 'approved' || profile.status === 'approved') {
            startButton.disabled = true;
            startButton.textContent = 'Verification approved';
        } else {
            startButton.disabled = false;
            startButton.textContent = 'Start identity verification';
        }

        return data;
    }

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearMessage();

        loginButton.disabled = true;
        loginButton.textContent = 'Signing in...';

        try {
            const result = await apiRequest('/api/v1/login', {
                method: 'POST',
                body: JSON.stringify({
                    email: document.getElementById('email').value.trim(),
                    password: document.getElementById('password').value
                })
            });

            apiToken = result.data?.token;

            if (!apiToken) {
                throw new Error('The login response did not contain an API token.');
            }

            loginSection.classList.add('hidden');
            verificationSection.classList.remove('hidden');

            accountMessage.textContent =
                `Signed in as ${result.data?.user?.email || 'investor'}.`;

            await refreshKycStatus();

            showMessage(
                'Signed in successfully. You can now start identity verification.'
            );
        } catch (error) {
            showMessage(error.message);
        } finally {
            loginButton.disabled = false;
            loginButton.textContent = 'Sign in';
        }
    });

    async function requestSumsubToken() {
        const result = await apiRequest('/api/v1/kyc/sumsub/token', {
            method: 'POST'
        });

        const token = result.data?.token;

        if (!token) {
            throw new Error('Laravel did not return a Sumsub access token.');
        }

        return token;
    }

    startButton.addEventListener('click', async () => {
        clearMessage();
        startButton.disabled = true;
        sdkContainer.replaceChildren();

        try {
            if (!window.snsWebSdk) {
                throw new Error(
                    'Sumsub WebSDK could not load. Check your internet connection.'
                );
            }

            showMessage('Preparing your secure verification session...');

            const token = await requestSumsubToken();

            sdkInstance = window.snsWebSdk
                .init(token, requestSumsubToken)
                .withConf({
                    lang: 'en'
                })
                .withOptions({
                    addViewportTag: false,
                    adaptIframeHeight: true
                })
                .on('idCheck.onApplicantSubmitted', () => {
                    showMessage(
                        'Your verification details have been submitted. ' +
                        'Your result will be updated after Sumsub reviews them.'
                    );
                })
                .on('idCheck.onError', (error) => {
                    console.error('Sumsub WebSDK error:', error);

                    showMessage(
                        'The verification interface encountered an error. ' +
                        'Please try again. If it continues, check the Laravel logs.'
                    );
                })
                .build();

            sdkInstance.launch('#sumsub-websdk-container');

            showMessage('Verification interface opened.');
        } catch (error) {
            showMessage(error.message);
        } finally {
            startButton.disabled = false;
        }
    });

    refreshButton.addEventListener('click', async () => {
        clearMessage();

        try {
            await refreshKycStatus();
            showMessage('KYC status refreshed.');
        } catch (error) {
            showMessage(error.message);
        }
    });

    logoutButton.addEventListener('click', async () => {
        try {
            if (apiToken) {
                await apiRequest('/api/v1/logout', {
                    method: 'POST'
                });
            }
        } catch (error) {
            console.warn('API logout did not complete:', error.message);
        } finally {
            apiToken = null;
            sdkInstance = null;
            sdkContainer.replaceChildren();
            verificationSection.classList.add('hidden');
            loginSection.classList.remove('hidden');
            statusBox.textContent = '';
            accountMessage.textContent = '';
            document.getElementById('password').value = '';
            clearMessage();
        }
    });
})();
</script>
</body>
</html>
