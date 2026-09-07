<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_publication_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('access_code'); // hashed, per employee
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['app_publication_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_users');
    }
};
