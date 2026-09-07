<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Max documents the organization may retain across all its projects.
            // Null falls back to raas.organizations.default_document_limit.
            $table->unsignedInteger('document_limit')->nullable()->after('owner_id');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('document_limit');
        });
    }
};
