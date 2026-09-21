<?php

namespace App\Notifications;

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
            ->subject('Engaja - Confirme a reativação da sua conta')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Encontramos uma conta desativada com este e-mail. Para reativá-la com os novos dados informados no cadastro, confirme clicando no botão abaixo.')
            ->action('Reativar minha conta', $this->url)
            ->line('Se você não solicitou isso, ignore este e-mail — nada será alterado.');
    }
}
