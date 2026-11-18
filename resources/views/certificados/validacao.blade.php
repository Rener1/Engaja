@extends('layouts.app')

@section('content')
<style>
  :root { --page-width: 1120px; }
  .wrapper { max-width: var(--page-width); margin: 20px auto; padding: 12px; }
  .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 12px; position: relative; overflow: hidden; }
  .cert-page { position: relative; width: 100%; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-bottom: 24px; }
  .cert-bg { width: 100%; height: auto; display: block; }
  .cert-text { position: absolute; color: #111; line-height: 1.4; white-space: pre-wrap; }
  .watermark {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    font-size: 72px;
    font-weight: 900;
    letter-spacing: 3px;
    color: rgba(0, 0, 0, 0.14);
    text-transform: uppercase;
    transform: rotate(-16deg);
    z-index: 5;
    text-align: center;
  }
  .label-valid { padding: 10px 12px; background: #ecfdf3; border: 1px solid #86efac; border-radius: 6px; color: #166534; font-weight: 700; display: inline-block; margin-bottom: 10px; }
</style>

<x-ui.page-hero eyebrow="Certificados" eyebrow-icon="patch-check" title="Validação de certificado"
    subtitle="Confira abaixo os dados do certificado emitido pelo sistema Engaja." class="mb-0" />

<div class="wrapper">
  <div class="label-valid">Certificado válido — Emitido pelo sistema Engaja</div>
  <div class="card">
    @php
      $modelo = $certificado->modelo;
      $frenteUrl = $modelo?->imagem_frente ? asset('storage/'.$modelo->imagem_frente) : '';
      $versoUrl = $modelo?->imagem_verso ? asset('storage/'.$modelo->imagem_verso) : '';
      $layoutFrente = $modelo->layout_frente ?? [];
      $layoutVerso  = $modelo->layout_verso ?? [];
      $textoFrente = trim($certificado->texto_frente ?? '');
      $textoVerso  = trim($certificado->texto_verso ?? '');
      $dataEmissao = ($certificado->created_at ?? now())->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y');
      $textoDataEmissao = "São Paulo, {$dataEmissao}.";
      $qrBase64 = null;
      $qrLink = $certificado->codigo_validacao ? route('certificados.validacao', $certificado->codigo_validacao) : null;
      $qrColorHex = $layoutVerso['qr_color'] ?? '#811283';
      $qrColorHex = ltrim($qrColorHex, '#');
      $qrColorHex = preg_match('/^[0-9a-fA-F]{6}$/', $qrColorHex) ? $qrColorHex : '811283';
      $qrRGB = [
        hexdec(substr($qrColorHex, 0, 2)),
        hexdec(substr($qrColorHex, 2, 2)),
        hexdec(substr($qrColorHex, 4, 2)),
      ];
      if ($qrLink && class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
        $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
          ->style('round')
          ->eye('circle')
          ->eyeColor(0, $qrRGB[0], $qrRGB[1], $qrRGB[2], $qrRGB[0], $qrRGB[1], $qrRGB[2])
          ->eyeColor(1, $qrRGB[0], $qrRGB[1], $qrRGB[2], $qrRGB[0], $qrRGB[1], $qrRGB[2])
          ->eyeColor(2, $qrRGB[0], $qrRGB[1], $qrRGB[2], $qrRGB[0], $qrRGB[1], $qrRGB[2])
          ->color($qrRGB[0], $qrRGB[1], $qrRGB[2])
          ->margin(0)
          ->size(200)
          ->errorCorrection('H')
          ->generate($qrLink);
        $qrBase64 = 'data:image/png;base64,'.base64_encode($qrPng);
      }
    @endphp

    <div class="cert-page">
      <div class="watermark">VÁLIDO APENAS PARA CONFERÊNCIA</div>
      @if($frenteUrl)
        <img src="{{ $frenteUrl }}" alt="Frente" class="cert-bg">
      @endif
      <div class="cert-text"
           data-cert-layer="frente"
           data-layout='@json($layoutFrente)'
           data-text="{!! nl2br(e($textoFrente)) !!}">
        {!! nl2br(e($textoFrente)) !!}
      </div>
      @if(is_numeric($layoutFrente['date_x'] ?? null) && is_numeric($layoutFrente['date_y'] ?? null))
        <div class="cert-text"
             data-cert-layer="frente-data"
             data-layout-prefix="date_"
             data-layout='@json($layoutFrente)'>
          {{ $textoDataEmissao }}
        </div>
      @endif
    </div>

    @if($versoUrl || $textoVerso)
    <div class="cert-page">
      <div class="watermark">VÁLIDO APENAS PARA CONFERÊNCIA</div>
      @if($versoUrl)
        <img src="{{ $versoUrl }}" alt="Verso" class="cert-bg">
      @endif
      <div class="cert-text"
           data-cert-layer="verso"
           data-layout='@json($layoutVerso)'
           data-text="{!! nl2br(e($textoVerso)) !!}">
        {!! nl2br(e($textoVerso)) !!}
      </div>
      @if($qrBase64)
        <div class="cert-qr"
             data-qr-layout='{{ json_encode([
               "x" => $layoutVerso["qr_x"] ?? 0,
               "y" => $layoutVerso["qr_y"] ?? 0,
               "size" => $layoutVerso["qr_size"] ?? 140,
               "canvas_w" => $layoutVerso["canvas_w"] ?? null,
               "canvas_h" => $layoutVerso["canvas_h"] ?? null,
             ]) }}'>
          <img src="{{ $qrBase64 }}" alt="QR validação">
        </div>
      @endif
    </div>
    @endif
  </div>
