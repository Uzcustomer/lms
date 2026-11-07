<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bitta kafedrada bir nechta mudir bo'lishi mumkin — ruxsatlari bir xil, lekin hujjatlarda
 * (vedomost, KTR, jurnal Excel) va HEMIS'dagi "kafedra mudiri" o'rniga asosiy mudir chiqadi.
 * Hozirgi barcha kafedra mudirlari asosiy deb belgilanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->boolean('is_primary_head')->default(false)->after('head_department_hemis_id');
        });

        $ids = DB::table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('r.name', 'kafedra_mudiri')
            ->where('mr.model_type', 'App\\Models\\Teacher')
            ->pluck('mr.model_id');

        foreach ($ids->chunk(500) as $chunk) {
            DB::table('teachers')->whereIn('id', $chunk->all())->update(['is_primary_head' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('is_primary_head');
        });
    }
};
