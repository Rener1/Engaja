@extends('layouts.app')

@section('content')
<style>
    /* Estilos para os Cards Selecionáveis da Ação Geral */
    .card-radio-input {
        display: none;
    }
    .card-radio-label {
        display: block;
        border: 2px solid #dee2e6;
        border-radius: 12px;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.2s ease;
        background-color: #ffffff;
    }
    .card-radio-label:hover {
        border-color: #babbbc;
        background-color: #f8f9fa;
    }
    .card-radio-input:checked + .card-radio-label {
        border-color: #421944;
        background-color: rgba(66, 25, 68, 0.04);
        box-shadow: 0 4px 12px rgba(66, 25, 68, 0.08);
    }
    .card-radio-input:checked + .card-radio-label .card-radio-title {
        color: #421944;
    }
    .custom-radio-circle {
        width: 24px;
        height: 24px;
        border: 2px solid #dee2e6;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        flex-shrink: 0;
        transition: all 0.2s ease;
    }
    .card-radio-input:checked + .card-radio-label .custom-radio-circle {
        border-color: #421944;
    }
    .card-radio-input:checked + .card-radio-label .custom-radio-circle::after {
        content: '';
        width: 12px;
        height: 12px;
        background-color: #421944;
        border-radius: 50%;
    }
</style>
<x-ui.page-hero contained eyebrow="Operações" eyebrow-icon="calendar-event" title="Editar ação pedagógica">
    <x-slot:actions>
        <a href="{{ route('eventos.planejamento.pdf', $evento) }}"
           class="eg-hero-btn" target="_blank" rel="noopener noreferrer">Ver Planejamento da Ação</a>
        <a href="{{ route('eventos.index') }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="container">
    @if ($errors->any())
    <div class="alert alert-danger">
        <strong>Ops!</strong> Verifique os campos abaixo.
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form id="form-planejamento" method="POST" action="{{ route('eventos.update', $evento) }}"
                  class="row g-3" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- ══ AÇÃO GERAL (Selectable Cards) ══ --}}
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Ação Geral <span class="text-danger">*</span></label>
                    <div class="form-text mb-3">Selecione uma das ações gerais abaixo para carregar as sub-ações correspondentes. O card selecionado ficará destacado.</div>

                    <div class="d-flex flex-column gap-3">
                        @foreach(\App\Models\Evento::ACOES_GERAIS as $key => $label)
                            <div>
                                <input type="radio"
                                       name="acao_geral"
                                       id="acao_geral_{{ $key }}"
                                       value="{{ $key }}"
                                       class="card-radio-input acao-geral-radio"
                                       @checked(old('acao_geral', $evento->acao_geral ?? '') == $key)
                                       required>
                                <label class="card-radio-label d-flex align-items-start m-0" for="acao_geral_{{ $key }}">
                                    <div class="custom-radio-circle mt-1"></div>
                                    <div>
                                        <strong class="d-block mb-1 card-radio-title" style="font-size: 1.1rem;">Ação Geral {{ $key }}</strong>
                                        <span class="text-muted" style="font-size: 0.9rem; line-height: 1.4;">{{ $label }}</span>
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('acao_geral')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div>

                {{-- ══ SUB-AÇÃO ══ --}}
                <div class="col-12">
                    <label for="subacao" class="form-label">Sub-Ação <span class="text-danger">*</span></label>
                    <select id="subacao" name="subacao"
                        class="form-select @error('subacao') is-invalid @enderror" required disabled>
                        <option value="">Selecione primeiro a Ação Geral…</option>
                    </select>
                    @error('subacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- ══ NOME ══ --}}
                <div class="col-12">
                    <label for="nome" class="form-label">Nome da ação pedagógica <span class="text-danger">*</span></label>
                    <input id="nome" name="nome" type="text"
                           value="{{ old('nome', $evento->nome) }}"
                           class="form-control @error('nome') is-invalid @enderror" required>
                    @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Tipo --}}
                <div class="col-md-4">
                    <label for="tipo" class="form-label">Tipo</label>
                    @php $tipoSel = old('tipo', $evento->tipo); @endphp
                    <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror">
                        <option value="">Selecione...</option>
                        <option value="Cartas para Esperançar" @selected($tipoSel=="Cartas para Esperançar")>Cartas para Esperançar</option>
                        <option value="Curso Como Alfabetizar com Paulo Freire" @selected($tipoSel=="Curso Como Alfabetizar com Paulo Freire")>Curso Como Alfabetizar com Paulo Freire</option>
                        <option value="Encontros Escuta Territorial" @selected($tipoSel=="Encontros Escuta Territorial")>Encontros Escuta Territorial</option>
                        <option value="Encontros de Educandos" @selected($tipoSel=="Encontros de Educandos")>Encontros de Educandos</option>
                        <option value="Encontros de Formação" @selected($tipoSel=="Encontros de Formação")>Encontros de Formação</option>
                        <option value="Feira Pedagógica, Artístico-Cultural com Educandos" @selected($tipoSel=="Feira Pedagógica, Artístico-Cultural com Educandos")>Feira Pedagógica, Artístico-Cultural com Educandos</option>
                        <option value="Lives e Webinars" @selected($tipoSel=="Lives e Webinars")>Lives e Webinars</option>
                        <option value="Reunião de Assessoria" @selected($tipoSel=="Reunião de Assessoria")>Reunião de Assessoria</option>
                        <option value="Seminários de Práticas" @selected($tipoSel=="Seminários de Práticas")>Seminários de Práticas</option>
                        <option value="Veja as Palavras" @selected($tipoSel=="Veja as Palavras")>Veja as Palavras</option>
                    </select>
                    @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Modalidade --}}
                <div class="col-md-4">
                    <label for="modalidade" class="form-label">Modalidade</label>
                    <select id="modalidade" name="modalidade" class="form-select @error('modalidade') is-invalid @enderror">
                        <option value="">Selecione...</option>
                        <option value="Presencial" @selected(old('modalidade', $evento->modalidade)=="Presencial")>Presencial</option>
                        <option value="Online" @selected(old('modalidade', $evento->modalidade)=="Online")>Online</option>
                        <option value="Híbrido" @selected(old('modalidade', $evento->modalidade)=="Híbrido")>Híbrido</option>
                    </select>
                    @error('modalidade')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Local --}}
                <div class="col-md-4">
                    <label for="local" class="form-label">Local</label>
                    <input id="local" name="local" type="text"
                           value="{{ old('local', $evento->local) }}"
                           placeholder="Auditório / Link"
                           class="form-control @error('local') is-invalid @enderror">
                    @error('local')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Datas --}}
                <div class="col-md-3">
                    <label for="data_inicio" class="form-label">Data de início</label>
                    <input id="data_inicio" name="data_inicio" type="date"
                           value="{{ old('data_inicio', optional($evento->data_inicio ? \Carbon\Carbon::parse($evento->data_inicio) : null)?->format('Y-m-d')) }}"
                           class="form-control @error('data_inicio') is-invalid @enderror">
                    @error('data_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="data_fim" class="form-label">Data de término</label>
                    <input id="data_fim" name="data_fim" type="date"
                           value="{{ old('data_fim', optional($evento->data_fim ? \Carbon\Carbon::parse($evento->data_fim) : null)?->format('Y-m-d')) }}"
                           class="form-control @error('data_fim') is-invalid @enderror">
                    @error('data_fim')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Link --}}
                <div class="col-md-6">
                    <label for="link" class="form-label">Link (se online)</label>
                    <input id="link" name="link" type="url"
                           value="{{ old('link', $evento->link) }}"
                           class="form-control @error('link') is-invalid @enderror" placeholder="https://...">
                    @error('link')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Imagem --}}
                <div class="col-md-6">
                    <label class="form-label d-block">Imagem da ação pedagógica</label>
                    @if ($evento->imagem)
                        <img src="{{ asset('storage/'.$evento->imagem) }}"
                             alt="Imagem atual" class="img-fluid rounded mb-2" style="max-height:160px">
                    @else
                        <img src="{{ asset('images/logo-aeb.png') }}"
                             alt="Sem imagem" class="img-fluid rounded mb-2"
                             style="max-height:160px; opacity:.4">
                    @endif
                    <input id="imagem" name="imagem" type="file"
                           class="form-control @error('imagem') is-invalid @enderror" accept="image/*">
                    <div class="form-text">Deixe em branco para manter a atual. Máx. 2MB</div>
                    @error('imagem')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <img id="preview-imagem" class="mt-2 img-fluid d-none rounded"
                         alt="Pré-visualização" style="max-height:160px">
                </div>

                {{-- ══ OBJETIVOS E CONTEXTO ══ --}}
                <div class="col-12">
                    <hr class="my-1">
                    <h5 class="fw-semibold text-muted mb-3">Objetivos e Contexto Pedagógico</h5>
                </div>

                <div class="col-12">
                    <label for="objetivos_gerais" class="form-label">Objetivos Gerais</label>
                    <textarea id="objetivos_gerais" name="objetivos_gerais" rows="3"
                        class="form-control @error('objetivos_gerais') is-invalid @enderror"
                        placeholder="Descreva os objetivos gerais…">{{ old('objetivos_gerais', $evento->objetivos_gerais) }}</textarea>
                    @error('objetivos_gerais')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="objetivos_especificos" class="form-label">Objetivos Específicos</label>
                    <textarea id="objetivos_especificos" name="objetivos_especificos" rows="3"
                        class="form-control @error('objetivos_especificos') is-invalid @enderror"
                        placeholder="Descreva os objetivos específicos…">{{ old('objetivos_especificos', $evento->objetivos_especificos) }}</textarea>
                    @error('objetivos_especificos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="recursos_materiais_necessarios" class="form-label">Recursos Materiais Necessários</label>
                    <div class="form-text mb-1">(Descreva aqui os recursos materiais que precisarão providenciar para a realização da ação)</div>
                    <textarea id="recursos_materiais_necessarios" name="recursos_materiais_necessarios" rows="3"
                        class="form-control @error('recursos_materiais_necessarios') is-invalid @enderror">{{ old('recursos_materiais_necessarios', $evento->recursos_materiais_necessarios) }}</textarea>
                    @error('recursos_materiais_necessarios')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="providencias_sme_parceria" class="form-label">Providências junto à SME / Parceria</label>
                    <div class="form-text mb-1">(Descreva aqui a lista de providências que precisarão ser encaminhadas junto às Secretarias de Educação parceiras)</div>
                    <textarea id="providencias_sme_parceria" name="providencias_sme_parceria" rows="3"
                        class="form-control @error('providencias_sme_parceria') is-invalid @enderror">{{ old('providencias_sme_parceria', $evento->providencias_sme_parceria) }}</textarea>
                    @error('providencias_sme_parceria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="observacoes_complementares" class="form-label">Observações Complementares</label>
                    <div class="form-text mb-1">(Descreva aqui eventuais observações complementares)</div>
                    <textarea id="observacoes_complementares" name="observacoes_complementares" rows="3"
                        class="form-control @error('observacoes_complementares') is-invalid @enderror">{{ old('observacoes_complementares', $evento->observacoes_complementares) }}</textarea>
                    @error('observacoes_complementares')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- ══ SITUAÇÕES DESAFIADORAS ══ --}}
                @if(isset($situacoes) && $situacoes->isNotEmpty())
                @php $sitsSelecionadas = old('situacoes_desafiadoras', $evento->situacoesDesafiadoras->pluck('id')->toArray()); @endphp
                <div class="col-12">
                    <hr class="my-1">
                    <h5 class="fw-semibold text-muted mb-2">Situações Desafiadoras da EJA a serem enfrentadas</h5>
                    <div class="form-text mb-2">(Selecione as situações desafiadoras que esta ação pedagógica pretende enfrentar)</div>
                    @foreach($situacoes as $categoria => $itens)
                        <p class="text-uppercase small text-secondary mb-1 mt-3">{{ $categoria }}</p>
                        <div class="border rounded p-3" style="max-height:200px; overflow-y:auto;">
                            @foreach($itens as $situacao)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox"
                                    name="situacoes_desafiadoras[]"
                                    id="sit_{{ $situacao->id }}"
                                    value="{{ $situacao->id }}"
                                    @checked(in_array($situacao->id, $sitsSelecionadas))>
                                <label class="form-check-label small" for="sit_{{ $situacao->id }}">
                                    {{ $situacao->nome }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @endif

                {{-- ══ MATRIZ DE APRENDIZAGENS ══ --}}
                @if(isset($matrizes) && $matrizes->isNotEmpty())
                @php $matrizesSelecionadas = old('matrizes', $evento->matrizes->pluck('id')->toArray()); @endphp
                <div class="col-12">
                    <hr class="my-1">
                    <h5 class="fw-semibold text-muted mb-2">Matriz de Aprendizagens</h5>
                    <div class="form-text mb-2">(Selecione as aprendizagens a serem fomentadas nesta ação pedagógica)</div>
                    <div class="border rounded p-3" style="max-height:320px; overflow-y:auto;">
                        @foreach($matrizes as $matriz)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                name="matrizes[]"
                                id="mat_{{ $matriz->id }}"
                                value="{{ $matriz->id }}"
                                @checked(in_array($matriz->id, $matrizesSelecionadas))>
                            <label class="form-check-label" for="mat_{{ $matriz->id }}">
                                <strong>{{ $matriz->nome }}</strong>
                                <div class="text-muted small" style="white-space:pre-line">{{ $matriz->descricao }}</div>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- ══ ODS ══ --}}
                <div class="col-12">
                    <hr class="my-1">
                    <h5 class="fw-semibold text-muted mb-2">Interfaces com os Objetivos de Desenvolvimento Sustentável (ODS)</h5>
                    <div class="form-text mb-2">(Esta Ação se relaciona com os ODS abaixo. Para cada um deles, marque os possíveis aspectos que esta Ação poderá contribuir).</div>

                    <div id="ods-container" class="border rounded p-3 bg-light d-none"></div>
                    <div id="ods-empty-state" class="alert alert-secondary small text-center mb-0 mt-2">
                        Selecione uma Ação Geral acima para visualizar os ODS relacionados.
                    </div>
                    @error('ods_selecionados')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                {{-- ══ SEQUÊNCIA DIDÁTICA ══ --}}
                @php
                    $sequenciasExistentes = old('sequencias',
                        $evento->sequenciasDidaticas->map(fn($s) => [
                            'periodo'   => $s->periodo,
                            'descricao' => $s->descricao,
                        ])->toArray()
                    );
                @endphp
                <div class="col-12">
                    <hr class="my-1">
                    <h5 class="fw-semibold text-muted mb-3">Sequência Didática das Atividades</h5>
                    <div class="form-text mb-2">(Descreva aqui o passo a passo da ação, em cada um dos dias/períodos em que ela se realizada)</div>

                    <div class="row align-items-center g-2 mb-3">
                        <div class="col-auto">
                            <label class="col-form-label">
                                Esta ação irá se realizar em mais de um dia/período? Se sim, indique quantos dias:
                            </label>
                        </div>
                        <div class="col-auto">
                            <input type="number" id="qtd_dias" min="0" max="30"
                                   value="{{ count($sequenciasExistentes) }}"
                                   class="form-control" style="width:90px">
                        </div>
                    </div>

                    <div id="blocos-sequencia"></div>
                </div>

                <div id="hidden-checklist-inputs"></div>

                {{-- Botões --}}
                <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    <a href="{{ route('eventos.show', $evento) }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-engaja">Salvar alterações</button>
                </div>

            </form>
        </div>
    </div>
