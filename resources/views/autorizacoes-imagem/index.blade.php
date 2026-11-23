@extends('layouts.app')
@section('content')
  <h1 class="h4 mb-3">Autorizações de imagem por ação pedagógica</h1>
  <p>Consulte as respostas específicas de cada ação.</p>
  @include('autorizacoes-imagem.partials.erros')
  <form class="row g-2 mb-4" method="GET">
    <div class="col-12 col-lg-5"><label class="form-label" for="evento_id">Ação pedagógica</label><select id="evento_id" name="evento_id" class="form-select" required><option value="">Selecione</option>@foreach($eventos as $opcao)<option value="{{ $opcao->id }}" @selected($evento?->id == $opcao->id)>{{ $opcao->nome }}</option>@endforeach</select></div>
    <div class="col-12 col-md-4 col-lg-3"><label class="form-label" for="situacao">Situação</label><select id="situacao" name="situacao" class="form-select"><option value="">Todas</option>@foreach(\App\Enums\SituacaoAutorizacaoImagem::cases() as $situacao)<option value="{{ $situacao->value }}" @selected(request('situacao') === $situacao->value)>{{ $situacao->rotulo() }}</option>@endforeach</select></div>
    <div class="col-12 col-md-5 col-lg-3"><label class="form-label" for="busca">Nome, e-mail ou CPF</label><input id="busca" class="form-control" name="busca" value="{{ request('busca') }}" maxlength="255"></div>
    <div class="col-auto align-self-end"><button class="btn btn-engaja">Filtrar</button></div>
  </form>
  @if($evento)
    <div class="d-flex flex-wrap gap-3 mb-3">
      @foreach(\App\Enums\SituacaoAutorizacaoImagem::cases() as $situacao)<span>{{ $situacao->rotulo() }}: <strong>{{ $totais[$situacao->value] }}</strong></span>@endforeach
    </div>
    @can('export', \App\Models\AutorizacaoImagem::class)
      <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-engaja" href="{{ route('autorizacoes-imagem.export', request()->only('evento_id', 'situacao', 'busca')) }}">Baixar planilha desta consulta</a>
      </div>
    @endcan
    <div class="table-responsive"><table class="table align-middle">
      <thead><tr><th>Participante</th><th>Resposta nesta ação</th>@can('create', \App\Models\AutorizacaoImagem::class)<th>Registrar resposta</th>@endcan</tr></thead>
      <tbody>@forelse($participantes as $participante)
        @php($resposta = $participante->autorizacoesImagem->first())
        <tr><td>{{ $participante->user->name }}<div class="small text-muted">{{ $participante->user->email }}</div><div class="small">{{ $participante->cpf }}</div></td>
        <td>@include('autorizacoes-imagem.partials.resposta', ['gestao' => true])</td>
        @can('create', \App\Models\AutorizacaoImagem::class)<td style="min-width: 260px">@include('autorizacoes-imagem.partials.formulario', ['contexto' => $contextos[$participante->id], 'url' => route('autorizacoes-imagem.registrar', [$evento, $participante]), 'chave' => $participante->id, 'gestao' => true])</td>@endcan</tr>
      @empty<tr><td colspan="3">Nenhum participante encontrado.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $participantes->links() }}
  @endif
@endsection
