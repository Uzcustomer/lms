<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xalqaro talabaning O'zbekistonda turish asosi: viza, vizasiz yoki
 * yashash guvohnomasi. Bo'sh qiymat — eski yozuvlar, ular viza deb hisoblanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['student_visa_infos', 'student_visa_info_histories'] as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'residence_basis')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('residence_basis', 30)->nullable();
                $table->string('residence_permit_number', 50)->nullable();
                $table->string('residence_permit_pinfl', 20)->nullable();
                $table->string('residence_permit_issued_place')->nullable();
                $table->date('residence_permit_issued_date')->nullable();
                $table->date('residence_permit_expiry_date')->nullable();
                $table->string('residence_permit_scan_path', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['student_visa_infos', 'student_visa_info_histories'] as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'residence_basis')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'residence_basis',
                    'residence_permit_number',
                    'residence_permit_pinfl',
                    'residence_permit_issued_place',
                    'residence_permit_issued_date',
                    'residence_permit_expiry_date',
                    'residence_permit_scan_path',
                ]);
            });
        }
    }
};
