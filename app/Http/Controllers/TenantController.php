<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\AccountEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Platform (super admin) management of tenants / workspaces.
 */
class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withCount('users')->orderBy('name')->get()->map(function (Tenant $t) {
            $admin = User::role('admin')->where('tenant_id', $t->id)->orderBy('id')->first();
            return [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'status' => $t->status,
                'users_count' => $t->users_count,
                'admin_email' => $admin?->email,
                'created_at' => $t->created_at,
            ];
        });

        return response()->json($tenants);
    }

    public function store(Request $request)
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('name', ''))]);

        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:tenants,slug',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        $tenant = DB::transaction(function () use ($request) {
            $tenant = Tenant::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'status' => 'active',
            ]);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'status' => 'approved',
            ]);
            $admin->assignRole('admin');

            return [$tenant, $admin];
        });
        [$tenant, $admin] = $tenant;

        AccountEmails::tenantCreated($tenant, $admin);

        return response()->json(['success' => true, 'tenant' => $tenant, 'message' => 'Tenant created.'], 201);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        $statusChanged = $tenant->status !== $request->status;
        $tenant->update($request->only('name', 'status'));

        if ($statusChanged) {
            AccountEmails::tenantStatusChanged($tenant);
        }

        return response()->json(['success' => true, 'tenant' => $tenant, 'message' => 'Tenant updated.']);
    }
}
