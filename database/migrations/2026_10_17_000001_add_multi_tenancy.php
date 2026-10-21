<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tenantTables = ['departments', 'semesters', 'requirements', 'submissions'];

    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active'); // active, suspended
            $table->timestamps();
        });

        // Users: tenant_id is null for the platform super admin.
        // Google users have no password and get a department when an admin approves them.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('password')->nullable()->change();
            $table->foreignId('department_id')->nullable()->change();
        });

        foreach ($this->tenantTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ([...$this->tenantTables, 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        Schema::dropIfExists('tenants');
    }
};
