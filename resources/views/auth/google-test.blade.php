
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FadaWebs | Create Account</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            background: #f5f7fb;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
            color: #172033;
        }

        .container {
            width: 100%;
            max-width: 1050px;
            min-height: 620px;
            background: #fff;
            border-radius: 22px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            box-shadow: 0 20px 70px rgba(25, 40, 75, .09);
        }

        .left {
            background: #102c25;
            color: white;
            padding: 55px 45px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .left::before {
            content: "";
            position: absolute;
            width: 330px;
            height: 330px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 50%;
            right: -150px;
            top: 120px;
        }

        .left::after {
            content: "";
            position: absolute;
            width: 230px;
            height: 230px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 50%;
            right: -100px;
            top: 170px;
        }

        .brand {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.7px;
            position: relative;
            z-index: 1;
        }

        .brand span {
            color: #8be0b2;
        }

        .hero {
            position: relative;
            z-index: 1;
            max-width: 390px;
        }

        .hero .tag {
            display: inline-block;
            color: #a6edc5;
            background: rgba(139,224,178,.12);
            padding: 9px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .hero h1 {
            font-size: clamp(34px, 4vw, 48px);
            line-height: 1.15;
            letter-spacing: -1.8px;
            margin-bottom: 20px;
        }

        .hero p {
            color: #c2d5ce;
            line-height: 1.8;
            font-size: 15px;
        }

        .left-footer {
            color: #a8c0b7;
            font-size: 12px;
            position: relative;
            z-index: 1;
        }

        .right {
            padding: 55px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .right h2 {
            font-size: 29px;
            letter-spacing: -1px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #7b8494;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 35px;
        }

        .google-btn {
            width: 100%;
            height: 55px;
            border: 1px solid #dce1e8;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: #253044;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s ease;
        }

        .google-btn:hover {
            border-color: #102c25;
            background: #f8fbf9;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16,44,37,.08);
        }

        .google-icon {
            width: 20px;
            height: 20px;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 28px 0;
            color: #a0a7b3;
            font-size: 12px;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e9ecf1;
        }

        .info {
            background: #f5f8f6;
            border: 1px solid #e3eee7;
            border-radius: 12px;
            padding: 17px;
            margin-bottom: 25px;
        }

        .info strong {
            display: block;
            font-size: 13px;
            margin-bottom: 7px;
            color: #214c38;
        }

        .info p {
            color: #718078;
            font-size: 12px;
            line-height: 1.7;
        }

        .error {
            background: #fff0f0;
            color: #b42318;
            padding: 13px;
            border-radius: 9px;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .success {
            background: #eaf8ef;
            color: #176b3a;
            padding: 13px;
            border-radius: 9px;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .terms {
            color: #929aa8;
            font-size: 11px;
            text-align: center;
            line-height: 1.8;
            margin-top: 25px;
        }

        .terms a {
            color: #214c38;
            text-decoration: none;
            font-weight: 700;
        }

        @media (max-width: 750px) {
            body {
                padding: 15px;
            }

            .container {
                grid-template-columns: 1fr;
                max-width: 470px;
            }

            .left {
                min-height: 220px;
                padding: 28px;
            }

            .hero {
                margin-top: 35px;
            }

            .hero h1 {
                font-size: 30px;
            }

            .hero p {
                font-size: 13px;
            }

            .left-footer {
                margin-top: 30px;
            }

            .right {
                padding: 38px 28px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <section class="left">
        <div class="brand">
            Fada<span>Webs.</span>
        </div>

        <div class="hero">
            <span class="tag">INVEST SMARTER. GROW BETTER.</span>

            <h1>Your financial future starts here.</h1>

            <p>
                Create your account and take your first step
                towards discovering investment opportunities
                with FadaWebs.
            </p>
        </div>

        <div class="left-footer">
            &copy; {{ date('Y') }} FadaWebs. All rights reserved.
        </div>
    </section>

    <section class="right">

        <h2>Create your account</h2>

        <p class="subtitle">
            Welcome to FadaWebs. Sign up securely using your
            Google account to get started.
        </p>

        @if(request()->query('error'))
            <div class="error">
                @switch(request()->query('error'))
                    @case('google_auth_failed')
                        Google authentication failed. Please try again.
                        @break
                    @case('google_email_not_verified')
                        Your Google email could not be verified.
                        @break
                    @case('account_inactive')
                        Your account is currently inactive.
                        @break
                    @default
                        Something went wrong. Please try again.
                @endswitch
            </div>
        @endif

        @if(request()->query('success'))
            <div class="success">
                Google authentication completed successfully.
            </div>
        @endif

        <a href="{{ url('/auth/google/redirect') }}" class="google-btn">

            <svg class="google-icon" viewBox="0 0 48 48">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.72 7.18l7.24 5.62c4.23-3.91 7.52-9.65 7.52-17.27z"/>
                <path fill="#FBBC05" d="M10.53 28.59a14.4 14.4 0 0 1 0-9.18l-7.98-6.19a24 24 0 0 0 0 21.56l7.98-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.9-5.81l-7.24-5.62c-2.01 1.35-4.6 2.15-8.66 2.15-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>

            Continue with Google
        </a>

        <div class="divider">SECURE GOOGLE SIGN-UP</div>

        <div class="info">
            <strong>Your account, your journey.</strong>
            <p>
                A new account starts as a regular user.
                No investor or administrator role is automatically
                assigned during registration.
            </p>
        </div>

        <p class="terms">
            By continuing, you agree to our
            <a href="#">Terms of Service</a> and
            <a href="#">Privacy Policy</a>.
            <br><br>
            Protected by Google authentication.
        </p>

    </section>

</div>

</body>
</html>
