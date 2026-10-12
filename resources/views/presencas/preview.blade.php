@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h1 class="h5 mb-3">
    Pré-visualização de presenças — {{ $evento->nome }}<br>
    <small class="text-muted">
      Momento: {{ \Carbon\Carbon::parse($atividade->dia)->format('d/m/Y') }}
      • {{ \Illuminate\Support\Str::of($atividade->hora_inicio)->substr(0,5) }}
      • {{ $atividade->descricao ?? 'Momento' }}
    </small>
  </h1>

  @if($errors->any())
  <div class="alert alert-danger" role="alert">
    <strong>Importação bloqueada. Corrija os problemas abaixo e salve cada página antes de continuar.</strong>
    <ul class="mb-0 mt-2">
      @foreach($errors->all() as $message)
      <li>{{ $message }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <p class="text-muted"><strong>Nome, data de nascimento e status são obrigatórios. Informe e-mail ou CPF em cada registro. Salve as alterações da página antes de confirmar.</strong></p>

  <form method="POST" action="{{ route('atividades.presencas.savepage', $atividade) }}" class="mb-3">
    @csrf
    <input type="hidden" name="session_key" value="{{ $sessionKey }}">

    <div class="table-responsive">
      @php $tagOptions = $participanteTags ?? config('engaja.participante_tags', \App\Models\Participante::TAGS); @endphp
      <table class="table table-sm table-bordered align-middle bg-white">
        <thead class="table-light">
          <tr>
            <th>Nome</th>
            <th>Email</th>
            <th>CPF</th>
            <th>Telefone</th>
            <th>Município</th>
            <th>Tipo de Organização</th>
            <th>Organização</th>
            <th>Tag</th>
            <th style="min-width:180px;">Data de nascimento *</th>
            <th style="min-width:180px;">Sexo</th>
            <th style="min-width:150px;">Status</th>
            <!-- <th>Justificativa</th> -->
            <!-- <th>Data entrada</th> -->
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $i => $r)
          @php $gi = $globalOffset + $i; @endphp
          <tr>
            <td><input name="rows[{{ $gi }}][nome]" class="form-control form-control-sm" value="{{ old('rows.'.$gi.'.nome', $r['nome']) }}" required maxlength="255"></td>
            <td><input name="rows[{{ $gi }}][email]" class="form-control form-control-sm" value="{{ old('rows.'.$gi.'.email', $r['email']) }}" type="email" maxlength="255"></td>
            <td><input name="rows[{{ $gi }}][cpf]" class="form-control form-control-sm" value="{{ old('rows.'.$gi.'.cpf', $r['cpf']) }}" maxlength="255"></td>
            <td><input name="rows[{{ $gi }}][telefone]" class="form-control form-control-sm" value="{{ old('rows.'.$gi.'.telefone', $r['telefone']) }}" maxlength="255"></td>
            <td>
              <select name="rows[{{ $gi }}][municipio_id]"
                class="form-select form-select-sm">
                <option value="">— Selecione —</option>
                @foreach($municipios as $m)
                <option value="{{ $m->id }}"
                  @selected((string)($r['municipio_id'] ?? '' )===(string)$m->id)>
                  {{ $m->nome_com_estado }}
                </option>
                @endforeach
              </select>
            </td>
            <td>
              <select
                name="rows[{{ $gi }}][tipo_organizacao]"
                class="form-select form-select-sm {{ (!empty($r['tipo_organizacao']) && empty($r['tipo_organizacao_ok'])) ? 'is-invalid' : '' }}">
                <option value="">Selecione...</option>
                @foreach($organizacoes as $org)
                <option value="{{ $org }}" @selected(($r['tipo_organizacao'] ?? '' )===$org)>{{ $org }}</option>
                @endforeach
              </select>

              @if(!empty($r['tipo_organizacao']) && empty($r['tipo_organizacao_ok']))
              <div class="invalid-feedback">
                Valor importado não está na lista. Selecione um tipo válido.
              </div>
              @endif
            </td>
            <td>
              <input
                name="rows[{{ $gi }}][escola_unidade]"
                class="form-control form-control-sm"
                value="{{ $r['escola_unidade'] ?? '' }}">
            </td>
            <td>
              <select
                name="rows[{{ $gi }}][tag]"
                class="form-select form-select-sm {{ (!empty($r['tag']) && empty($r['tag_ok'])) ? 'is-invalid' : '' }}">
                <option value="">Selecione...</option>
                @foreach($tagOptions as $tagOption)
                <option value="{{ $tagOption }}" @selected(($r['tag'] ?? '')===$tagOption)>{{ $tagOption }}</option>
                @endforeach
              </select>

              @if(!empty($r['tag']) && empty($r['tag_ok']))
              <div class="invalid-feedback">Selecione uma tag válida.</div>
              @endif
            </td>
            <td>
              <input type="date"
                class="form-control form-control-sm @error("rows.$gi.data_nascimento") is-invalid @enderror"
                name="rows[{{ $gi }}][data_nascimento]"
                value="{{ old("rows.$gi.data_nascimento", $r['data_nascimento'] ?? '') }}"
                max="{{ now()->toDateString() }}" required>
              @error("rows.$gi.data_nascimento") <div class="invalid-feedback">{{ $message }}</div> @enderror
            </td>
            <td>
              <select class="form-select form-select-sm @error("rows.$gi.sexo") is-invalid @enderror"
                name="rows[{{ $gi }}][sexo]">
                <option value="">Selecione...</option>
                @foreach(\App\Models\Participante::SEXOS as $sexo)
                <option value="{{ $sexo }}" @selected(old("rows.$gi.sexo", $r['sexo'] ?? null) === $sexo)>{{ $sexo }}</option>
                @endforeach
              </select>
              @error("rows.$gi.sexo") <div class="invalid-feedback">{{ $message }}</div> @enderror
            </td>
            <td>
              @php
                $statusKey = 'rows.'.$gi.'.status';
                $statusValue = old($statusKey, $r['status'] ?? null);
                $statusHasError = !in_array($statusValue, ['presente', 'ausente', 'justificado'], true) || $errors->has($statusKey);
              @endphp
              <select name="rows[{{ $gi }}][status]"
                class="form-select form-select-sm {{ $statusHasError ? 'is-invalid' : '' }}"
                aria-invalid="{{ $statusHasError ? 'true' : 'false' }}"
                @if($statusHasError) aria-describedby="status-error-{{ $gi }}" @endif
                required>
                <option value="">— Selecionar —</option>
                <option value="presente" @selected($statusValue==='presente')>Presente</option>
                <option value="ausente" @selected($statusValue==='ausente')>Ausente</option>
                <option value="justificado" @selected($statusValue==='justificado')>Justificado</option>
              </select>
              @if($statusHasError)
              <div class="invalid-feedback" id="status-error-{{ $gi }}">
                {{ $errors->first($statusKey) ?: 'Selecione o status da presença.' }}
              </div>
              @endif
            </td>
            <!-- <td><input name="rows[{{ $gi }}][justificativa]" class="form-control form-control-sm" value="{{ $r['justificativa'] }}"></td> -->
            <!-- <td><input type="date" name="rows[{{ $gi }}][data_entrada]" class="form-control form-control-sm" value="{{ $r['data_entrada'] }}"></td> -->
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-between align-items-center">
      <div>{{ $rows->links() }}</div>
      <div class="d-flex gap-2">
        <a href="{{ route('atividades.presencas.preview', ['atividade'=>$atividade, 'session_key'=>$sessionKey]) }}" class="btn btn-outline-secondary btn-sm">Recarregar</a>
        <button class="btn btn-primary btn-sm">Salvar esta página</button>
      </div>
    </div>
  </form>

  <form method="POST" action="{{ route('atividades.presencas.confirmar', $atividade) }}" class="mt-3">
    @csrf
    <input type="hidden" name="session_key" value="{{ $sessionKey }}">
    <button class="btn btn-engaja">Confirmar e salvar tudo</button>
  </form>
</div>
@endsection
