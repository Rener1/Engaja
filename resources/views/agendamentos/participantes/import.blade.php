@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-check" title="Importar participantes do agendamento" class="mb-0">
    <x-slot:actions>
        <a href="{{ route('agendamentos.participantes.index', $agendamento) }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="py-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route('agendamentos.participantes.upload', $agendamento) }}" enctype="multipart/form-data" class="row g-3">
        @csrf

        <div class="col-12">
          <label for="observacoes" class="form-label">Observações do envio</label>
          <textarea id="observacoes" name="observacoes" rows="3" class="form-control @error('observacoes') is-invalid @enderror" placeholder="Ex.: Planilha da visita técnica de 15/03.">{{ old('observacoes') }}</textarea>
          @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-12">
          <label for="arquivo" class="form-label">Arquivo de dados (.xlsx) <span class="text-danger">*</span></label>
          <input type="file" id="arquivo" name="arquivo" class="form-control @error('arquivo') is-invalid @enderror" accept=".xlsx" required>
          @error('arquivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
          <div class="form-text">Colunas esperadas: nome, cpf, email, data_nascimento, telefone, vinculo. A turma será atribuída automaticamente conforme o agendamento.</div>
        </div>

        <div class="col-12">
          <div class="border rounded p-3 bg-light">
            <h2 class="h6 mb-2">Segurança e Proteção de Dados (LGPD)</h2>
            <p class="mb-2 text-muted small">Declaro que estou autorizado(a) a enviar estes dados e confirmo ciência sobre os termos de uso e política de privacidade da plataforma.</p>
            <div class="form-check">
              <input class="form-check-input @error('aceite_lgpd') is-invalid @enderror" type="checkbox" value="1" id="aceite_lgpd" name="aceite_lgpd" @checked(old('aceite_lgpd'))>
              <label class="form-check-label" for="aceite_lgpd">Li e concordo com o envio dos dados.</label>
              @error('aceite_lgpd') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <a href="{{ route('agendamentos.participantes.index', $agendamento) }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-engaja">Enviar arquivo</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection