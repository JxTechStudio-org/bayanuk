<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->index(['analysis_id', 'category', 'severity']);
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->change()->constrained('users')->nullOnDelete();
        });

        Schema::table('usage_events', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropIndex(['analysis_id', 'category', 'severity']);
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('usage_events', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