</div>

<script>
  function applyLayout(layer) {
    const page = layer.closest('.cert-page');
    const img = page ? page.querySelector('.cert-bg') : null;
    const layout = layer.dataset.layout ? JSON.parse(layer.dataset.layout) : {};
    const prefix = layer.dataset.layoutPrefix || '';
    const apply = () => {
      const cw = Number(layout.canvas_w || img?.naturalWidth || img?.clientWidth || 1);
      const ch = Number(layout.canvas_h || img?.naturalHeight || img?.clientHeight || 1);
      const scaleW = (img?.clientWidth || cw) / cw;
      const scaleH = (img?.clientHeight || ch) / ch;
      const scale = Math.min(scaleW || 1, scaleH || 1);
      const x = (layout[`${prefix}x`] || 0) * scale;
      const y = (layout[`${prefix}y`] || 0) * scale;
      const w = (layout[`${prefix}w`] || 0) * scale;
      const h = (layout[`${prefix}h`] || 0) * scale;
      const fs = (prefix === 'date_' ? (layout.font_size || 20) : (layout[`${prefix}font_size`] || 20)) * scale;
      const ff = prefix === 'date_' ? (layout.font_family || 'Arial') : (layout[`${prefix}font_family`] || 'Arial');
      const fw = prefix === 'date_' ? (layout.font_weight || 'normal') : (layout[`${prefix}font_weight`] || 'normal');
      const fst = prefix === 'date_' ? (layout.font_style || 'normal') : (layout[`${prefix}font_style`] || 'normal');
      const align = layout[`${prefix}align`] || 'left';

      layer.style.left = `${x}px`;
      layer.style.top = `${y}px`;
      layer.style.fontSize = `${fs}px`;
      layer.style.fontFamily = ff;
      layer.style.fontWeight = fw;
      layer.style.fontStyle = fst;
      layer.style.textAlign = align;
      layer.style.width = w > 0 ? `${w}px` : 'auto';
      layer.style.height = h > 0 ? `${h}px` : 'auto';
    };
    if (img) {
      if (img.complete) apply(); else img.onload = apply;
    } else {
      apply();
    }
  }

    document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cert-layer]').forEach(layer => applyLayout(layer));
    document.querySelectorAll('.cert-qr').forEach(qr => {
      const page = qr.closest('.cert-page');
      const img = page ? page.querySelector('.cert-bg') : null;
      const data = qr.dataset.qrLayout ? JSON.parse(qr.dataset.qrLayout) : {};
      const applyQr = () => {
        const cw = Number(data.canvas_w || img?.naturalWidth || img?.clientWidth || 1);
        const ch = Number(data.canvas_h || img?.naturalHeight || img?.clientHeight || 1);
        const scaleW = (img?.clientWidth || cw) / cw;
        const scaleH = (img?.clientHeight || ch) / ch;
        const size = (data.size || 140) * Math.min(scaleW || 1, scaleH || 1);
        const left = (data.x || 0) * (scaleW || 1);
        const top  = (data.y || 0) * (scaleH || 1);
        Object.assign(qr.style, {
          position: 'absolute',
          left: `${left}px`,
          top: `${top}px`,
          background: '#fff',
          padding: '6px',
          borderRadius: '6px',
          boxShadow: '0 1px 4px rgba(0,0,0,0.12)',
        });
        const imgEl = qr.querySelector('img');
        if (imgEl) {
          imgEl.style.width = `${size}px`;
          imgEl.style.height = `${size}px`;
          imgEl.style.display = 'block';
        }
      };
      if (img) {
        if (img.complete) applyQr(); else img.onload = applyQr;
      } else {
        applyQr();
      }
    });
  });
</script>
@endsection
      @php
        $qrLink = $certificado->codigo_validacao ? route('certificados.validacao', $certificado->codigo_validacao) : null;
        $qrBase64 = null;
        $qrColorHex = $layoutVerso['qr_color'] ?? '#811283';
        $qrColorHex = ltrim($qrColorHex, '#');
        $qrColorHex = preg_match('/^[0-9a-fA-F]{6}$/', $qrColorHex) ? $qrColorHex : '811283';
        $qrRGB = [
          hexdec(substr($qrColorHex, 0, 2)),
          hexdec(substr($qrColorHex, 2, 2)),
          hexdec(substr($qrColorHex, 4, 2)),
        ];
        if ($qrLink && class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
          $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->style('round')
            ->eye('circle')
            ->eyeColor(0, $qrRGB[0], $qrRGB[1], $qrRGB[2], $qrRGB[0], $qrRGB[1], $qrRGB[2])
            ->eyeColor(1, $qrRGB[0], $qrRGB[1], $qrRGB[2], $qrRGB[0], $qrRGB[1], $qrRGB[2])
            ->eyeColor(2, $qrRGB[0], $qrRGB[1], $qrRGB[2], $qrRGB[0], $qrRGB[1], $qrRGB[2])
            ->color($qrRGB[0], $qrRGB[1], $qrRGB[2])
            ->margin(0)
            ->size(200)
            ->errorCorrection('H')
            ->generate($qrLink);
          $qrBase64 = 'data:image/png;base64,'.base64_encode($qrPng);
        }
      @endphp
