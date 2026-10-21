<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ready for Clearance - ProfSync</title>
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
                            <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 14px 18px; border-radius: 6px; margin-bottom: 24px;">
                                <strong style="color: #065f46; font-size: 15px; display: block;">&#10004; TAGGED: READY FOR CLEARANCE</strong>
                                <span style="color: #047857; font-size: 13px;">All semester requirements have been completed and verified.</span>
                            </div>

                            <h2 style="color: #0f172a; margin-top: 0; margin-bottom: 12px; font-size: 22px; font-weight: 700;">
                                Congratulations, {{ $faculty->name }}!
                            </h2>
                            
                            <p style="color: #334155; font-size: 15px; margin-bottom: 18px;">
                                We are pleased to inform you that <strong>all required document submissions</strong> for <strong>{{ $semester?->name ?? 'the current semester' }}</strong> have been reviewed and approved by your Program Chair.
                            </p>

                            <p style="color: #334155; font-size: 15px; margin-bottom: 20px;">
                                Your clearance status is now officially tagged as <strong style="color: #10b981;">Ready for Clearance</strong>.
                            </p>

                            <!-- Summary Info Box -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin: 24px 0; padding: 16px 20px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; color: #64748b; width: 40%;">Faculty Member:</td>
                                    <td style="padding: 6px 0; font-size: 14px; color: #0f172a; font-weight: 600;">{{ $faculty->name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Department:</td>
                                    <td style="padding: 6px 0; font-size: 14px; color: #0f172a; font-weight: 600;">{{ $faculty->department?->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Academic Semester:</td>
                                    <td style="padding: 6px 0; font-size: 14px; color: #0f172a; font-weight: 600;">{{ $semester?->name ?? 'Active Semester' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Approved Requirements:</td>
                                    <td style="padding: 6px 0; font-size: 14px; color: #10b981; font-weight: 700;">{{ $totalCount }} of {{ $totalCount }} (100% Completed)</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Clearance Status:</td>
                                    <td style="padding: 6px 0; font-size: 14px; color: #10b981; font-weight: 700;">READY FOR CLEARANCE</td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <div style="text-align: center; margin: 32px 0;">
                                <a href="{{ $dashboardUrl }}" style="background-color: #10b981; color: #ffffff; padding: 14px 34px; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 15px; display: inline-block; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                                    View Clearance Dashboard
                                </a>
                            </div>

                            <p style="color: #64748b; font-size: 13px; text-align: center; margin: 0;">
                                You can access your document checklist at any time: <br/>
                                <a href="{{ $dashboardUrl }}" style="color: #242364; word-break: break-all;">{{ $dashboardUrl }}</a>
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
