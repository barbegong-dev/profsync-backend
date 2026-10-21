<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Notification;
use App\Support\AccountEmails;

class AuthController extends Controller
{
    /**
     * Look up a workspace by its slug or name, e.g. "prmsu-castillejos" or "PRMSU Castillejos".
     */
    public function tenant(string $workspace)
    {
        $tenant = $this->findTenant($workspace);

        if (!$tenant) {
            return response()->json(['message' => 'Workspace not found. Check the name with your administrator.'], 404);
        }
        if (!$tenant->isActive()) {
            return response()->json(['message' => 'This workspace is currently suspended.'], 403);
        }

        return response()->json(['name' => $tenant->name, 'slug' => $tenant->slug]);
    }

    /**
     * Email + password sign-in. Used by administrators only; everyone else signs in with Google.
     * With a workspace the user must belong to it; without one only the platform super admin may sign in.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'tenant' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        if ($request->filled('tenant')) {
            $tenant = $this->findTenant($request->tenant);
            if (!$tenant || $user->tenant_id !== $tenant->id) {
                return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
            }
        } elseif (!$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Enter your workspace name first.'], 422);
        }

        return $this->issueToken($user);
    }

    /**
     * Sign in or sign up with a Google ID token inside a workspace.
     * New accounts are created as pending faculty until a workspace admin approves them.
     */
    public function google(Request $request)
    {
        $request->validate([
            'credential' => 'required|string',
            'tenant' => 'required|string',
        ]);

        $tenant = $this->findTenant($request->tenant);
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Workspace not found.'], 404);
        }

        $profile = $this->verifyGoogleToken($request->credential);
        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Google sign-in failed. Please try again.'], 401);
        }

        $user = User::where('google_id', $profile['sub'])->first()
            ?? User::where('email', $profile['email'])->first();

        if ($user && $user->tenant_id !== $tenant->id) {
            return response()->json(['success' => false, 'message' => 'This Google account belongs to a different workspace.'], 403);
        }

        if (!$user) {
            $user = $this->registerGoogleUser($tenant, $profile);

            return response()->json([
                'success' => false,
                'pending' => true,
                'message' => "Account created! An administrator of {$tenant->name} needs to approve it before you can sign in.",
            ], 202);
        }

        if (!$user->google_id) {
            $user->google_id = $profile['sub'];
        }
        $user->email_verified_at ??= now();
        $user->save();

        return $this->issueToken($user);
    }

    public function logout(Request $request)
    {
        // Revoke the bearer token used for this request
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['success' => true]);
    }

    private function issueToken(User $user)
    {
        if ($user->tenant && !$user->tenant->isActive()) {
            return response()->json(['success' => false, 'message' => 'This workspace is currently suspended.'], 403);
        }

        if ($user->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Your account is pending approval.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => $user->load('roles', 'department', 'tenant'),
            'token' => $token,
        ]);
    }

    private function findTenant(string $workspace): ?Tenant
    {
        $workspace = trim($workspace);

        return Tenant::where('slug', Str::slug($workspace))
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($workspace)])
            ->first();
    }

    /**
     * Verify a Google ID token and return its profile, or null if it is not valid for this app.
     */
    private function verifyGoogleToken(string $credential): ?array
    {
        $clientId = config('services.google.client_id');
        if (!$clientId) {
            Log::error('Google sign-in attempted but GOOGLE_CLIENT_ID is not set.');
            return null;
        }

        try {
            // POST keeps the token out of the URL (and out of error logs)
            $response = Http::timeout(10)->asForm()->post('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $credential]);
        } catch (\Throwable $e) {
            Log::error('Google token verification failed: ' . $e->getMessage());
            return null;
        }

        $data = $response->json();
        $valid = $response->ok()
            && ($data['aud'] ?? null) === $clientId
            && in_array($data['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            && ($data['email_verified'] ?? null) === 'true'
            && !empty($data['email']);

        return $valid ? $data : null;
    }

    private function registerGoogleUser(Tenant $tenant, array $profile): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $profile['name'] ?? Str::before($profile['email'], '@'),
            'email' => $profile['email'],
            'google_id' => $profile['sub'],
            'status' => 'pending',
        ]);
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole('faculty');

        try {
            Notification::create([
                'user_id' => $user->id,
                'message' => 'Account created with Google. Your account is pending administrator review.',
                'read' => false,
            ]);

            $admins = User::role('admin')->where('tenant_id', $tenant->id)->get();
            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'message' => "New sign-up pending approval: {$user->name} ({$user->email}). Assign a role and department, then approve.",
                    'read' => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create sign-up notifications: ' . $e->getMessage());
        }

        AccountEmails::signupReceived($user);
        AccountEmails::newSignupForAdmins($user);

        return $user;
    }
}
