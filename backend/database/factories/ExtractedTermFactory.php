<?php

namespace Database\Factories;

use App\Models\Analysis;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExtractedTermFactory extends Factory
{
    public function definition(): array
    {
        return [
            'analysis_id' => Analysis::factory(),
            'key' => 'compensation.basic_salary',
            'value_text' => '8000',
            'value_number' => 8000.00,
            'unit' => 'SAR',
            'found' => true,
            'page_number' => 3,
            'source_quote' => 'Basic salary is 8000 SAR',
            'confidence' => 0.95, 
            'corrected_by_user' => false,
        ];
    }
}