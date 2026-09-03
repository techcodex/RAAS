<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // The strategy actually used for the current chunks (may differ from the
            // project default, and "auto" is resolved to a concrete name here).
            $table->string('chunking_strategy')->nullable();
            $table->json('chunking_config')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->timestamp('processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['chunking_strategy', 'chunking_config', 'chunk_count', 'processed_at']);
        });
    }
};
