@extends('emails.layouts.brand', [
    'title' => 'Password Reset OTP',
    'eyebrow' => 'Password reset',
])

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#111827;font-weight:700;">
        Reset your <span style="color:#6d28d9;">password</span>
    </h1>

    <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#4b5563;">
        Hello,
    </p>

    <p style="margin:0 0 22px;font-size:14px;line-height:1.6;color:#4b5563;">
        Use this one-time password (OTP) to reset your account password:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
        <tr>
            <td align="center" style="background:#f5f0ff;border:1px solid #e9ddff;border-radius:12px;padding:18px;">
                <span style="display:inline-block;font-size:28px;letter-spacing:6px;font-weight:700;color:#6d28d9;">
                    {{ $otp }}
                </span>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#6b7280;">
        This OTP expires in <strong style="color:#111827;">3 minutes</strong>.
    </p>

    <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
        If you didn’t request this, you can safely ignore this email.
    </p>
@endsection
