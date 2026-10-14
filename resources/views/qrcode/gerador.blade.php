@extends('layouts.app')

@section('content')
@php
  $qrBase64 = null;
  if ($link && class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
      $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
          ->style('round')
          ->color(129, 18, 131)
          ->eye('circle')
          ->eyeColor(0, 0, 156, 209, 0, 156, 209)
          ->eyeColor(1, 44, 181, 124, 44, 181, 124)
          ->eyeColor(2, 192, 12, 142, 192, 12, 142)
          ->size(300)
          ->margin(0)
          ->merge(public_path('/images/favicon-eja.png'), 0.3, true)
          ->errorCorrection('H')
          ->generate($link);
      $qrBase64 = 'data:image/png;base64,' . base64_encode($qrPng);
  }
@endphp

<div class="row justify-content-center">
  <div class="col-xl-8">
    <div class="mb-4">
      <h1 class="h3 fw-bold text-engaja mb-1">Gerador de QR Code</h1>
      <p class="text-muted mb-0">Informe um link para criar um QR Code no padrão visual do Engaja.</p>
    </div>

    <div class="card shadow-sm">
      <div class="card-body p-md-4">
        <form method="GET" action="{{ route('qrcode.gerador') }}">
          <label for="link" class="form-label fw-semibold">Link</label>
          <div class="input-group">
            <input
              type="url"
              class="form-control @error('link') is-invalid @enderror"
              id="link"
              name="link"
              value="{{ old('link', $link) }}"
              placeholder="https://exemplo.com"
              required
              autofocus
            >
            <button type="submit" class="btn btn-engaja">Gerar QR Code</button>
          </div>
          @error('link')
            <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
        </form>

        @if ($link)
          <hr class="my-4">
          <div class="row align-items-center g-4">
            <div class="col-md">
              <h2 class="h5 fw-bold text-engaja mb-2">Seu QR Code está pronto</h2>
              <p class="text-muted mb-0 text-break">{{ $link }}</p>
            </div>
            <div class="col-md-auto text-center">
              @if ($qrBase64)
                <div class="p-2 border rounded bg-white d-inline-block">
                  <img src="{{ $qrBase64 }}" alt="QR Code para {{ $link }}" width="300" height="300" class="img-fluid">
                </div>
                <div class="mt-3">
                  <a href="{{ $qrBase64 }}" download="qr-code-engaja.png" class="btn btn-engaja">Baixar QR Code</a>
                </div>
              @else
                <div class="alert alert-warning mb-0">Não foi possível gerar o QR Code neste ambiente.</div>
              @endif
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
