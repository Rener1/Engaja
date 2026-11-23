<div class="cpe-table-card cpe-manager-table">
    <table class="cpe-table">
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">
                    <input type="checkbox" id="selectAllApprovals" class="cpe-approval-checkbox"
                        aria-label="Selecionar todas as respostas em preparação desta página"
                        title="Selecionar todas as respostas em preparação desta página"
                        @disabled(! $cartas->contains(fn ($carta) => $carta->podeAprovarResposta()))>
                </th>
                <th>ID</th>
                <th>Status</th>
                <th>Remetente</th>
                <th>Município do remetente</th>
                <th>Destinatário</th>
                <th>Cartas</th>
                <th>Município do destinatário</th>
                <th>Data</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            @if ($cartas->count() > 0)
                @foreach ($cartas as $carta)
                    @php
                        $podeAprovar = $carta->podeAprovarResposta();
                        $ultimoStatus = $carta->ultimaMensagem?->status ?? $carta->status;
                        $statusClass = match (true) {
                            $carta->status === 'respondida' => 'cpe-pill--green',
                            $carta->status === 'aguardando_ajuste' || $ultimoStatus === 'ajuste_solicitado' => 'cpe-pill--red',
                            str_contains($ultimoStatus, 'verificacao') => 'cpe-pill--yellow',
                            default => 'cpe-pill--blue',
                        };
                        $statusLabel = match (true) {
                            $carta->status === 'respondida' => 'Respondida',
                            $carta->status === 'aguardando_ajuste' || $ultimoStatus === 'ajuste_solicitado' => 'Ajuste solicitado',
                            str_contains($ultimoStatus, 'verificacao') => 'Em preparação',
                            default => 'Enviada',
                        };
                    @endphp
                    <tr>
                        <td style="text-align: center;">
                            <span @if(! $podeAprovar) tabindex="0" title="Esta troca não possui resposta aguardando aprovação" @endif>
                                <input type="checkbox" name="mensagem_ids[]" value="{{ $carta->ultimaMensagem?->id }}"
                                    class="carta-checkbox cpe-approval-checkbox" form="approveBatchForm"
                                    aria-label="{{ $podeAprovar ? 'Selecionar resposta da carta '.$carta->codigo.' para aprovação' : 'Esta troca não possui resposta aguardando aprovação' }}"
                                    @disabled(! $podeAprovar)>
                            </span>
                        </td>
                        <td>{{ $carta->codigo }}</td>
                        <td>
                            <span class="cpe-pill {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td>{{ $carta->educando?->nome ?? 'Remetente' }}</td>
                        <td>
                            @if($carta->educando?->municipio)
                                <div style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <span>{{ collect([$carta->educando->municipio->nome, $carta->educando->municipio->estado?->sigla])->filter()->implode(' - ') }}</span>
                                </div>
                            @else
                                Não informado
                            @endif
                        </td>
                        <td>{{ $carta->voluntario?->nome ?? 'Sem voluntário' }}</td>
                        <td>
                            @if($carta->voluntario)
                                {{ $carta->voluntario->cartas_como_voluntario_count ?? 0 }} / {{ $carta->voluntario->cartas_limite_respostas ?? 1 }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($carta->voluntario?->participante?->municipio)
                                <span>{{ collect([$carta->voluntario->participante->municipio->nome, $carta->voluntario->participante->municipio->estado?->sigla])->filter()->implode(' - ') }}</span>
                            @else
                                Não informado
                            @endif
                        </td>
                        <td>{{ optional($carta->created_at)->format('d/m/Y') }}</td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <a href="{{ route('cartas.cartas.show', $carta) }}" class="cpe-icon-button" aria-label="Abrir carta" title="Visualizar">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                                <button type="button" class="cpe-trash-button" aria-label="Remover carta" title="Excluir" data-modal-open="deleteCarta-{{ $carta->id }}">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M3 6h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M8 6V4h8v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M6 6l1 15h10l1-15" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                        <path d="M10 11v6M14 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="10" class="cpe-empty">Nenhuma carta cadastrada.</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

<div class="cpe-pagination">
    {{ $cartas->links('cartas.gestor.pagination') }}
</div>
