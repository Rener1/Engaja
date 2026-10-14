@extends('layouts.app')

@section('content')
<div class="container">
  @php $disableImport = $atividades->isEmpty(); @endphp
  {{-- Cabeçalho --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h4 fw-bold text-engaja mb-1">Importar inscrições</h1>
      <div class="text-muted small">
        Ação pedagógica: <strong>{{ $evento->nome }}</strong>
        @php
          $periodoInicio = $evento->data_inicio ? \Carbon\Carbon::parse($evento->data_inicio)->format('d/m/Y') : null;
          $periodoFim = $evento->data_fim ? \Carbon\Carbon::parse($evento->data_fim)->format('d/m/Y') : null;
        @endphp
        @php $mostrarPeriodoFim = $periodoFim && (!$periodoInicio || $periodoFim !== $periodoInicio); @endphp
        @if($periodoInicio || $periodoFim)
        • {{ $periodoInicio ?? '—' }} @if($mostrarPeriodoFim)<span class="text-muted">até {{ $periodoFim }}</span>@endif
        @endif
      </div>
    </div>

    <a href="{{ route('eventos.show', $evento) }}" class="btn btn-outline-secondary">
      Voltar à ação pedagógica
    </a>
  </div>

  {{-- Aviso: campos obrigatórios --}}
  <div class="alert alert-info d-flex align-items-center gap-2 mb-4" role="alert">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-info-circle-fill flex-shrink-0" viewBox="0 0 16 16">
      <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
    </svg>
    <div>
      <strong>Nome e data de nascimento são obrigatórios. Informe e-mail ou CPF em cada registro.</strong>
      Todos os demais campos da planilha são opcionais durante o processo de importação.
    </div>
  </div>

  @if ($errors->any())
  <div class="alert alert-danger">
    <strong>Verifique os registros antes de importar:</strong>
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  {{-- Card do Formulário --}}
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST"
        action="{{ route('inscricoes.cadastro', $evento) }}"
        enctype="multipart/form-data"
        class="row g-3">
        @csrf

        <div class="col-12">
          <label class="form-label">Momento <span class="text-danger">*</span></label>
          @if($disableImport)
          <div class="alert alert-warning mb-2">
            Nenhum momento cadastrado para este evento. Cadastre um momento antes de importar as inscrições.
          </div>
          <select class="form-select" disabled>
            <option>Cadastre um momento antes de prosseguir</option>
          </select>
          @else
          <select
            name="atividade_id"
            class="form-select @error('atividade_id') is-invalid @enderror">
            <option value="" @selected(old('atividade_id', '') === '')>Todos os momentos desta ação</option>
            @foreach($atividades as $at)
            @php
              $dia = \Carbon\Carbon::parse($at->dia)->format('d/m/Y');
              $hora = $at->hora_inicio ? \Carbon\Carbon::parse($at->hora_inicio)->format('H:i') : null;
              $label = trim(($at->descricao ?: 'Momento') . ' — ' . $dia . ($hora ? ' ' . $hora : ''));
            @endphp
            <option value="{{ $at->id }}" @selected(old('atividade_id') == $at->id)>{{ $label }}</option>
            @endforeach
          </select>
          @error('atividade_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
          <div class="form-text">
            Em <strong>todos os momentos</strong>, cada participante da planilha será inscrito em cada momento da ação (o mesmo comportamento de "Inscrever selecionados" com o filtro em todos os momentos).
          </div>
          @endif
        </div>

        <div class="col-12">
          <label class="form-label">Origem da importação</label>
          <input type="text"
            name="origem"
            class="form-control @error('origem') is-invalid @enderror"
            value="{{ old('origem') }}"
            maxlength="255"
            placeholder="Ex.: LP, Moodle, formulário externo"
            @if($disableImport) disabled @endif>
          @error('origem') <div class="invalid-feedback">{{ $message }}</div> @enderror
          <div class="form-text">
            Se informada, essa origem será gravada para todos os usuários da planilha nesta ação.
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Arquivo Excel ou CSV (.xlsx, .xls, .csv) <span class="text-danger">*</span></label>
          <input type="file"
            name="your_file"
            class="form-control @error('your_file') is-invalid @enderror"
            accept=".xlsx,.xls,.csv"
            @if($disableImport) disabled @endif
            required>
          @error('your_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
          <div class="form-text">
            Envie um arquivo Excel ou CSV com a primeira linha como cabeçalho.
          </div>
        </div>

        <div class="mb-3">
          <div class="form-text">
            Colunas aceitas: <code>nome</code>, <code>email</code>, <code>cpf</code>, <code>telefone</code>, <code>municipio</code>, <code>estado</code>, <code>tipo_de_organizacao</code>, <code>organizacao</code>, <code>tag</code>, <code>data_nascimento</code>, <code>sexo</code>, <code>identidade_genero</code>, <code>raca_cor</code>, <code>comunidade_tradicional</code>, <code>faixa_etaria</code>, <code>pcd</code>, <code>orientacao_sexual</code>.
            <br>
            Municípios não cadastrados serão consultados no IBGE e criados automaticamente. Informe o estado/UF quando houver cidades com o mesmo nome.
          </div>
          <div class="mt-2 d-flex align-items-center gap-2">
            <a href="{{ asset('modelos/modelo_inscricoes_engaja.xlsx') }}" class="btn btn-sm btn-outline-primary">
              📥 Baixar modelo de planilha
            </a>
            <button type="button"
              class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
              data-bs-toggle="modal"
              data-bs-target="#modalTutorialPlanilha"
              title="Como preencher a planilha">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-question-circle" viewBox="0 0 16 16">
                <path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94z"/>
                <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zM0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8z"/>
              </svg>
              Como preencher corretamente
            </button>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <a href="{{ route('eventos.show', $evento) }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-engaja" @if($disableImport) disabled @endif>
            Importar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Tutorial de preenchimento da planilha --}}
@php
  $organizacoes = config('engaja.organizacoes', []);
  $tags = config('engaja.participante_tags', \App\Models\Participante::TAGS);
  $demograficos = config('engaja.demograficos', []);
@endphp

<div class="modal fade" id="modalTutorialPlanilha" tabindex="-1" aria-labelledby="modalTutorialLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="modalTutorialLabel">
           Como preencher a planilha de importação
        </h5>
      </div>
      <div class="modal-body">

        {{-- Aviso obrigatórios --}}
        <div class="alert alert-success py-2 mb-4">
           <strong>Nome e data de nascimento são obrigatórios. Informe e-mail ou CPF em cada registro.</strong> Todos os outros campos são opcionais durante o processo de importação.
        </div>

        {{-- Accordion por campo --}}
        <div class="accordion" id="accordionCampos">

          {{-- Nome --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-nome">
                <span class="badge bg-danger me-2">Obrigatório</span> Nome
              </button>
            </h2>
            <div id="campo-nome" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Nome completo do participante.</p>
                <p class="mb-0 text-muted small">Exemplo: <code>Maria da Silva</code></p>
              </div>
            </div>
          </div>

          {{-- Email --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-email">
                <span class="badge bg-secondary me-2">Email ou CPF</span> Email
              </button>
            </h2>
            <div id="campo-email" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Endereço de e-mail válido. É utilizado como identificador único do participante no sistema — se o e-mail já existir, o registro será <strong>atualizado</strong>; caso contrário, um novo usuário será <strong>criado</strong>.</p>
                <p class="mb-0 text-muted small">Exemplo: <code>maria@email.com</code></p>
              </div>
            </div>
          </div>

          {{-- CPF --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-cpf">
                <span class="badge bg-secondary me-2">Email ou CPF</span> CPF
              </button>
            </h2>
            <div id="campo-cpf" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>CPF do participante, obrigatório quando não houver e-mail. Nesse caso, o sistema utiliza o perfil mais recente com esse CPF ou cria um novo cadastro com e-mail fictício derivado do nome. Pode ser informado <strong>somente com números</strong> (recomendado) ou com pontuação — o sistema aceita ambos os formatos.</p>
                <p class="mb-0 text-muted small">Exemplos aceitos: <code>12345678900</code> ou <code>123.456.789-00</code></p>
              </div>
            </div>
          </div>

          {{-- Telefone --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-telefone">
                <span class="badge bg-secondary me-2">Opcional</span> Telefone
              </button>
            </h2>
            <div id="campo-telefone" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Número de telefone ou celular. Pode ser informado somente com dígitos ou com formatação.</p>
                <p class="mb-0 text-muted small">Exemplos aceitos: <code>11987654321</code> ou <code>(11) 98765-4321</code></p>
              </div>
            </div>
          </div>

          {{-- Município --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-municipio">
                <span class="badge bg-secondary me-2">Opcional</span> Município
              </button>
            </h2>
            <div id="campo-municipio" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Nome da cidade do participante. O sistema tentará localizar automaticamente no banco de dados. Se não existir, o município será criado via consulta ao IBGE.</p>
                <p>Sempre preencha também a coluna <strong>Estado/UF</strong> para evitar ambiguidade de municípios e causar problemas.</p>
                <p class="mb-0 text-muted small">Exemplo: <code>Fortaleza</code></p>
              </div>
            </div>
          </div>

          {{-- Estado --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-estado">
                <span class="badge bg-secondary me-2">Opcional</span> Estado / UF
              </button>
            </h2>
            <div id="campo-estado" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Sigla ou nome do estado.</p>
                <p class="mb-0 text-muted small">Exemplos: <code>CE</code>, <code>Ceará</code>, <code>SP</code></p>
              </div>
            </div>
          </div>

          {{-- Tipo de Organização --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-tipo-org">
                <span class="badge bg-secondary me-2">Opcional</span> Tipo de Organização
              </button>
            </h2>
            <div id="campo-tipo-org" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Deve ser exatamente uma das opções abaixo:</p>
                <ul class="mb-2 small">
                  @foreach($organizacoes as $org)
                  <li><code>{{ $org }}</code></li>
                  @endforeach
                </ul>
                <p class="mb-0 text-muted small">Valores que não corresponderem a nenhuma opção serão ignorados.</p>
              </div>
            </div>
          </div>

          {{-- Organização --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-org">
                <span class="badge bg-secondary me-2">Opcional</span> Organização
              </button>
            </h2>
            <div id="campo-org" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Nome livre da escola, unidade, coletivo ou organização à qual o participante pertence.</p>
                <p class="mb-0 text-muted small">Exemplo: <code>E.E. Professor João</code></p>
              </div>
            </div>
          </div>

          {{-- Tag --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-tag">
                <span class="badge bg-secondary me-2">Opcional</span> Tag
              </button>
            </h2>
            <div id="campo-tag" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Etiqueta que classifica o participante. Deve ser exatamente uma das opções abaixo:</p>
                <ul class="mb-2 small">
                  @foreach($tags as $tag)
                  <li><code>{{ $tag }}</code></li>
                  @endforeach
                </ul>
                <p class="mb-0 text-muted small">Valores fora da lista serão ignorados.</p>
              </div>
            </div>
          </div>

          {{-- Data de nascimento --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-nascimento">
                <span class="badge bg-danger me-2">Obrigatório</span> Data de nascimento
              </button>
            </h2>
            <div id="campo-nascimento" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Informe em todos os registros uma data de nascimento válida, sem data futura. Use ano com quatro dígitos: <code>25/03/1990</code> ou <code>1990-03-25</code>.</p>
                <p class="mb-0 text-muted small">Células reconhecidas pelo Excel como data também são aceitas, mesmo quando exibem o ano com dois dígitos.</p>
              </div>
            </div>
          </div>

          {{-- Sexo --}}
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-sexo">
                <span class="badge bg-secondary me-2">Opcional</span> Sexo
              </button>
            </h2>
            <div id="campo-sexo" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Informe uma das opções abaixo:</p>
                <ul class="mb-2 small">
                  @foreach(\App\Models\Participante::SEXOS as $sexo)
                  <li><code>{{ $sexo }}</code></li>
                  @endforeach
                </ul>
                <p class="mb-0 text-muted small">Deixe vazio para preservar o valor do cadastro existente. Valores fora da lista precisam ser corrigidos antes da importação.</p>
              </div>
            </div>
          </div>

          {{-- Campos demográficos --}}
          @php
            $labelsDemograficos = [
              'identidade_genero'      => 'Identidade de Gênero',
              'raca_cor'               => 'Raça / Cor',
              'comunidade_tradicional' => 'Comunidade Tradicional',
              'faixa_etaria'           => 'Faixa Etária',
              'pcd'                    => 'PcD',
              'orientacao_sexual'      => 'Orientação Sexual',
            ];
          @endphp

          @foreach($demograficos as $campo => $def)
          @php
            $label = $labelsDemograficos[$campo] ?? $campo;
            $temOutro = !empty($def['campo_outro']);
            $valorOutro = $def['valor_outro'] ?? null;
          @endphp
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#campo-{{ $campo }}">
                <span class="badge bg-secondary me-2">Opcional</span> {{ $label }}
              </button>
            </h2>
            <div id="campo-{{ $campo }}" class="accordion-collapse collapse" data-bs-parent="#accordionCampos">
              <div class="accordion-body">
                <p>Deve ser exatamente uma das opções abaixo:</p>
                <ul class="mb-2 small">
                  @foreach($def['opcoes'] as $opcao)
                  <li>
                    <code>{{ $opcao }}</code>
                    @if($campo === 'faixa_etaria' && str_contains($opcao, '('))
                      <span class="text-muted">— também aceita apenas <code>{{ trim(explode('(', $opcao)[0]) }}</code></span>
                    @endif
                  </li>
                  @endforeach
                </ul>
                @if($temOutro)
                <div class="alert alert-light py-2 small mb-0">
                   Se o valor não constar na lista, o sistema salvará como <strong>"{{ $valorOutro }}"</strong> e preservará o texto original que você digitou como informação complementar.
                </div>
                @else
                <p class="mb-0 text-muted small">Valores que não corresponderem a nenhuma opção serão <strong>ignorados</strong> (o campo ficará vazio).</p>
                @endif
              </div>
            </div>
          </div>
          @endforeach

        </div>{{-- /accordion --}}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
        <a href="{{ asset('modelos/modelo_inscricoes_engaja.xlsx') }}" class="btn btn-outline-primary">
          📥 Baixar modelo de planilha
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
