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
        Schema::create('findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('analysis_id')->constrained()->cascadeOnDelete()->index();
            $table->string('category', 40);
            $table->string('severity', 10);
            $table->string('source', 10);
            $table->string('rule_id', 40)->nullable();
            $table->string('title', 160);
            $table->text('explanation');
            $table->smallInteger('page_number')->nullable();
            $table->string('source_quote', 500)->nullable();
            $table->smallInteger('sort_order')->default(0);
        });

        DB::statement("ALTER TABLE findings ADD CONSTRAINT findings_severity_check CHECK (severity IN ('info', 'attention', 'high'))");
        DB::statement("ALTER TABLE findings ADD CONSTRAINT findings_source_check CHECK (source IN ('rule', 'ai'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
