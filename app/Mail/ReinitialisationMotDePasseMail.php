<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReinitialisationMotDePasseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $lienReinitialisation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Réinitialisation de votre mot de passe — AMANAH',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reinitialisation-mot-de-passe',
        );
    }
}
