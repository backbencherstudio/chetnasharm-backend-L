@extends('emails.layouts.brand', [
    'title' => 'Password Reset OTP',
])

@section('content')
    <h1 style="margin:0 0 16px;font-size:20px;line-height:1.4;color:#111827;font-weight:700;text-align:center;">
        Password reset code
    </h1>

    <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#6b7280;text-align:center;">
        Use this OTP to reset your password. It expires in <strong style="color:#111827;">3 minutes</strong>.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
        <tr>
            <td align="center" style="background:#f5f3ff;border-radius:10px;padding:16px;">
                <span style="display:inline-block;font-size:28px;letter-spacing:8px;font-weight:700;color:#6d28d9;">
                    {{ $otp }}
                </span>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;line-height:1.6;color:#9ca3af;text-align:center;">
        If you didn’t request this, you can ignore this email.
    </p>
@endsection
