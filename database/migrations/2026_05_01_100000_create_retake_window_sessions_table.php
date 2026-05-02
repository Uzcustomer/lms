<?php

use App\Models\RetakeApplicationWindow;
use App\Models\RetakeWindowSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Sessiyalar — yangi o'quv yili / muddat uchun guruhlovchi konteyner.
        // Har sessiya ichida bir nechta oyna ochiladi (fakultet/kurs/yo'nalish kesimida).
        // Talabaning slot hisobi har sessiyada qayta tiklanadi.
        Schema::create('retake_window_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_closed')->default(false);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('is_closed');
        });

        // Mavjud oynalar uchun "Eski sessiya" yaratamiz va session_id qo'shamiz.
        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->unsignedBigInteger('session_id')->nullable()->after('id');
        });

        // Eski oynalar mavjud bo'lsa — ularni "Eski sessiya"ga biriktiramiz.
        if (DB::table('retake_application_windows')->whereNull('session_id')->exists()) {
            $defaultSessionId = DB::table('retake_window_sessions')->insertGetId([
                'name' => 'Eski oynalar (avtomatik)',
                'is_closed' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'closed_at' => now(),
            ]);

            DB::table('retake_application_windows')
                ->whereNull('session_id')
                ->update(['session_id' => $defaultSessionId]);
        }

        // Endi NOT NULL + foreign key
        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->unsignedBigInteger('session_id')->nullable(false)->change();
            $table->foreign('session_id')
                ->references('id')->on('retake_window_sessions')
                ->onDelete('restrict');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropIndex(['session_id']);
            $table->dropColumn('session_id');
        });

        Schema::dropIfExists('retake_window_sessions');
    }
};
