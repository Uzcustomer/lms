<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('akademik_mobillik_arizalar', function (Blueprint $table) {
            $table->string('basis_document_path')->nullable()->after('document_size');
            $table->string('basis_document_name')->nullable()->after('basis_document_path');
            $table->string('basis_document_mime', 120)->nullable()->after('basis_document_name');
            $table->unsignedBigInteger('basis_document_size')->nullable()->after('basis_document_mime');
        });
    }

    public function down(): void
    {
        Schema::table('akademik_mobillik_arizalar', function (Blueprint $table) {
            $table->dropColumn([
                'basis_document_path',
                'basis_document_name',
                'basis_document_mime',
                'basis_document_size',
            ]);
        });
    }
};
