<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Default chunking + embedding settings for documents in this project.
            $table->string('chunking_strategy')->default('auto');
            $table->json('chunking_config')->nullable();
            $table->string('embedder_provider')->default('local');
            $table->string('embedder_model')->nullable();

            // Bound at first successful embed; the Qdrant collection is pinned to this.
            $table->string('embedding_model_id')->nullable();
            $table->unsignedInteger('embedding_dimension')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'chunking_strategy',
                'chunking_config',
                'embedder_provider',
                'embedder_model',
                'embedding_model_id',
                'embedding_dimension',
            ]);
        });
    }
};
