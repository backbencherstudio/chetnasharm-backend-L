<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f7f7f8;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f7f8;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:480px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #ececef;">
                    <tr>
                        <td align="center" style="padding:28px 28px 8px;">
                            <img
                                src="{{ asset('assets/img/logo/logo.png') }}"
                                alt="{{ config('app.name') }}"
                                width="160"
                                style="display:block;border:0;outline:none;text-decoration:none;height:auto;max-width:160px;margin:0 auto;"
                            >
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 28px 28px;">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 28px 24px;border-top:1px solid #f0f0f2;text-align:center;">
                            <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.5;">
                                © {{ date('Y') }} {{ config('app.name') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
