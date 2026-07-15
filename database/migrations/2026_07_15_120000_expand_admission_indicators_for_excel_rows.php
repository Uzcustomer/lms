<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_indicators', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_indicators', 't_r')) {
                $table->unsignedInteger('t_r')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'jshshir_kod')) {
                $table->string('jshshir_kod', 14)->nullable()->index();
            }
            if (!Schema::hasColumn('admission_indicators', 'student_id')) {
                $table->unsignedBigInteger('student_id')->nullable()->index();
            }
            if (!Schema::hasColumn('admission_indicators', 'full_name')) {
                $table->string('full_name')->nullable()->index();
            }
            if (!Schema::hasColumn('admission_indicators', 'farmoyish')) {
                $table->string('farmoyish')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'buyruq')) {
                $table->string('buyruq')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'fuqarolik')) {
                $table->string('fuqarolik')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'davlat')) {
                $table->string('davlat')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'millat')) {
                $table->string('millat')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'viloyat')) {
                $table->string('viloyat')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'tuman')) {
                $table->string('tuman')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'jinsi')) {
                $table->string('jinsi', 20)->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'tugilgan_sana')) {
                $table->date('tugilgan_sana')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'passport_raqami')) {
                $table->string('passport_raqami', 30)->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'jshshir_kod_2')) {
                $table->string('jshshir_kod_2', 14)->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'kurs')) {
                $table->unsignedSmallInteger('kurs')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'fakultet')) {
                $table->string('fakultet')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'talim_tili')) {
                $table->string('talim_tili', 80)->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'oquv_yili')) {
                $table->string('oquv_yili', 30)->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'talaba_toifasi')) {
                $table->string('talaba_toifasi')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'imtiyoz_toifasi')) {
                $table->string('imtiyoz_toifasi')->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'toplagan_bali')) {
                $table->decimal('toplagan_bali', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'otish_bali')) {
                $table->decimal('otish_bali', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'tolov_kontrakt_barobari_bazaviy')) {
                $table->decimal('tolov_kontrakt_barobari_bazaviy', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'tolov_kontrakt_shartnoma_summasi')) {
                $table->decimal('tolov_kontrakt_shartnoma_summasi', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('admission_indicators', 'kvota')) {
                $table->unsignedInteger('kvota')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('admission_indicators', function (Blueprint $table) {
            $columns = [
                't_r',
                'jshshir_kod',
                'student_id',
                'full_name',
                'farmoyish',
                'buyruq',
                'fuqarolik',
                'davlat',
                'millat',
                'viloyat',
                'tuman',
                'jinsi',
                'tugilgan_sana',
                'passport_raqami',
                'jshshir_kod_2',
                'kurs',
                'fakultet',
                'talim_tili',
                'oquv_yili',
                'talaba_toifasi',
                'imtiyoz_toifasi',
                'toplagan_bali',
                'otish_bali',
                'tolov_kontrakt_barobari_bazaviy',
                'tolov_kontrakt_shartnoma_summasi',
                'kvota',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('admission_indicators', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
