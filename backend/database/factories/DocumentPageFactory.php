<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentPageFactory extends Factory
{
    protected $model = DocumentPage::class;

    public function definition(): array
    {
        $text = fake()->paragraph();

        return [
            'document_id' => Document::factory(),
            'page_number' => 1,
            'text' => $text,
            'char_count' => mb_strlen($text),
            'extraction_method' => 'text_layer',
        ];
    }
}