<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('club_memberships', 'department_hemis_id')) {
            Schema::table('club_memberships', function (Blueprint $table) {
                $table->unsignedBigInteger('department_hemis_id')->nullable()->after('kafedra_name');
                $table->index('department_hemis_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('club_memberships', function (Blueprint $table) {
            $table->dropIndex(['department_hemis_id']);
            $table->dropColumn('department_hemis_id');
        });
    }
};
