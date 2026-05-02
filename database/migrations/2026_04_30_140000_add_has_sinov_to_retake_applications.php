<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_applications', function (Blueprint $table) {
            $table->boolean('has_sinov')->default(false)->after('has_test');
        });
    }

    public function down(): void
    {
        Schema::table('retake_applications', function (Blueprint $table) {
            $table->dropColumn('has_sinov');
        });
    }
};
