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
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('guest_token_hash', 64)->nullable()->index();
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->integer('file_size');
            $table->char('sha256', 64);
            $table->string('storage_key', 255)->unique();
            $table->smallInteger('page_count')->nullable();
            $table->string('language', 5)->nullable();
            $table->string('document_type', 30)->nullable();
            $table->string('status', 20)->index();
            $table->string('status_detail', 255)->nullable();
            $table->boolean('is_scanned')->default(false);
            $table->timestampTz('processing_started_at')->nullable();
            $table->timestampTz('processing_finished_at')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();

            $table->index(['user_id', 'sha256']);

        });

        DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_file_size_check CHECK (file_size <= 10485760)');
        DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_page_count_check CHECK (page_count <= 30)');
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_language_check CHECK (language IN ('ar', 'en', 'mixed'))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_document_type_check CHECK (document_type IN ('job_offer', 'employment_contract', 'other'))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('uploaded', 'scanning', 'extracting', 'classifying', 'analyzing', 'indexing', 'ready', 'failed', 'rejected'))");
        DB::statement('CREATE INDEX documents_user_id_created_at_index ON documents (user_id, created_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
