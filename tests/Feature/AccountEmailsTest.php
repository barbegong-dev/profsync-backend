<?php

namespace Tests\Feature;

use App\Mail\AccountNotificationMail;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountEmailsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $this->tenant = Tenant::where('slug', 'prmsu-castillejos')->first();
        $this->admin = User::where('email', 'castillejos@prmsu.com')->first();
        $this->department = Department::create(['tenant_id' => $this->tenant->id, 'name' => 'Computer Science']);
    }

    private function assertEmailed(string $to, string $subjectContains): void
    {
        Mail::assertSent(AccountNotificationMail::class, fn ($mail) =>
            $mail->hasTo($to) && str_contains($mail->subjectLine, $subjectContains));
    }

    private function pendingUser(): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Juan', 'email' => 'juan@gmail.com', 'status' => 'pending']);
        $user->assignRole('faculty');
        return $user;
    }

    public function test_google_sign_up_emails_the_user_and_the_workspace_admin(): void
    {
        config(['services.google.client_id' => 'cid']);
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'aud' => 'cid', 'iss' => 'accounts.google.com', 'sub' => 's1',
            'email' => 'juan@gmail.com', 'email_verified' => 'true', 'name' => 'Juan',
        ])]);

        $this->postJson('/api/auth/google', ['credential' => 'x', 'tenant' => 'prmsu-castillejos'])->assertStatus(202);

        $this->assertEmailed('juan@gmail.com', 'Sign-up received');
        $this->assertEmailed('castillejos@prmsu.com', 'New sign-up waiting');
    }

    public function test_adding_a_member_emails_an_invitation(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/users', [
            'name' => 'Maria', 'email' => 'maria@prmsu.com', 'role' => 'faculty', 'department_id' => $this->department->id,
        ])->assertOk();

        $this->assertEmailed('maria@prmsu.com', "You've been added");
    }

    public function test_approving_emails_the_user(): void
    {
        $user = $this->pendingUser();
        $user->update(['department_id' => $this->department->id]);
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/users/{$user->id}/approve")->assertOk();

        $this->assertEmailed('juan@gmail.com', 'approved');
    }

    public function test_rejecting_a_sign_up_emails_the_user(): void
    {
        $user = $this->pendingUser();
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/admin/users/{$user->id}")->assertOk();

        $this->assertEmailed('juan@gmail.com', 'not approved');
    }

    public function test_changing_an_active_members_role_emails_them(): void
    {
        $user = $this->pendingUser();
        $user->update(['status' => 'approved', 'department_id' => $this->department->id]);
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Juan', 'email' => 'juan@gmail.com', 'role' => 'program_chair', 'department_id' => $this->department->id,
        ])->assertOk();

        $this->assertEmailed('juan@gmail.com', 'role or department');
    }

    public function test_suspending_a_workspace_emails_its_admin(): void
    {
        Sanctum::actingAs(User::where('email', 'admin@profsync.com')->first());

        $this->putJson("/api/super/tenants/{$this->tenant->id}", ['name' => $this->tenant->name, 'status' => 'suspended'])->assertOk();

        $this->assertEmailed('castillejos@prmsu.com', 'suspended');
    }

    public function test_emails_render(): void
    {
        $user = $this->pendingUser();
        \App\Support\AccountEmails::signupReceived($user);

        Mail::assertSent(AccountNotificationMail::class, function ($mail) {
            $html = $mail->render();
            return str_contains($html, 'PRMSU Castillejos') && str_contains($html, 'Pending approval');
        });
    }
}
