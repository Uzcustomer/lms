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
        // Bo'sh bazada (yangi o'rnatish, testlar) `token` ustuni hali yo'q — u keyingi migratsiyada qo'shiladi.
        $hasToken = Schema::hasColumn('students', 'token');

        Schema::table('students', function (Blueprint $table) use ($hasToken) {
            $column = $table->string('local_password')->nullable();
            if ($hasToken) {
                $column->after('token');
            }
            $table->timestamp('local_password_expires_at')->nullable()->after('local_password');
            $table->boolean('must_change_password')->default(false)->after('local_password_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'local_password',
                'local_password_expires_at',
                'must_change_password',
            ]);
        });
    }
};
