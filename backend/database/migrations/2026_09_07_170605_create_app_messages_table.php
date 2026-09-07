<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // user | assistant
            $table->longText('content');
            $table->json('citations')->nullable();
            $table->timestamps();

            $table->index(['app_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_messages');
    }
};
