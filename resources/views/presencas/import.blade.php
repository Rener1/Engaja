@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h1 class="h5 mb-3">Importar presenças — {{ $evento->nome }}</h1>
  <p class="text-muted mb-3">
    Momento: {{ \Carbon\Carbon::parse($atividade->dia)->format('d/m/Y') }}
    • {{ \Illuminate\Support\Str::of($atividade->hora_inicio)->substr(0,5) }}
    • {{ $atividade->descricao ?? 'Momento' }}
  </p>

  @if($errors->any())
  <div class="alert alert-danger" role="alert">
    <strong>Importação interrompida. Corrija os problemas abaixo antes de continuar.</strong>
    <ul class="mb-0 mt-2">
      @foreach($errors->all() as $message)
      <li>{{ $message }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <form method="POST" action="{{ route('atividades.presencas.cadastro', $atividade) }}" enctype="multipart/form-data" class="card p-3 shadow-sm">
    @csrf
    <div class="mb-3">
      <label class="form-label">Arquivo Excel (.xlsx)</label>
      <input type="file" name="your_file" class="form-control" accept=".xlsx,.xls,.csv" required>
      <div class="form-text">
        <strong>Cada registro de participante deve ter nome e data de nascimento, além de pelo menos um identificador: E-mail ou CPF.</strong><br>
        Colunas: nome, email, cpf, telefone, municipio, tipo_de_organizacao, organização, tag, data_nascimento, sexo, status, data_entrada<br>
        Data de nascimento: use <code>DD/MM/AAAA</code>, <code>AAAA-MM-DD</code> ou uma célula de data do Excel. Datas futuras não são aceitas.<br>
        Sexo é opcional: {{ implode(', ', \App\Models\Participante::SEXOS) }}. Campos opcionais vazios preservam os dados já cadastrados.
      </div>
      <div class="mt-2">
        <a href="{{ asset('modelos/modelo_presencas_engaja.xlsx') }}" class="btn btn-sm btn-outline-primary">
          📥 Baixar modelo de planilha
        </a>
      </div>
    </div>
    <div class="col-12 d-flex justify-content-end gap-2">
      <a href="{{ route('atividades.show', $atividade) }}" class="btn btn-outline-secondary">Cancelar</a>
      <button class="btn btn-engaja">Enviar</button>
    </div>
  </form>
</div>
@endsection
