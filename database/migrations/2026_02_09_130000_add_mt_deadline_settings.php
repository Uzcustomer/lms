<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('settings')->insert([
            [
                'key' => 'mt_deadline_type',
                'value' => 'before_last',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mt_deadline_time',
                'value' => '17:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['mt_deadline_type', 'mt_deadline_time'])->delete();
    }
};
