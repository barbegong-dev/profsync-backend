<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public: workspace lookup and sign-in
Route::get('/tenants/{workspace}', [AuthController::class, 'tenant']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/google', [AuthController::class, 'google'])->middleware('throttle:20,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['roles', 'department', 'tenant']);
    });

    // Profile
    Route::put('/user/profile', [ProfileController::class, 'update']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

    // Departments of the current workspace
    Route::get('/departments', [DepartmentController::class, 'index']);

    // Download
    Route::get('/submissions/{submission}/download', [SubmissionController::class, 'download']);

    // Faculty routes
    Route::middleware('role:faculty')->group(function () {
        Route::post('/submissions/upload', [SubmissionController::class, 'upload']);
        Route::get('/submissions/checklist', [SubmissionController::class, 'checklist']);
    });

    // Chair routes
    Route::middleware('role:program_chair')->group(function () {
        Route::get('/reviews', [ReviewController::class, 'index']);
        Route::post('/reviews/{submission}', [ReviewController::class, 'review']);
        Route::get('/compliance', [ReportController::class, 'compliance']);

        // Faculty of the chair's own department
        Route::get('/chair/members', [MemberController::class, 'index']);
        Route::post('/chair/members', [MemberController::class, 'store']);
        Route::put('/chair/members/{user}', [MemberController::class, 'update']);
        Route::post('/chair/members/{user}/approve', [MemberController::class, 'approve']);
        Route::delete('/chair/members/{user}', [MemberController::class, 'destroy']);
        Route::get('/chair/members/{user}/submissions', [MemberController::class, 'submissions']);
    });

    // Analytics: whole workspace for admins, own department for chairs
    Route::middleware('role:admin|program_chair')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index']);
    });

    // Dean routes
    Route::middleware('role:dean')->group(function () {
        Route::get('/reports', [ReportController::class, 'reports']);
    });

    // Workspace admin routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/setup', [SetupController::class, 'status']);

        Route::get('/admin/users', [MemberController::class, 'index']);
        Route::post('/admin/users', [MemberController::class, 'store']);
        Route::put('/admin/users/{user}', [MemberController::class, 'update']);
        Route::post('/admin/users/{user}/approve', [MemberController::class, 'approve']);
        Route::delete('/admin/users/{user}', [MemberController::class, 'destroy']);
        Route::get('/admin/users/{user}/submissions', [MemberController::class, 'submissions']);

        // Departments management
        Route::post('/admin/departments', [DepartmentController::class, 'store']);
        Route::put('/admin/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/admin/departments/{department}', [DepartmentController::class, 'destroy']);

        // Requirements management
        Route::get('/admin/requirements', [RequirementController::class, 'index']);
        Route::post('/admin/requirements', [RequirementController::class, 'store']);
        Route::put('/admin/requirements/{requirement}', [RequirementController::class, 'update']);
        Route::delete('/admin/requirements/{requirement}', [RequirementController::class, 'destroy']);

        // Semester management
        Route::apiResource('semesters', SemesterController::class);
    });

    // Platform super admin routes
    Route::middleware('role:super_admin')->prefix('super')->group(function () {
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::put('/tenants/{tenant}', [TenantController::class, 'update']);
    });
});
