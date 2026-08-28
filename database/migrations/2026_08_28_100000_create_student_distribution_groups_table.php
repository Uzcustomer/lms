<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_distribution_groups')) {
            Schema::create('student_distribution_groups', function (Blueprint $table) {
                $table->id();
                $table->string('faculty_name');
                $table->string('specialty_name');
                $table->unsignedTinyInteger('course');
                $table->string('group_name');
                $table->string('group_hemis_id')->nullable();
                $table->unsignedInteger('capacity')->default(0);
                $table->unsignedInteger('occupied_count')->default(0);
                $table->unsignedInteger('free_places')->default(0);
                $table->string('source_file')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->uuid('import_key');
                $table->char('scope_hash', 64)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('student_distribution_groups', 'scope_hash')) {
            Schema::table('student_distribution_groups', function (Blueprint $table) {
                $table->char('scope_hash', 64)->nullable()->after('import_key');
            });
        }

        Schema::table('student_distribution_groups', function (Blueprint $table) {
            $table->unique(['import_key', 'scope_hash'], 'sd_groups_scope_unique');
            $table->index(['faculty_name', 'specialty_name', 'course'], 'sd_groups_filter_index');
            $table->index('free_places', 'sd_groups_free_places_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_distribution_groups');
    }
};
