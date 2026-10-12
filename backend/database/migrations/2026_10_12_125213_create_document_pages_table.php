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
        Schema::create('document_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('page_number');
            $table->text('text');
            $table->integer('char_count');
            $table->string('extraction_method', 20);

            $table->unique(['document_id', 'page_number']);
        });

        DB::statement("ALTER TABLE document_pages ADD CONSTRAINT document_pages_extraction_method_check CHECK (extraction_method IN ('text_layer', 'ocr', 'vision'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_pages');
    }
};
