<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MotDePasseTemporaireMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nomDestinataire,
        public string $email,
        public string $motDePasse,
        public bool $estNouveauCompte = true,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->estNouveauCompte
                ? 'Votre accès à la plateforme AMANAH'
                : 'Réinitialisation de votre mot de passe — AMANAH',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mot-de-passe-temporaire',
        );
    }
}
