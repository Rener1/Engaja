@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="globe" title="Avaliações universais">
    <p class="text-muted mb-0">Avaliações anônimas, sem vínculo com momento.</p>
    <x-slot:actions>
        <a href="{{ route('avaliacoes-universais.create') }}" class="eg-hero-btn eg-hero-btn--solid">Nova avaliação universal</a>
    </x-slot:actions>
</x-ui.page-hero>

<x-ui.filter-card>
<form method="GET" action="{{ route('avaliacoes-universais.index') }}">
    <div class="row g-3 align-items-end">
      <div class="col-lg-3 col-md-6">
        <label for="search" class="eg-filter-label">Buscar por modelo ou descrição</label>
        <x-ui.input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Digite para filtrar..." />
      </div>
      <div class="col-lg-3 col-md-6">
        <label for="template_id" class="eg-filter-label">Modelo</label>
        <x-ui.select id="template_id" name="template_id">
          <option value="">Todos</option>
          @foreach ($templatesDisponiveis as $id => $nome)
          <option value="{{ $id }}" @selected((string) request('template_id') === (string) $id)>{{ $nome }}</option>
          @endforeach
        </x-ui.select>
      </div>
      <div class="col-lg-2 col-md-6">
        <label for="de" class="eg-filter-label">Registrada de</label>
        <x-ui.input type="date" id="de" name="de" value="{{ request('de') }}" />
      </div>
      <div class="col-lg-2 col-md-6">
        <label for="ate" class="eg-filter-label">Registrada até</label>
        <x-ui.input type="date" id="ate" name="ate" value="{{ request('ate') }}" />
      </div>
      <div class="col-2 d-flex gap-1 eg-filter-actions">
        <input type="hidden" name="sort" value="{{ request('sort', 'created_at') }}">
        <input type="hidden" name="dir"
          value="{{ strtolower(request('dir', request('direction', 'desc'))) === 'asc' ? 'asc' : 'desc' }}">
        <button type="submit" class="btn btn-engaja">Aplicar</button>
        <a href="{{ route('avaliacoes-universais.index') }}" class="btn btn-outline-secondary">Limpar</a>
      </div>
    </div>
</form>
</x-ui.filter-card>

@php
    $sortLink = function ($label, $key) {
        $currentSort = request('sort', 'created_at');
        $dirParam = request('dir', request('direction', 'desc'));
        $currentDir = strtolower((string) $dirParam) === 'asc' ? 'asc' : 'desc';
        $nextDir = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
        $params = array_merge(request()->except('page'), ['sort' => $key, 'dir' => $nextDir]);
        $url = request()->url() . '?' . http_build_query($params);
        $isActive = $currentSort === $key;
        $arrow = $isActive ? ($currentDir === 'asc' ? '↑' : '↓') : '';

        return '<a href="' . $url . '" class="text-decoration-none text-nowrap">' . e($label) . ' <span class="text-muted">' . $arrow . '</span></a>';
    };

    $columns = [
        ['field' => 'descricao', 'headerName' => 'Descrição', 'flex' => 2],
        ['field' => 'modelo', 'headerHtml' => $sortLink('Modelo', 'template'), 'flex' => 2],
        ['field' => 'submissoes', 'headerName' => 'Submissões', 'flex' => 1],
        ['field' => 'registrada_em', 'headerHtml' => $sortLink('Registrada em', 'created_at'), 'flex' => 1],
        ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true, 'align' => 'center'],
    ];

    $rows = $avaliacoes->map(function ($avaliacao) {
        $acoesHtml = '<div class="dropdown">'
            . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Gerenciar</button>'
            . '<ul class="dropdown-menu dropdown-menu-end shadow-sm">'
            . '<li><a class="dropdown-item" href="' . route('avaliacoes-universais.show', $avaliacao) . '">Ver</a></li>'
            . '<li><a class="dropdown-item" href="' . route('avaliacoes-universais.edit', $avaliacao) . '">Editar</a></li>'
            . '<li><a class="dropdown-item" href="' . route('avaliacoes.transcricao', $avaliacao) . '">Transcrição</a></li>'
            . '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalLinkQrCode' . $avaliacao->id . '">Link e QR Code</button></li>'
            . '<li><a class="dropdown-item" href="' . route('avaliacoes.respostas', $avaliacao) . '">Respostas</a></li>';

        if (auth()->user()->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica'])) {
            $acoesHtml .= '<li><hr class="dropdown-divider"></li>'
                . '<li>'
                . '<form method="POST" action="' . route('avaliacoes-universais.destroy', $avaliacao) . '" data-confirm="Tem certeza que deseja excluir esta avaliação universal?">'
                . csrf_field() . method_field('DELETE')
                . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
                . '</form>'
                . '</li>';
        }

        $acoesHtml .= '</ul></div>';

        return [
            'descricao' => $avaliacao->descricao_universal ?: '—',
            'modelo' => $avaliacao->templateAvaliacao->nome ?? '—',
            'submissoes' => $avaliacao->respostas->pluck('submissao_avaliacao_id')->filter()->unique()->count(),
            'registrada_em' => $avaliacao->created_at ? $avaliacao->created_at->format('d/m/Y H:i') : '—',
            'acoes' => $acoesHtml,
        ];
    })->values();
