<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'test-client.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['services.google.client_id' => self::CLIENT_ID]);
    }

    private function fakeGoogle(string $email, string $sub = 'google-sub-1', string $aud = self::CLIENT_ID): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'aud' => $aud,
                'iss' => 'https://accounts.google.com',
                'sub' => $sub,
                'email' => $email,
                'email_verified' => 'true',
                'name' => 'Juan Dela Cruz',
            ]),
        ]);
    }

    public function test_new_google_user_is_created_pending_in_the_workspace(): void
    {
        $this->fakeGoogle('juan@gmail.com');

        $this->postJson('/api/auth/google', ['credential' => 'x', 'tenant' => 'PRMSU Castillejos'])
            ->assertStatus(202)
            ->assertJson(['pending' => true]);

        $user = User::where('email', 'juan@gmail.com')->first();
        $this->assertSame(Tenant::where('slug', 'prmsu-castillejos')->value('id'), $user->tenant_id);
        $this->assertSame('pending', $user->status);
        $this->assertTrue($user->hasRole('faculty'));
        $this->assertNull($user->password);
    }

    public function test_pending_user_cannot_sign_in_until_approved(): void
    {
        $this->fakeGoogle('juan@gmail.com');
        $this->postJson('/api/auth/google', ['credential' => 'x', 'tenant' => 'prmsu-castillejos'])->assertStatus(202);

        $this->postJson('/api/auth/google', ['credential' => 'x', 'tenant' => 'prmsu-castillejos'])
            ->assertStatus(403);

        User::where('email', 'juan@gmail.com')->update(['status' => 'approved']);

        $this->postJson('/api/auth/google', ['credential' => 'x', 'tenant' => 'prmsu-castillejos'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['tenant']]);
    }

    public function test_user_invited_by_email_can_sign_in_with_google(): void
    {
        $tenant = Tenant::where('slug', 'prmsu-castillejos')->first();
        $invited = User::create(['tenant_id' => $tenant->id, 'name' => 'Invited', 'email' => 'invited@prmsu.com', 'status' => 'approved']);
        $invited->assignRole('faculty');

        $this->fakeGoogle('invited@prmsu.com', 'sub-invited');

        $this->postJson('/api/auth/google', ['credential' => 'x', 'tenant' => 'prmsu-castillejos'])->assertOk();
        $this->assertSame('sub-invited', $invited->fresh()->google_id);
    }

    public function test_google_account_from_another_workspace_is_rejected(): void
    {
        Tenant::create(['name' => 'Other School', 'slug' => 'other-school']);
        $this->fakeGoogle('castillejos@prmsu.com');

        $this->postJson('/api/auth/google', ['credential' => 'x', 'tenant' => 'other-school'])
            ->assertStatus(403);
    }

    public function test_token_for_another_app_is_rejected(): void
    {
        $this->fakeGoogle('juan@gmail.com', 'sub', 'some-other-app');

        $this->postJson('/api/auth/google', ['credential' => 'x', 'tenant' => 'prmsu-castillejos'])
            ->assertStatus(401);
        $this->assertDatabaseMissing('users', ['email' => 'juan@gmail.com']);
    }

    public function test_unknown_workspace_is_rejected(): void
    {
        $this->postJson('/api/auth/google', ['credential' => 'x', 'tenant' => 'nowhere'])->assertStatus(404);
    }

    public function test_password_login_needs_the_right_workspace(): void
    {
        $this->postJson('/api/login', ['email' => 'castillejos@prmsu.com', 'password' => 'password'])->assertStatus(422);
        $this->postJson('/api/login', ['email' => 'castillejos@prmsu.com', 'password' => 'password', 'tenant' => 'prmsu-castillejos'])->assertOk();
        $this->postJson('/api/login', ['email' => 'admin@profsync.com', 'password' => 'password'])->assertOk();
    }

    public function test_suspended_workspace_cannot_sign_in(): void
    {
        Tenant::where('slug', 'prmsu-castillejos')->update(['status' => 'suspended']);

        $this->postJson('/api/login', ['email' => 'castillejos@prmsu.com', 'password' => 'password', 'tenant' => 'prmsu-castillejos'])
            ->assertStatus(403);
    }
}
