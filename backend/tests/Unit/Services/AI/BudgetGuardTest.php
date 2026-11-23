<?php

use App\Models\UsageEvent;
use App\Services\AI\BudgetGuard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->guard = new BudgetGuard;
});

it('returns false for budget reached when no events exist today', function () {
    expect($this->guard->isBudgetReached())->toBeFalse()
        ->and($this->guard->getSpentToday())->toBe(0.0)
        ->and($this->guard->getRemainingBudget())->toBe(2.0);
});

it('calculates spent amount accurately for today in Asia/Riyadh time', function () {
    $todayRiyadh = Carbon::now('Asia/Riyadh')->startOfDay()->addHours(3)->setTimezone('UTC');

    UsageEvent::create([
        'event' => 'analysis',
        'cost_usd' => 0.50,
        'input_tokens' => 1000,
        'output_tokens' => 200,
        'created_at' => $todayRiyadh,
    ]);

    UsageEvent::create([
        'event' => 'chat_question',
        'cost_usd' => 0.25,
        'input_tokens' => 500,
        'output_tokens' => 100,
        'created_at' => $todayRiyadh,
    ]);

    expect($this->guard->getSpentToday())->toBe(0.75)
        ->and($this->guard->isBudgetReached())->toBeFalse()
        ->and($this->guard->getRemainingBudget())->toBe(1.25);
});

it('determines budget is reached when spend meets or exceeds daily limit ($2.00)', function () {
    $todayRiyadh = Carbon::now('Asia/Riyadh')->startOfDay()->addHours(5)->setTimezone('UTC');

    UsageEvent::create([
        'event' => 'analysis',
        'cost_usd' => 2.05,
        'created_at' => $todayRiyadh,
    ]);

    expect($this->guard->isBudgetReached())->toBeTrue()
        ->and($this->guard->getRemainingBudget())->toBe(0.0);
});

it('ignores usage events from yesterday before Riyadh midnight (00:00)', function () {
    $yesterdayRiyadh = Carbon::now('Asia/Riyadh')->startOfDay()->subHours(2)->setTimezone('UTC');

    UsageEvent::create([
        'event' => 'analysis',
        'cost_usd' => 3.00,
        'created_at' => $yesterdayRiyadh,
    ]);

    expect($this->guard->getSpentToday())->toBe(0.0)
        ->and($this->guard->isBudgetReached())->toBeFalse();
});
