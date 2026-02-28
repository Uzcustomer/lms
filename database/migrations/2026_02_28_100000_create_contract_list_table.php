<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_list', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->unique();
            $table->string('key')->nullable();
            $table->string('education_year')->nullable();
            $table->unsignedBigInteger('student_hemis_id')->nullable()->index();
            $table->string('year', 10)->nullable()->index();
            $table->string('status')->nullable();
            $table->unsignedSmallInteger('status_id')->nullable();
            $table->string('edu_form')->nullable();
            $table->unsignedTinyInteger('edu_form_id')->nullable();
            $table->string('edu_year')->nullable();
            $table->string('full_name')->nullable();
            $table->string('edu_course', 50)->nullable();
            $table->unsignedTinyInteger('edu_cours_id')->nullable();
            $table->string('edu_type_code', 20)->nullable();
            $table->string('edu_type_name')->nullable();
            $table->string('faculty_code', 50)->nullable();
            $table->string('faculty_name')->nullable()->index();
            $table->string('contract_number')->nullable()->index();
            $table->decimal('edu_contract_sum', 15, 2)->nullable();
            $table->string('edu_organization')->nullable();
            $table->string('edu_organization_code', 50)->nullable();
            $table->decimal('paid_credit_amount', 15, 2)->nullable();
            $table->string('edu_speciality_code', 50)->nullable()->index();
            $table->string('edu_speciality_name')->nullable();
            $table->decimal('end_rest_debet_amount', 15, 2)->nullable();
            $table->decimal('unpaid_credit_amount', 15, 2)->nullable();
            $table->decimal('vozvrat_debet_amount', 15, 2)->nullable();
            $table->decimal('contract_debet_amount', 15, 2)->nullable();
            $table->string('edu_contract_type_code', 20)->nullable();
            $table->string('edu_contract_type_name')->nullable();
            $table->decimal('end_rest_credit_amount', 15, 2)->nullable();
            $table->decimal('begin_rest_debet_amount', 15, 2)->nullable();
            $table->decimal('begin_rest_credit_amount', 15, 2)->nullable();
            $table->string('edu_contract_sum_type_code', 20)->nullable();
            $table->string('edu_contract_sum_type_name')->nullable();
            $table->unsignedInteger('hemis_created_at')->nullable();
            $table->unsignedInteger('hemis_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_list');
    }
};
