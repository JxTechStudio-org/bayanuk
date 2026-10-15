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
        Schema::create('deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('document_id');
            $table->string('reason', 20);
            $table->boolean('file_deleted');
            $table->timestampTz('deleted_at');
        });

        DB::statement("ALTER TABLE deletion_logs ADD CONSTRAINT deletion_logs_reason_check CHECK (reason IN ('user_request', 'retention_expired', 'account_deleted'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deletion_logs');
    }
};
