<?php

namespace App\Services\AI;

use App\Models\UsageEvent;
use Carbon\Carbon;

class BudgetGuard
{
    /**
     * Determine if the daily AI budget limit ($2.00) has been reached.
     * Resets at 00:00 Asia/Riyadh time (UTC+3) for both pipeline and chat.
     */
    public function isBudgetReached(): bool
    {
        return $this->getSpentToday() >= $this->getDailyBudget();
    }

    /**
     * Get the total amount in USD spent on AI calls today since 00:00 Asia/Riyadh.
     */
    public function getSpentToday(): float
    {
        $startOfDayRiyadh = Carbon::now('Asia/Riyadh')->startOfDay()->setTimezone('UTC');

        return (float) UsageEvent::query()
            ->where('created_at', '>=', $startOfDayRiyadh)
            ->sum('cost_usd');
    }

    /**
     * Get the configured daily AI budget in USD.
     */
    public function getDailyBudget(): float
    {
        return (float) config('bayanuk.ai_budget.daily_usd', 2.00);
    }

    /**
     * Get the remaining AI budget for today in USD.
     */
    public function getRemainingBudget(): float
    {
        return max(0.0, $this->getDailyBudget() - $this->getSpentToday());
    }

    /**
     * Next reset timestamp (00:00 Asia/Riyadh).
     */
    public function nextResetAt(): Carbon
    {
        return Carbon::now('Asia/Riyadh')->endOfDay()->addSecond()->setTimezone('UTC');
    }
}
