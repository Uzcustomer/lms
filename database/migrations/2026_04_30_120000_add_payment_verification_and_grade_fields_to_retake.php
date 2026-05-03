<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // To'lov chekining haqiqiyligini registrator ofisi tasdiqlashi uchun.
        Schema::table('retake_application_groups', function (Blueprint $table) {
            $table->enum('payment_verification_status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->after('payment_uploaded_at');
            $table->unsignedBigInteger('payment_verified_by_user_id')->nullable()->after('payment_verification_status');
            $table->string('payment_verified_by_name')->nullable()->after('payment_verified_by_user_id');
            $table->timestamp('payment_verified_at')->nullable()->after('payment_verified_by_name');
            $table->text('payment_rejection_reason')->nullable()->after('payment_verified_at');
        });

        // Registrator tasdiqlashda majburiy to'ldiriladigan oldingi semestr baholari
        // va OSKE/TEST topshirilishi kerakligi flaglari.
        Schema::table('retake_applications', function (Blueprint $table) {
            $table->decimal('previous_joriy_grade', 5, 2)->nullable()->after('credit');
            $table->decimal('previous_mustaqil_grade', 5, 2)->nullable()->after('previous_joriy_grade');
            $table->boolean('has_oske')->default(false)->after('previous_mustaqil_grade');
            $table->boolean('has_test')->default(false)->after('has_oske');
        });
    }

    public function down(): void
    {
        Schema::table('retake_application_groups', function (Blueprint $table) {
            $table->dropColumn([
                'payment_verification_status',
                'payment_verified_by_user_id',
                'payment_verified_by_name',
                'payment_verified_at',
                'payment_rejection_reason',
            ]);
        });

        Schema::table('retake_applications', function (Blueprint $table) {
            $table->dropColumn([
                'previous_joriy_grade',
                'previous_mustaqil_grade',
                'has_oske',
                'has_test',
            ]);
        });
    }
};
