<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('akademik_mobillik_arizalar', function (Blueprint $table) {
            $table->string('curriculum_document_path')->nullable()->after('document_size');
            $table->string('curriculum_document_name')->nullable()->after('curriculum_document_path');
            $table->string('curriculum_document_mime', 120)->nullable()->after('curriculum_document_name');
            $table->unsignedBigInteger('curriculum_document_size')->nullable()->after('curriculum_document_mime');
        });
    }

    public function down(): void
    {
        Schema::table('akademik_mobillik_arizalar', function (Blueprint $table) {
            $table->dropColumn([
                'curriculum_document_path',
                'curriculum_document_name',
                'curriculum_document_mime',
                'curriculum_document_size',
            ]);
        });
    }
};
