<?php

namespace App\Notifications\Cartas;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReativacaoCadastroNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $url) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cartas para Esperançar - Reative sua conta')
            ->view('emails.cartas.reativacao-conta', [
                'userName' => $notifiable->name,
                'url' => $this->url,
            ]);
    }
}
