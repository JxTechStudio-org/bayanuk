<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id(); 
            $table->uuid('user_id')->nullable();
            $table->char('guest_token_hash', 64)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->string('event', 30);
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 5)->default(0);
            $table->timestampTz('created_at');
        });

        DB::statement('CREATE INDEX usage_events_user_id_created_at_index ON usage_events (user_id, created_at)');
        DB::statement('CREATE INDEX usage_events_guest_token_hash_created_at_index ON usage_events (guest_token_hash, created_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
