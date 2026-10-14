<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'question_count' => 0,
        ];
    }
}
