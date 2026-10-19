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
        Schema::table('document_chunks', function (Blueprint $table) {
            $table->dropIndex('document_chunks_embedding_hnsw_index');
        });

        // Vectors from another model cannot be cast to a new size; they must be regenerated.
        DB::table('document_chunks')->truncate();

        DB::statement('ALTER TABLE document_chunks ALTER COLUMN embedding TYPE vector(1024)');

        DB::statement('CREATE INDEX document_chunks_embedding_hnsw_index ON document_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_chunks', function (Blueprint $table) {
            $table->dropIndex('document_chunks_embedding_hnsw_index');
        });

        DB::table('document_chunks')->truncate();

        DB::statement('ALTER TABLE document_chunks ALTER COLUMN embedding TYPE vector(1536)');

        DB::statement('CREATE INDEX document_chunks_embedding_hnsw_index ON document_chunks USING hnsw (embedding vector_cosine_ops)');
    }
};
