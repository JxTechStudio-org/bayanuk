<?php

namespace Database\Factories;

use App\Models\Analysis;
use Illuminate\Database\Eloquent\Factories\Factory;

class FindingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'analysis_id' => Analysis::factory(),
            'category' => 'non_compete',
            'severity' => 'attention',
            'source' => 'rule',
            'rule_id' => 'RULE_01',
            'title' => 'Non-compete clause detected',
            'explanation' => 'The contract contains a non-compete clause restricting work for 12 months.',
            'page_number' => 9,
            'source_quote' => 'The employee shall not work for competitors...',
            'sort_order' => 0,
        ];
    }
}
