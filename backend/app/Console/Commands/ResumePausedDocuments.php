<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Jobs\Pipeline\ExtractText;
use App\Models\Document;
use App\Services\AI\BudgetGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Resumes documents that were paused due to daily AI budget exhaustion (status_detail = 'ai_budget_reached').
 * Runs after daily budget resets at 00:00 Asia/Riyadh.
 *
 * References:
 * - Technical Plan v1.3 (Section 5, Step 7 box)
 * - Integration Contract (Section 6, Daily AI budget)
 */
class ResumePausedDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:resume-paused';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resume documents paused by the daily AI budget after reset at 00:00 Asia/Riyadh';

    /**
     * Execute the console command.
     */
    public function handle(?BudgetGuard $budgetGuard = null): int
    {
        $budgetGuard = $budgetGuard ?? app(BudgetGuard::class);

        // If daily budget is somehow still reached, do not resume
        if ($budgetGuard->isBudgetReached()) {
            $this->warn('Daily AI budget is currently exhausted. Paused documents cannot be resumed yet.');

            return Command::SUCCESS;
        }

        $pausedDocuments = Document::query()
            ->where('status_detail', 'ai_budget_reached')
            ->get();

        if ($pausedDocuments->isEmpty()) {
            $this->info('No paused documents found to resume.');

            return Command::SUCCESS;
        }

        $resumedCount = 0;

        foreach ($pausedDocuments as $document) {
            // Clear the paused reason
            $document->update([
                'status_detail' => null,
            ]);

            // Re-dispatch the appropriate pipeline job based on current document status
            if ($document->status === DocumentStatus::EXTRACTING) {
                ExtractText::dispatch($document);
                $resumedCount++;
            }

            Log::info("Resumed document {$document->id} from ai_budget_reached pause", [
                'document_id' => $document->id,
                'status' => $document->status->value,
            ]);
        }

        $this->info("Resumed {$resumedCount} paused document(s).");

        return Command::SUCCESS;
    }
}