</div>

{{-- ══ MODAL CHECKLIST DE PLANEJAMENTO ══ --}}
<div class="modal fade" id="modalChecklistPlanejamento" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <x-ui.modal-header icon="list-check" title="Checklist do Planejamento" :dismissible="false" />
            <div class="modal-body">
                <div class="form-text mb-3">(Antes de concluir este planejamento, faça a seguinte verificação, marcando os tópicos realizados)</div>

                @php
                    $itensChecklist = [
                        'Ao planejar cada ação, recorri aos objetivos gerais do projeto, em diálogo com os dados da Leitura do Mundo e com ações que já foram realizadas? Tenho clareza do que já foi feito e do que precisa ser feito na ação que estou planejando?',
                        'Ao planejar, estabeleci conexão com as outras ações do projeto? Por exemplo, Oficinas de Leitura e Escrita não estão desconectadas do Cartas para Esperançar, do Semear Palavras, da Escuta Territorial e do livreto dos educandos da EJA a ser publicado em 2027. Ao planejar, você se perguntou se estabeleceu as devidas conexões com outras ações do projeto e esta que vc está realizando?',
                        'Preparei listas de presença impressas de acordo com os dados a serem inseridos no sistema ENGAJA?',
                        'Preparei formulários de avaliação de cada ação de formação, incluindo questões que possam medir os impactos que se pretende alcançar no projeto? As avaliações preparadas oferecem informações relevantes para medir o impacto das ações? Você tem clareza do que precisa ser avaliado e está contemplando no instrumental preparado?',
                        'Organizei a lista de materiais necessários para as ações de formação que pretendo realizar e apresentei à coordenação geral/gerência com antecedência?',
                        'Organizei a demanda de infraestrutura local com antecedência?',
                        'A inscrição do público esperado na formação foi feita?',
                        'A informação sobre o dia e horário da formação com cada grupo chegou com antecedência aos públicos participantes?',
                        'Os materiais institucionais do projeto para entregar para os participantes estão organizados?',
                        'Equipe Pedagógica e Educadores articuladores sociais estão com clareza de quem fará o que durante os encontros presenciais de assessoria e formação.',
                        'Planejei os momentos de registros audiovisual de cada ação de formação?',
                        'Sei como nomear os arquivos e o local onde compartilhar os registros processuais?',
                        'Estou de posse de todos os contatos estratégicos relacionados os envolvidos na ação que vou realizar? Em caso de necessidade, sei a quem recorrer e o contato da pessoa?',
                    ];
                    $salvos = isset($evento) && is_array($evento->checklist_planejamento) ? $evento->checklist_planejamento : [];
                @endphp

                <div class="vstack gap-2">
                    @foreach($itensChecklist as $idx => $item)
                        @php $isChecked = in_array($idx, old('checklist_planejamento', $salvos)); @endphp
                        <label class="d-flex align-items-start gap-3 p-3 border rounded"
                               style="cursor:pointer; transition: all 0.2s;"
                               onchange="this.style.borderColor = this.querySelector('input').checked ? '#421944' : '#dee2e6'; this.style.backgroundColor = this.querySelector('input').checked ? 'rgba(66,25,68,0.05)' : 'transparent';">
                            <input type="checkbox" class="form-check-input mt-1 item-checklist-modal"
                                   value="{{ $idx }}" @checked($isChecked) style="transform: scale(1.3);">
                            <span class="small" style="line-height: 1.4;">{{ $item }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar ao formulário</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary" id="btn-concluir-depois">Salvar e concluir depois</button>
                    <button type="button" class="btn btn-engaja" id="btn-finalizar-planejamento" style="background-color: #421944; color: white;">Finalizar planejamento</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // ── Sub-ação dependency map ───────────────────────────────────────────
    const SUBACOES_MAP = {
        '1': [
            '1.1 - Mapeamento inicial - Leitura do Mundo',
            '1.2 - Formação aos educandos da EJA',
            '1.3 - Escuta Territorial - Feira Pedagógica Artístico-Cultural',
        ],
        '2': [
            '2.1 - Assessoria e formação às equipes de EJA nas redes municipais parceiras',
            '2.2 - Realização de curso EaD "Como Alfabetizar com Paulo Freire"',
            '2.3 - Realização de lives formativas educacionais ampliadas',
            '2.4 - Participação em eventos ampliados, como COP 30 e Encontro de EJA',
        ],
        '3': [
            '3.1 - Produção de materiais de formação em vídeos, cadernos, ebooks e podcasts/videocasts',
            '3.2 - Criação e inserção de dados no Centro de Referência da EJA – CREJA',
        ],
    };

    // ── ODS dependency map ────────────────────────────────────────────────
    const ODS_MAP = {
        '1': {
            'ODS 4 – Educação de Qualidade': [
                'Ampliação da permanência dos educandos nas turmas participantes.',
                'Melhoria progressiva dos níveis de leitura e escrita observada nos educandos.',
                'Incorporação de práticas pedagógicas contextualizadas ao território.',
                'Produção autoral dos educandos (textos, registros, narrativas).',
                'Fortalecimento do vínculo entre educandos e escola.',
                'Ampliação da participação ativa dos educandos nas decisões pedagógicas.'
            ],
            'ODS 10 – Redução das Desigualdades': [
                'Ampliação do acesso à EJA por públicos historicamente excluídos.',
                'Redução das barreiras de permanência relacionadas a gênero, idade e território.',
                'Inclusão efetiva de sujeitos com deficiência nas atividades formativas.',
                'Democratização do acesso à cultura digital.',
                'Ampliação da representatividade de diferentes perfis de educandos nas ações.'
            ],
            'ODS 16 – Paz, Justiça e Instituições Eficazes': [
                'Consolidação de espaços permanentes de escuta dos educandos.',
                'Incorporação das demandas territoriais ao planejamento pedagógico.',
                'Fortalecimento da transparência institucional por meio de relatórios públicos.',
                'Estabelecimento de mecanismos participativos na gestão da EJA.',
                'Maior articulação entre escola, comunidade e poder público.'
            ]
        },
        '2': {
            'ODS 4 – Educação de Qualidade': [
                'Mudança qualitativa nas práticas de alfabetização adotadas pelas redes.',
                'Adoção de metodologias coerentes com a Educação Popular.',
                'Consolidação de rotinas de planejamento pedagógico colaborativo.',
                'Elevação da confiança e segurança pedagógica dos alfabetizadores.',
                'Integração entre formação continuada e prática cotidiana.'
            ],
            'ODS 5 – Igualdade de Gênero': [
                'Participação expressiva de mulheres nas formações e espaços de liderança.',
                'Inclusão sistemática da pauta de gênero nas propostas pedagógicas.',
                'Produção de materiais que valorizam trajetórias femininas na EJA.',
                'Ampliação da autonomia profissional de educadoras.',
                'Maior visibilidade das mulheres nos processos decisórios da EJA.'
            ],
            'ODS 8 – Trabalho Decente e Crescimento Econômico': [
                'Elevação da escolaridade de jovens e adultos trabalhadores.',
                'Ampliação das competências leitoras e matemáticas aplicadas ao mundo do trabalho.',
                'Fortalecimento da profissionalização docente.',
                'Contribuição indireta para melhoria das condições de empregabilidade dos educandos.',
                'Reconhecimento da EJA como política estratégica para inclusão produtiva.'
            ],
            'ODS 16 – Paz, Justiça e Instituições Eficazes': [
                'Consolidação da EJA como política institucionalizada nas redes municipais.',
                'Fortalecimento da governança pedagógica da EJA.',
                'Integração entre gestão escolar e gestão da Secretaria.',
                'Institucionalização de processos de monitoramento e avaliação.',
                'Melhoria da capacidade técnica das equipes gestoras.'
            ]
        },
        '3': {
            'ODS 4 – Educação de Qualidade': [
                'Ampliação do acesso público a materiais formativos de qualidade.',
                'Sistematização e difusão de práticas pedagógicas inovadoras.',
                'Fortalecimento da cultura de estudo permanente nas redes.',
                'Ampliação da autonomia docente por meio do acesso a referenciais teóricos.'
            ],
            'ODS 10 – Redução das Desigualdades': [
                'Disponibilização gratuita de conteúdos formativos.',
                'Produção de materiais acessíveis (LIBRAS, legendas, linguagem inclusiva).',
                'Valorização de experiências oriundas de territórios periféricos.',
                'Inclusão da voz dos educandos na memória institucional.'
            ],
            'ODS 16 – Paz, Justiça e Instituições Eficazes': [
                'Consolidação de repositório público de memória da EJA.',
                'Transparência no acompanhamento das ações do projeto.',
                'Fortalecimento institucional da política pública de EJA.',
                'Integração entre memória, avaliação e planejamento.',
                'Sustentabilidade institucional da formação continuada.'
            ]
        }
    };

    // Valores actuais do evento (com fallback para old() em erros de validação)
    const currentAcaoGeral       = @json(old('acao_geral', $evento->acao_geral ?? ''));
    const currentSubacao         = @json(old('subacao', $evento->subacao ?? ''));
    const odsSelecionadosAntigos = @json(old('ods_selecionados', $evento->ods_selecionados ?? []));

    function preencherSubacoes(acaoGeral, subacaoSelecionada) {
        const select = document.getElementById('subacao');
        const opcoes = SUBACOES_MAP[acaoGeral] ?? [];

        select.innerHTML = '<option value="">Selecione a Sub-Ação…</option>';
        select.disabled  = opcoes.length === 0;

        opcoes.forEach(function (texto) {
            const opt = document.createElement('option');
            opt.value       = texto;
            opt.textContent = texto;
            if (texto === subacaoSelecionada) opt.selected = true;
            select.appendChild(opt);
        });
    }

    // ── ODS ───────────────────────────────────────────────────────────────
    function preencherODS(acaoGeral) {
        const container  = document.getElementById('ods-container');
        const emptyState = document.getElementById('ods-empty-state');
        if (!container || !emptyState) return;

        const categorias = ODS_MAP[acaoGeral] ?? null;

        if (!categorias) {
            container.innerHTML = '';
            container.classList.add('d-none');
            emptyState.classList.remove('d-none');
            return;
        }

        container.innerHTML = '';
        emptyState.classList.add('d-none');
        container.classList.remove('d-none');

        let catIdx = 0;
        Object.entries(categorias).forEach(function ([nomeODS, opcoes]) {
            let html = `<p class="fw-semibold text-uppercase small text-secondary mb-1 mt-2">${escHtml(nomeODS)}</p><div class="vstack gap-1 mb-3">`;
            opcoes.forEach(function (texto, i) {
                const id      = 'ods_' + acaoGeral + '_' + catIdx + '_' + i;
                const checked = odsSelecionadosAntigos.includes(texto) ? 'checked' : '';
                html += `<div class="form-check">
                <input class="form-check-input" type="checkbox" name="ods_selecionados[]" id="${id}" value="${escHtml(texto)}" ${checked}>
                <label class="form-check-label small" for="${id}">${escHtml(texto)}</label>
            </div>`;
            });
            html += '</div>';
            container.insertAdjacentHTML('beforeend', html);
            catIdx++;
        });
    }

    // ── Image preview ─────────────────────────────────────────────────────
    document.getElementById('imagem')?.addEventListener('change', function (e) {
        const file = e.target.files?.[0];
        const img  = document.getElementById('preview-imagem');
        if (file && img) {
            img.src = URL.createObjectURL(file);
            img.classList.remove('d-none');
        }
    });

    // ── Sequência Didática ────────────────────────────────────────────────
    const sequenciasIniciais = @json($sequenciasExistentes);

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function criarBloco(index, periodo, descricao) {
        return `
        <div class="card mb-3 border-secondary-subtle">
            <div class="card-header bg-light py-2 d-flex align-items-center gap-2">
                <span class="badge bg-secondary">${index + 1}</span>
                <strong>Dia / Período ${index + 1}</strong>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Dia / Período</label>
                    <input type="text" name="sequencias[${index}][periodo]"
                           value="${escHtml(periodo)}" class="form-control"
                           placeholder="Ex.: Dia 1 – Manhã">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Descrição do passo a passo</label>
                    <textarea name="sequencias[${index}][descricao]" class="form-control" rows="3"
                              placeholder="Descreva as atividades previstas…">${escHtml(descricao)}</textarea>
                </div>
            </div>
        </div>`;
    }

    function ajustarBlocos(qtd) {
        qtd = Math.max(0, Math.min(30, parseInt(qtd) || 0));
        const container = document.getElementById('blocos-sequencia');
        while (container.children.length > qtd) container.removeChild(container.lastChild);
        for (let i = container.children.length; i < qtd; i++) {
            const seq = sequenciasIniciais[i] ?? { periodo: '', descricao: '' };
            container.insertAdjacentHTML('beforeend', criarBloco(i, seq.periodo, seq.descricao));
        }
    }

    // ── Bootstrap ────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const radiosAcaoGeral = document.querySelectorAll('.acao-geral-radio');

        if (currentAcaoGeral) {
            preencherSubacoes(currentAcaoGeral, currentSubacao);
            preencherODS(currentAcaoGeral);
        }

        radiosAcaoGeral.forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (this.checked) {
                    preencherSubacoes(this.value, '');
                    preencherODS(this.value);
                }
            });
        });

        ajustarBlocos(sequenciasIniciais.length);
        document.getElementById('qtd_dias')?.addEventListener('input', function () {
            ajustarBlocos(this.value);
        });
    });

    // ── Modal Checklist de Planejamento ───────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const formPlanejamento = document.getElementById('form-planejamento');
        const modalChecklistEl = document.getElementById('modalChecklistPlanejamento');
        let modalChecklistObj  = null;

        if (modalChecklistEl) {
            modalChecklistObj = new bootstrap.Modal(modalChecklistEl);
        }

        if (formPlanejamento) {
            formPlanejamento.addEventListener('submit', function (e) {
                if (!formPlanejamento.dataset.readyToSubmit) {
                    e.preventDefault();
                    if (modalChecklistObj) modalChecklistObj.show();
                }
            });
        }

        function submeterFormulario() {
            const container = document.getElementById('hidden-checklist-inputs');
            container.innerHTML = '';
            document.querySelectorAll('.item-checklist-modal:checked').forEach(function (cb) {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'checklist_planejamento[]';
                input.value = cb.value;
                container.appendChild(input);
            });
            formPlanejamento.dataset.readyToSubmit = 'true';
            formPlanejamento.submit();
        }

        document.getElementById('btn-concluir-depois')?.addEventListener('click', function () {
            submeterFormulario();
        });

        document.getElementById('btn-finalizar-planejamento')?.addEventListener('click', function () {
            const todosChecks = document.querySelectorAll('.item-checklist-modal');
            const marcados    = document.querySelectorAll('.item-checklist-modal:checked');
            if (marcados.length < todosChecks.length) {
                alert('Para finalizar o planejamento, você precisa verificar e marcar todos os tópicos do checklist.\n\nSe ainda não concluiu tudo, clique em "Salvar e concluir depois".');
                return;
            }
            submeterFormulario();
        });
    });
}());
</script>
@endpush
@endsection