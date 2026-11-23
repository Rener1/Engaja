@extends('layouts.app')
@section('content')
<x-ui.page-hero eyebrow="Meu perfil" eyebrow-icon="camera" title="Minhas autorizações de imagem"
    subtitle="Sua decisão vale para cada ação pedagógica e seus momentos. Autorizar, recusar ou alterar uma resposta não afeta suas presenças." />

@include('autorizacoes-imagem.partials.erros')

@forelse($eventos as $evento)
  @php($resposta = $evento->autorizacoesImagem->first())
  <section class="eg-section-card mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
      <div class="min-w-0">
        <h2 class="eg-section-card__title mb-2"><i class="bi bi-easel"></i> {{ $evento->nome }}</h2>
        @include('autorizacoes-imagem.partials.resposta', ['resposta' => $resposta, 'gestao' => false])
      </div>
      <button type="button" class="btn btn-engaja" data-bs-toggle="modal" data-bs-target="#modalAutorizacaoImagem-{{ $evento->id }}">
        {{ $resposta ? 'Alterar resposta' : 'Responder' }}
      </button>
    </div>
  </section>

  @include('autorizacoes-imagem.partials.modal', [
      'chave' => $evento->id,
      'titulo' => $resposta ? 'Alterar resposta desta ação' : 'Responder autorização desta ação',
      'subtitulo' => $evento->nome,
      'resposta' => $resposta,
      'gestao' => false,
      'podeResponder' => true,
      'contexto' => $contextos[$evento->id],
      'url' => route('profile.autorizacoes-imagem.salvar', $evento),
  ])
@empty
  <x-ui.empty-state icon="camera" title="Nenhuma ação para responder">
    Você ainda não possui presença ou resposta registrada em uma ação pedagógica.
  </x-ui.empty-state>
@endforelse

{{ $eventos->links() }}
@endsection
