<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retake_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable(); // teachers.id (no FK)
            $table->string('updated_by_name')->nullable();
            $table->timestamps();
        });

        DB::table('retake_settings')->insert([
            [
                'key' => 'credit_price',
                'value' => '175000',
                'description' => 'Bir kredit narxi (UZS)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'min_group_size',
                'value' => '1',
                'description' => 'Qayta o\'qish guruhida eng kam talaba soni',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'receipt_max_mb',
                'value' => '5',
                'description' => 'Kvitansiya fayli maksimal hajmi (MB)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'reject_reason_min_length',
                'value' => '10',
                'description' => 'Rad etish sababi minimal belgilar soni',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_settings');
    }
};
