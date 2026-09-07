<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_publication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();

            $table->index(['app_user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_conversations');
    }
};
