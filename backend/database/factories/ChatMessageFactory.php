<?php

namespace Database\Factories;

use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chat_session_id' => ChatSession::factory(),
            'role' => 'user',
            'content' => 'What is the notice period?',
            'citations' => json_encode([['page' => 7, 'chunk_id' => '...', 'quote' => '...']]),
            'answer_found' => true,
            'input_tokens' => 10,
            'output_tokens' => 25,
        ];
    }
}
