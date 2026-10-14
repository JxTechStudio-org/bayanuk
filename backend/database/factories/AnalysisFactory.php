<?php

namespace Database\Factories;

use App\Models\Analysis;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalysisFactory extends Factory
{
    protected $model = Analysis::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'status' => 'succeeded',
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'prompt_version' => '1.0',
            'schema_version' => '1.0',
            'result' => [
                'document_type' => 'employment_contract',
                'language' => 'ar',
                'probation_days' => ['value' => 90, 'page' => 2, 'quote' => 'فترة التجربة 90 يوماً']
            ],
            'summary' => fake()->paragraph(),
            'summary_language' => 'ar',
            'input_tokens' => 1200,
            'output_tokens' => 400,
            'cost_usd' => 0.01500,
            'duration_ms' => 1500,
        ];
    }
}