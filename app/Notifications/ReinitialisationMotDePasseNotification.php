<?php

namespace App\Notifications;

use App\Mail\ReinitialisationMotDePasseMail;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordBase;
use Illuminate\Notifications\Messages\MailMessage;

class ReinitialisationMotDePasseNotification extends ResetPasswordBase
{
    public function toMail($notifiable): MailMessage|ReinitialisationMotDePasseMail
    {
        $url = $this->resetUrl($notifiable);

        return (new ReinitialisationMotDePasseMail($url))->to($notifiable->getEmailForPasswordReset());
    }
}
