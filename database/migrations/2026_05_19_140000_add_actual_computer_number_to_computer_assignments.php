<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_assignments', function (Blueprint $table) {
            // PC the student physically sat at when the Moodle attempt
            // started, resolved from the request IP. NULL means the
            // attempt hasn't started yet or the IP could not be resolved
            // to a known computer (e.g. proxy/reverseproxy misconfig).
            $table->unsignedSmallInteger('actual_computer_number')
                ->nullable()
                ->after('moodle_attempt_id');
            $table->index(
                ['actual_computer_number', 'status'],
                'comp_assign_actual_pc_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('computer_assignments', function (Blueprint $table) {
            $table->dropIndex('comp_assign_actual_pc_status_idx');
            $table->dropColumn('actual_computer_number');
        });
    }
};
