<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Shift column 4 (PCs #44–#49) up one row so that #36 and #49 sit in the
 * same physical row and the seat directly in front of #30 stays empty,
 * matching the actual room layout.
 */
return new class extends Migration
{
    public function up(): void
    {
        $map = [
            44 => 2,
            45 => 3,
            46 => 4,
            47 => 5,
            48 => 6,
            49 => 7,
        ];

        foreach ($map as $number => $row) {
            DB::table('computers')
                ->where('number', $number)
                ->update(['grid_row' => $row, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $map = [
            44 => 1,
            45 => 2,
            46 => 3,
            47 => 4,
            48 => 5,
            49 => 6,
        ];

        foreach ($map as $number => $row) {
            DB::table('computers')
                ->where('number', $number)
                ->update(['grid_row' => $row, 'updated_at' => now()]);
        }
    }
};
