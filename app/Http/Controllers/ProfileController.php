<?php

namespace App\Http\Controllers;

use App\Support\AccountEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $oldEmail = $user->email;
        $passwordChanged = $request->filled('password');

        $user->name = $request->name;
        $user->email = $request->email;

        if ($passwordChanged) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // Security notices
        if ($passwordChanged) {
            AccountEmails::passwordChanged($user);
        }
        if ($oldEmail !== $user->email) {
            AccountEmails::emailChanged($user, $oldEmail);
        }

        return response()->json([
            'success' => true,
            'user' => $user->load('roles', 'department', 'tenant'),
            'message' => 'Profile updated successfully.'
        ]);
    }
}
