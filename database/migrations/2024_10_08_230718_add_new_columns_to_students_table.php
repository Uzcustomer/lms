<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('language_code')->nullable();
            $table->string('language_name')->nullable();
            $table->year('year_of_enter')->nullable();
            $table->integer('roommate_count')->nullable();
            $table->decimal('total_acload', 8, 2)->nullable();
            $table->boolean('is_graduate')->default(false);
            $table->text('other')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'language_code',
                'language_name',
                'year_of_enter',
                'roommate_count',
                'total_acload',
                'is_graduate',
                'other',
            ]);
        });
    }
};
