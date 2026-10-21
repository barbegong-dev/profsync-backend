<?php

namespace App\Support;

use App\Mail\AccountNotificationMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails that tell people what is happening with their account or workspace.
 * Every send is logged, and a failed send never breaks the request that triggered it.
 */
class AccountEmails
{
    private const ROLE_LABELS = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'dean' => 'Dean',
        'program_chair' => 'Program Chair',
        'faculty' => 'Faculty',
    ];

    /** New Google sign-up: confirm to the user that their request is waiting for approval. */
    public static function signupReceived(User $user): void
    {
        $tenant = $user->tenant;

        self::send($user->email, 'sign-up received', new AccountNotificationMail(
            subjectLine: 'Sign-up received – waiting for approval',
            status: 'Status: Pending approval',
            recipientName: $user->name,
            paragraphs: [
                'Thanks for signing up to <strong>' . e($tenant->name) . '</strong> on ProfSync with your Google account.',
                'An administrator will review your request and assign your role and department. We will email you as soon as your account is approved. Until then you will not be able to sign in.',
            ],
            details: ['Name' => $user->name, 'Google account' => $user->email, 'Workspace' => $tenant->name],
            tone: 'warning',
            workspaceName: $tenant->name,
            footnote: 'If you did not sign up for ProfSync, you can ignore this email.',
        ));
    }

    /** New Google sign-up: tell every admin of the workspace someone is waiting. */
    public static function newSignupForAdmins(User $user): void
    {
        $tenant = $user->tenant;
        $admins = User::role('admin')->where('tenant_id', $tenant->id)->get();

        foreach ($admins as $admin) {
            self::send($admin->email, 'new sign-up alert', new AccountNotificationMail(
                subjectLine: "New sign-up waiting for approval – {$user->name}",
                status: 'Action needed: approve or reject',
                recipientName: $admin->name,
                paragraphs: [
                    '<strong>' . e($user->name) . '</strong> signed up to <strong>' . e($tenant->name) . '</strong> with Google and is waiting for your approval.',
                    'Open <strong>Members</strong>, choose their role and department, then approve, or reject the request.',
                ],
                details: ['Name' => $user->name, 'Google account' => $user->email],
                actionText: 'Review sign-up',
                actionUrl: self::url('/admin/users'),
                tone: 'info',
                workspaceName: $tenant->name,
            ));
        }
    }

    /** Admin added a member by email: invite them to sign in with Google. */
    public static function memberAdded(User $user): void
    {
        $user->loadMissing('roles', 'department', 'tenant');
        $tenant = $user->tenant;

        self::send($user->email, 'member invitation', new AccountNotificationMail(
            subjectLine: "You've been added to {$tenant->name}",
            status: 'Your account is ready',
            recipientName: $user->name,
            paragraphs: [
                'An administrator added you to <strong>' . e($tenant->name) . '</strong> on ProfSync, the faculty document tracking system.',
                'Sign in with the <strong>Google account for this email address</strong>. No password needed.',
            ],
            details: self::accountDetails($user),
            actionText: 'Sign in with Google',
            actionUrl: self::signInUrl($tenant),
            tone: 'success',
            workspaceName: $tenant->name,
        ));
    }

    /** Pending sign-up approved. */
    public static function approved(User $user): void
    {
        $user->loadMissing('roles', 'department', 'tenant');
        $tenant = $user->tenant;

        self::send($user->email, 'account approved', new AccountNotificationMail(
            subjectLine: 'Your account has been approved',
            status: 'Status: Approved & active',
            recipientName: $user->name,
            paragraphs: [
                'Good news! Your sign-up to <strong>' . e($tenant->name) . '</strong> has been <strong style="color:#10b981;">approved</strong>. You can now sign in with Google.',
            ],
            details: self::accountDetails($user),
            actionText: 'Sign in to ProfSync',
            actionUrl: self::signInUrl($tenant),
            tone: 'success',
            workspaceName: $tenant->name,
        ));
    }

    /** Admin changed the member's role and/or department. */
    public static function accountUpdated(User $user, array $changes): void
    {
        $user->loadMissing('tenant');
        $tenant = $user->tenant;

        self::send($user->email, 'account updated', new AccountNotificationMail(
            subjectLine: 'Your role or department was updated',
            status: 'Account updated',
            recipientName: $user->name,
            paragraphs: [
                'An administrator of <strong>' . e($tenant->name) . '</strong> updated your account. The changes apply the next time you open ProfSync.',
            ],
            details: $changes,
            actionText: 'Open ProfSync',
            actionUrl: self::signInUrl($tenant),
            tone: 'info',
            workspaceName: $tenant->name,
        ));
    }

    /** Pending sign-up rejected, or an active member removed. Called before the user is deleted. */
    public static function removed(User $user, bool $wasPending): void
    {
        $tenant = $user->tenant;

        self::send($user->email, $wasPending ? 'sign-up rejected' : 'access removed', new AccountNotificationMail(
            subjectLine: $wasPending ? 'Your sign-up request was not approved' : "Your access to {$tenant->name} was removed",
            status: $wasPending ? 'Status: Sign-up not approved' : 'Status: Access removed',
            recipientName: $user->name,
            paragraphs: [
                $wasPending
                    ? 'Your request to join <strong>' . e($tenant->name) . '</strong> on ProfSync was not approved by the administrator.'
                    : 'An administrator removed your account from <strong>' . e($tenant->name) . '</strong> on ProfSync. You can no longer sign in to this workspace.',
                'If you think this is a mistake, please contact your department or the workspace administrator.',
            ],
            tone: 'danger',
            workspaceName: $tenant->name,
        ));
    }

    /** Super admin created a workspace: welcome its admin. The password is shared separately. */
    public static function tenantCreated(Tenant $tenant, User $admin): void
    {
        self::send($admin->email, 'workspace created', new AccountNotificationMail(
            subjectLine: "Your workspace {$tenant->name} is ready",
            status: 'Workspace created',
            recipientName: $admin->name,
            paragraphs: [
                'A ProfSync workspace has been created for <strong>' . e($tenant->name) . '</strong>, and you are its administrator.',
                'To sign in: open the link below, choose <strong>Administrator sign in with email</strong>, and use this email address with the temporary password the ProfSync team gave you. Please change it under <strong>Profile</strong> after your first sign-in.',
                'After signing in, the <strong>Setup Guide</strong> walks you through adding departments, the semester, requirements and your members.',
            ],
            details: ['Workspace name' => $tenant->slug, 'Admin email' => $admin->email],
            actionText: 'Open your workspace',
            actionUrl: self::signInUrl($tenant),
            tone: 'success',
            workspaceName: $tenant->name,
        ));
    }

    /** Super admin suspended or reactivated a workspace: tell its admins. */
    public static function tenantStatusChanged(Tenant $tenant): void
    {
        $suspended = $tenant->status === 'suspended';
        $admins = User::role('admin')->where('tenant_id', $tenant->id)->get();

        foreach ($admins as $admin) {
            self::send($admin->email, $suspended ? 'workspace suspended' : 'workspace reactivated', new AccountNotificationMail(
                subjectLine: $suspended ? "Workspace {$tenant->name} has been suspended" : "Workspace {$tenant->name} is active again",
                status: $suspended ? 'Workspace suspended' : 'Workspace reactivated',
                recipientName: $admin->name,
                paragraphs: [
                    $suspended
                        ? 'The ProfSync workspace <strong>' . e($tenant->name) . '</strong> has been suspended. Nobody in this workspace can sign in until it is reactivated. Your data is kept.'
                        : 'The ProfSync workspace <strong>' . e($tenant->name) . '</strong> has been reactivated. Everyone can sign in again.',
                    'Questions? Contact the ProfSync administrator.',
                ],
                actionText: $suspended ? null : 'Open your workspace',
                actionUrl: $suspended ? null : self::signInUrl($tenant),
                tone: $suspended ? 'danger' : 'success',
                workspaceName: $tenant->name,
            ));
        }
    }

    /** Security notice after a password change from the Profile page. */
    public static function passwordChanged(User $user): void
    {
        self::send($user->email, 'password changed', new AccountNotificationMail(
            subjectLine: 'Your password was changed',
            status: 'Security notice',
            recipientName: $user->name,
            paragraphs: [
                'The password for your ProfSync account <strong>' . e($user->email) . '</strong> was just changed.',
                'If you did not make this change, contact your administrator right away.',
            ],
            tone: 'warning',
            workspaceName: $user->tenant?->name,
        ));
    }

    /** Security notice to the old address after an email change from the Profile page. */
    public static function emailChanged(User $user, string $oldEmail): void
    {
        self::send($oldEmail, 'email changed', new AccountNotificationMail(
            subjectLine: 'Your account email was changed',
            status: 'Security notice',
            recipientName: $user->name,
            paragraphs: [
                'The email address of your ProfSync account was changed from <strong>' . e($oldEmail) . '</strong> to <strong>' . e($user->email) . '</strong>.',
                'If you did not make this change, contact your administrator right away.',
            ],
            tone: 'warning',
            workspaceName: $user->tenant?->name,
        ));
    }

    public static function roleLabel(?string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ucwords(str_replace('_', ' ', (string) $role));
    }

    public static function signInUrl(?Tenant $tenant): string
    {
        return self::url('/login' . ($tenant ? '?workspace=' . urlencode($tenant->slug) : ''));
    }

    private static function url(string $path): string
    {
        return config('app.frontend_url') . $path;
    }

    private static function accountDetails(User $user): array
    {
        return array_filter([
            'Name' => $user->name,
            'Google account' => $user->email,
            'Role' => self::roleLabel($user->roles->first()?->name),
            'Department' => $user->department?->name,
            'Workspace' => $user->tenant?->name,
        ]);
    }

    private static function send(string $to, string $what, AccountNotificationMail $mail): void
    {
        try {
            Mail::to($to)->send($mail);
            Log::info("Email sent ({$what}) to {$to}");
        } catch (\Throwable $e) {
            Log::error("Email failed ({$what}) to {$to}: " . $e->getMessage());
        }
    }
}
