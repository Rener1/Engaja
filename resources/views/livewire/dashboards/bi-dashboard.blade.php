<div class="py-4">
    <x-ui.filter-card>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="bi-ano" class="eg-filter-label">Ano do indicador</label>
                    <x-ui.select id="bi-ano" wire:model.live="ano">
                        @forelse($anosDisponiveis as $itemAno)
                            <option value="{{ $itemAno }}">{{ $itemAno }}</option>
                        @empty
                            <option value="{{ $ano }}">{{ $ano }}</option>
                        @endforelse
                    </x-ui.select>
                </div>
                <div class="col-md-5">
                    <label for="bi-municipio" class="eg-filter-label">Município (gráficos por dimensão)</label>
                    <x-ui.select id="bi-municipio" wire:model.live="municipioId">
                        <option value="">Todos os municípios</option>
                        @foreach($municipiosDisponiveis as $municipio)
                            <option value="{{ $municipio['id'] }}">{{ $municipio['nome'] }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            </div>
    </x-ui.filter-card>

    @if(empty($anosDisponiveis))
        <x-ui.empty-state icon="database-x" muted title="Sem dados de BI">
            Não há dados de BI carregados para exibição no dashboard.
        </x-ui.empty-state>
    @else
        @php($anoAtual = (int) $ano)
        @php($municipioIdAtual = $this->municipioIdNormalizado())
        @php($municipioSelecionadoNome = $this->municipioSelecionadoNome())
        @php($tituloRankingAnalfabetismo = "Ranking de não alfabetizados por município ({$anoAtual})")
        @php($tituloRankingEja = "Ranking de acesso à EJA por município ({$anoAtual})")
        @php($sufixoMunicipioDimensao = $municipioSelecionadoNome ? " - {$municipioSelecionadoNome}" : '')

        <livewire:graficos.ranking-municipios
            :indicador-percentual="'ANALFABETISMO_TAXA'"
            :indicador-absoluto="'ANALFABETISMO_QTDE'"
            :ano="$anoAtual"
            :titulo="$tituloRankingAnalfabetismo"
            :cor="'--engaja-purple'"
            :key="'ranking-analfabetismo-'.$anoAtual" />

        <livewire:graficos.ranking-municipios
            :indicador-percentual="'EJA_ACESSO_TAXA'"
            :indicador-absoluto="'EJA_MATRICULAS_QTDE'"
            :ano="$anoAtual"
            :titulo="$tituloRankingEja"
            :cor="'--engaja-blue'"
            :key="'ranking-eja-'.$anoAtual" />

        <div class="row g-3 mt-1">
            <div class="col-lg-4">
                <livewire:graficos.distribuicao-dimensao
                    :indicador="'ANALFABETISMO_QTDE'"
                    :ano="$anoAtual"
                    :municipio-id="$municipioIdAtual"
                    :dimensao="'SEXO'"
                    :titulo="'Distribuição por sexo'.$sufixoMunicipioDimensao"
                    :tipo-grafico="'donut'"
                    :key="'dimensao-sexo-'.$anoAtual.'-'.($municipioIdAtual ?? 'todos')" />
            </div>
            <div class="col-lg-4">
                <livewire:graficos.distribuicao-dimensao
                    :indicador="'ANALFABETISMO_QTDE'"
                    :ano="$anoAtual"
                    :municipio-id="$municipioIdAtual"
                    :dimensao="'RACA'"
                    :titulo="'Distribuição por raça'.$sufixoMunicipioDimensao"
                    :tipo-grafico="'polarArea'"
                    :key="'dimensao-raca-'.$anoAtual.'-'.($municipioIdAtual ?? 'todos')" />
            </div>
            <div class="col-lg-4">
                <livewire:graficos.distribuicao-dimensao
                    :indicador="'ANALFABETISMO_QTDE'"
                    :ano="$anoAtual"
                    :municipio-id="$municipioIdAtual"
                    :dimensao="'RESIDENCIA'"
                    :titulo="'Distribuição por residência'.$sufixoMunicipioDimensao"
                    :tipo-grafico="'bar'"
                    :mostrar-valores="false"
                    :usar-percentual="true"
                    :casas-decimais-percentual="0"
                    :key="'dimensao-residencia-'.$anoAtual.'-'.($municipioIdAtual ?? 'todos')" />
            </div>
        </div>
    @endif
</div>
