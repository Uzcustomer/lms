<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->char('scope_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['import_key', 'scope_hash'], 'sd_groups_scope_unique');
            $table->index(['faculty_name', 'specialty_name', 'course']);
            $table->index('free_places');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_distribution_groups');
    }
};
