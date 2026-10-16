<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Monitoring moduli yadrosi (TZ 3-bo'lim): detektorlar, ishga tushirishlar,
 * istisnolar, ularning izoh/dalillari va importlar jurnali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_detectors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('group_name');
            $table->string('title', 500);
            $table->text('rule_text');
            $table->text('legal_basis')->nullable();
            $table->text('detection_text')->nullable();
            $table->string('catalog_status', 20)->nullable()->comment('✅ ✏️ 🆕 ⚠ — katalogdagi holat');
            $table->json('params')->nullable()->comment('Chegara va muddatlar — paneldan tahrirlanadi');
            $table->string('severity', 20)->default('orta');
            $table->boolean('enabled')->default(false);
            $table->boolean('pilot')->default(false);
            $table->boolean('warning_mode')->default(false)->comment('Faqat ogohlantirish: mas\'ulga yuborilmaydi');
            $table->unsignedTinyInteger('due_workdays')->default(3);
            $table->string('default_owner_role', 50)->nullable();
            $table->nullableMorphs('default_owner', 'mon_detectors_default_owner_idx');
            $table->string('schedule', 50)->default('daily');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('monitoring_data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40)->comment('buyruqlar | tavsiya_etilganlar');
            $table->string('source', 20)->default('excel')->comment('excel | csv | hemis_api');
            $table->string('status', 20)->default('kutilmoqda')->comment('kutilmoqda | bajarildi | xato');
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('evidence_path')->nullable()->comment('Qog\'oz manba skani (masalan mandat)');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_ok')->default(0);
            $table->unsignedInteger('rows_error')->default(0);
            $table->json('errors')->nullable();
            $table->json('meta')->nullable()->comment('Ustun moslash, o\'quv yili va h.k.');
            $table->nullableMorphs('imported_by', 'mon_imports_imported_by_idx');
            $table->timestamps();

            $table->index(['kind', 'created_at']);
        });

        Schema::create('monitoring_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detector_id')->constrained('monitoring_detectors')->cascadeOnDelete();
            $table->string('status', 20)->default('ishlamoqda')->comment('ishlamoqda | ok | xato');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('found_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('closed_count')->default(0);
            $table->unsignedInteger('reopened_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('params_snapshot')->nullable();
            $table->nullableMorphs('triggered_by', 'mon_runs_triggered_by_idx');
            $table->foreignId('data_import_id')->nullable()->constrained('monitoring_data_imports')->nullOnDelete();
            $table->timestamps();

            $table->index(['detector_id', 'started_at']);
        });

        Schema::create('monitoring_istisnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detector_id')->constrained('monitoring_detectors')->cascadeOnDelete();
            $table->foreignId('first_run_id')->nullable()->constrained('monitoring_runs')->nullOnDelete();
            $table->foreignId('last_run_id')->nullable()->constrained('monitoring_runs')->nullOnDelete();
            $table->string('fingerprint', 191);
            $table->string('scope_key', 100)->nullable()->comment('Detektor shu run\'da tekshirgan qamrov (masalan semestr)');
            $table->string('subject_type', 30)->comment('talaba | buyruq | qaydnoma | ariza | tolov | guruh_fan');
            $table->string('subject_ref', 100)->nullable();
            $table->string('subject_label');
            $table->unsignedBigInteger('faculty_hemis_id')->nullable();
            $table->unsignedBigInteger('department_hemis_id')->nullable();
            $table->timestamp('found_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('due_at')->nullable();
            $table->nullableMorphs('owner', 'mon_istisnos_owner_idx');
            $table->nullableMorphs('assigned_by', 'mon_istisnos_assigned_by_idx');
            $table->timestamp('assigned_at')->nullable();
            $table->string('status', 20)->default('ochiq')
                ->comment('ochiq | javob_berildi | yopildi | asosli_istisno | qayta_ochildi');
            $table->boolean('warning_only')->default(false);
            $table->boolean('closed_by_system')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->nullableMorphs('closed_by', 'mon_istisnos_closed_by_idx');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->unique(['detector_id', 'fingerprint']);
            $table->index(['status', 'due_at']);
            $table->index(['detector_id', 'status']);
            $table->index('faculty_hemis_id');
        });

        Schema::create('monitoring_istisno_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('istisno_id')->constrained('monitoring_istisnos')->cascadeOnDelete();
            $table->nullableMorphs('author', 'mon_ist_comments_author_idx');
            $table->string('kind', 20)->default('izoh')->comment('javob | izoh | holat | tizim');
            $table->text('body')->nullable();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('monitoring_istisno_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('istisno_id')->constrained('monitoring_istisnos')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('monitoring_istisno_comments')->nullOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->nullableMorphs('uploaded_by', 'mon_ist_evidences_uploaded_by_idx');
            $table->timestamps();
        });

        // TZ 2-bo'lim: «Monitoring administratori» roli
        Role::firstOrCreate(['name' => 'monitoring_admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_istisno_evidences');
        Schema::dropIfExists('monitoring_istisno_comments');
        Schema::dropIfExists('monitoring_istisnos');
        Schema::dropIfExists('monitoring_runs');
        Schema::dropIfExists('monitoring_data_imports');
        Schema::dropIfExists('monitoring_detectors');
    }
};
