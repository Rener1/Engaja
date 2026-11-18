@extends('layouts.app')

@section('content')
@php
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
          ->generate($link);
      $qrBase64 = 'data:image/png;base64,' . base64_encode($qrPng);
  }
@endphp

<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="qr-code" title="Link e QR Code">
    <p class="text-muted mb-0">{{ $avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? 'Avaliação universal') }}</p>
    <x-slot:actions>
        <a href="{{ route('avaliacoes-universais.index') }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
          <div>
            <h2 class="h6 fw-semibold mb-1">Recebimento de respostas</h2>
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

        <label for="avaliacao-link" class="form-label">Link público da avaliação</label>
        <div class="input-group mb-4">
          <input type="url" id="avaliacao-link" class="form-control" value="{{ $link }}" readonly>
          <button type="button" class="btn btn-outline-primary" id="copiar-link-avaliacao">Copiar</button>
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

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('copiar-link-avaliacao');
    const input = document.getElementById('avaliacao-link');

    if (!button || !input) {
      return;
    }

    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(input.value);
        button.textContent = 'Copiado';
        setTimeout(() => {
          button.textContent = 'Copiar';
        }, 1800);
      } catch (error) {
        input.select();
        document.execCommand('copy');
        button.textContent = 'Copiado';
        setTimeout(() => {
          button.textContent = 'Copiar';
        }, 1800);
      }
    });
  });
</script>
@endpush
@endsection
