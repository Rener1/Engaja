@extends('layouts.app')
@section('content')
<x-ui.page-hero eyebrow="Pessoas" eyebrow-icon="camera" title="Autorizações de imagem por ação pedagógica"
    subtitle="Consulte e registre as respostas de uso de imagem, voz e nome específicas de cada ação.">
  @if($evento)
    <x-slot:actions>
      @can('export', \App\Models\AutorizacaoImagem::class)
        <a class="eg-hero-btn eg-hero-btn--solid" href="{{ route('autorizacoes-imagem.export', request()->only('evento_id', 'situacao', 'busca')) }}">
          <i class="bi bi-file-earmark-spreadsheet me-1"></i> Baixar planilha desta consulta
        </a>
      @endcan
    </x-slot:actions>
  @endif
</x-ui.page-hero>

@include('autorizacoes-imagem.partials.erros')

<x-ui.filter-card>
  <form method="GET" action="{{ route('autorizacoes-imagem.index') }}" class="row g-3 align-items-end">
    <div class="col-12 col-lg-5">
      <label class="eg-filter-label" for="evento_id">Ação pedagógica</label>
      <x-ui.select id="evento_id" name="evento_id" required>
        <option value="">Selecione</option>
        @foreach($eventos as $opcao)
          <option value="{{ $opcao->id }}" @selected($evento?->id == $opcao->id)>{{ $opcao->nome }}</option>
        @endforeach
      </x-ui.select>
    </div>
    <div class="col-12 col-md-4 col-lg-3">
      <label class="eg-filter-label" for="situacao">Situação</label>
      <x-ui.select id="situacao" name="situacao">
        <option value="">Todas</option>
        @foreach(\App\Enums\SituacaoAutorizacaoImagem::cases() as $situacao)
          <option value="{{ $situacao->value }}" @selected(request('situacao') === $situacao->value)>{{ $situacao->rotulo() }}</option>
        @endforeach
      </x-ui.select>
    </div>
    <div class="col-12 col-md-8 col-lg-4">
      <label class="eg-filter-label" for="busca">Nome, e-mail ou CPF</label>
      <x-ui.input type="search" id="busca" name="busca" value="{{ request('busca') }}" maxlength="255" placeholder="Buscar participante" />
    </div>
    <div class="col-12 eg-filter-actions justify-content-end">
      @if(request()->anyFilled(['evento_id', 'situacao', 'busca']))
        <a href="{{ route('autorizacoes-imagem.index') }}" class="btn btn-outline-secondary">
          <i class="bi bi-x-circle me-1"></i> Limpar filtros
        </a>
      @endif
      <button type="submit" class="btn btn-engaja px-3">
        <i class="bi bi-funnel-fill me-1"></i> Filtrar
      </button>
    </div>
  </form>
</x-ui.filter-card>

@if($evento)
  @php
    $estiloSituacao = [
        'pendente' => ['color' => 'gray', 'icon' => 'hourglass-split'],
        'autorizada' => ['color' => 'green', 'icon' => 'check-circle-fill'],
        'negada' => ['color' => 'pink', 'icon' => 'x-circle-fill'],
    ];
    $podeRegistrar = auth()->user()->can('create', \App\Models\AutorizacaoImagem::class);

    $columns = [
        ['field' => 'participante', 'headerName' => 'Participante', 'flex' => 2, 'minWidth' => 240, 'html' => true],
        ['field' => 'cpf', 'headerName' => 'CPF', 'flex' => 1, 'minWidth' => 140],
        ['field' => 'situacao', 'headerName' => 'Resposta nesta ação', 'flex' => 1.2, 'minWidth' => 180, 'html' => true],
        ['field' => 'respondido', 'headerName' => 'Respondida em', 'flex' => 1.2, 'minWidth' => 180, 'html' => true],
        ['field' => 'acoes', 'headerName' => 'Ação', 'flex' => 1, 'minWidth' => 170, 'html' => true],
    ];

    $rows = $participantes->map(function ($participante) use ($podeRegistrar) {
        $resposta = $participante->autorizacoesImagem->first();
        $rotuloBotao = $podeRegistrar ? ($resposta ? 'Alterar resposta' : 'Registrar resposta') : 'Ver histórico';
        $acao = ($podeRegistrar || $resposta)
            ? '<button type="button" class="btn btn-sm btn-engaja" data-bs-toggle="modal" data-bs-target="#modalAutorizacaoImagem-'.$participante->id.'">'.$rotuloBotao.'</button>'
            : '<span class="text-muted">—</span>';

        return [
            'id' => $participante->id,
            'participante' => '<div class="lh-sm"><div class="fw-semibold">'.e($participante->user->name).'</div><div class="small text-muted">'.e($participante->user->email).'</div></div>',
            'cpf' => $participante->cpf ?: '—',
            'situacao' => view('autorizacoes-imagem.partials.situacao-badge', ['resposta' => $resposta])->render(),
            'respondido' => $resposta
                ? '<div class="lh-sm"><div>'.$resposta->respondido_em->format('d/m/Y H:i').'</div><div class="small text-muted">'.e(\App\Enums\OrigemAutorizacaoImagem::from($resposta->origem)->rotulo()).'</div></div>'
                : '<span class="text-muted">—</span>',
            'acoes' => $acao,
        ];
    })->values();
  @endphp

  <div class="eg-stats-row" style="--eg-stat-cols: 3">
    @foreach(\App\Enums\SituacaoAutorizacaoImagem::cases() as $situacao)
      <x-ui.stat-card :color="$estiloSituacao[$situacao->value]['color']" :icon="$estiloSituacao[$situacao->value]['icon']"
          :label="$situacao->rotulo()" :value="$totais[$situacao->value]" />
    @endforeach
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <x-data-table
          id="grid-autorizacoes-imagem"
          :columns="$columns"
          :rows="$rows"
          :pagination="false"
          :paginator="$participantes"
          empty-text="Nenhum participante encontrado."
      />
    </div>
  </div>

  @foreach($participantes as $participante)
    @php($resposta = $participante->autorizacoesImagem->first())
    @if($podeRegistrar || $resposta)
      @include('autorizacoes-imagem.partials.modal', [
          'chave' => $participante->id,
          'titulo' => 'Autorização de imagem',
          'subtitulo' => $participante->user->name.' · '.$evento->nome,
          'resposta' => $resposta,
          'gestao' => true,
          'podeResponder' => $podeRegistrar,
          'contexto' => $contextos[$participante->id] ?? null,
          'url' => route('autorizacoes-imagem.registrar', [$evento, $participante]),
      ])
    @endif
  @endforeach
@else
  <x-ui.empty-state icon="funnel" title="Selecione uma ação pedagógica">
    Escolha a ação no filtro acima para ver e registrar as respostas dos participantes.
  </x-ui.empty-state>
@endif
@endsection
