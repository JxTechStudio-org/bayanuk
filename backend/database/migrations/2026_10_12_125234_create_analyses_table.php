<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('provider', 30);
            $table->string('model', 80);
            $table->string('prompt_version', 20);
            $table->string('schema_version', 20);
            $table->jsonb('result')->nullable();
            $table->text('summary')->nullable();
            $table->string('summary_language', 2)->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 5)->default(0);
            $table->integer('duration_ms')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE analyses ADD CONSTRAINT analyses_status_check CHECK (status IN ('pending', 'running', 'succeeded', 'failed'))");
        DB::statement('CREATE INDEX analyses_document_id_created_at_index ON analyses (document_id, created_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
