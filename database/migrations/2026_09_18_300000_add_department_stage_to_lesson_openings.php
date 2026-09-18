<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dars ochish so'rovida o'quv bo'limi boshlig'i bosqichi.
 *
 * Tasdiqlovchilar so'rov raqamiga bog'liq: 1-so'rov — registrator ofisi;
 * 2-so'rov — registrator ofisi va o'quv bo'limi boshlig'i; 3-dan boshlab —
 * ular va o'quv prorektori. Bittasi rad etsa so'rov rad etiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lesson_openings')) {
            return;
        }

        Schema::table('lesson_openings', function (Blueprint $table) {
            if (!Schema::hasColumn('lesson_openings', 'department_status')) {
                $table->string('department_status')->nullable()->after('registrar_at');
            }
            if (!Schema::hasColumn('lesson_openings', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('department_status');
            }
            if (!Schema::hasColumn('lesson_openings', 'department_name')) {
                $table->string('department_name')->nullable()->after('department_id');
            }
            if (!Schema::hasColumn('lesson_openings', 'department_guard')) {
                $table->string('department_guard')->nullable()->after('department_name');
            }
            if (!Schema::hasColumn('lesson_openings', 'department_at')) {
                $table->dateTime('department_at')->nullable()->after('department_guard');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lesson_openings')) {
            return;
        }

        Schema::table('lesson_openings', function (Blueprint $table) {
            foreach (['department_status', 'department_id', 'department_name', 'department_guard', 'department_at'] as $column) {
                if (Schema::hasColumn('lesson_openings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
