<?php

namespace App\Http\Controllers\Cartas;

use App\Http\Controllers\Controller;
use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaEvento;
use App\Models\Cartas\CartaMensagem;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Municipio;
use App\Models\User;
use App\Notifications\Cartas\AjusteSolicitadoNotification;
use App\Notifications\Cartas\CartaRecebidaNotification;
use App\Services\Cartas\CartaAprovacaoService;
use App\Services\Cartas\CartaTimbradoService;
use App\Services\Cartas\CartaViewerLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartaController extends Controller
{
    public function __construct(
        private CartaTimbradoService $timbrado,
        private CartaViewerLogger $viewerLog,
        private CartaAprovacaoService $aprovacao,
    ) {}

    public function dashboard(Request $request): View
    {
        $user = $request->user();

        if ($this->isGestor($user)) {
            return $this->gestorDashboard($request);
        }

        return $this->voluntarioDashboard($request);
    }

    public function show(Request $request, Carta $carta): View
    {
        $this->authorizeCartaAccess($request, $carta);

        $carta->load([
            'educando.user',
            'educando.municipio.estado.regiao',
            'voluntario.participante.municipio.estado.regiao',
            'ultimaMensagem',
            'mensagens.remetenteUsuario.participante.municipio.estado.regiao',
            'mensagens.remetenteParticipante.user',
            'mensagens.remetenteParticipante.municipio.estado.regiao',
            'mensagens.destinatarioUsuario.participante.municipio.estado.regiao',
            'mensagens.destinatarioParticipante.user',
            'mensagens.destinatarioParticipante.municipio.estado.regiao',
        ]);

        $gestor = $this->isGestor($request->user());
        if (! $gestor) {
            $this->markConversationAsRead($request->user(), $carta);
            $carta->load('mensagens');
        }

        $engajaUsers = $gestor ? $this->engajaUsersQuery()->get() : collect();
        $voluntarios = $gestor ? $this->voluntariosQuery()->get() : collect();

        $this->viewerLog->aberturaDaCarta($carta, $gestor ? 'gestor' : 'voluntario', $request->user()->id);

        return view('cartas.cartas.show', compact('carta', 'gestor', 'engajaUsers', 'voluntarios'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isGestor($request->user()), 403);

        $data = $request->validate([
            'fluxo' => ['nullable', Rule::in(['automatico', 'manual'])],
            'voluntario_user_id' => ['exclude_unless:fluxo,manual', 'required', 'integer', 'exists:users,id'],
            'arquivo_resposta' => ['exclude_unless:fluxo,manual', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'envio_token' => ['required', 'uuid'],
            'remetente_user_id' => ['required', 'integer', 'exists:users,id'],
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'envio_token.required' => 'Atualize a página e tente enviar a carta novamente.',
            'envio_token.uuid' => 'Atualize a página e tente enviar a carta novamente.',
            'remetente_user_id.required' => 'Selecione o remetente.',
            'arquivo.required' => 'Selecione o arquivo da carta.',
            'arquivo.mimes' => 'Envie um arquivo em PDF.',
        ]);

        $file = $request->file('arquivo');
        $envioToken = strtolower($data['envio_token']);
        $dadosHash = [
            (int) $data['remetente_user_id'],
            hash('sha256', $file->getClientOriginalName()),
            hash_file('sha256', $file->getRealPath()),
        ];
        $manual = ($data['fluxo'] ?? 'automatico') === 'manual';
        $resposta = $manual ? $request->file('arquivo_resposta') : null;
        if ($manual && ! $this->remetenteCandidatosQuery()->whereKey($data['remetente_user_id'])->exists()) {
            throw ValidationException::withMessages(['remetente_user_id' => 'Selecione um educando da lista de remetentes.']);
        }
        if ($manual) {
            $dadosHash[] = (int) $data['voluntario_user_id'];
            $dadosHash[] = $resposta ? hash_file('sha256', $resposta->getRealPath()) : null;
            $dadosHash[] = $resposta?->getClientOriginalName();
        }
        $envioHash = hash('sha256', json_encode($dadosHash, JSON_THROW_ON_ERROR));

        if ($this->cartaDoEnvio($request->user(), $envioToken, $envioHash)) {
            return redirect()->route('cartas.dashboard')->with('status', $manual ? 'Conversa registrada.' : 'Carta enviada para o voluntário.');
        }

        $remetente = User::with('participante')->findOrFail($data['remetente_user_id']);
        $participante = $remetente->participante;

        if (! $participante) {
            return back()->withErrors(['remetente_user_id' => 'O remetente selecionado nao possui participante vinculado.'])->withInput();
        }

        $eventosCartas = Evento::where('is_cartas', true)->get();
        abort_if($eventosCartas->count() > 1, 500, 'Configuração inválida: mais de uma ação de cartas está marcada como ativa.');
        $eventoCartas = $eventosCartas->first();

        if (! $eventoCartas) {
            return back()->withErrors(['remetente_user_id' => 'Nenhuma ação de cartas está configurada.'])->withInput();
        }

        $voluntario = $this->selectVoluntario($remetente, $manual ? (int) $data['voluntario_user_id'] : null);
        if (! $voluntario) {
            return back()->withErrors($manual
                ? ['voluntario_user_id' => 'Selecione um voluntário válido que não seja o próprio remetente.']
                : ['destinatario' => 'Não há voluntários disponíveis para receber a carta.'])->withInput();
        }

        try {
            $carta = DB::transaction(function () use ($request, $participante, $voluntario, $file, $eventoCartas, $envioToken, $envioHash, $resposta) {
                if (! $participante->inscricoes()->where('evento_id', $eventoCartas->id)->exists()) {
                    Inscricao::create([
                        'evento_id' => $eventoCartas->id,
                        'participante_id' => $participante->id,
                    ]);
                }

                $codigo = $this->nextCodigo();

                $carta = Carta::create([
                    'envio_token' => $envioToken,
                    'envio_hash' => $envioHash,
                    'codigo' => $codigo,
                    'educando_participante_id' => $participante->id,
                    'voluntario_user_id' => $voluntario->id,
                    'municipio_id' => $participante->municipio_id,
                    'status' => Carta::STATUS_AGUARDANDO_VOLUNTARIO,
                    'distribuida_em' => now(),
                    'criada_por' => $request->user()->id,
                    'atualizada_por' => $request->user()->id,
                ]);

                $path = $file->store("cartas/{$carta->id}/originais", 'local');

                $mensagem = CartaMensagem::create([
                    'carta_id' => $carta->id,
                    'rodada' => 1,
                    'remetente_participante_id' => $participante->id,
                    'destinatario_user_id' => $voluntario->id,
                    'tipo_remetente' => CartaMensagem::TIPO_REMETENTE_EDUCANDO,
                    'canal_entrada' => CartaMensagem::CANAL_ANEXO_DIGITALIZADO,
                    'status' => CartaMensagem::STATUS_APROVADA,
                    'anexo_original_path' => $path,
                    'anexo_original_nome' => $file->getClientOriginalName(),
                    'anexo_original_mime' => $file->getClientMimeType(),
                    'anexo_original_tamanho' => $file->getSize(),
                    'enviada_em' => now(),
                    'criada_por' => $request->user()->id,
                    'atualizada_por' => $request->user()->id,
                ]);

                $this->timbrado->aplicarAnexo($mensagem);

                CartaEvento::create([
                    'carta_id' => $carta->id,
                    'carta_mensagem_id' => $mensagem->id,
                    'user_id' => $request->user()->id,
                    'tipo' => CartaEvento::TIPO_CRIADA,
                    'dados_depois' => [
                        'codigo' => $codigo,
                        'voluntario_user_id' => $voluntario->id,
                        'educando_participante_id' => $participante->id,
                    ],
                ]);

                if ($resposta) {
                    $this->registrarRespostaGestor($request, $carta, $resposta, 2);
                }

                return $carta;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // The unique index also protects requests that passed the initial check together.
            if (! $this->cartaDoEnvio($request->user(), $envioToken, $envioHash)) {
                throw $exception;
            }

            return redirect()->route('cartas.dashboard')->with('status', $manual ? 'Conversa registrada.' : 'Carta enviada para o voluntário.');
        }

        if (! $resposta) {
            $voluntario->notify(new CartaRecebidaNotification($carta->load('mensagens')));
        }

        return redirect()->route('cartas.dashboard')->with('status', $manual ? 'Conversa registrada.' : 'Carta enviada para o voluntário.');
    }

    public function storeMessage(Request $request, Carta $carta): RedirectResponse
    {
        abort_unless($this->isGestor($request->user()), 403);

        $data = $request->validate([
            'tipo_remetente' => ['nullable', Rule::in([CartaMensagem::TIPO_REMETENTE_EDUCANDO, CartaMensagem::TIPO_REMETENTE_VOLUNTARIO])],
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'arquivo.required' => 'Selecione o arquivo da carta.',
            'arquivo.mimes' => 'Envie um arquivo em PDF.',
        ]);

        $carta->loadMissing(['educando.user', 'voluntario', 'ultimaMensagem']);
        $participante = $carta->educando;
        $voluntario = $carta->voluntario;

        abort_unless($participante && $voluntario, 422);

        $tipo = $data['tipo_remetente'] ?? CartaMensagem::TIPO_REMETENTE_EDUCANDO;
        if ($carta->proximoTipoRemetente() !== $tipo) {
            return back()->withErrors(['arquivo' => 'A conversa mudou ou há uma resposta pendente. Atualize a página antes de anexar a próxima carta.']);
        }

        if ($tipo === CartaMensagem::TIPO_REMETENTE_VOLUNTARIO) {
            DB::transaction(function () use ($request, $carta) {
                $carta = Carta::whereKey($carta->id)->lockForUpdate()->firstOrFail();
                abort_unless($carta->podeVoluntarioEnviar(), 422);
                $this->registrarRespostaGestor($request, $carta, $request->file('arquivo'), ((int) $carta->mensagens()->max('rodada')) + 1);
            });

            return redirect()->route('cartas.cartas.show', $carta)->with('status', 'Resposta do voluntário adicionada.');
        }

        if (! $carta->podeEducandoEnviar()) {
            return back()->withErrors(['arquivo' => 'Aguarde a resposta do voluntário antes de enviar uma nova carta.']);
        }

        $file = $request->file('arquivo');

        DB::transaction(function () use ($request, $carta, $participante, $voluntario, $file) {
            $carta = Carta::whereKey($carta->id)->lockForUpdate()->firstOrFail();
            abort_unless($carta->podeEducandoEnviar(), 422);
            $rodada = ((int) $carta->mensagens()->max('rodada')) + 1;
            $path = $file->store("cartas/{$carta->id}/originais", 'local');

            $mensagem = CartaMensagem::create([
                'carta_id' => $carta->id,
                'rodada' => $rodada,
                'remetente_participante_id' => $participante->id,
                'destinatario_user_id' => $voluntario->id,
                'tipo_remetente' => CartaMensagem::TIPO_REMETENTE_EDUCANDO,
                'canal_entrada' => CartaMensagem::CANAL_ANEXO_DIGITALIZADO,
                'status' => CartaMensagem::STATUS_APROVADA,
                'anexo_original_path' => $path,
                'anexo_original_nome' => $file->getClientOriginalName(),
                'anexo_original_mime' => $file->getClientMimeType(),
                'anexo_original_tamanho' => $file->getSize(),
                'enviada_em' => now(),
                'criada_por' => $request->user()->id,
                'atualizada_por' => $request->user()->id,
            ]);

            $this->timbrado->aplicarAnexo($mensagem);

            $carta->update([
                'status' => Carta::STATUS_RESPONDIDA,
                'atualizada_por' => $request->user()->id,
            ]);

            CartaEvento::create([
                'carta_id' => $carta->id,
                'carta_mensagem_id' => $mensagem->id,
                'user_id' => $request->user()->id,
                'tipo' => CartaEvento::TIPO_MENSAGEM_ENVIADA,
            ]);
        });

        $voluntario->notify(new CartaRecebidaNotification($carta->load('mensagens')));

        return redirect()->route('cartas.cartas.show', $carta)->with('status', 'Carta adicionada.');
    }

    private function registrarRespostaGestor(Request $request, Carta $carta, \Illuminate\Http\UploadedFile $file, int $rodada): void
    {
        $path = $file->store("cartas/{$carta->id}/originais", 'local');
        $mensagem = $carta->mensagens()->create([
            'rodada' => $rodada,
            'remetente_user_id' => $carta->voluntario_user_id,
            'destinatario_participante_id' => $carta->educando_participante_id,
            'tipo_remetente' => CartaMensagem::TIPO_REMETENTE_VOLUNTARIO,
            'canal_entrada' => CartaMensagem::CANAL_ANEXO_MANUSCRITO,
            'status' => CartaMensagem::STATUS_APROVADA,
            'anexo_original_path' => $path,
            'anexo_original_nome' => $file->getClientOriginalName(),
            'anexo_original_mime' => $file->getClientMimeType(),
            'anexo_original_tamanho' => $file->getSize(),
            'enviada_em' => now(),
            'verificada_por' => $request->user()->id,
            'verificada_em' => now(),
            'criada_por' => $request->user()->id,
            'atualizada_por' => $request->user()->id,
        ]);
        $this->timbrado->aplicarAnexo($mensagem);
        $carta->update(['status' => Carta::STATUS_RESPONDIDA, 'atualizada_por' => $request->user()->id]);
        CartaEvento::create([
            'carta_id' => $carta->id,
            'carta_mensagem_id' => $mensagem->id,
            'user_id' => $request->user()->id,
            'tipo' => CartaEvento::TIPO_MENSAGEM_ENVIADA,
            'dados_depois' => ['tipo_remetente' => CartaMensagem::TIPO_REMETENTE_VOLUNTARIO, 'registrada_pelo_gestor' => true],
        ]);
    }

    public function respond(Request $request, Carta $carta): RedirectResponse
    {
        abort_unless($carta->voluntario_user_id === $request->user()->id, 403);

        $carta->loadMissing('ultimaMensagem');

        if (! $carta->podeVoluntarioEnviar()) {
            return back()->withErrors(['modo_resposta' => 'Aguarde a próxima carta do educando antes de responder novamente.']);
        }

        $data = $request->validate([
            'modo_resposta' => ['required', Rule::in(['digitada', 'anexo_manuscrito'])],
            'texto' => ['nullable', 'required_if:modo_resposta,digitada', 'string', 'max:12000'],
            'arquivo' => ['nullable', 'required_if:modo_resposta,anexo_manuscrito', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'modo_resposta.required' => 'Selecione como deseja responder.',
            'texto.required_if' => 'Digite a carta antes de enviar.',
            'arquivo.required_if' => 'Anexe a carta em PDF antes de enviar.',
            'arquivo.mimes' => 'Envie um arquivo em PDF.',
        ]);

        DB::transaction(function () use ($request, $carta, $data) {
            $rodada = ((int) $carta->mensagens()->max('rodada')) + 1;
            $path = null;
            $file = $request->file('arquivo');

            if ($file) {
                $path = $file->store("cartas/{$carta->id}/originais", 'local');
            }

            $textoLimpo = isset($data['texto'])
                ? $this->timbrado->normalizarTexto($data['texto'])
                : null;

            $mensagem = CartaMensagem::create([
                'carta_id' => $carta->id,
                'rodada' => $rodada,
                'remetente_user_id' => $request->user()->id,
                'destinatario_participante_id' => $carta->educando_participante_id,
                'tipo_remetente' => CartaMensagem::TIPO_REMETENTE_VOLUNTARIO,
                'canal_entrada' => $data['modo_resposta'] === 'digitada' ? CartaMensagem::CANAL_DIGITADA : CartaMensagem::CANAL_ANEXO_MANUSCRITO,
                'status' => CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO,
                'texto' => $textoLimpo,
                'texto_resumo' => $textoLimpo ? str($textoLimpo)->limit(500)->toString() : null,
                'anexo_original_path' => $path,
                'anexo_original_nome' => $file?->getClientOriginalName(),
                'anexo_original_mime' => $file?->getClientMimeType(),
                'anexo_original_tamanho' => $file?->getSize(),
                'enviada_em' => now(),
                'criada_por' => $request->user()->id,
                'atualizada_por' => $request->user()->id,
            ]);

            if ($data['modo_resposta'] === 'digitada') {
                $this->timbrado->aplicar($mensagem);
            } else {
                $this->timbrado->aplicarAnexo($mensagem);
            }

            $carta->update([
                'status' => Carta::STATUS_AGUARDANDO_VERIFICACAO,
                'atualizada_por' => $request->user()->id,
            ]);

            CartaEvento::create([
                'carta_id' => $carta->id,
                'carta_mensagem_id' => $mensagem->id,
                'user_id' => $request->user()->id,
                'tipo' => CartaEvento::TIPO_MENSAGEM_ENVIADA,
            ]);
        });

        return redirect()->route('cartas.dashboard')->with('cartas_thanks', true);
    }

    public function storeVolunteerLetter(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('cartas_voluntario') || $user->can('cartas.responder'), 403);

        $data = $request->validate([
            'destinatario_user_id' => [
                'required',
                Rule::exists('users', 'id')->where('sistema_origem', User::SISTEMA_ENGAJA),
            ],
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'destinatario_user_id.required' => 'Selecione o destinatario.',
            'arquivo.required' => 'Selecione o arquivo da carta.',
            'arquivo.mimes' => 'Envie um arquivo em PDF.',
        ]);

        $destinatario = User::with('participante')->findOrFail($data['destinatario_user_id']);
        if (! $destinatario->participante) {
            return back()->withErrors(['destinatario_user_id' => 'O destinatário selecionado não possui participante vinculado.'])->withInput();
        }

        $remetenteCpf = $user->participante?->cpf ? preg_replace('/\D+/', '', $user->participante->cpf) : null;
        $destinatarioCpf = $destinatario->participante?->cpf ? preg_replace('/\D+/', '', $destinatario->participante->cpf) : null;

        if ($remetenteCpf && $destinatarioCpf && $remetenteCpf === $destinatarioCpf) {
            return back()->withErrors(['destinatario_user_id' => 'Você não pode enviar uma carta para si mesmo.'])->withInput();
        }

        $file = $request->file('arquivo');

        $carta = DB::transaction(function () use ($user, $destinatario, $file) {
            $carta = Carta::create([
                'codigo' => $this->nextCodigo(),
                'educando_participante_id' => $destinatario->participante->id,
                'voluntario_user_id' => $user->id,
                'municipio_id' => $destinatario->participante->municipio_id,
                'status' => Carta::STATUS_AGUARDANDO_VERIFICACAO,
                'distribuida_em' => now(),
                'criada_por' => $user->id,
                'atualizada_por' => $user->id,
            ]);

            $path = $file->store("cartas/{$carta->id}/originais", 'local');

            $mensagem = CartaMensagem::create([
                'carta_id' => $carta->id,
                'rodada' => 1,
                'remetente_user_id' => $user->id,
                'destinatario_participante_id' => $destinatario->participante->id,
                'tipo_remetente' => CartaMensagem::TIPO_REMETENTE_VOLUNTARIO,
                'canal_entrada' => CartaMensagem::CANAL_ANEXO_MANUSCRITO,
                'status' => CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO,
                'anexo_original_path' => $path,
                'anexo_original_nome' => $file->getClientOriginalName(),
                'anexo_original_mime' => $file->getClientMimeType(),
                'anexo_original_tamanho' => $file->getSize(),
                'enviada_em' => now(),
                'criada_por' => $user->id,
                'atualizada_por' => $user->id,
            ]);

            $this->timbrado->aplicarAnexo($mensagem);

            CartaEvento::create([
                'carta_id' => $carta->id,
                'carta_mensagem_id' => $mensagem->id,
                'user_id' => $user->id,
                'tipo' => CartaEvento::TIPO_MENSAGEM_ENVIADA,
            ]);

            return $carta;
        });

        return redirect()->route('cartas.dashboard')->with('cartas_thanks', true);
    }

    public function destroy(Request $request, Carta $carta): RedirectResponse
    {
        abort_unless($this->isGestor($request->user()), 403);

        $carta->delete();

        return back()->with('status', 'Carta removida.');
    }

    public function approveMessage(Request $request, CartaMensagem $mensagem): RedirectResponse
    {
        abort_unless($this->isGestor($request->user()), 403);

        abort_unless($this->aprovacao->aprovar($mensagem, $request->user()), 422);

        return back()->with('status', 'Resposta aprovada.');
    }

    public function approveBatch(Request $request): RedirectResponse
    {
        abort_unless($this->isGestor($request->user()), 403);

        $data = $request->validate([
            'mensagem_ids' => ['required', 'array', 'min:1', 'max:15'],
            'mensagem_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ], [
            'mensagem_ids.required' => 'Selecione pelo menos uma resposta para aprovar.',
            'mensagem_ids.max' => 'Selecione no máximo 15 respostas por vez.',
            'mensagem_ids.*.distinct' => 'Cada resposta deve ser selecionada apenas uma vez.',
        ]);

        $aprovadas = 0;
        $mensagens = CartaMensagem::query()
            ->whereIn('id', $data['mensagem_ids'])
            ->orderBy('carta_id')
            ->orderBy('id')
            ->get();

        foreach ($mensagens as $mensagem) {
            if ($this->aprovacao->aprovar($mensagem, $request->user())) {
                $aprovadas++;
            }
        }

        $ignoradas = count($data['mensagem_ids']) - $aprovadas;
        $resultado = $aprovadas === 1 ? '1 resposta aprovada.' : "{$aprovadas} respostas aprovadas.";

        if ($ignoradas > 0) {
            $resultado .= $ignoradas === 1
                ? ' 1 resposta não foi aprovada porque sua situação mudou ou ela não está mais disponível.'
                : " {$ignoradas} respostas não foram aprovadas porque sua situação mudou ou elas não estão mais disponíveis.";
            $resultado .= ' Atualizamos a listagem.';
        }

        return back()->with('status', $resultado);
    }

    public function requestMessageAdjustment(Request $request, CartaMensagem $mensagem): RedirectResponse
    {
        abort_unless($this->isGestor($request->user()), 403);

        $mensagem->load('carta');
        abort_unless($mensagem->status === CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO, 422);

        $data = $request->validate([
            'parecer_verificacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'parecer_verificacao.max' => 'O parecer deve ter no máximo 2000 caracteres.',
        ]);

        DB::transaction(function () use ($request, $mensagem, $data) {
            $before = [
                'mensagem_status' => $mensagem->status,
                'carta_status' => $mensagem->carta->status,
            ];

            $mensagem->update([
                'status' => CartaMensagem::STATUS_AJUSTE_SOLICITADO,
                'verificada_por' => $request->user()->id,
                'verificada_em' => now(),
                'parecer_verificacao' => $data['parecer_verificacao'] ?? null,
                'atualizada_por' => $request->user()->id,
            ]);

            $mensagem->carta->update([
                'status' => Carta::STATUS_AGUARDANDO_AJUSTE,
                'atualizada_por' => $request->user()->id,
            ]);

            CartaEvento::create([
                'carta_id' => $mensagem->carta_id,
                'carta_mensagem_id' => $mensagem->id,
                'user_id' => $request->user()->id,
                'tipo' => CartaEvento::TIPO_AJUSTE_SOLICITADO,
                'dados_antes' => $before,
                'dados_depois' => [
                    'mensagem_status' => CartaMensagem::STATUS_AJUSTE_SOLICITADO,
                    'carta_status' => Carta::STATUS_AGUARDANDO_AJUSTE,
                    'parecer_verificacao' => $data['parecer_verificacao'] ?? null,
                ],
            ]);
        });

        $mensagem->loadMissing('carta');
        $voluntario = $mensagem->carta->voluntario;
        $voluntario?->notify(new AjusteSolicitadoNotification($mensagem));

        return back()->with('status', 'Ajuste solicitado ao voluntário.');
    }

    public function updateAdjustedMessage(Request $request, CartaMensagem $mensagem): RedirectResponse
    {
        $mensagem->load('carta');

        abort_unless($mensagem->isEditavelPor($request->user()), 403);
        abort_unless($mensagem->status === CartaMensagem::STATUS_AJUSTE_SOLICITADO, 422);

        $data = $request->validate([
            'modo_resposta' => ['required', Rule::in(['digitada', 'anexo_manuscrito'])],
            'texto' => ['nullable', 'required_if:modo_resposta,digitada', 'string', 'max:12000'],
            'arquivo' => ['nullable', 'required_if:modo_resposta,anexo_manuscrito', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'modo_resposta.required' => 'Selecione como deseja ajustar a resposta.',
            'texto.required_if' => 'Digite a carta ajustada antes de enviar.',
            'arquivo.required_if' => 'Anexe a carta ajustada antes de enviar.',
            'arquivo.mimes' => 'Envie um arquivo em PDF.',
        ]);

        DB::transaction(function () use ($request, $mensagem, $data) {
            $before = [
                'mensagem_status' => $mensagem->status,
                'carta_status' => $mensagem->carta->status,
                'parecer_verificacao' => $mensagem->parecer_verificacao,
            ];

            $file = $request->file('arquivo');
            $path = null;

            if ($file) {
                $path = $file->store("cartas/{$mensagem->carta_id}/originais", 'local');
            }

            $updates = [
                'canal_entrada' => $data['modo_resposta'] === 'digitada'
                    ? CartaMensagem::CANAL_DIGITADA
                    : CartaMensagem::CANAL_ANEXO_MANUSCRITO,
                'status' => CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO,
                'texto' => $data['modo_resposta'] === 'digitada' ? ($data['texto'] ?? null) : null,
                'texto_resumo' => $data['modo_resposta'] === 'digitada' && isset($data['texto'])
                    ? str($data['texto'])->limit(500)->toString()
                    : null,
                'parecer_verificacao' => null,
                'verificada_por' => null,
                'verificada_em' => null,
                'enviada_em' => now(),
                'timbrado_desativado' => false,
                'atualizada_por' => $request->user()->id,
            ];

            if ($file) {
                $updates = array_merge($updates, [
                    'anexo_original_path' => $path,
                    'anexo_original_nome' => $file->getClientOriginalName(),
                    'anexo_original_mime' => $file->getClientMimeType(),
                    'anexo_original_tamanho' => $file->getSize(),
                ]);
            } else {
                $updates = array_merge($updates, [
                    'anexo_original_path' => null,
                    'anexo_original_nome' => null,
                    'anexo_original_mime' => null,
                    'anexo_original_tamanho' => null,
                ]);
            }

            $mensagem->update($updates);

            if ($data['modo_resposta'] === 'digitada') {
                $this->timbrado->aplicar($mensagem);
            } else {
                $this->timbrado->aplicarAnexo($mensagem);
            }

            $mensagem->carta->update([
                'status' => Carta::STATUS_AGUARDANDO_VERIFICACAO,
                'atualizada_por' => $request->user()->id,
            ]);

            CartaEvento::create([
                'carta_id' => $mensagem->carta_id,
                'carta_mensagem_id' => $mensagem->id,
                'user_id' => $request->user()->id,
                'tipo' => CartaEvento::TIPO_MENSAGEM_ENVIADA,
                'dados_antes' => $before,
                'dados_depois' => [
                    'mensagem_status' => CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO,
                    'carta_status' => Carta::STATUS_AGUARDANDO_VERIFICACAO,
                ],
            ]);
        });

        return redirect()
            ->route('cartas.cartas.show', $mensagem->carta)
            ->with('status', 'Ajuste enviado para verificação.');
    }

    /**
     * Edição de uma carta já enviada pelo gestor/admin: substitui o PDF
     * original (o anterior é apagado do storage) e define se a carta recebe
     * o papel timbrado — a escolha vale para qualquer carta, inclusive as de
     * educandos, e é preservada pelo reprocessamento automático.
     */
    public function updateSentMessage(Request $request, CartaMensagem $mensagem): RedirectResponse
    {
        abort_unless($this->isGestor($request->user()), 403);

        $request->validate([
            'arquivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'aplicar_timbrado' => ['nullable', 'boolean'],
        ], [
            'arquivo.mimes' => 'Envie um arquivo em PDF.',
            'arquivo.max' => 'O arquivo deve ter no máximo 10MB.',
        ]);

        $mensagem->load('carta');
        $file = $request->file('arquivo');
        $aplicarTimbrado = $request->boolean('aplicar_timbrado');
        $originalAntigo = $mensagem->anexo_original_path;

        DB::transaction(function () use ($request, $mensagem, $file, $aplicarTimbrado) {
            $before = [
                'anexo_original_path' => $mensagem->anexo_original_path,
                'anexo_original_nome' => $mensagem->anexo_original_nome,
                'timbrado_aplicado' => $mensagem->timbrado_aplicado_em !== null,
            ];

            $updates = [
                'timbrado_desativado' => ! $aplicarTimbrado,
                'atualizada_por' => $request->user()->id,
            ];

            if ($file) {
                $updates = array_merge($updates, [
                    'anexo_original_path' => $file->store("cartas/{$mensagem->carta_id}/originais", 'local'),
                    'anexo_original_nome' => $file->getClientOriginalName(),
                    'anexo_original_mime' => $file->getClientMimeType(),
                    'anexo_original_tamanho' => $file->getSize(),
                ]);
            }

            $mensagem->update($updates);

            // Sempre descarta o PDF final anterior: ou ele seria do arquivo
            // substituído, ou o timbrado foi desligado.
            $this->timbrado->removerTimbrado($mensagem);

            if ($aplicarTimbrado) {
                if ($mensagem->anexo_original_path) {
                    $this->timbrado->aplicarAnexo($mensagem, forcar: true);
                } elseif (filled($mensagem->texto)) {
                    $this->timbrado->aplicar($mensagem);
                }
            }

            CartaEvento::create([
                'carta_id' => $mensagem->carta_id,
                'carta_mensagem_id' => $mensagem->id,
                'user_id' => $request->user()->id,
                'tipo' => CartaEvento::TIPO_EDITADA_ADMIN,
                'dados_antes' => $before,
                'dados_depois' => [
                    'anexo_original_path' => $mensagem->anexo_original_path,
                    'anexo_original_nome' => $mensagem->anexo_original_nome,
                    'timbrado_aplicado' => $mensagem->timbrado_aplicado_em !== null,
                ],
            ]);
        });

        if ($file && $originalAntigo && $originalAntigo !== $mensagem->anexo_original_path) {
            Storage::disk('local')->delete($originalAntigo);
        }

        $status = $aplicarTimbrado && ! $mensagem->timbrado_aplicado_em
            ? 'Carta atualizada, mas não foi possível aplicar o timbrado.'
            : 'Carta atualizada.';

        return redirect()->route('cartas.cartas.show', $mensagem->carta)->with('status', $status);
    }

    public function download(Request $request, CartaMensagem $mensagem)
    {
        $mensagem->load('carta.voluntario', 'carta.educando.user', 'carta.educando.municipio');
        $this->authorizeCartaAccess($request, $mensagem->carta);

        $path = $mensagem->arquivo_final_path ?: $mensagem->anexo_original_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->download($path, $this->gerarNomeArquivo($mensagem, $path));
    }

    /**
     * Baixa o PDF original submetido, sem o papel timbrado aplicado pelo sistema.
     */
    public function downloadOriginal(Request $request, CartaMensagem $mensagem)
    {
        abort_unless($this->isGestor($request->user()), 403);

        $mensagem->load('carta.voluntario', 'carta.educando.user', 'carta.educando.municipio');
        $path = $mensagem->anexo_original_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->download($path, $this->gerarNomeArquivo($mensagem, $path, '_SEM_TIMBRADO'));
    }

    public function preview(Request $request, CartaMensagem $mensagem)
    {
        $mensagem->load('carta.voluntario', 'carta.educando.user', 'carta.educando.municipio');

        $this->authorizeCartaAccess($request, $mensagem->carta);
        $this->viewerLog->passo(CartaViewerLogger::ETAPA_AUTORIZACAO, true, 'usuario#'.$request->user()->id, $mensagem->id);

        $arquivo = $this->viewerLog->inspecionarArquivo($mensagem);
        $this->viewerLog->passo(CartaViewerLogger::ETAPA_ARQUIVO, $arquivo['existe'], $arquivo['detalhe'], $mensagem->id);

        if (! $arquivo['existe']) {
            abort(404);
        }

        $this->viewerLog->passo(
            CartaViewerLogger::ETAPA_CONTEUDO,
            $arquivo['parece_pdf'],
            $arquivo['parece_pdf'] ? 'assinatura %PDF- encontrada' : 'o arquivo nao comeca com %PDF- (corrompido, truncado ou nao e PDF)',
            $mensagem->id
        );

        $path = $mensagem->arquivo_final_path ?: $mensagem->anexo_original_path;
        $filename = $this->gerarNomeArquivo($mensagem, $path);
        $mime = $mensagem->arquivo_final_mime ?: $mensagem->anexo_original_mime ?: Storage::disk('local')->mimeType($path);

        $resposta = response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
        ]);

        $this->viewerLog->passo(CartaViewerLogger::ETAPA_ENTREGA, true, "HTTP 200, {$mime}", $mensagem->id);

        return $resposta;
    }

    public function downloadBatch(Request $request)
    {
        abort_unless($this->isGestor($request->user()), 403);

        $search = trim((string) $request->input('q', $request->query('q', '')));
        $municipioId = $request->input('municipio_id', $request->query('municipio_id'));
        $statusFilter = $request->input('status', $request->query('status'));

        $cartas = Carta::query()
            ->with([
                'educando.user',
                'educando.municipio.estado',
                'voluntario',
                'mensagens' => function ($q) {
                    $q->where('tipo_remetente', CartaMensagem::TIPO_REMETENTE_VOLUNTARIO)
                        ->whereNotNull('enviada_em')
                        ->reorder('rodada', 'desc')
                        ->limit(1);
                },
            ])
            ->when($search !== '', function ($query) use ($search) {
                $this->applySearchFilter($query, $search);
            })
            ->when($municipioId, function ($query) use ($municipioId) {
                $query->whereHas('educando', function ($q) use ($municipioId) {
                    $q->where('municipio_id', $municipioId);
                });
            })
            ->when($statusFilter, function ($query) use ($statusFilter) {
                $this->applyStatusFilter($query, $statusFilter);
            })
            ->latest()->get();

        $zipFile = storage_path('app/temp_cartas_lote_'.uniqid().'.zip');
        $zip = new \ZipArchive;
        $hasFiles = false;

        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $addedFiles = [];

            foreach ($cartas as $carta) {
                foreach ($carta->mensagens as $mensagem) {
                    $path = $mensagem->arquivo_final_path ?: $mensagem->anexo_original_path;

                    if ($path && Storage::disk('local')->exists($path)) {
                        $filename = $this->gerarNomeArquivo($mensagem, $path);

                        // Evita conflito de nomes idênticos no ZIP
                        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);

                        $uniqueName = $filename;
                        $counter = 1;
                        while (in_array($uniqueName, $addedFiles)) {
                            $uniqueName = "{$nameWithoutExt}_".str_pad((string) $counter, 2, '0', STR_PAD_LEFT).".{$ext}";
                            $counter++;
                        }

                        $addedFiles[] = $uniqueName;
                        $zip->addFile(Storage::disk('local')->path($path), $uniqueName);
                        $hasFiles = true;
                    }
                }
            }

            $zip->close();
        }

        if (! $hasFiles || ! file_exists($zipFile)) {
            if (file_exists($zipFile)) {
                @unlink($zipFile);
            }

            return redirect()->back()->with('status', 'Nenhuma carta com anexo/PDF foi encontrada para gerar o lote.');
        }

        return response()->download($zipFile, 'Cartas-Lote.zip')->deleteFileAfterSend(true);
    }

    private function gestorDashboard(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $municipioId = $request->query('municipio_id');
        $statusFilter = $request->query('status');

        $cartas = Carta::query()
            ->with([
                'educando.user',
                'educando.municipio.estado.regiao',
                'voluntario' => fn ($q) => $q->withCount('cartasComoVoluntario')->with('participante.municipio.estado.regiao'),
                'ultimaMensagem',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $this->applySearchFilter($query, $search);
            })
            ->when($municipioId, function ($query) use ($municipioId) {
                $query->whereHas('educando', function ($q) use ($municipioId) {
                    $q->where('municipio_id', $municipioId);
                });
            })
            ->when($statusFilter, function ($query) use ($statusFilter) {
                $this->applyStatusFilter($query, $statusFilter);
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $hasActiveFilter = filled($municipioId) || filled($statusFilter) || ($search !== '');

        if ($request->ajax()) {
            return view('cartas.gestor._table', compact('cartas', 'hasActiveFilter', 'search', 'municipioId', 'statusFilter'));
        }

        $engajaUsers = $this->remetenteCandidatosQuery()->get();
        $voluntarios = $this->voluntariosQuery()->get();
        $municipios = Municipio::with('estado.regiao')->orderBy('nome')->get();

        return view('cartas.gestor.index', compact('cartas', 'engajaUsers', 'search', 'municipioId', 'statusFilter', 'municipios', 'voluntarios'));
    }

    /**
     * Busca livre da listagem de cartas: codigo, nome do remetente (educando),
     * nome do destinatario (voluntario) e o CPF de ambos.
     */
    private function applySearchFilter($query, string $search): void
    {
        $searchLower = mb_strtolower($search, 'UTF-8');
        $searchDigits = preg_replace('/\D/', '', $search);

        $query->where(function ($nested) use ($searchLower, $searchDigits) {
            $nested->whereRaw('LOWER(codigo) LIKE ?', ["%{$searchLower}%"])
                ->orWhereHas('educando.user', fn ($q) => $q->whereRaw('LOWER(users.name) LIKE ?', ["%{$searchLower}%"]))
                ->orWhereHas('voluntario', fn ($q) => $q->whereRaw('LOWER(users.name) LIKE ?', ["%{$searchLower}%"]));

            // O CPF fica em participantes.cpf e pode estar gravado com ou sem pontuacao.
            if (mb_strlen($searchDigits) >= 3) {
                $cpfSemPontuacao = "REPLACE(REPLACE(REPLACE(participantes.cpf, '.', ''), '-', ''), ' ', '')";

                $nested->orWhereHas('educando', fn ($q) => $q->whereRaw("{$cpfSemPontuacao} LIKE ?", ["%{$searchDigits}%"]))
                    ->orWhereHas('voluntario.participante', fn ($q) => $q->whereRaw("{$cpfSemPontuacao} LIKE ?", ["%{$searchDigits}%"]));
            }
        });
    }

    private function applyStatusFilter($query, string $statusFilter): void
    {
        if ($statusFilter === 'respondida') {
            $query->where('cartas.status', 'respondida');
        } elseif ($statusFilter === 'ajuste_solicitado') {
            $query->where(function ($q) {
                $q->where('cartas.status', 'aguardando_ajuste')
                    ->orWhereHas('ultimaMensagem', function ($sub) {
                        $sub->where('status', 'ajuste_solicitado');
                    });
            });
        } elseif ($statusFilter === 'pendente') {
            $query->whereHas('ultimaMensagem', function ($sub) {
                $sub->where('status', 'like', '%verificacao%');
            });
        } elseif ($statusFilter === 'enviada') {
            $query->where('cartas.status', '!=', 'respondida')
                ->where('cartas.status', '!=', 'aguardando_ajuste')
                ->whereDoesntHave('ultimaMensagem', function ($sub) {
                    $sub->where('status', 'ajuste_solicitado')
                        ->orWhere('status', 'like', '%verificacao%');
                });
        }
    }

    private function voluntarioDashboard(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('participante.municipio.estado.regiao');

        $cartas = Carta::query()
            ->with(['educando.user', 'educando.municipio.estado.regiao', 'voluntario.participante.municipio.estado.regiao', 'mensagens' => fn ($q) => $q->latest('rodada'), 'ultimaMensagem'])
            ->withCount(['mensagens as mensagens_nao_lidas_count' => function ($query) use ($user) {
                $query->where('destinatario_user_id', $user->id)
                    ->whereNull('lida_em');
            }])
            ->doVoluntario($user)
            ->latest()
            ->get();

        // Prioriza cartas recebidas e com ajuste solicitado, mantendo a data como criterio secundario.
        $prioridade = [
            Carta::STATUS_AGUARDANDO_VOLUNTARIO => 0,
            Carta::STATUS_AGUARDANDO_AJUSTE => 1,
        ];
        $cartas = $cartas->sortBy(fn (Carta $carta) => [
            $prioridade[$carta->status] ?? 2,
            -($carta->updated_at?->timestamp ?? $carta->created_at?->timestamp ?? 0),
        ])->values();

        $destinatarios = $this->engajaUsersQuery()->limit(80)->get();

        return view('cartas.voluntario.index', compact('cartas', 'destinatarios'));
    }

    private function engajaUsersQuery()
    {
        return User::query()
            ->where('sistema_origem', User::SISTEMA_ENGAJA)
            ->whereHas('participante.eventos', fn ($q) => $q->where('is_cartas', true))
            ->with('participante.municipio.estado.regiao')
            ->orderBy('name');
    }

    private function remetenteCandidatosQuery()
    {
        return User::query()
            ->where('sistema_origem', User::SISTEMA_ENGAJA)
            ->whereHas('participante')
            ->with('participante.municipio.estado.regiao')
            ->orderBy('name');
    }

    private function voluntariosQuery()
    {
        return User::query()
            ->where('sistema_origem', User::SISTEMA_CARTAS)
            ->whereHas('roles', fn ($query) => $query->where('name', 'cartas_voluntario')->where('guard_name', 'web'))
            ->with('participante.municipio.estado')
            ->orderBy('name');
    }

    private function selectVoluntario(?User $remetente = null, ?int $voluntarioId = null): ?User
    {
        $randomFn = DB::getDriverName() === 'mysql' ? 'RAND()' : 'RANDOM()';
        $remetenteCpf = $remetente?->participante?->cpf ? preg_replace('/\D+/', '', $remetente->participante->cpf) : null;

        return $this->voluntariosQuery()
            ->when($voluntarioId, fn ($query) => $query->whereKey($voluntarioId))
            ->when($remetente, fn ($query) => $query->where('users.id', '!=', $remetente->id))
            ->when($remetenteCpf, function ($query) use ($remetenteCpf) {
                $query->whereDoesntHave('participante', function ($sub) use ($remetenteCpf) {
                    $sub->whereRaw("regexp_replace(cpf, '[^0-9]', '', 'g') = ?", [$remetenteCpf]);
                });
            })
            ->withCount('cartasComoVoluntario as cartas_atribuidas_count')
            ->when($voluntarioId === null, fn ($query) => $query->whereRaw('(SELECT COUNT(*) FROM "cartas" WHERE "cartas"."voluntario_user_id" = "users"."id" AND "cartas"."deleted_at" IS NULL) < "users"."cartas_limite_respostas"'))
            ->reorder()
            ->orderByRaw("CASE WHEN \"users\".\"cartas_tipo_vinculo\" = 'petrobras' THEN 0 ELSE 1 END ASC")
            ->orderByRaw("cartas_atribuidas_count ASC, {$randomFn}")
            ->first();
    }

    private function cartaDoEnvio(User $gestor, string $token, string $hash): ?Carta
    {
        $carta = Carta::withTrashed()
            ->where('criada_por', $gestor->id)
            ->where('envio_token', $token)
            ->first();

        if ($carta && ($carta->trashed() || ! hash_equals($carta->envio_hash, $hash))) {
            throw ValidationException::withMessages([
                'envio_token' => 'Este envio já foi utilizado com outros dados ou sua carta foi excluída. Atualize a página para iniciar um novo envio.',
            ]);
        }

        return $carta;
    }

    private function nextCodigo(): string
    {
        $next = ((int) Carta::query()->withTrashed()->max('id')) + 1;

        return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function isGestor(User $user): bool
    {
        return $user->hasAnyRole(['cartas_admin', 'cartas_gestao'])
            || $user->can('cartas.criar')
            || $user->can('cartas.verificar');
    }

    private function authorizeCartaAccess(Request $request, Carta $carta): void
    {
        $user = $request->user();

        if ($this->isGestor($user) || $carta->voluntario_user_id === $user->id) {
            return;
        }

        $this->viewerLog->passo(
            CartaViewerLogger::ETAPA_AUTORIZACAO,
            false,
            "usuario#{$user->id} nao e gestor nem o voluntario da carta #".($carta->codigo ?? $carta->id)
        );

        abort(403);
    }

    private function markConversationAsRead(User $user, Carta $carta): void
    {
        $carta->mensagens()
            ->where('destinatario_user_id', $user->id)
            ->whereNull('lida_em')
            ->update(['lida_em' => now()]);
    }

    private function gerarNomeArquivo(CartaMensagem $mensagem, string $path, string $sufixo = ''): string
    {
        $carta = $mensagem->carta;
        $voluntario = $carta->voluntario;

        $educandoParticipante = $carta->educando;
        $educandoUser = $educandoParticipante?->user;

        $cidade = $educandoParticipante?->municipio?->nome ?? 'Cidade';

        if ($mensagem->tipo_remetente === CartaMensagem::TIPO_REMETENTE_VOLUNTARIO) {
            $remetenteNome = $voluntario?->name ?? 'Voluntario';
            $destinatarioNome = $educandoUser?->name ?? 'Educando';
        } else {
            $remetenteNome = $educandoUser?->name ?? 'Educando';
            $destinatarioNome = $voluntario?->name ?? 'Voluntario';
        }

        $sanitize = function ($string) {
            $string = Str::ascii((string) $string);
            $string = preg_replace('/[^a-zA-Z0-9]/', '_', $string);

            return trim((string) preg_replace('/_+/', '_', $string), '_');
        };

        $cidadeSanitized = $sanitize($cidade);
        $remetenteSanitized = $sanitize($remetenteNome);
        $destinatarioSanitized = $sanitize($destinatarioNome);

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (! $extension) {
            $originalName = $mensagem->arquivo_final_nome ?: $mensagem->anexo_original_nome;
            $extension = pathinfo((string) $originalName, PATHINFO_EXTENSION) ?: 'pdf';
        }

        return "PAEB_{$cidadeSanitized}_DE_{$remetenteSanitized}_PARA_{$destinatarioSanitized}{$sufixo}.{$extension}";
    }
}
