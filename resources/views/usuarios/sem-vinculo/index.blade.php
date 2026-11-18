@extends('layouts.app')

@section('content')
<div class="mb-4">
  <x-ui.page-hero eyebrow="Pessoas" eyebrow-icon="people" title="Usuários sem vínculo">
      <p class="text-muted mb-0">
        Usuários sem vínculo com ações pedagógicas ou momentos ativos no sistema.
      </p>
      <x-slot:actions>
          <a href="{{ route('usuarios.index') }}" class="eg-hero-btn border">Voltar</a>
          <a href="{{ route('usuarios.sem-vinculo.exportar') }}" class="eg-hero-btn">Exportar XLSX</a>
          <a href="{{ route('usuarios.sem-vinculo.exportar', ['formato' => 'docx']) }}" class="eg-hero-btn"><i class="bi bi-file-earmark-word"></i> Word</a>
      </x-slot:actions>
  </x-ui.page-hero>

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
