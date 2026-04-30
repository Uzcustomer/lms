<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_application_groups', function (Blueprint $table) {
            // Talaba dekan + registrator tasdig'idan keyin yuklaydigan to'lov cheki.
            // `receipt_path` (tushuntirish xati) bilan aralashmaslik uchun alohida ustun.
            $table->string('payment_receipt_path')->nullable()->after('receipt_path');
            $table->timestamp('payment_uploaded_at')->nullable()->after('payment_receipt_path');
        });
    }

    public function down(): void
    {
        Schema::table('retake_application_groups', function (Blueprint $table) {
            $table->dropColumn(['payment_receipt_path', 'payment_uploaded_at']);
        });
    }
};
