<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('timetable_cards')) {
            return;
        }

        Schema::table('timetable_cards', function (Blueprint $table) {
            if (!Schema::hasColumn('timetable_cards', 'placement_reason_code')) {
                $table->string('placement_reason_code', 80)->nullable()->index();
            }
            if (!Schema::hasColumn('timetable_cards', 'placement_reason')) {
                $table->text('placement_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('timetable_cards')) {
            return;
        }

        Schema::table('timetable_cards', function (Blueprint $table) {
            if (Schema::hasColumn('timetable_cards', 'placement_reason')) {
                $table->dropColumn('placement_reason');
            }
            if (Schema::hasColumn('timetable_cards', 'placement_reason_code')) {
                $table->dropIndex(['placement_reason_code']);
                $table->dropColumn('placement_reason_code');
            }
        });
    }
};
