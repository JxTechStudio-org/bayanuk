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
        Schema::create('analysis_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('analysis_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('rating');
            $table->string('field_key', 60)->nullable();
            $table->string('comment', 1000)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE analysis_feedback ADD CONSTRAINT analysis_feedback_rating_check CHECK (rating IN (-1, 1))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_feedback');
    }
};
