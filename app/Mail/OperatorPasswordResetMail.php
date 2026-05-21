<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Operator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperatorPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Operator $operator,
        public readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'meWork360 — Redefinição de Senha',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.operator-password-reset',
        );
    }
}
