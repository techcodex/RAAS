<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Set while the project's Qdrant collection is being rebuilt under a
            // newly-selected embedding model. Null = steady state.
            $table->string('reembed_status')->nullable()->after('embedding_dimension');
            $table->text('reembed_error')->nullable()->after('reembed_status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['reembed_status', 'reembed_error']);
        });
    }
};
