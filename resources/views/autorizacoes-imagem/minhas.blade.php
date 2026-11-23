@extends('layouts.app')
@section('content')
  <h1 class="h4">Minhas autorizações de imagem</h1>
  <p>Sua decisão vale para cada ação pedagógica e seus momentos. Autorizar, recusar ou alterar uma resposta não afeta suas presenças.</p>
  @include('autorizacoes-imagem.partials.erros')
  @forelse($eventos as $evento)
    @php($resposta = $evento->autorizacoesImagem->first())
    <section class="card mb-3"><div class="card-body">
      <h2 class="h5">{{ $evento->nome }}</h2>
      @include('autorizacoes-imagem.partials.resposta', ['gestao' => false])
      @include('autorizacoes-imagem.partials.formulario', ['contexto' => $contextos[$evento->id], 'url' => route('profile.autorizacoes-imagem.salvar', $evento), 'chave' => $evento->id, 'gestao' => false])
    </div></section>
  @empty<p>Você ainda não possui presença ou resposta registrada em uma ação pedagógica.</p>@endforelse
  {{ $eventos->links() }}
@endsection