@endphp

<div class="card shadow-sm">
    <x-data-table id="grid-avaliacoes-universais" :columns="$columns" :rows="$rows" :pagination="false" />
</div>

@foreach ($avaliacoes as $avaliacao)
@php
  $linkAvaliacao = $linksFormulario[$avaliacao->id];
  $qrBase64 = null;

  if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
      $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
          ->style('round')
          ->color(129, 18, 131)
          ->eye('circle')
          ->eyeColor(0, 0, 156, 209, 0, 156, 209)
          ->eyeColor(1, 44, 181, 124, 44, 181, 124)
          ->eyeColor(2, 192, 12, 142, 192, 12, 142)
          ->size(220)
          ->margin(0)
          ->merge(public_path('/images/favicon-eja.png'), 0.3, true)
          ->errorCorrection('H')
          ->generate($linkAvaliacao);
      $qrBase64 = 'data:image/png;base64,' . base64_encode($qrPng);
  }
@endphp
<div class="modal fade" id="modalLinkQrCode{{ $avaliacao->id }}" tabindex="-1" aria-labelledby="modalLinkQrCodeLabel{{ $avaliacao->id }}" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <x-ui.modal-header icon="qr-code" title="Link e QR Code" :title-id="'modalLinkQrCodeLabel'.$avaliacao->id">
        <x-slot:subtitle>{{ $avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? 'Avaliação universal') }}</x-slot:subtitle>
      </x-ui.modal-header>
      <div class="modal-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
          <div>
            <h3 class="h6 fw-semibold mb-1">Recebimento de respostas</h3>
            <p class="text-muted mb-0">
              Status atual:
              <span class="badge {{ $avaliacao->formulario_aberto ? 'bg-success' : 'bg-secondary' }}">
                {{ $avaliacao->formulario_aberto ? 'Aberto' : 'Fechado' }}
              </span>
            </p>
          </div>
          <form method="POST" action="{{ route('avaliacoes-universais.toggle-formulario', $avaliacao) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn {{ $avaliacao->formulario_aberto ? 'btn-outline-danger' : 'btn-engaja' }}">
              {{ $avaliacao->formulario_aberto ? 'Fechar formulário' : 'Abrir formulário' }}
            </button>
          </form>
        </div>

        <label for="avaliacao-link-{{ $avaliacao->id }}" class="form-label">Link público da avaliação</label>
        <div class="input-group mb-4">
          <input type="url" id="avaliacao-link-{{ $avaliacao->id }}" class="form-control" value="{{ $linkAvaliacao }}" readonly>
          <button type="button" class="btn btn-outline-primary" data-copy-link="#avaliacao-link-{{ $avaliacao->id }}">Copiar</button>
        </div>

        @if($qrBase64)
          <div class="d-flex justify-content-center">
            <div class="p-2 border rounded bg-white">
              <img src="{{ $qrBase64 }}" alt="QR Code da avaliação universal" class="img-fluid" style="width: 220px; height: 220px;">
            </div>
          </div>
        @else
          <div class="alert alert-warning mb-0">Não foi possível gerar o QR Code neste ambiente.</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endforeach

<div class="mt-3">
  {{ $avaliacoes->links() }}
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-copy-link]').forEach((button) => {
      button.addEventListener('click', async () => {
        const input = document.querySelector(button.dataset.copyLink);

        if (!input) {
          return;
        }

        try {
          await navigator.clipboard.writeText(input.value);
        } catch (error) {
          input.select();
          document.execCommand('copy');
        }

        button.textContent = 'Copiado';
        setTimeout(() => {
          button.textContent = 'Copiar';
        }, 1800);
      });
    });
  });
</script>
@endpush
