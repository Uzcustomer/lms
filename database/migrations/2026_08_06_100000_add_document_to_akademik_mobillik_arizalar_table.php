<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('akademik_mobillik_arizalar', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('reason');
            $table->string('document_name')->nullable()->after('document_path');
            $table->string('document_mime', 120)->nullable()->after('document_name');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_mime');
        });
    }

    public function down(): void
    {
        Schema::table('akademik_mobillik_arizalar', function (Blueprint $table) {
            $table->dropColumn([
                'document_path',
                'document_name',
                'document_mime',
                'document_size',
            ]);
        });
    }
};
