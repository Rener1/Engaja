@extends('layouts.app')

@section('content')
<x-ui.page-hero contained eyebrow="Minha conta" eyebrow-icon="person-check" title="Minhas Presenças" />

<div class="container">
        <x-ui.filter-card>
            <form method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="eg-filter-label">Ação pedagógica (evento)</label>
                        <x-ui.select name="evento_id">
                            <option value="">Todas</option>
                            @foreach($eventos as $evento)
                               <option value="{{ $evento->id }}" {{ ($filtros['evento_id'] ?? '') == $evento->id ? 'selected' : '' }}>
                                    {{ $evento->nome }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="col-md-2">
                        <label class="eg-filter-label">De</label>
                        <x-ui.input type="date" name="data_de" value="{{ $filtros['data_de'] }}" />
                    </div>
                    <div class="col-md-2">
                        <label class="eg-filter-label">Até</label>
                        <x-ui.input type="date" name="data_ate" value="{{ $filtros['data_ate'] }}" />
                    </div>
                    <div class="col-md-3">
                        <label class="eg-filter-label">Buscar (momento/ação)</label>
                        <x-ui.input type="text" name="busca" value="{{ $filtros['busca'] }}" />
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        <a href="{{ route('profile.presencas') }}" class="btn btn-secondary w-100">Limpar</a>
                    </div>
                </div>
            </form>
        </x-ui.filter-card>
    <div class="card">
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Hora</th>
                        <th>Momento</th>
                        <th>Ação Pedagógica</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($atividades as $atividade)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($atividade['data'])->format('d/m/Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($atividade['hora'])->format('H:i') }}</td>
                            <td>{{ $atividade['momento'] }}</td>
                            <td>{{ $atividade['evento'] }}</td>
                            <td>
                                @if($atividade['status'] === 'Presente')
                                    <span class="badge bg-success">Presente</span>
                                @elseif($atividade['status'] === 'Ouvinte')
                                    <span class="badge bg-info">Ouvinte</span>
                                @else
                                    <span class="badge bg-danger">Ausente</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"><x-ui.empty-state compact muted icon="inbox" title="Nenhuma presença encontrada." /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
