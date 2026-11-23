<?php

namespace App\Http\Controllers;

use App\Enums\OrigemAutorizacaoImagem;
use App\Exceptions\AutorizacaoImagemConflitoException;
use App\Http\Requests\ResponderAutorizacaoImagemRequest;
use App\Models\Atividade;
use App\Services\AutorizacaoImagemContexto;

class PresencaAutorizacaoImagemController extends Controller
{
    public function store(ResponderAutorizacaoImagemRequest $request, Atividade $atividade, AutorizacaoImagemContexto $contextos)
    {
        $contexto = $contextos->resolver($request->string('contexto')->toString(), $request->session());
        $origem = OrigemAutorizacaoImagem::from($contexto['origem']);
        abort_unless(in_array($origem, [OrigemAutorizacaoImagem::PresencaPublica, OrigemAutorizacaoImagem::PresencaAutenticada], true), 403);
        $usuario = $origem === OrigemAutorizacaoImagem::PresencaAutenticada ? $request->user() : null;
        abort_if($origem === OrigemAutorizacaoImagem::PresencaAutenticada && ! $usuario, 403);

        try {
            $contextos->responder($request->input('contexto'), $request->boolean('autorizado'), $request->session(), $origem, $usuario, atividade: $atividade);
        } catch (AutorizacaoImagemConflitoException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return back()->withErrors(['autorizacao_imagem' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Resposta registrada. Sua presença continua confirmada.']);
        }

        $request->session()->keep(['success-presenca', 'usuario_nome', 'evento_nome', 'atividade_nome', 'dia', 'status_presenca_label', 'artigo_status_presenca', 'avaliacao_token', 'avaliacao_disponivel']);

        return back()->with('success', 'Resposta sobre o uso de imagem registrada.');
    }
}
