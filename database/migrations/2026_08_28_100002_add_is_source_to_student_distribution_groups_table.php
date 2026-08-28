<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_distribution_groups')
            && !Schema::hasColumn('student_distribution_groups', 'is_source')) {
            Schema::table('student_distribution_groups', function (Blueprint $table) {
                $table->boolean('is_source')->default(false)->after('scope_hash');
                $table->index('is_source', 'sd_groups_source_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('student_distribution_groups')
            && Schema::hasColumn('student_distribution_groups', 'is_source')) {
            Schema::table('student_distribution_groups', function (Blueprint $table) {
                $table->dropIndex('sd_groups_source_index');
                $table->dropColumn('is_source');
            });
        }
    }
};
