<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->constrained()->onDelete('cascade');
            // We can drop the old 'semester' string column if we want to migrate fully, 
            // but for now let's keep it or just make it nullable if we are transitioning.
            // Since we are doing fresh seed, let's drop it to force usage of the relation.
            $table->dropColumn('semester'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropForeign(['semester_id']);
            $table->dropColumn('semester_id');
            $table->string('semester'); // Restore if rolled back
        });
    }
};
