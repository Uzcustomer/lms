<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilot detektorlar uchun manba jadvallar:
 *  - monitoring_orders — buyruqlar reyestri (HEMIS'da buyruqlar sinxronlanmaydi; Excel/CSV importi);
 *  - monitoring_admission_recommendations — A-5: kontrakt bo'yicha tavsiya etilganlar (mandat);
 *  - monitoring_notice_logs — E-1: buyruq bo'yicha ijara kotibi va buxgalteriyaga xabarnoma (M3);
 *  - monitoring_transfer_checklists — E-7: ko'chirib kelganlar yig'majildi cheklisti (M8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_id')->nullable()->constrained('monitoring_data_imports')->nullOnDelete();
            $table->string('dedupe_key', 64)->unique()->comment('raqam + sana + talaba bo\'yicha sha1');
            $table->string('hemis_id', 50)->nullable();
            $table->string('order_number', 100);
            $table->date('order_date');
            $table->string('order_type', 30)->comment('config(monitoring.order_types) kalitlari');
            $table->string('order_type_raw')->nullable();
            $table->unsignedBigInteger('student_hemis_id')->nullable();
            $table->string('student_id_number', 50)->nullable();
            $table->string('full_name')->nullable();
            $table->string('basis', 500)->nullable();
            $table->timestamps();

            $table->index(['order_type', 'order_date']);
            $table->index('student_hemis_id');
            $table->index('student_id_number');
        });

        Schema::create('monitoring_admission_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_id')->nullable()->constrained('monitoring_data_imports')->nullOnDelete();
            $table->string('dedupe_key', 64)->unique();
            $table->string('education_year', 20)->comment('masalan 2026-2027');
            $table->string('applicant_id', 50)->nullable()->comment('Abituriyent ID (mandatdagi)');
            $table->string('full_name');
            $table->string('passport', 30)->nullable()->comment('Faqat talabaga moslash uchun');
            $table->string('specialty_name')->nullable();
            $table->string('education_form', 100)->nullable();
            $table->string('contract_type', 30)->default('oddiy')->comment('oddiy | qoshimcha | tabaqalashtirilgan');
            $table->string('contract_type_raw')->nullable();
            $table->date('recommended_at')->nullable();
            $table->unsignedBigInteger('student_hemis_id')->nullable();
            $table->string('match_method', 20)->nullable()->comment('passport | hemis_id | id_raqam | fio | qolda');
            $table->boolean('match_confirmed')->default(false);
            $table->timestamps();

            $table->index(['education_year', 'student_hemis_id'], 'mon_adm_rec_year_student_idx');
        });

        Schema::create('monitoring_notice_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('monitoring_orders')->cascadeOnDelete();
            $table->string('recipient_kind', 30)->comment('ijara_kotibi | buxgalteriya');
            $table->nullableMorphs('recipient', 'mon_notice_recipient_idx');
            $table->string('recipient_name')->nullable();
            $table->timestamp('sent_at');
            $table->string('method', 30)->default('mark')->comment('mark | telegram | qogoz | boshqa');
            $table->string('note', 500)->nullable();
            $table->string('evidence_path')->nullable();
            $table->nullableMorphs('recorded_by', 'mon_notice_recorded_by_idx');
            $table->timestamps();

            $table->index(['order_id', 'recipient_kind']);
        });

        Schema::create('monitoring_transfer_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('monitoring_orders')->nullOnDelete();
            $table->unsignedBigInteger('student_hemis_id')->nullable();
            $table->string('student_id_number', 50)->nullable();
            $table->string('full_name');
            $table->date('arrived_at')->nullable()->comment('Darsga ruxsat / buyruq sanasi');
            $table->json('items')->nullable()->comment('{kalit: {bor, asli_tekshirildi, izoh}}');
            $table->text('note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->nullableMorphs('updated_by', 'mon_transfer_updated_by_idx');
            $table->timestamps();

            $table->index('student_hemis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_transfer_checklists');
        Schema::dropIfExists('monitoring_notice_logs');
        Schema::dropIfExists('monitoring_admission_recommendations');
        Schema::dropIfExists('monitoring_orders');
    }
};
