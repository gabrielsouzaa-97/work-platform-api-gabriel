<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Operator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperatorInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Operator $operator,
        public readonly string $signedUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Convite meWork360 — Acesso ao Painel',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.operator-invite',
        );
    }
}
