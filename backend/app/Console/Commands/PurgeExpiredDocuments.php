<?php

namespace App\Console\Commands;

use App\Enums\DeletionReason;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeExpiredDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:purge-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge documents that have exceeded their retention period from storage and database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = Storage::disk('s3');
        $now = now();

        $expiredDocuments = Document::query()
            ->where('expires_at', '<', $now)
            ->get();

        $purgedCount = 0;
        $failedCount = 0;

        foreach ($expiredDocuments as $document) {
            // 1. Remove storage file from RustFS first
            if ($document->storage_key !== '' && $disk->exists($document->storage_key)) {
                $deleted = $disk->delete($document->storage_key);
                if (! $deleted) {
                    Log::error("Failed to delete storage file for expired document {$document->id} (key: {$document->storage_key})");
                    $failedCount++;

                    // Do not delete DB row if storage deletion fails
                    continue;
                }
            }

            // 2. Cascade delete database records and log retention deletion
            try {
                DB::transaction(function () use ($document) {
                    DB::table('deletion_logs')->insert([
                        'document_id' => $document->id,
                        'reason' => DeletionReason::RETENTION_EXPIRED->value,
                        'file_deleted' => true,
                        'deleted_at' => now(),
                    ]);

                    $document->delete();
                });

                $purgedCount++;
            } catch (Throwable $e) {
                Log::error("Failed to delete database record for expired document {$document->id}: {$e->getMessage()}");
                $failedCount++;
            }
        }

        $this->info("Purged {$purgedCount} expired documents. {$failedCount} failed.");

        return Command::SUCCESS;
    }
}
