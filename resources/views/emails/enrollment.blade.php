@extends('emails.layouts.brand', [
    'title' => 'Enrollment Confirmation',
    'eyebrow' => 'Enrollment',
])

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#111827;font-weight:700;">
        You’re <span style="color:#6d28d9;">enrolled</span>
    </h1>

    <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#4b5563;">
        Hello {{ $user->name }},
    </p>

    <p style="margin:0 0 22px;font-size:14px;line-height:1.6;color:#4b5563;">
        Your class enrollment is confirmed. Here are your details:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;background:#f8f5ff;border:1px solid #ebe4ff;border-radius:12px;">
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0 0 8px;font-size:13px;color:#4b5563;">
                    <strong style="color:#111827;">Class:</strong> {{ $class->title }}
                </p>
                <p style="margin:0 0 8px;font-size:13px;color:#4b5563;">
                    <strong style="color:#111827;">Batch:</strong> {{ $batch->name }}
                </p>
                <p style="margin:0 0 8px;font-size:13px;color:#4b5563;">
                    <strong style="color:#111827;">Start date:</strong> {{ $batch->start_date->format('d M Y') }}
                </p>
                <p style="margin:0;font-size:13px;color:#4b5563;">
                    <strong style="color:#111827;">End date:</strong> {{ optional($batch->end_date)->format('d M Y') }}
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 10px;font-size:14px;color:#111827;font-weight:700;">
        Class schedule
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;background:#fafafa;border:1px solid #f0f0f0;border-radius:12px;">
        <tr>
            <td style="padding:14px 18px;">
                @foreach($schedules as $schedule)
                    <p style="margin:{{ $loop->first ? '0' : '8px' }} 0 {{ $loop->last ? '0' : '0' }};font-size:13px;color:#374151;">
                        {{ $schedule['day'] }}
                        <span style="color:#9b87c9;">·</span>
                        {{ $schedule['start'] }} – {{ $schedule['end'] }}
                    </p>
                @endforeach
            </td>
        </tr>
    </table>

    @if(! empty($batch->zoom_link))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
            <tr>
                <td align="center">
                    <a href="{{ $batch->zoom_link }}"
                       style="display:inline-block;background:#6d28d9;color:#ffffff;padding:12px 28px;text-decoration:none;border-radius:999px;font-size:14px;font-weight:700;">
                        Join class
                    </a>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin:0;font-size:12px;line-height:1.6;color:#6b7280;">
        Please join on time using the class link. For the latest updates, always open your class from the student dashboard.
    </p>
@endsection
