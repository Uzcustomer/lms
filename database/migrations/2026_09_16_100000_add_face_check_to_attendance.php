<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Second layer for beacon attendance: the selfie taken at confirm time is
 * matched against the student's approved photo (ArcFace microservice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // Per-session switch so a teacher can run a lesson without the
            // face layer (e.g. a group whose photos are not approved yet).
            $table->boolean('require_face')->default(false)->after('beacon_id');
        });

        Schema::table('attendance_confirmations', function (Blueprint $table) {
            // null = not checked (disabled / no reference / service down),
            // true = matched, false = checked and did not match.
            $table->boolean('face_verified')->nullable()->after('rssi');
            $table->decimal('face_similarity', 5, 2)->nullable()->after('face_verified');
            $table->string('face_note', 32)->nullable()->after('face_similarity');
            $table->dateTime('face_checked_at')->nullable()->after('face_note');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn('require_face');
        });

        Schema::table('attendance_confirmations', function (Blueprint $table) {
            $table->dropColumn(['face_verified', 'face_similarity', 'face_note', 'face_checked_at']);
        });
    }
};
