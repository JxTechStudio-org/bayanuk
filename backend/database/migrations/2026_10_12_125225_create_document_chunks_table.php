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
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->index()->constrained()->cascadeOnDelete();
            $table->smallInteger('page_number');
            $table->smallInteger('chunk_index');
            $table->text('content');
            $table->smallInteger('token_count');
            $table->vector('embedding', 1536);
            $table->string('embedding_model', 60);
        });

        DB::statement('CREATE INDEX document_chunks_embedding_hnsw_index ON document_chunks USING hnsw (embedding vector_cosine_ops)');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
