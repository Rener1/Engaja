<x-ui.filter-card>
    <div class="row g-3 align-items-end">
      <div class="col-lg-3 col-md-6">
        <label class="eg-filter-label">Modelo</label>
        <x-ui.select class="js-filter" id="f-template">
          <option value="">Todos</option>
          @foreach($templates as $template)
          <option value="{{ $template->id }}" @selected(request('template_id') == $template->id)>{{ $template->nome }}</option>
          @endforeach
        </x-ui.select>
      </div>
      <div class="col-lg-3 col-md-6">
        <label class="eg-filter-label">Evento</label>
        <x-ui.select class="js-filter" id="f-evento">
          <option value="">Todos</option>
          @foreach($eventos as $evento)
          <option value="{{ $evento->id }}">{{ $evento->nome }}</option>
          @endforeach
        </x-ui.select>
      </div>
      <div class="col-lg-3 col-md-6">
        <label class="eg-filter-label">Atividade / momento</label>
        <x-ui.select class="js-filter" id="f-atividade">
          <option value="">Todas</option>
          @foreach($atividades as $atividade)
          @php
            $diaFormatado = $atividade->dia ? \Illuminate\Support\Carbon::parse($atividade->dia)->format('d/m') : '';
          @endphp
          <option value="{{ $atividade->id }}">
            {{ $atividade->descricao ?? 'Momento' }} - {{ $diaFormatado }} {{ $atividade->hora_inicio }}
            @if($atividade->evento) ({{ $atividade->evento->nome }}) @endif
          </option>
          @endforeach
        </x-ui.select>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="row g-3">
          <div class="col-6">
            <label class="eg-filter-label">De</label>
            <x-ui.input type="date" class="js-filter" id="f-de" />
          </div>
          <div class="col-6">
            <label class="eg-filter-label">Ate</label>
            <x-ui.input type="date" class="js-filter" id="f-ate" />
          </div>
        </div>
      </div>
    </div>
    <div class="eg-filter-actions mt-3">
      <button class="btn btn-engaja" id="btn-recarregar">
        Atualizar agora
      </button>
    </div>
</x-ui.filter-card>
