<?php

namespace App\Notifications\Cartas;

use App\Models\Avaliacao;
use App\Support\CartasUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AvaliacaoEnviadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Avaliacao $avaliacao) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = CartasUrl::route('cartas.avaliacao.formulario', $this->avaliacao);

        $titulo = $this->avaliacao->descricao_universal
            ?: ($this->avaliacao->templateAvaliacao->nome ?? 'Avaliação');

        return (new MailMessage)
            ->subject('Cartas para Esperançar - Avaliação disponível')
            ->view('emails.cartas.avaliacao-enviada', [
                'voluntarioNome' => $notifiable->name,
                'titulo' => $titulo,
                'url' => $url,
            ]);
    }
}
