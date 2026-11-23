<?php

namespace App\Services;

use App\Enums\OrigemAutorizacaoImagem;
use App\Exceptions\AutorizacaoImagemConflitoException;
use App\Models\Atividade;
use App\Models\AutorizacaoImagem;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Presenca;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

class AutorizacaoImagemContexto
{
    public const FLASH_KEY = 'autorizacao_imagem_contexto';

    private const SESSION_KEY = 'autorizacao_imagem.contextos';

    public function __construct(private readonly AutorizacaoImagemService $autorizacoes) {}

    public function paraPresenca(Presenca $presenca, Session $session, OrigemAutorizacaoImagem $origem, ?User $usuario = null): ?array
    {
        abort_unless(in_array($origem, [OrigemAutorizacaoImagem::PresencaPublica, OrigemAutorizacaoImagem::PresencaAutenticada], true), 403);
        $presenca->loadMissing(['atividade.evento', 'inscricao.participante.user']);
        $participante = $presenca->inscricao?->participante;
        $evento = $presenca->atividade?->evento;
        if (! $participante?->user?->isEngajaUser() || ! $evento || $presenca->status !== 'presente'
            || $this->autorizacoes->resposta($participante, $evento)) {
            return null;
        }

        return $this->criar($participante, $evento, $session, $origem, $usuario, $presenca);
    }

    public function paraFormulario(Participante $participante, Evento $evento, Session $session, OrigemAutorizacaoImagem $origem, User $usuario): array
    {
        abort_unless($usuario->isEngajaUser() && in_array($origem, [OrigemAutorizacaoImagem::Gestao, OrigemAutorizacaoImagem::Perfil], true), 403);
        abort_unless($this->autorizacoes->participantesDaAcao($evento)->whereKey($participante->id)->exists(), 404);
        if ($origem === OrigemAutorizacaoImagem::Gestao) {
            abort_unless($usuario->can('autorizacao-imagem.registrar'), 403);
        } else {
            abort_unless($participante->user_id === $usuario->id, 403);
            abort_unless($this->autorizacoes->resposta($participante, $evento) || $this->autorizacoes->temPresenca($participante, $evento), 403);
        }

        return $this->criar($participante, $evento, $session, $origem, $usuario);
    }

    public function resolver(string $token, Session $session): array
    {
        $contexto = $session->get($this->chave($token));
        if (! is_array($contexto) || ($contexto['expira_em'] ?? 0) <= now()->timestamp) {
            throw ValidationException::withMessages(['contexto' => 'Esta solicitação expirou. Reabra a página para responder; sua presença continua registrada.']);
        }

        return $contexto;
    }

    public function responder(
        string $token,
        bool $autorizado,
        Session $session,
        OrigemAutorizacaoImagem $origemEsperada,
        ?User $usuario = null,
        ?Atividade $atividade = null,
        ?Evento $evento = null,
        ?Participante $participante = null,
    ): AutorizacaoImagem {
        $contexto = $this->resolver($token, $session);
        abort_unless($contexto['origem'] === $origemEsperada->value, 403);
        abort_unless($contexto['usuario_id'] === $usuario?->id, 403);
        abort_if($atividade && $contexto['atividade_id'] !== $atividade->id, 403);
        abort_if($evento && $contexto['evento_id'] !== $evento->id, 403);
        abort_if($participante && $contexto['participante_id'] !== $participante->id, 403);

        $participante = Participante::findOrFail($contexto['participante_id']);
        $evento = Evento::findOrFail($contexto['evento_id']);
        if ($contexto['concluido']) {
            if ($contexto['decisao'] !== $autorizado) {
                throw new AutorizacaoImagemConflitoException('Esta solicitação já foi utilizada. Consulte suas autorizações para alterar a decisão.');
            }

            return $this->autorizacoes->resposta($participante, $evento) ?? abort(404);
        }

        $resposta = $this->autorizacoes->registrar(
            $participante, $evento, $autorizado,
            $origemEsperada, $usuario,
            $contexto['presenca_id'] ? Presenca::with(['atividade', 'inscricao'])->findOrFail($contexto['presenca_id']) : null,
            $contexto['versao_esperada'], $contexto['acao_nome'],
        );
        $contexto['concluido'] = true;
        $contexto['decisao'] = $autorizado;
        $session->put($this->chave($token), $contexto);

        return $resposta;
    }

    private function criar(Participante $participante, Evento $evento, Session $session, OrigemAutorizacaoImagem $origem, ?User $usuario, ?Presenca $presenca = null): array
    {
        $token = bin2hex(random_bytes(32));
        $inicial = in_array($origem, [OrigemAutorizacaoImagem::PresencaPublica, OrigemAutorizacaoImagem::PresencaAutenticada], true);
        $contextos = array_filter($session->get(self::SESSION_KEY, []), fn ($contexto) => ($contexto['expira_em'] ?? 0) > now()->timestamp);
        $contextos = array_slice($contextos, -299, null, true);
        $contextos[hash('sha256', $token)] = [
            'participante_id' => $participante->id, 'evento_id' => $evento->id,
            'atividade_id' => $presenca?->atividade_id, 'presenca_id' => $presenca?->id,
            'usuario_id' => $usuario?->id,
            'acao_nome' => $evento->nome, 'origem' => $origem->value,
            'versao_esperada' => $inicial ? null : ($this->autorizacoes->resposta($participante, $evento)?->versao ?? 0),
            'expira_em' => now()->addMinutes((int) config('autorizacao_imagem.contexto_minutos', 15))->timestamp,
            'concluido' => false, 'decisao' => null,
        ];
        $session->put(self::SESSION_KEY, $contextos);

        return [
            'token' => $token, 'participante_nome' => $participante->user?->name,
            'acao_nome' => $evento->nome, 'termo_texto' => config('autorizacao_imagem.texto'),
            'atividade_id' => $presenca?->atividade_id,
        ];
    }

    private function chave(string $token): string
    {
        return self::SESSION_KEY.'.'.hash('sha256', $token);
    }
}
