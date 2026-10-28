<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Requirement;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChairManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Department $cs;
    private Department $it;
    private User $chair;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $this->tenant = Tenant::where('slug', 'prmsu-castillejos')->first();
        $this->admin = User::where('email', 'castillejos@prmsu.com')->first();
        $this->cs = Department::create(['tenant_id' => $this->tenant->id, 'name' => 'Computer Science']);
        $this->it = Department::create(['tenant_id' => $this->tenant->id, 'name' => 'Information Technology']);
        $this->chair = $this->member('Chair', 'chair@prmsu.com', 'program_chair', $this->cs);
    }

    private function member(string $name, string $email, string $role, ?Department $department, string $status = 'approved'): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'email' => $email,
            'department_id' => $department?->id, 'status' => $status,
        ]);
        $user->assignRole($role);
        return $user;
    }

    public function test_chair_adds_faculty_to_their_own_department(): void
    {
        Sanctum::actingAs($this->chair);

        $this->postJson('/api/chair/members', [
            'name' => 'Maria', 'email' => 'maria@prmsu.com', 'role' => 'faculty', 'department_id' => $this->it->id,
        ])->assertOk();

        $this->assertSame($this->cs->id, User::where('email', 'maria@prmsu.com')->first()->department_id);
    }

    public function test_chair_cannot_add_a_program_chair(): void
    {
        Sanctum::actingAs($this->chair);

        $this->postJson('/api/chair/members', [
            'name' => 'Maria', 'email' => 'maria@prmsu.com', 'role' => 'program_chair',
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_chair_lists_only_their_department_and_unassigned_sign_ups(): void
    {
        $this->member('Own', 'own@prmsu.com', 'faculty', $this->cs);
        $this->member('Other', 'other@prmsu.com', 'faculty', $this->it);
        $this->member('Pending', 'pending@gmail.com', 'faculty', null, 'pending');
        Sanctum::actingAs($this->chair);

        $emails = collect($this->getJson('/api/chair/members')->assertOk()->json())->pluck('email');

        $this->assertEqualsCanonicalizing(['chair@prmsu.com', 'own@prmsu.com', 'pending@gmail.com'], $emails->all());
    }

    public function test_chair_cannot_manage_other_departments_or_other_chairs(): void
    {
        $other = $this->member('Other', 'other@prmsu.com', 'faculty', $this->it);
        $coChair = $this->member('Co-chair', 'co@prmsu.com', 'program_chair', $this->cs);
        Sanctum::actingAs($this->chair);

        $this->deleteJson("/api/chair/members/{$other->id}")->assertNotFound();
        $this->deleteJson("/api/chair/members/{$coChair->id}")->assertForbidden();
        $this->getJson("/api/chair/members/{$other->id}/submissions")->assertNotFound();
    }

    public function test_chair_approves_a_pending_sign_up_into_their_department(): void
    {
        $pending = $this->member('Juan', 'juan@gmail.com', 'faculty', null, 'pending');
        Sanctum::actingAs($this->chair);

        $this->putJson("/api/chair/members/{$pending->id}", [
            'name' => 'Juan', 'email' => 'juan@gmail.com', 'role' => 'faculty',
        ])->assertOk();
        $this->postJson("/api/chair/members/{$pending->id}/approve")->assertOk();

        $pending->refresh();
        $this->assertSame('approved', $pending->status);
        $this->assertSame($this->cs->id, $pending->department_id);
    }

    public function test_admin_routes_are_closed_to_chairs(): void
    {
        Sanctum::actingAs($this->chair);

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_a_department_has_at_most_two_program_chairs(): void
    {
        $this->member('Second', 'second@prmsu.com', 'program_chair', $this->cs);
        $faculty = $this->member('Faculty', 'fac@prmsu.com', 'faculty', $this->cs);
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/users', [
            'name' => 'Third', 'email' => 'third@prmsu.com', 'role' => 'program_chair', 'department_id' => $this->cs->id,
        ])->assertStatus(422);

        $this->putJson("/api/admin/users/{$faculty->id}", [
            'name' => 'Faculty', 'email' => 'fac@prmsu.com', 'role' => 'program_chair', 'department_id' => $this->cs->id,
        ])->assertStatus(422);

        // Another department still has room, and editing an existing chair is fine
        $this->postJson('/api/admin/users', [
            'name' => 'IT Chair', 'email' => 'itchair@prmsu.com', 'role' => 'program_chair', 'department_id' => $this->it->id,
        ])->assertOk();
        $this->putJson("/api/admin/users/{$this->chair->id}", [
            'name' => 'Chair Renamed', 'email' => 'chair@prmsu.com', 'role' => 'program_chair', 'department_id' => $this->cs->id,
        ])->assertOk();
    }

    public function test_analytics_are_scoped_by_role(): void
    {
        $semester = Semester::create(['tenant_id' => $this->tenant->id, 'name' => '1st Sem', 'is_active' => true]);
        $syllabus = Requirement::create(['tenant_id' => $this->tenant->id, 'semester_id' => $semester->id, 'name' => 'Syllabus', 'is_required' => true]);
        Requirement::create(['tenant_id' => $this->tenant->id, 'semester_id' => $semester->id, 'name' => 'Class Record', 'is_required' => true, 'deadline' => now()->subDay()]);

        $csFaculty = $this->member('CS Faculty', 'csf@prmsu.com', 'faculty', $this->cs);
        $itFaculty = $this->member('IT Faculty', 'itf@prmsu.com', 'faculty', $this->it);
        Submission::create(['tenant_id' => $this->tenant->id, 'faculty_id' => $csFaculty->id, 'requirement_id' => $syllabus->id, 'file_path' => 'a.pdf', 'status' => 'approved']);
        Submission::create(['tenant_id' => $this->tenant->id, 'faculty_id' => $itFaculty->id, 'requirement_id' => $syllabus->id, 'file_path' => 'b.pdf', 'status' => 'pending']);

        Sanctum::actingAs($this->admin);
        $admin = $this->getJson('/api/analytics')->assertOk();
        $admin->assertJsonPath('scope', 'workspace')
            ->assertJsonPath('summary.faculty', 2)
            ->assertJsonPath('summary.expected', 4)
            ->assertJsonPath('summary.approved', 1)
            ->assertJsonPath('summary.pending', 1)
            ->assertJsonPath('summary.missing', 2)
            ->assertJsonPath('summary.overdue', 2)
            ->assertJsonCount(2, 'by_department');

        Sanctum::actingAs($this->chair);
        $this->getJson('/api/analytics')->assertOk()
            ->assertJsonPath('scope', 'department')
            ->assertJsonPath('summary.faculty', 1)
            ->assertJsonPath('summary.approved', 1)
            ->assertJsonPath('summary.missing', 1)
            ->assertJsonPath('by_department', [])
            ->assertJsonMissingPath('summary.pending_signups');

        Sanctum::actingAs($csFaculty);
        $this->getJson('/api/analytics')->assertForbidden();
    }
}
