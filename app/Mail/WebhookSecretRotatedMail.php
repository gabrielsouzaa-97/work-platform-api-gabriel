<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ClusterServer;
use App\Models\WebhookSecretHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebhookSecretRotatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ClusterServer $cluster,
        public readonly WebhookSecretHistory $newHistory,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[meWork360] Webhook secret rotacionado — {$this->cluster->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.webhook-secret-rotated',
        );
    }
}
