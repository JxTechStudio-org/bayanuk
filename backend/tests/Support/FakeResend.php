<?php

namespace Tests\Support;

use Illuminate\Support\Str;
use Mockery;
use Resend\Client;
use Resend\Laravel\Facades\Resend;
use Resend\Service\Email;
use Throwable;

/**
 * Replaces the Resend SDK client, so tests see the exact email the application would send
 * (recipient, subject, HTML and the sign-in link inside it) without calling the real API.
 */
final class FakeResend
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public static function install(?Throwable $failWith = null): self
    {
        $fake = new self;

        $emails = Mockery::mock(Email::class);
        $emails->shouldReceive('send')->andReturnUsing(function (array $payload) use ($fake, $failWith) {
            if ($failWith !== null) {
                throw $failWith;
            }

            $fake->sent[] = $payload;

            $email = Mockery::mock(\Resend\Email::class);
            $email->id = 'email_'.Str::random(8);

            return $email;
        });

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('emails')->andReturn($emails);

        Resend::swap($client);

        return $fake;
    }

    /** The raw magic-link token found in the link of the last email sent. */
    public function lastToken(): string
    {
        $html = (string) ($this->sent[array_key_last($this->sent)]['html'] ?? '');

        preg_match('#/auth/verify\?token=([A-Za-z0-9]+)#', $html, $matches);

        return $matches[1] ?? '';
    }
}
