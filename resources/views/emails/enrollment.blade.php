@extends('emails.layouts.brand', [
    'title' => 'Enrollment Confirmation',
])

@section('content')
    <h1 style="margin:0 0 16px;font-size:20px;line-height:1.4;color:#111827;font-weight:700;text-align:center;">
        Enrollment confirmed
    </h1>

    <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#6b7280;text-align:center;">
        Hello {{ $user->name }}, your class enrollment is confirmed.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:#f9fafb;border-radius:10px;">
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0 0 8px;font-size:13px;color:#6b7280;">
                    <strong style="color:#111827;">Class:</strong> {{ $class->title }}
                </p>
                <p style="margin:0 0 8px;font-size:13px;color:#6b7280;">
                    <strong style="color:#111827;">Batch:</strong> {{ $batch->name }}
                </p>
                <p style="margin:0 0 8px;font-size:13px;color:#6b7280;">
                    <strong style="color:#111827;">Start date:</strong> {{ $batch->start_date->format('d M Y') }}
                </p>
                <p style="margin:0;font-size:13px;color:#6b7280;">
                    <strong style="color:#111827;">End date:</strong> {{ optional($batch->end_date)->format('d M Y') }}
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px;font-size:13px;color:#111827;font-weight:700;">
        Schedule
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
        <tr>
            <td>
                @foreach($schedules as $schedule)
                    <p style="margin:0 0 6px;font-size:13px;color:#6b7280;">
                        {{ $schedule['day'] }} · {{ $schedule['start'] }} – {{ $schedule['end'] }}
                    </p>
                @endforeach
            </td>
        </tr>
    </table>

    @if(! empty($batch->zoom_link))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
            <tr>
                <td align="center">
                    <a href="{{ $batch->zoom_link }}"
                       style="display:inline-block;background:#6d28d9;color:#ffffff;padding:12px 24px;text-decoration:none;border-radius:8px;font-size:14px;font-weight:700;">
                        Join class
                    </a>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin:0;font-size:12px;line-height:1.6;color:#9ca3af;text-align:center;">
        Please join on time. For the latest link, use the student dashboard.
    </p>
@endsection
