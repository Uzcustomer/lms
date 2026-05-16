<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computers', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number')->unique();
            $table->string('ip_address', 45)->nullable()->index(); // IPv4 or IPv6
            $table->string('mac_address', 32)->nullable();
            $table->string('hostname', 100)->nullable();
            $table->string('label', 100)->nullable(); // human-friendly: "Lab1, Row3 #5"
            // Physical layout coordinates (5 columns x 15 rows, row 1 = bottom of room)
            $table->unsignedTinyInteger('grid_column')->nullable();
            $table->unsignedTinyInteger('grid_row')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['grid_column', 'grid_row']);
        });

        // Seed 60 computers with sequential IPs 196.168.7.101 .. 196.168.7.160
        // and the physical layout shown in the test centre room.
        // Layout map: number => [grid_column, grid_row] (row 1 = bottom).
        $layout = [
            1 => [1, 1], 2 => [1, 2], 3 => [1, 3], 4 => [1, 4], 5 => [1, 5],
            6 => [1, 6], 7 => [1, 7], 8 => [1, 8], 9 => [1, 9], 10 => [1, 10],
            11 => [1, 11], 12 => [1, 12], 13 => [1, 13], 14 => [1, 14], 15 => [1, 15],

            16 => [2, 1], 17 => [2, 2], 18 => [2, 3], 19 => [2, 4], 20 => [2, 5],
            21 => [2, 6], 22 => [2, 7],
            // gap at (2, 8)
            23 => [2, 9], 24 => [2, 10], 25 => [2, 11], 26 => [2, 12],
            27 => [2, 13], 28 => [2, 14], 29 => [2, 15],

            30 => [3, 1], 31 => [3, 2], 32 => [3, 3], 33 => [3, 4], 34 => [3, 5],
            35 => [3, 6], 36 => [3, 7],
            // gap at (3, 8)
            37 => [3, 9], 38 => [3, 10], 39 => [3, 11], 40 => [3, 12],
            41 => [3, 13], 42 => [3, 14], 43 => [3, 15],

            // Column 4 starts one row above #30 — seat in front of #30 is empty.
            44 => [4, 2], 45 => [4, 3], 46 => [4, 4], 47 => [4, 5], 48 => [4, 6],
            49 => [4, 7],
            // gap at (4, 8)
            50 => [4, 9], 51 => [4, 10], 52 => [4, 11], 53 => [4, 12],
            54 => [4, 13], 55 => [4, 14], 56 => [4, 15],

            // Column 5: only 4 PCs (rows 10-13)
            57 => [5, 10], 58 => [5, 11], 59 => [5, 12], 60 => [5, 13],
        ];

        $rows = [];
        $now = now();
        for ($n = 1; $n <= 60; $n++) {
            [$col, $row] = $layout[$n];
            $rows[] = [
                'number' => $n,
                'ip_address' => '196.168.7.' . (100 + $n),
                'grid_column' => $col,
                'grid_row' => $row,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        \Illuminate\Support\Facades\DB::table('computers')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('computers');
    }
};
