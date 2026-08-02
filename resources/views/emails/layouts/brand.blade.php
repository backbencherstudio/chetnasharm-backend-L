<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f0ff;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f0ff;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #ebe4ff;">
                    <tr>
                        <td style="padding:22px 28px 18px;border-bottom:1px solid #f0ebff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="left">
                                        <img
                                            src="{{ asset('assets/img/logo/logo.webp') }}"
                                            alt="{{ config('app.name') }}"
                                            width="140"
                                            style="display:block;border:0;outline:none;height:auto;max-width:140px;"
                                        >
                                    </td>
                                    <td align="right" style="font-size:12px;color:#9b87c9;font-weight:600;">
                                        {{ $eyebrow ?? '' }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#111827;padding:18px 28px;text-align:center;">
                            <p style="margin:0 0 4px;font-size:12px;color:#ffffff;font-weight:600;">
                                {{ config('app.name') }}
                            </p>
                            <p style="margin:0;font-size:11px;color:#9ca3af;line-height:1.5;">
                                © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
