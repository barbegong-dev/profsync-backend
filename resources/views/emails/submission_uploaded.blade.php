<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Submission Uploaded - ProfSync</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc; padding: 40px 15px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 580px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #242364; padding: 36px 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.5px;">ProfSync</h1>
                            <p style="color: #cbd5e1; margin: 8px 0 0 0; font-size: 14px;">Faculty Document Tracking Management System</p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 36px 32px;">
                            <div style="background-color: #e0f2fe; border-left: 4px solid #0284c7; padding: 12px 16px; border-radius: 6px; margin-bottom: 24px;">
                                <strong style="color: #0369a1; font-size: 14px;">Notice: New Document Ready for Review</strong>
                            </div>

                            <h2 style="color: #0f172a; margin-top: 0; margin-bottom: 12px; font-size: 20px; font-weight: 600;">
                                Hello {{ $chair?->name ?? 'Program Chair' }},
                            </h2>
                            
                            <p style="color: #334155; font-size: 15px; margin-bottom: 18px;">
                                A new faculty document has been uploaded and is ready for your evaluation and review.
                            </p>

                            <!-- Submission Info Box -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin: 20px 0; padding: 16px 20px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; color: #64748b; width: 35%;">Faculty Member:</td>
                                    <td style="padding: 6px 0; font-size: 14px; color: #0f172a; font-weight: 600;">{{ $submission->faculty?->name ?? 'Faculty' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Faculty Email:</td>
                                    <td style="padding: 6px 0; font-size: 14px; color: #0f172a; font-weight: 600;">{{ $submission->faculty?->email ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Requirement:</td>
                                    <td style="padding: 6px 0; font-size: 14px; color: #0f172a; font-weight: 600;">{{ $submission->requirement?->name ?? 'Requirement' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Department:</td>
                                    <td style="padding: 6px 0; font-size: 14px; color: #0f172a; font-weight: 600;">{{ $submission->faculty?->department?->name ?? 'Department' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Uploaded At:</td>
                                    <td style="padding: 6px 0; font-size: 14px; color: #0f172a; font-weight: 600;">{{ now()->format('M d, Y h:i A') }}</td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <div style="text-align: center; margin: 32px 0;">
                                <a href="{{ $reviewUrl }}" style="background-color: #242364; color: #ffffff; padding: 14px 32px; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 15px; display: inline-block; box-shadow: 0 4px 12px rgba(36, 35, 100, 0.25);">
                                    Review Submissions
                                </a>
                            </div>

                            <p style="color: #64748b; font-size: 13px; text-align: center; margin: 0;">
                                Or copy and paste this link into your browser: <br/>
                                <a href="{{ $reviewUrl }}" style="color: #242364; word-break: break-all;">{{ $reviewUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f1f5f9; padding: 24px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b;">
                                &copy; {{ date('Y') }} ProfSync. All rights reserved.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                                Automated notification. Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
