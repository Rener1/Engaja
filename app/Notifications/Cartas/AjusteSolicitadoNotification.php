<?php

namespace App\Notifications\Cartas;

use App\Models\Cartas\CartaMensagem;
use App\Support\CartasUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AjusteSolicitadoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CartaMensagem $mensagem) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = CartasUrl::route('cartas.cartas.show', $this->mensagem->carta_id);

        return (new MailMessage)
            ->subject('Cartas para Esperançar - Precisamos de um ajuste no seu envio.')
            ->view('emails.cartas.ajuste-solicitado', [
                'voluntarioNome' => $notifiable->name,
                'parecerVerificacao' => $this->mensagem->parecer_verificacao,
                'url' => $url,
            ]);
    }
}
