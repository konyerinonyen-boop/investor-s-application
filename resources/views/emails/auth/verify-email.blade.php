<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verify Your Email Address</title>

    <style>
        /* ----------------------------------------
           Base
        ---------------------------------------- */

        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            background-color: #f4f7fb;
            font-family: Arial, Helvetica, sans-serif;
            color: #172033;
            -webkit-font-smoothing: antialiased;
        }

        table {
            border-spacing: 0;
            border-collapse: collapse;
        }

        img {
            border: 0;
            outline: none;
            text-decoration: none;
            max-width: 100%;
        }

        a {
            text-decoration: none;
        }

        /* ----------------------------------------
           Main wrapper
        ---------------------------------------- */

        .email-wrapper {
            width: 100%;
            padding: 48px 20px;
            background:
                linear-gradient(
                    180deg,
                    #eef3f9 0%,
                    #f7f9fc 45%,
                    #f4f7fb 100%
                );
        }

        .email-container {
            width: 100%;
            max-width: 620px;
            margin: 0 auto;
        }

        /* ----------------------------------------
           Brand header
        ---------------------------------------- */

        .brand {
            text-align: center;
            padding-bottom: 28px;
        }

        .brand-mark {
            display: inline-block;
            width: 46px;
            height: 46px;
            line-height: 46px;
            border-radius: 12px;
            background-color: #111827;
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            vertical-align: middle;
        }

        .brand-name {
            display: inline-block;
            margin-left: 10px;
            vertical-align: middle;
            color: #111827;
            font-size: 21px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        /* ----------------------------------------
           Main card
        ---------------------------------------- */

        .card {
            background-color: #ffffff;
            border: 1px solid #e7ebf1;
            border-radius: 18px;
            overflow: hidden;
            box-shadow:
                0 18px 50px rgba(17, 24, 39, 0.08);
        }

        .card-top {
            height: 6px;
            background-color: #111827;
        }

        .card-content {
            padding: 48px 50px 42px;
        }

        /* ----------------------------------------
           Verification icon
        ---------------------------------------- */

        .icon-wrapper {
            margin-bottom: 26px;
        }

        .icon {
            width: 62px;
            height: 62px;
            line-height: 62px;
            display: inline-block;
            border-radius: 16px;
            background-color: #f0f4f8;
            color: #111827;
            font-size: 27px;
            font-weight: 700;
            text-align: center;
        }

        /* ----------------------------------------
           Typography
        ---------------------------------------- */

        .eyebrow {
            margin: 0 0 10px;
            color: #667085;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 18px;
            color: #111827;
            font-size: 31px;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: -0.7px;
        }

        .intro {
            margin: 0 0 18px;
            color: #4b5563;
            font-size: 16px;
            line-height: 1.75;
        }

        .intro strong {
            color: #1f2937;
        }

        /* ----------------------------------------
           CTA
        ---------------------------------------- */

        .button-wrapper {
            padding: 12px 0 8px;
        }

        .button {
            display: inline-block;
            padding: 15px 27px;
            background-color: #111827;
            color: #ffffff !important;
            border-radius: 9px;
            font-size: 15px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 0.1px;
        }

        .button-arrow {
            margin-left: 8px;
            font-size: 17px;
        }

        /* ----------------------------------------
           Expiry notice
        ---------------------------------------- */

        .notice {
            margin-top: 28px;
            padding: 17px 18px;
            background-color: #f7f8fa;
            border: 1px solid #eaedf1;
            border-radius: 10px;
        }

        .notice-title {
            margin: 0 0 5px;
            color: #374151;
            font-size: 13px;
            font-weight: 700;
        }

        .notice-text {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.6;
        }

        /* ----------------------------------------
           Fallback URL
        ---------------------------------------- */

        .fallback {
            margin-top: 28px;
        }

        .fallback-label {
            margin: 0 0 8px;
            color: #6b7280;
            font-size: 12px;
            font-weight: 600;
        }

        .url-box {
            padding: 13px 15px;
            background-color: #fafbfc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            color: #667085;
            font-size: 11px;
            line-height: 1.6;
            word-break: break-all;
        }

        /* ----------------------------------------
           Security section
        ---------------------------------------- */

        .security {
            margin-top: 34px;
            padding-top: 25px;
            border-top: 1px solid #edf0f3;
        }

        .security-title {
            margin: 0 0 7px;
            color: #374151;
            font-size: 13px;
            font-weight: 700;
        }

        .security-text {
            margin: 0;
            color: #7b8492;
            font-size: 12px;
            line-height: 1.65;
        }

        /* ----------------------------------------
           Footer
        ---------------------------------------- */

        .footer {
            padding: 27px 20px 0;
            text-align: center;
        }

        .footer-brand {
            margin: 0 0 8px;
            color: #4b5563;
            font-size: 13px;
            font-weight: 700;
        }

        .footer-text {
            margin: 0;
            color: #98a2b3;
            font-size: 11px;
            line-height: 1.7;
        }

        /* ----------------------------------------
           Mobile
        ---------------------------------------- */

        @media only screen and (max-width: 600px) {

            .email-wrapper {
                padding: 25px 12px;
            }

            .card-content {
                padding: 35px 25px 30px;
            }

            h1 {
                font-size: 26px;
            }

            .brand {
                padding-bottom: 22px;
            }

            .button {
                display: block;
                text-align: center;
            }

        }
    </style>
</head>

<body>

<table
    role="presentation"
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    class="email-wrapper"
>
    <tr>
        <td align="center">

            <div class="email-container">

                <!-- =========================================
                     BRAND
                ========================================== -->

                <div class="brand">

                    <span class="brand-mark">
                        {{ strtoupper(substr(config('app.name'), 0, 1)) }}
                    </span>

                    <span class="brand-name">
                        {{ config('app.name') }}
                    </span>

                </div>


                <!-- =========================================
                     CARD
                ========================================== -->

                <table
                    role="presentation"
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    class="card"
                >

                    <tr>
                        <td class="card-top"></td>
                    </tr>

                    <tr>
                        <td class="card-content">


                            <!-- Icon -->

                            <div class="icon-wrapper">

                                <span class="icon">
                                    ✓
                                </span>

                            </div>


                            <!-- Heading -->

                            <p class="eyebrow">
                                Account verification
                            </p>

                            <h1>
                                Verify your email address
                            </h1>


                            <!-- Greeting -->

                            <p class="intro">
                                Hello <strong>{{ $user->name }}</strong>,
                            </p>


                            <!-- Introduction -->

                            <p class="intro">
                                Welcome to
                                <strong>{{ config('app.name') }}</strong>.
                                We're excited to have you with us.
                            </p>

                            <p class="intro">
                                To complete your account setup and keep
                                your account secure, please confirm that
                                this email address belongs to you.
                            </p>


                            <!-- CTA -->

                            <div class="button-wrapper">

                                <a
                                    href="{{ $url }}"
                                    class="button"
                                >
                                    Verify Email Address
                                    <span class="button-arrow">
                                        →
                                    </span>
                                </a>

                            </div>


                            <!-- Expiration -->

                            <div class="notice">

                                <p class="notice-title">
                                    Verification link expiration
                                </p>

                                <p class="notice-text">
                                    For your security, this verification
                                    link will expire in
                                    <strong>
                                        {{ $expiresIn }} minutes
                                    </strong>.
                                </p>

                            </div>


                            <!-- Fallback URL -->

                            <div class="fallback">

                                <p class="fallback-label">
                                    Having trouble with the button?
                                </p>

                                <div class="url-box">
                                    {{ $url }}
                                </div>

                            </div>


                            <!-- Security -->

                            <div class="security">

                                <p class="security-title">
                                    Security notice
                                </p>

                                <p class="security-text">
                                    If you did not create an account with
                                    {{ config('app.name') }}, you can safely
                                    ignore this email. No action is required
                                    from you.
                                </p>

                            </div>

                        </td>
                    </tr>

                </table>


                <!-- =========================================
                     FOOTER
                ========================================== -->

                <div class="footer">

                    <p class="footer-brand">
                        {{ config('app.name') }}
                    </p>

                    <p class="footer-text">
                        This is an automated security email.
                        Please do not reply directly to this message.
                    </p>

                    <p class="footer-text">
                        &copy; {{ date('Y') }}
                        {{ config('app.name') }}.
                        All rights reserved.
                    </p>

                </div>

            </div>

        </td>
    </tr>
</table>

</body>

</html>