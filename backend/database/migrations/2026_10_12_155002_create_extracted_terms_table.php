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
        Schema::create('extracted_terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('analysis_id')->constrained('analyses')->cascadeOnDelete();
            $table->string('key', 60);
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 14, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->boolean('found');
            $table->smallInteger('page_number')->nullable();
            $table->string('source_quote', 500)->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->boolean('corrected_by_user')->default(false);
        });

        DB::statement('CREATE UNIQUE INDEX extracted_terms_analysis_id_key_unique ON extracted_terms (analysis_id, key)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extracted_terms');
    }
};
