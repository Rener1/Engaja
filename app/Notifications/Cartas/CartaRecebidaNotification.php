<?php

namespace App\Notifications\Cartas;

use App\Models\Cartas\Carta;
use App\Support\CartasUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CartaRecebidaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Carta $carta) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = CartasUrl::route('cartas.cartas.show', $this->carta);

        $remetenteNome = $this->carta->educando?->user?->name ?? 'um educando';

        $ultimaMensagem = $this->carta->mensagens->last() ?? $this->carta->ultimaMensagem;
        $isPrimeiraVez = $ultimaMensagem ? $ultimaMensagem->rodada === 1 : $this->carta->mensagens->count() <= 1;

        $subject = $isPrimeiraVez
            ? 'Cartas para Esperançar - Chegou uma carta para você!'
            : 'Cartas para Esperançar - Sua carta foi respondida!';

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.cartas.carta-recebida', [
                'voluntarioNome' => $notifiable->name,
                'remetenteNome' => $remetenteNome,
                'isPrimeiraVez' => $isPrimeiraVez,
                'url' => $url,
            ]);
    }
}
