@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="person-lines-fill" :title="'Inscritos — '.$evento->nome" class="mb-0">
    <x-slot:actions>
        @hasanyrole('administrador|gerente|eq_pedagogica')
        <div class="d-flex gap-2">
          <a href="{{ route('inscricoes.import', $evento) }}" class="eg-hero-btn">Importar inscrições</a>
          <a href="{{ route('inscricoes.moodle.import', $evento) }}" class="eg-hero-btn fw-semibold">Importação Moodle</a>
        </div>
        @endhasrole
    </x-slot:actions>
</x-ui.page-hero>

<div class="py-4">
  {{-- Filtros --}}
  <x-ui.filter-card>
      <form method="GET" action="{{ route('inscricoes.inscritos', $evento) }}" class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="eg-filter-label">Buscar (nome, e-mail, CPF, telefone)</label>
          <x-ui.input type="text" name="q" value="{{ $search }}" placeholder="Digite para buscar..." />
        </div>
        <div class="col-lg-3">
          <label class="eg-filter-label">Município</label>
          <x-ui.select name="municipio_id">
            <option value="">— Todos —</option>
            @foreach($municipios as $m)
              <option value="{{ $m->id }}" @selected((string)$municipioId === (string)$m->id)>
                {{ $m->nome_com_estado }}
              </option>
            @endforeach
          </x-ui.select>
        </div>
        <div class="col-lg-3">
          <label class="eg-filter-label">Momento</label>
          <x-ui.select name="atividade_id">
            <option value="">- Todos -</option>
            @foreach($atividades as $at)
              @php
                $dia = \Carbon\Carbon::parse($at->dia)->format('d/m/Y');
                $hora = $at->hora_inicio ? \Carbon\Carbon::parse($at->hora_inicio)->format('H:i') : null;
                $label = ($at->descricao ?: 'Momento') . ' — ' . $dia . ($hora ? ' '.$hora : '');
              @endphp
              <option value="{{ $at->id }}" @selected((string)$atividadeId === (string)$at->id)>{{ $label }}</option>
            @endforeach
          </x-ui.select>
        </div>
        <div class="col-lg-2">
          <label class="eg-filter-label">Por página</label>
          <x-ui.select name="per_page">
            @foreach([25,50,100,200] as $pp)
              <option value="{{ $pp }}" @selected($perPage == $pp)>{{ $pp }}</option>
            @endforeach
          </x-ui.select>
        </div>
        <div class="col-lg-12 col-xl-0 col-sm-12 col-md-auto d-grid">
          <button class="btn btn-engaja">Filtrar</button>
        </div>
      </form>
  </x-ui.filter-card>

  {{-- Resumo --}}
  <div class="small text-muted mb-2">
    Total: {{ $inscricoes->total() }} - Pagina {{ $inscricoes->currentPage() }} de {{ $inscricoes->lastPage() }}
  </div>

  @php
      $columns = [
          ['field' => 'nome', 'headerName' => 'Nome', 'flex' => 2],
          ['field' => 'email', 'headerName' => 'Email', 'flex' => 2],
          ['field' => 'cpf', 'headerName' => 'CPF', 'flex' => 1],
          ['field' => 'telefone', 'headerName' => 'Telefone', 'flex' => 1],
          ['field' => 'municipio', 'headerName' => 'Município', 'flex' => 2],
          ['field' => 'momento', 'headerName' => 'Momento', 'flex' => 2],
          ['field' => 'inscrito_em', 'headerName' => 'Inscrito em', 'flex' => 1],
      ];

      $rows = collect($inscricoes->items())->map(function ($inscricao) {
          $participante = $inscricao->participante;
          $user = optional($participante?->user);
          $municipio = optional($participante?->municipio);
          $atividade = optional($inscricao->atividade);
          $cpfValido = $participante?->cpf_valido;

          $momento = '-';
          if ($atividade->descricao !== null || $atividade->dia !== null) {
              $momentoTexto = $atividade->descricao ?: 'Momento';
              $dia = $atividade->dia ? \Carbon\Carbon::parse($atividade->dia)->format('d/m/Y') : null;
              $hora = $atividade->hora_inicio ? \Carbon\Carbon::parse($atividade->hora_inicio)->format('H:i') : null;
              $momento = trim($momentoTexto . ($dia ? " — {$dia}" : '') . ($hora ? " {$hora}" : ''));
          }

          return [
              'nome' => $user->name ?? '-',
              'email' => $user->email ?? '-',
              'cpf' => ($participante && ! $cpfValido) ? ($participante->cpf . ' (inválido)') : ($participante?->cpf ?? '-'),
              'telefone' => $participante?->telefone ?? '-',
              'municipio' => $municipio ? $municipio->nome_com_estado : '-',
              'momento' => $momento,
              'inscrito_em' => optional($inscricao->created_at)->format('d/m/Y H:i') ?? '-',
          ];
      })->values();
  @endphp

  {{-- Tabela --}}
  <x-data-table
      id="grid-inscricoes"
      :columns="$columns"
      :rows="$rows"
      :pagination="false"
            :paginator="$inscricoes"
        />
</div>
@endsection
