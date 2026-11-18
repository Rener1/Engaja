@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Certificados" eyebrow-icon="award" title="Modelos de certificado">
    <div class="text-muted small">Configure textos e imagens para gerar certificados.</div>
    <x-slot:actions>
        <a href="{{ route('certificados.modelos.create') }}" class="eg-hero-btn eg-hero-btn--solid">Novo modelo</a>
    </x-slot:actions>
</x-ui.page-hero>

@if ($modelos->isEmpty())
  <x-ui.empty-state icon="inbox" muted>Nenhum modelo cadastrado.</x-ui.empty-state>
@else
  @php
      $columns = [
          ['field' => 'nome', 'headerName' => 'Nome', 'flex' => 1],
          ['field' => 'descricao', 'headerName' => 'Descrição', 'flex' => 2],
          ['field' => 'eixo', 'headerName' => 'Eixo', 'flex' => 1],
          ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true],
      ];

      $podeExcluir = auth()->user()?->hasRole('administrador');

      $rows = $modelos->map(function ($modelo) use ($podeExcluir) {
          $acoesHtml = '<div class="dropdown">'
              . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Gerenciar</button>'
              . '<ul class="dropdown-menu dropdown-menu-end">'
              . '<li><a class="dropdown-item" href="' . route('certificados.modelos.edit', $modelo) . '">Editar</a></li>';

          if ($podeExcluir) {
              $acoesHtml .= '<li>'
                  . '<form method="POST" action="' . route('certificados.modelos.destroy', $modelo) . '" data-confirm="Remover este modelo?">'
                  . csrf_field() . method_field('DELETE')
                  . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
                  . '</form>'
                  . '</li>';
          }

          $acoesHtml .= '</ul></div>';

          return [
              'nome' => $modelo->nome,
              'descricao' => $modelo->descricao ?: '—',
              'eixo' => $modelo->eixo->nome ?? '—',
              'acoes' => $acoesHtml,
          ];
      })->values();
  @endphp

  <div class="card shadow-sm">
      <x-data-table
          id="grid-certificados-modelos"
          :columns="$columns"
          :rows="$rows"
          :page-size="15"
      />
  </div>
@endif
@endsection
