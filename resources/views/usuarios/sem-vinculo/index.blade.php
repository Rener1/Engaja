@extends('layouts.app')

@section('content')
<div class="mb-4">
  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div>
      <p class="text-uppercase text-muted small mb-1">Pessoas</p>
      <h1 class="h4 fw-bold text-engaja mb-2">Usuários sem vínculo</h1>
      <p class="text-muted mb-0">
        Usuários sem vínculo com ações pedagógicas ou momentos ativos no sistema.
      </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('usuarios.index') }}" class="btn btn-light border">Voltar</a>
      <a href="{{ route('usuarios.sem-vinculo.exportar') }}" class="btn btn-engaja">Exportar XLSX</a>
      <a href="{{ route('usuarios.sem-vinculo.exportar', ['formato' => 'docx']) }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-word"></i> Word</a>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="text-muted">
        <strong>{{ $usuarios->count() }}</strong> usuário(s) sem vínculo encontrado(s)
      </div>
      <div class="text-muted small mt-2">
        A checagem ignora ações pedagógicas e momentos excluídos.
      </div>
    </div>
  </div>

  @php
      $columns = [
          ['field' => 'nome', 'headerName' => 'Nome', 'flex' => 2],
          ['field' => 'email', 'headerName' => 'Email', 'flex' => 2],
          ['field' => 'cpf', 'headerName' => 'CPF', 'flex' => 1],
          ['field' => 'telefone', 'headerName' => 'Telefone', 'flex' => 1],
          ['field' => 'sexo', 'headerName' => 'Sexo', 'flex' => 1],
          ['field' => 'idade', 'headerName' => 'Idade', 'flex' => 1],
          ['field' => 'municipio', 'headerName' => 'Município', 'flex' => 1],
          ['field' => 'instituicao', 'headerName' => 'Instituição', 'flex' => 1],
      ];

      $rows = $usuarios->map(function ($usuario) {
          $municipio = $usuario->participante?->municipio?->nome;
          $estado = $usuario->participante?->municipio?->estado?->sigla;
          $idade = $usuario->participante?->idade;

          return [
              'nome' => $usuario->name,
              'email' => $usuario->email,
              'cpf' => $usuario->participante?->cpf ?? '-',
              'telefone' => $usuario->participante?->telefone ?? '-',
              'sexo' => $usuario->participante?->sexo ?? '-',
              'idade' => $idade !== null ? "{$idade} anos" : '-',
              'municipio' => $municipio && $estado ? "{$municipio} - {$estado}" : ($municipio ?? $estado ?? '-'),
              'instituicao' => $usuario->participante?->escola_unidade ?? '-',
          ];
      })->values();
  @endphp

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <x-data-table
          id="grid-usuarios-sem-vinculo"
          :columns="$columns"
          :rows="$rows"
          :page-size="20"
      />
    </div>
  </div>
</div>
@endsection
