@extends('layouts.app')

@section('content')
@php
  $universal = $universal ?? false;
  $transcricao = $transcricao ?? false;
  $formAction = $formAction ?? route('avaliacoes.update', $avaliacao);
  $cancelUrl = $cancelUrl ?? route('avaliacoes.index');
  $showUrl = $showUrl ?? route('avaliacoes.show', $avaliacao);
  $bloquearEstrutura = $bloquearEstrutura ?? false;
@endphp
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="clipboard2-check">
    <x-slot:heading>@if($universal) Editar avaliação universal @else Editar avaliação @endif</x-slot:heading>
    <x-slot:actions>
        <a href="{{ $showUrl }}" class="eg-hero-btn">Ver detalhes</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-xl-10">
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <form method="POST" action="{{ $formAction }}">
          @csrf
          @method('PUT')

          <div class="row g-3">
            @unless($universal)
            <div class="col-md-6">
              <label for="atividade_id" class="form-label">Atividade</label>
              <select id="atividade_id" name="atividade_id"
                class="form-select @error('atividade_id') is-invalid @enderror" required @disabled($bloquearEstrutura)>
                <option value="">Selecione...</option>
                @foreach ($atividades as $atividade)
                <option value="{{ $atividade->id }}"
                  @selected(old('atividade_id', $avaliacao->atividade_id) == $atividade->id)>
                  {{ $atividade->descricao }}@if($atividade->dia) ({{ \Carbon\Carbon::parse($atividade->dia)->format('d/m/Y') }})@endif — {{ $atividade->evento->nome ?? 'Sem evento' }}
                </option>
                @endforeach
              </select>
              @if($bloquearEstrutura)
              <input type="hidden" name="atividade_id" value="{{ $avaliacao->atividade_id }}">
              @endif
              @error('atividade_id')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            @endunless

            <div class="col-md-6">
              <label for="template_avaliacao_id" class="form-label">Modelo de avaliação</label>
              <select id="template_avaliacao_id" name="template_avaliacao_id"
                class="form-select @error('template_avaliacao_id') is-invalid @enderror" required @disabled($bloquearEstrutura)>
                <option value="">Selecione...</option>
                @foreach ($templates as $template)
                <option value="{{ $template->id }}"
                  @selected($selectedTemplateId == $template->id)>
                  {{ $template->nome }}
                </option>
                @endforeach
              </select>
              @if($bloquearEstrutura)
              <input type="hidden" name="template_avaliacao_id" value="{{ $avaliacao->template_avaliacao_id }}">
              <div class="form-text text-danger">Esta avaliação já possui respostas. Apenas a descrição pode ser alterada.</div>
              @endif
              @error('template_avaliacao_id')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label for="descricao_universal" class="form-label">Descrição</label>
              <input type="text" id="descricao_universal" name="descricao_universal"
                class="form-control @error('descricao_universal') is-invalid @enderror"
                value="{{ old('descricao_universal', $avaliacao->descricao_universal) }}"
                placeholder="Ex.: Avaliação geral do ciclo formativo">
              @error('descricao_universal')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            @if(!$universal)
            <div class="col-md-6 d-flex align-items-center">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" value="1" id="anonima" disabled
                  @checked($avaliacao->anonima)>
                <label class="form-check-label" for="anonima">
                  Avaliação anônima
                </label>
                <div class="form-text">
                  Definido na criação.
                </div>
              </div>
            </div>
            @else
            <div class="col-md-6 d-flex align-items-center">
              <div class="form-text mt-4">
                Avaliações universais são sempre anônimas e não ficam vinculadas a um momento.
              </div>
            </div>
            @endif
          </div>

          <div class="mt-4">
            @if($bloquearEstrutura)
            <fieldset disabled>
            @endif
            @include('avaliacoes._questoes', [
        'questoesForm' => $questoesForm,
            ])
            @if($bloquearEstrutura)
            </fieldset>
            @endif
          </div>

          <div class="d-flex justify-content-between mt-4">
            <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-engaja">Salvar alterações</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
