<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Enrollment Confirmation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="margin:0; padding:0; background-color:#f4f6f8; font-family: Arial, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="padding:20px 10px;">
    <tr>
        <td align="center">

            <table cellpadding="0" cellspacing="0"
                   style="background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.06); max-width:460px; width:100%;">

                <!-- Header -->
                <tr>
                    <td style="background:#2563eb; padding:22px; text-align:center;">
                        <h1 style="color:#ffffff; margin:0; font-size:20px;">Listenact</h1>
                        <p style="color:#dbeafe; margin:6px 0 0; font-size:13px;">
                            Enrollment Confirmation
                        </p>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:22px;">

                        <p style="font-size:14px; color:#374151; margin:0 0 10px;">
                            Hello {{ $user->name }},
                        </p>

                        <p style="font-size:14px; color:#374151; margin:0 0 18px;">
                            You have successfully enrolled in your class. Please find your class details below.
                        </p>

                        <!-- Class Info Box -->
                        <div style="background:#f1f5f9; padding:16px; border-radius:8px; margin-bottom:18px;">

                            <p style="margin:0 0 6px; font-size:13px;">
                                <strong>Class:</strong> {{ $class->title }}
                            </p>

                            <p style="margin:0 0 6px; font-size:13px;">
                                <strong>Batch:</strong> {{ $batch->name }}
                            </p>

                            <p style="margin:0 0 6px; font-size:13px;">
                                <strong>Start Date:</strong> {{ $batch->start_date->format('d M Y') }}
                            </p>

                            <p style="margin:0; font-size:13px;">
                                <strong>End Date:</strong> {{ optional($batch->end_date)->format('d M Y') }}
                            </p>

                        </div>

                        <!-- Schedule -->
                        <p style="font-size:14px; color:#374151; margin:0 0 10px;">
                            <strong>Class Schedule</strong>
                        </p>

                        <div style="background:#f9fafb; border-radius:8px; padding:12px; margin-bottom:20px;">
                            @foreach($schedules as $schedule)
                                <p style="margin:5px 0; font-size:13px; color:#111827;">
                                    {{ $schedule['day'] }} |
                                    {{ $schedule['start'] }} - {{ $schedule['end'] }}
                                </p>
                            @endforeach
                        </div>

                        <!-- Button -->
                        <div style="text-align:center; margin:22px 0;">
                            <a href="{{ $batch->zoom_link }}"
                               style="background:#2563eb; color:#ffffff; padding:12px 20px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold; display:inline-block;">
                                Join Class
                            </a>
                        </div>

                        <!-- Note -->
                        <p style="font-size:12px; color:#6b7280; margin:0; line-height:1.6;">
                            Please join on time using the official class link above.
                            The link may change if updated by the instructor.
                            Always access your class from the student dashboard to ensure you have the latest updates.
                        </p>

                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background:#f9fafb; padding:16px; text-align:center;">
                        <p style="font-size:11px; color:#6b7280; margin:0;">
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
