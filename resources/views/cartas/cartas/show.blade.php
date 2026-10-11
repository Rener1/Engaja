@extends('cartas.layouts.app')

@section('title', 'Cartas entre pessoas - Cartas para Esperançar')

@section('body')
    @include('cartas.shared._styles')

    <main class="cpe-page cpe-conversation">
        @include('cartas.shared._logo')

        <section class="cpe-conversation__main">
            <div class="cpe-conversation__content">
                @php
                    $remetenteNome = $carta->educando?->nome ?? 'Remetente';
                    $voluntarioNome = $carta->voluntario?->nome ?? 'Voluntário';
                    $remetentePrimeiroNome = str($remetenteNome)->trim()->before(' ')->toString();
                    $voluntarioPrimeiroNome = str($voluntarioNome)->trim()->before(' ')->toString();
                @endphp

                <h1 class="cpe-title">
                    Cartas entre {{ $remetentePrimeiroNome }} e {{ $voluntarioPrimeiroNome }}
                </h1>

                @if($gestor && $carta->educando?->municipio)
                    <div style="margin-top: 8px; margin-bottom: 12px; font-size: 14px; color: #444; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span>Remetente: <strong>{{ $carta->educando->nome }}</strong> ({{ collect([$carta->educando->municipio->nome, $carta->educando->municipio->estado?->sigla])->filter()->implode(' - ') }})</span>
                    </div>
                @endif

                <div class="cpe-remetentes-trigger">
                    <button type="button" class="cpe-button cpe-button--ghost" data-modal-open="remetentesModal">
                        {{ $gestor ? 'Ver dados dos remetentes' : "Ver dados de {$remetentePrimeiroNome}" }}
                    </button>
                </div>


                @if (session('status'))
                    <div class="cpe-alert">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="cpe-alert cpe-alert--error">{{ $errors->first() }}</div>
                @endif

                <div class="cpe-msg-list">
                    <?php $respostasExibidas = 0; ?>
                    @foreach($carta->mensagens as $mensagem)
                        <?php
                            if ($mensagem->status === 'aprovada' && ! $loop->first) {
                                $respostasExibidas++;
                            }

                            $isVoluntario = ! $gestor && $mensagem->tipo_remetente === 'educando';
                            $statusLabel = match ($mensagem->status) {
                                'aprovada' => $loop->first
                                    ? ($isVoluntario ? 'Recebida' : 'Enviada')
                                    : ($respostasExibidas === 1 ? 'Respondida' : "Respondida {$respostasExibidas}x"),
                                'aguardando_verificacao' => 'Em preparação',
                                'ajuste_solicitado' => 'Ajuste solicitado',
                                default => 'Enviada',
                            };

                            $statusClass = match (true) {
                                str_starts_with($statusLabel, 'Respondida') || $statusLabel === 'Recebida' => 'cpe-pill--green',
                                $statusLabel === 'Em preparação' => 'cpe-pill--yellow',
                                $statusLabel === 'Ajuste solicitado' => 'cpe-pill--red',
                                default => 'cpe-pill--blue',
                            };

                            $remetenteItemNome = $mensagem->remetenteUsuario?->nome
                                ?? $mensagem->remetenteParticipante?->nome
                                ?? 'Remetente';
                        ?>
                        <div class="cpe-msg-item">
                            <button type="button" class="cpe-msg-item__open" data-aside-open="aside-mensagem-{{ $mensagem->id }}">
                                <span class="cpe-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                                <span class="cpe-msg-item__info">
                                    <span class="cpe-msg-item__name" title="{{ $remetenteItemNome }}">{{ $remetenteItemNome }}</span>
                                    <span class="cpe-msg-item__date">{{ optional($mensagem->enviada_em ?? $mensagem->created_at)->format('d/m/Y') }}</span>
                                </span>
                            </button>
                            @if($gestor)
                                <a href="{{ route('cartas.mensagens.download', $mensagem) }}" class="cpe-icon-button cpe-msg-item__download" aria-label="Baixar anexo" download>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($gestor)
                    @unless($carta->podeEducandoEnviar())
                        <p class="cpe-turn-note">
                            {{ $carta->temMensagemPendente()
                                ? 'Há uma resposta aguardando verificação. Resolva-a antes de enviar uma nova carta.'
                                : 'Aguardando a resposta do voluntário para enviar uma nova carta.' }}
                        </p>
                    @endunless
                @else
                    @unless($carta->podeVoluntarioEnviar())
                        <p class="cpe-turn-note">
                            {{ $carta->ultimaMensagem?->status === 'ajuste_solicitado'
                                ? 'Uma solicitação de ajuste foi recebida. Abra a carta para verificar e realizar o ajuste.'
                                : 'Você poderá responder quando uma nova carta for recebida.' }}
                        </p>
                    @endunless
                @endif
                <a href="{{ route('cartas.dashboard') }}" class="cpe-button cpe-button--ghost cpe-conversation__wide-button">Voltar</a>
            </div>
        </section>

        <aside class="cpe-conversation__aside">
            <div class="cpe-aside-panel cpe-aside-panel--default" id="asideDefault">
                <span>Selecione uma carta na lista para visualizá-la.</span>
            </div>

            @foreach($carta->mensagens as $mensagem)
                @php($mensagemMime = $mensagem->arquivo_final_mime ?: $mensagem->anexo_original_mime)
                <div class="cpe-aside-panel" id="aside-mensagem-{{ $mensagem->id }}" hidden>
                    @php($remetentePanelNome = $mensagem->remetenteUsuario?->nome ?? $mensagem->remetenteParticipante?->nome ?? 'Remetente')
                    @php($destinatarioPanelNome = $mensagem->destinatarioUsuario?->nome ?? $mensagem->destinatarioParticipante?->nome ?? 'Destinatário')
                    <div class="cpe-letter-stage">
                        <div class="cpe-letter-header">
                            <div class="cpe-letter-header__party">
                                <span class="cpe-letter-header__line" title="De: {{ $remetentePanelNome }}">De: {{ $remetentePanelNome }}</span>
                                <span class="cpe-letter-header__line" title="Para: {{ $destinatarioPanelNome }}">Para: {{ $destinatarioPanelNome }}</span>
                                <span class="cpe-letter-header__date">{{ optional($mensagem->enviada_em ?? $mensagem->created_at)->format('d/m/Y') }}</span>
                            </div>

                            @if(! ($gestor && $mensagem->status === 'aguardando_verificacao'))
                                <div class="cpe-letter-actions">
                                    @if($mensagem->anexo_original_path || $mensagem->arquivo_final_path)
                                        <button type="button" class="cpe-button cpe-button--ghost" data-print-src="{{ route('cartas.mensagens.preview', $mensagem) }}">Imprimir</button>
                                    @else
                                        <button type="button" class="cpe-button cpe-button--ghost">Imprimir</button>
                                    @endif

                                    @if($loop->last)
                                        @if($gestor)
                                            @if($carta->podeEducandoEnviar())
                                                <button type="button" class="cpe-button" data-modal-open="addCartaModal">Adicionar carta</button>
                                            @endif
                                        @else
                                            @if($carta->podeVoluntarioEnviar())
                                                <button type="button" class="cpe-button" data-modal-open="respondCartaModal">Responder {{ $remetentePrimeiroNome }}</button>
                                            @endif
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if($gestor && $mensagem->status === 'aguardando_verificacao')
                            <div class="cpe-verification-box">
                                <form method="POST" class="cpe-adjustment-form">
                                    @csrf
                                    <textarea name="parecer_verificacao" class="cpe-textarea" placeholder="Informe o ajuste solicitado ao voluntário, caso seja necessário."></textarea>
                                    <div class="cpe-letter-actions">
                                        <button type="button" class="cpe-button cpe-button--ghost" data-aside-close>Fechar</button>
                                        <button type="submit" formaction="{{ route('cartas.mensagens.adjustment', $mensagem) }}" class="cpe-button cpe-button--ghost">Solicitar ajuste</button>
                                        <button type="submit" formaction="{{ route('cartas.mensagens.approve', $mensagem) }}" class="cpe-button">Aprovar resposta</button>
                                    </div>
                                </form>
                            </div>
                        @elseif($mensagem->status === 'ajuste_solicitado')
                            <div class="cpe-verification-note">
                                <strong>Ajuste solicitado:</strong>
                                <span>{{ $mensagem->parecer_verificacao ?: 'Revise sua resposta e envie novamente para verificação.' }}</span>

                                @if(! $gestor && $mensagem->isEditavelPor(Auth::user()))
                                    <button type="button" class="cpe-button" data-aside-open="aside-adjustMensagem-{{ $mensagem->id }}">
                                        Realizar ajuste
                                    </button>
                                @endif
                            </div>
                        @endif

                        @if($mensagem->anexo_original_path || $mensagem->arquivo_final_path)
                            @if(str_starts_with((string) $mensagemMime, 'image/'))
                                <div class="cpe-letter-preview cpe-letter-preview--media cpe-aside-preview">
                                    <img class="cpe-letter-media" src="{{ route('cartas.mensagens.preview', $mensagem) }}" alt="Carta enviada">
                                </div>
                            @elseif($mensagemMime === 'application/pdf')
                                <div class="cpe-letter-doc"
                                     data-pdf-src="{{ route('cartas.mensagens.preview', $mensagem) }}"
                                     data-mensagem-id="{{ $mensagem->id }}"
                                     data-diag-url="{{ route('cartas.diagnostico.visualizador') }}"
                                     role="img" aria-label="Carta enviada">
                                    <div class="cpe-letter-doc__loading">Carregando carta…</div>
                                </div>
                            @else
                                <div class="cpe-letter-preview cpe-aside-preview cpe-file-placeholder">Arquivo anexado: {{ $mensagem->arquivo_final_nome ?: $mensagem->anexo_original_nome }}</div>
                            @endif
                        @else
                            <div class="cpe-letter-preview cpe-aside-preview">{{ $mensagem->texto ?? 'Carta sem visualização disponível.' }}</div>
                        @endif
                    </div>
                </div>
            @endforeach

            @if(! $gestor)
                @foreach($carta->mensagens as $mensagem)
                    @if($mensagem->status === 'ajuste_solicitado' && $mensagem->isEditavelPor(Auth::user()))
                        <div class="cpe-aside-panel" id="aside-adjustMensagem-{{ $mensagem->id }}" hidden>
                            <h2>Realizar ajuste</h2>
                            <p class="cpe-aside-date">Atualize sua resposta conforme a solicitação recebida.</p>

                            @if($mensagem->parecer_verificacao)
                                <div class="cpe-verification-note cpe-verification-note--compact">
                                    <strong>Ajuste solicitado:</strong>
                                    <span>{{ $mensagem->parecer_verificacao }}</span>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('cartas.mensagens.update-adjustment', $mensagem) }}" enctype="multipart/form-data" data-modo-form>
                                @csrf
                                @method('PUT')

                                <div class="cpe-option-grid">
                                    <label class="cpe-choice">
                                        <span class="cpe-choice__icon">T</span>
                                        <span>Escreva sua carta direto na Plataforma</span>
                                        <input type="radio" name="modo_resposta" value="digitada" @checked($mensagem->canal_entrada === 'digitada') required>
                                    </label>
                                    <label class="cpe-choice">
                                        <span class="cpe-choice__icon" aria-hidden="true">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                                <path d="M21 12.5l-8.8 8.8a6 6 0 0 1-8.5-8.5l9.5-9.5a4 4 0 0 1 5.7 5.7l-9.6 9.6a2 2 0 1 1-2.8-2.8l8.8-8.8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <span>Anexe sua carta em PDF aqui</span>
                                        <input type="radio" name="modo_resposta" value="anexo_manuscrito" @checked($mensagem->canal_entrada === 'anexo_manuscrito') required>
                                    </label>
                                </div>

                                <div class="cpe-modo-field" data-modo="digitada" hidden>
                                    <textarea name="texto" class="cpe-textarea" placeholder="Digite sua carta ajustada aqui.">{{ old('texto', $mensagem->texto) }}</textarea>
                                </div>

                                <div class="cpe-modo-field" data-modo="anexo_manuscrito" hidden>
                                    <label class="cpe-upload cpe-upload--compact">
                                        <input type="file" name="arquivo" accept=".pdf,application/pdf">
                                        <span>
                                            <span class="cpe-upload__icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 16V4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 9l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 20h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                                            <span class="cpe-upload__link">Clique para selecionar o arquivo</span>
                                            <span class="cpe-upload__hint">PDF (máx. 10MB)</span>
                                        </span>
                                    </label>

                                    @if($mensagem->anexo_original_nome)
                                        <p class="cpe-current-file">Arquivo atual: {{ $mensagem->anexo_original_nome }}</p>
                                    @endif
                                </div>

                                <div class="cpe-modal-actions">
                                    <button type="button" class="cpe-button cpe-button--ghost" data-aside-close>Fechar</button>
                                    <button type="submit" class="cpe-button">Enviar ajuste</button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endforeach
            @endif
        </aside>

        @include('cartas.shared._user-menu')

        @unless($gestor)
            @include('cartas.shared._help_fab')
        @endunless
    </main>

    @if($gestor)
        <div class="cpe-modal" id="addCartaModal">
            <div class="cpe-modal__backdrop"></div>
            <div class="cpe-modal__dialog">
                <h2>Responder {{ $remetentePrimeiroNome }}</h2>
                <p>Anexe o arquivo PDF da sua carta.</p>
                <form method="POST" action="{{ route('cartas.cartas.mensagens.store', $carta) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="cpe-upload">
                        <input type="file" name="arquivo" required accept=".pdf,application/pdf">
                        <span>
                            <span class="cpe-upload__icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 16V4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 9l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 20h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                            <span class="cpe-upload__link">Clique para selecionar o arquivo</span>
                            <span class="cpe-upload__hint">PDF (máx. 10MB)</span>
                        </span>
                    </label>

                    <div class="cpe-fixed-participants">
                        <div>
                            <span>Remetente</span>
                            <strong>{{ $carta->educando?->nome ?? 'Remetente' }}</strong>
                        </div>
                        <div>
                            <span>Destinatário</span>
                            <strong>{{ $carta->voluntario?->nome ?? 'Voluntário' }}</strong>
                        </div>
                    </div>

                    <div class="cpe-modal-actions">
                        <button type="button" class="cpe-button cpe-button--ghost" data-modal-close>Fechar</button>
                        <button type="submit" class="cpe-button">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="cpe-modal" id="respondCartaModal">
            <div class="cpe-modal__backdrop"></div>
            <div class="cpe-modal__dialog">
                <h2>Enviar uma carta</h2>
                <p>Digite a sua carta direto na plataforma ou escreva à mão, escaneie e anexe em PDF.</p>
                <form method="POST" action="{{ route('cartas.cartas.respond', $carta) }}" enctype="multipart/form-data" data-modo-form>
                    @csrf
                    <div class="cpe-option-grid">
                        <label class="cpe-choice">
                            <span class="cpe-choice__icon">T</span>
                            <span>Escreva sua carta direto na Plataforma</span>
                            <input type="radio" name="modo_resposta" value="digitada" required>
                        </label>
                        <label class="cpe-choice">
                            <span class="cpe-choice__icon" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                    <path d="M21 12.5l-8.8 8.8a6 6 0 0 1-8.5-8.5l9.5-9.5a4 4 0 0 1 5.7 5.7l-9.6 9.6a2 2 0 1 1-2.8-2.8l8.8-8.8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <span>Anexe sua carta em PDF aqui</span>
                            <input type="radio" name="modo_resposta" value="anexo_manuscrito" required>
                        </label>
                    </div>
                    <div class="cpe-modo-field" data-modo="digitada" hidden>
                        <textarea name="texto" class="cpe-textarea" placeholder="Escreva aqui sua resposta de forma respeitosa e acolhedora."></textarea>
                    </div>
                    <div class="cpe-modo-field" data-modo="anexo_manuscrito" hidden>
                        <label class="cpe-upload cpe-upload--compact">
                            <input type="file" name="arquivo" accept=".pdf,application/pdf">
                            <span>
                                <span class="cpe-upload__icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 16V4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 9l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 20h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                                <span class="cpe-upload__link">Clique para selecionar o arquivo</span>
                                <span class="cpe-upload__hint">PDF (máx. 10MB)</span>
                            </span>
                        </label>
                    </div>
                    <div class="cpe-modal-actions">
                        <button type="button" class="cpe-button cpe-button--ghost" data-modal-close>Fechar</button>
                        <button type="submit" class="cpe-button">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <?php
        // Bloco PHP puro de propósito: a diretiva de bloco do Blade casaria com as diretivas inline acima.
        $remetentesModal = [
            ['titulo' => 'Remetente', 'dados' => ($carta->educando ?? new \App\Models\Participante)->dadosRemetente()],
        ];

        if ($gestor) {
            $remetentesModal[] = [
                'titulo' => 'Voluntário',
                'dados' => ($carta->voluntario?->participante ?? new \App\Models\Participante)->dadosRemetente($carta->voluntario),
            ];
        }

        $remetentesRotulos = [
            'nome' => 'Nome',
            'cidade' => 'Cidade',
            'estado' => 'Estado',
            'idade' => 'Idade',
            'sexo' => 'Identidade de gênero',
        ];
    ?>

    <div class="cpe-modal" id="remetentesModal">
        <div class="cpe-modal__backdrop" data-modal-close></div>
        <div class="cpe-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="remetentesModalTitulo">
            <h2 id="remetentesModalTitulo">{{ $gestor ? 'Dados dos remetentes' : 'Dados do remetente' }}</h2>

            @foreach($remetentesModal as $remetenteModal)
                <section class="cpe-remetente-dados">
                    @if($gestor)
                        <h3>{{ $remetenteModal['titulo'] }}</h3>
                    @endif
                    <dl>
                        @foreach($remetentesRotulos as $campo => $rotulo)
                            <div>
                                <dt>{{ $rotulo }}</dt>
                                <dd>{{ $remetenteModal['dados'][$campo] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach

            <div class="cpe-modal-actions cpe-modal-actions--single">
                <button type="button" class="cpe-button cpe-button--ghost" data-modal-close>Fechar</button>
            </div>
        </div>
    </div>

    @include('cartas.shared._scripts')

    <style>
        .cpe-remetentes-trigger {
            margin-bottom: 12px;
        }

        .cpe-remetente-dados + .cpe-remetente-dados {
            margin-top: 20px;
        }

        .cpe-remetente-dados h3 {
            margin: 0 0 8px;
            font-size: 14px;
            font-weight: 800;
            color: #222;
        }

        .cpe-remetente-dados dl {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 0;
        }

        .cpe-remetente-dados dl > div {
            min-width: 0;
            border: 1px solid #d6d6d6;
            border-radius: 6px;
            background: #fff;
            padding: 10px 12px;
        }

        .cpe-remetente-dados dt {
            color: #777;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .cpe-remetente-dados dd {
            margin: 0;
            overflow-wrap: anywhere;
            color: #222;
            font-size: 13px;
            font-weight: 700;
        }

        .cpe-modal-actions--single {
            grid-template-columns: 1fr;
        }

        .cpe-conversation {
            position: relative;
            --cpe-sidebar-w: clamp(360px, 28vw, 460px);
            display: grid;
            grid-template-columns: var(--cpe-sidebar-w) minmax(0, 1fr);
            align-items: start;
        }

        .cpe-conversation > .cpe-logo-top {
            grid-column: 1 / -1;
            width: 100%;
            margin-bottom: 24px;
        }

        /* Nesta tela o titulo compete com a lista de mensagens na barra lateral estreita */
        .cpe-conversation__main .cpe-title {
            font-size: 24px;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        /* Barra lateral sticky: acompanha o scroll mas respeita o limite do footer */
        .cpe-conversation__main {
            position: sticky;
            top: 0;
            width: var(--cpe-sidebar-w);
            min-width: 0;
            max-height: 100vh;
            overflow-y: auto;
            box-sizing: border-box;
            padding: 8px 26px 28px;
            z-index: 30;
        }

        .cpe-conversation__content {
            min-width: 0;
            grid-template-columns: minmax(0, 1fr);
            margin-top: 8px;
            padding-bottom: 24px;
            display: grid;
            gap: 20px;
        }

        /* Lista lateral compacta de mensagens */
        .cpe-msg-list {
            min-width: 0;
            grid-template-columns: minmax(0, 1fr);
            display: grid;
            gap: 10px;
        }

        .cpe-msg-item {
            min-width: 0;
            display: flex;
            align-items: stretch;
            gap: 6px;
        }

        .cpe-msg-item__open {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border: 1px solid var(--cpe-line);
            border-radius: 10px;
            background: #fff;
            padding: 10px 12px;
            cursor: pointer;
            text-align: left;
            transition: border-color .18s, box-shadow .18s;
        }

        .cpe-msg-item__open:hover {
            border-color: #b9b1ab;
        }

        .cpe-msg-item__open.is-active {
            border-color: #008BBC;
            box-shadow: inset 0 0 0 1px #008BBC;
        }

        .cpe-msg-item__info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
            min-width: 0;
        }

        .cpe-msg-item__name {
            width: 100%;
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #222;
            font-size: 16px;
            font-weight: 600;
        }

        .cpe-msg-item__date {
            color: #777;
            font-size: 14px;
        }

        .cpe-msg-item__download {
            flex: none;
            align-self: center;
        }

        /* O visualizador ocupa o espaco disponivel ao lado da lista, sem
           descontar uma segunda barra lateral da largura da carta. */
        .cpe-conversation__aside {
            min-width: 0;
            background: transparent;
            min-height: calc(100vh - 130px);
            border-left: 0;
            display: flex;
            justify-content: center;
            padding: 8px 24px 48px;
            box-sizing: border-box;
        }

        .cpe-aside-panel[hidden] {
            display: none !important;
        }

        .cpe-aside-panel--default {
            align-self: stretch;
            display: grid;
            place-items: center;
            padding: 24px;
            color: #8a827b;
            font-size: 14px;
            text-align: center;
        }

        .cpe-aside-panel:not(.cpe-aside-panel--default) {
            width: 100%;
            max-width: 1100px;
            min-width: 0;
            padding: 8px 0 0;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .cpe-aside-panel:not(.cpe-aside-panel--default) > * {
            width: 100%;
        }

        .cpe-aside-panel:not(.cpe-aside-panel--default) h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
        }

        .cpe-aside-date {
            margin: -8px 0 8px;
            color: #333;
            font-size: 14px;
        }

        /* Acoes logo abaixo do "De:/Para:", com botoes compactos alinhados a direita */
        .cpe-letter-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }

        /* Informacoes e botoes formam uma linha unica, com as bases alinhadas */
        .cpe-letter-header .cpe-letter-actions {
            flex: 0 1 auto;
            min-width: 0;
            max-width: 100%;
            align-self: flex-end;
        }

        /* Respiro entre o parecer e os botoes de decisao do gestor */
        .cpe-adjustment-form .cpe-letter-actions {
            margin-top: 6px;
        }

        .cpe-letter-stage .cpe-letter-actions .cpe-button {
            width: auto;
            min-width: 0;
            max-width: 100%;
            min-height: 34px;
            height: auto;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.3;
            white-space: normal;
            overflow-wrap: anywhere;
            text-align: center;
        }

        .cpe-letter-stage > .cpe-verification-note .cpe-button {
            justify-self: end;
            width: auto;
            height: 34px;
            padding: 0 14px;
            font-size: 13px;
        }

        .cpe-letter-stage {
            min-width: 0;
            overflow-wrap: anywhere;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .cpe-letter-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px 24px;
            color: #008bbc;
            font-weight: 500;
            font-size: 19px;
            line-height: 1.2;
        }

        .cpe-letter-header__party {
            min-width: 0;
            flex: 1 1 240px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .cpe-letter-header__line {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            max-width: 100%;
        }

        .cpe-letter-header__date {
            display: block;
            margin-top: 4px;
            white-space: nowrap;
        }

        .cpe-conversation .cpe-letter-preview {
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
            overflow-wrap: anywhere;
        }

        .cpe-conversation .cpe-letter-preview--media {
            border: 0;
            background: transparent;
            border-radius: 9px;
            width: 100%;
            min-height: 0;
            max-height: none;
            overflow: visible;
        }

        /* Documento PDF renderizado como imagem (pdf.js), rolavel entre paginas */
        .cpe-letter-doc {
            width: 100%;
            min-width: 0;
            max-height: 82vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            border-radius: 9px;
            background: #fff;
            padding: 14px;
            box-sizing: border-box;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .08);
        }

        .cpe-letter-doc.is-loading {
            min-height: 320px;
        }

        .cpe-letter-page {
            display: block;
            flex: none;
            width: 100%;
            max-width: 100%;
            height: auto;
            border-radius: 4px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, .14);
        }

        .cpe-letter-doc__loading,
        .cpe-letter-doc__error {
            color: #8a827b;
            font-size: 14px;
            padding: 40px 0;
        }

        .cpe-conversation .cpe-letter-media {
            width: 100%;
            height: auto;
            max-height: none;
            object-fit: contain;
            border-radius: 9px;
            user-select: none;
            -webkit-user-select: none;
            pointer-events: none;
        }

        .cpe-conversation__wide-button {
            width: 100%;
        }

        .cpe-turn-note {
            border: 1px solid #d7d0ca;
            border-radius: 6px;
            background: #fff;
            padding: 12px 14px;
            margin: 0;
            color: #333;
            font-size: 13px;
            text-align: center;
        }

        .cpe-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 18px;
        }

        .cpe-fixed-participants {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 16px;
        }

        .cpe-fixed-participants div {
            min-width: 0;
            border: 1px solid #d6d6d6;
            border-radius: 6px;
            background: #fff;
            padding: 10px 12px;
        }

        .cpe-fixed-participants span {
            display: block;
            color: #777;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .cpe-fixed-participants strong {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #222;
            font-size: 13px;
        }

        .cpe-verification-box {
            border-top: 1px solid #ddd8d4;
            display: grid;
            gap: 12px;
            margin-top: 18px;
            padding-top: 18px;
        }

        .cpe-verification-box .cpe-button,
        .cpe-adjustment-form .cpe-button {
            width: 100%;
        }

        .cpe-adjustment-form {
            display: grid;
            gap: 10px;
        }

        .cpe-adjustment-form .cpe-textarea,
        .cpe-aside-panel .cpe-textarea {
            min-height: 110px;
            color: #222;
        }

        .cpe-textarea::placeholder {
            color: #333;
            opacity: 1;
        }

        .cpe-current-file {
            color: #333;
            font-size: 12px;
            font-weight: 700;
            margin: 10px 0 0;
        }

        .cpe-verification-note {
            border: 1px solid #dfd8d3;
            border-radius: 8px;
            background: #faf8f6;
            padding: 18px 20px;
            display: grid;
            gap: 10px;
            margin: 14px 0;
        }

        .cpe-verification-note .cpe-button {
            margin-top: 8px;
            width: 100%;
        }

        .cpe-verification-note--compact {
            margin: 14px 0;
        }

        /* Em telas mais estreitas, a lista e o visualizador se empilham
           e a leitura usa a rolagem normal da pagina. */
        @media (max-width: 1050px) {
            .cpe-conversation {
                grid-template-columns: minmax(0, 1fr);
            }

            .cpe-conversation__main {
                position: static;
                width: 100%;
                max-width: 720px;
                margin: 0 auto;
                max-height: none;
                overflow: visible;
                padding: 8px 24px;
            }

            .cpe-conversation__aside {
                width: 100%;
                min-height: 0;
            }

            .cpe-letter-doc,
            .cpe-conversation .cpe-letter-preview {
                max-height: none;
                overflow: visible;
            }
        }

        @media (max-width: 720px) {
            .cpe-conversation > .cpe-logo-top {
                padding-top: 68px;
            }

            .cpe-conversation__main {
                padding: 8px 16px;
            }

            .cpe-conversation__aside {
                padding: 8px 16px 32px;
            }

            .cpe-letter-header {
                gap: 12px;
                font-size: 16px;
            }

            .cpe-letter-header__party {
                flex-basis: 100%;
            }

            .cpe-letter-header .cpe-letter-actions {
                width: 100%;
            }

            .cpe-letter-doc {
                padding: 8px;
            }

            .cpe-form-row {
                grid-template-columns: 1fr;
            }

            .cpe-fixed-participants,
            .cpe-remetente-dados dl {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection
