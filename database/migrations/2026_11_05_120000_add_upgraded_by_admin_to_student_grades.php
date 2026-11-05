<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin buyrug'i bilan oshirilgan baholar (grades:admin-upgrade): HEMIS importi ularni
 * o'chirmaydi va ustidan yozmaydi; jurnalda eski baho hover'da ko'rinadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->boolean('upgraded_by_admin')->default(false)->index();
            $table->decimal('admin_original_grade', 8, 2)->nullable();
            $table->timestamp('admin_upgraded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropIndex(['upgraded_by_admin']);
            $table->dropColumn(['upgraded_by_admin', 'admin_original_grade', 'admin_upgraded_at']);
        });
    }
};
