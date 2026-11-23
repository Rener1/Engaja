@extends('layouts.app')

@section('content')
  <x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar3" :title="'Momentos — '.$evento->nome">
      @hasanyrole('administrador|formador')
      <small class="text-muted">Gerencie a programação da ação pedagógica</small>
      @endhasanyrole
      <x-slot:actions>
          @hasanyrole('administrador|formador')
          <a href="{{ route('eventos.atividades.create', $evento) }}" class="eg-hero-btn eg-hero-btn--solid">+ Novo momento</a>
          @endhasanyrole
      </x-slot:actions>
  </x-ui.page-hero>

  <div>
    <x-ui.filter-card>
        <form method="GET" action="{{ route('eventos.atividades.index', $evento) }}" class="row g-3 align-items-end">
          <div class="col-12 col-md-6 col-lg-3">
            <label for="municipio_id" class="eg-filter-label">Município</label>
            <x-ui.select name="municipio_id" id="municipio_id">
              <option value="">Todos</option>
              @if($temAbrangenciaNacional ?? false)
                <option value="brasil" @selected(request('municipio_id') === 'brasil')>Brasil</option>
              @endif
              @foreach($municipiosDisponiveis as $m)
                <option value="{{ $m->id }}" @selected(request('municipio_id') == $m->id)>
                  {{ $m->nome_com_estado ?? $m->nome }}
                </option>
              @endforeach
            </x-ui.select>
          </div>
          <div class="col-6 col-md-3 col-lg-2">
            <label for="de" class="eg-filter-label">Data inicial</label>
            <x-ui.input type="date" name="de" id="de" value="{{ request('de') }}" />
          </div>
          <div class="col-6 col-md-3 col-lg-2">
            <label for="ate" class="eg-filter-label">Data final</label>
            <x-ui.input type="date" name="ate" id="ate" value="{{ request('ate') }}" />
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <label for="descricao" class="eg-filter-label">Descrição</label>
            <x-ui.input type="search" name="descricao" id="descricao" maxlength="255" value="{{ request('descricao') }}" placeholder="Buscar descrição" />
          </div>
          <div class="col eg-filter-actions justify-content-end">
            @if(request()->hasAny(['municipio_id', 'de', 'ate', 'descricao']))
              <a href="{{ route('eventos.atividades.index', $evento) }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle me-1"></i> Limpar filtros
              </a>
            @endif
            <button type="submit" class="btn btn-engaja px-3">
              <i class="bi bi-funnel-fill me-1"></i> Filtrar
            </button>
          </div>
        </form>
    </x-ui.filter-card>

    @php
        $columns = [
            ['field' => 'dia', 'headerName' => 'Data', 'flex' => 1],
            ['field' => 'municipios', 'headerName' => 'Municípios', 'flex' => 2],
            ['field' => 'descricao', 'headerName' => 'Descrição', 'flex' => 2],
            ['field' => 'carga_horaria', 'headerName' => 'Carga horária', 'flex' => 1],
            ['field' => 'status', 'headerName' => 'Status', 'flex' => 1, 'html' => true],
        ];

        if (auth()->check()) {
            $columns[] = ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true, 'align' => 'end'];
        }

        $rows = $atividades->map(function ($at) {
            $munLabel = $at->abrangencia_nacional
                ? 'Brasil'
                : ($at->municipios->isNotEmpty()
                    ? $at->municipios->map(fn ($m) => $m->nome_com_estado ?? $m->nome)->join(', ')
                    : '-');

            $statusHtml = $at->checklists_incompletos
                ? '<button type="button" class="badge bg-warning text-dark border-0 btn-checklist-reabrir" data-atividade-id="' . $at->id . '" data-checklist-pl="' . e(json_encode($at->checklist_planejamento ?? [])) . '" data-checklist-en="' . e(json_encode($at->checklist_encerramento ?? [])) . '" style="cursor:pointer; font-size:0.75rem; padding:0.35rem 0.5rem;">⚠️ Checklist incompleto</button>'
                : '';

            $row = [
                'dia' => \Carbon\Carbon::parse($at->dia)->format('d/m/Y'),
                'municipios' => $munLabel,
                'descricao' => $at->descricao,
                'carga_horaria' => !is_null($at->carga_horaria) ? \App\Support\CargaHoraria::formatMinutos((int) $at->carga_horaria) : '—',
                'status' => $statusHtml,
            ];

            if (auth()->check()) {
                $minhaAvaliacaoAtividade = $at->minha_avaliacao_atividade;

                $acoesHtml = '<div class="dropdown">'
                    . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Gerenciar</button>'
                    . '<ul class="dropdown-menu dropdown-menu-end">'
                    . '<li><a class="dropdown-item" href="' . route('atividades.show', $at) . '">Ver</a></li>';

                if (auth()->user()->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica'])) {
                    $acoesHtml .= '<li><a class="dropdown-item" href="' . route('atividades.edit', $at) . '">Editar</a></li>';
                }

                $acoesHtml .= '<li><a class="dropdown-item" href="' . ($minhaAvaliacaoAtividade ? route('avaliacao-atividade.edit', $at) : route('avaliacao-atividade.create', $at)) . '">📋 ' . ($minhaAvaliacaoAtividade ? 'Editar meu relatório' : 'Criar meu relatório') . '</a></li>';

                if (auth()->user()->hasAnyRole(['administrador', 'gerente'])) {
                    $acoesHtml .= '<li><hr class="dropdown-divider"></li>'
                        . '<li>';

                    $presencasCount = (int) ($at->presencas_count ?? 0);
                    if ($presencasCount > 0) {
                        $presencasTexto = $presencasCount === 1 ? '1 presença registrada' : "{$presencasCount} presenças registradas";
                        $msgBloqueio = "Este momento possui {$presencasTexto} e não pode ser excluído.";
                        $acoesHtml .= '<button type="button" class="dropdown-item text-danger" data-blocked-delete="' . e($msgBloqueio) . '" data-blocked-delete-title="Momento possui presenças associadas">Excluir</button>';
                    } else {
                        $acoesHtml .= '<form method="POST" action="' . route('atividades.destroy', $at) . '" data-confirm="Tem certeza que deseja excluir este momento?">'
                            . csrf_field() . method_field('DELETE')
                            . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
                            . '</form>';
                    }

                    $acoesHtml .= '</li>';
                }

                $acoesHtml .= '</ul></div>';
                $row['acoes'] = $acoesHtml;
            }

            return $row;
        })->values();
    @endphp

    <div class="card shadow-sm">
        <x-data-table id="grid-atividades" :columns="$columns" :rows="$rows" :pagination="false" :paginator="$atividades" />
    </div>

    <div class="mt-3">
      <a href="{{ route('eventos.show', $evento) }}" class="btn btn-outline-secondary">Voltar à ação pedagógica</a>
    </div>
  </div>

  {{-- Modal de reabertura de checklist --}}
  <div class="modal fade" id="modalReopenChecklist" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <x-ui.modal-header icon="exclamation-triangle" title="Checklist Incompleto" :dismissible="false" />
        <div class="modal-body" id="reopen-checklist-body">
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
          <button type="button" class="btn btn-engaja" id="btn-salvar-checklist-reopen">Salvar progresso</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
      const ITENS_PLANEJAMENTO = [
          'Ao planejar cada ação, recorri aos objetivos gerais do projeto, em diálogo com os dados da Leitura do Mundo?',
          'Ao planejar, estabeleci conexão com as outras ações do projeto? (Ex: Cartas para Esperançar, Semear Palavras)',
          'Preparei listas de presença impressas de acordo com os dados a serem inseridos no sistema ENGAJA?',
          'Preparei formulários de avaliação de cada ação de formação, para medir os impactos?',
          'Organizei a lista de materiais necessários e apresentei à coordenação com antecedência?',
          'Organizei a demanda de infraestrutura local com antecedência?',
          'A inscrição do público esperado na formação foi feita?',
          'A informação sobre o dia e horário chegou com antecedência aos públicos participantes?',
          'Os materiais institucionais do projeto para entregar aos participantes estão organizados?',
          'Equipe Pedagógica e Educadores estão com clareza de quem fará o que durante os encontros?',
          'Planejei os momentos de registros audiovisual de cada ação?',
          'Sei como nomear os arquivos e o local onde compartilhar os registros processuais?',
          'Estou de posse de todos os contatos estratégicos em caso de necessidade?'
      ];
      const ITENS_ENCERRAMENTO = [
          'Verifiquei se os municípios estão corretos?',
          'Confirmei a carga horária e os horários de início e término?',
          'O público esperado e os dados do momento estão preenchidos corretamente?'
      ];

      let atividadeIdAtual = null;

      // Delegação de evento: os botões .btn-checklist-reabrir são renderizados
      // pelo AG Grid de forma assíncrona (depois do DOMContentLoaded).
      document.addEventListener('click', function (e) {
          const btn = e.target.closest('.btn-checklist-reabrir');
          if (!btn) return;

          atividadeIdAtual = btn.dataset.atividadeId;
          const marcadosPl = normalizeMarkedIndexes(ITENS_PLANEJAMENTO, JSON.parse(btn.dataset.checklistPl || '[]'));
          const marcadosEn = normalizeMarkedIndexes(ITENS_ENCERRAMENTO, JSON.parse(btn.dataset.checklistEn || '[]'));

          const body = document.getElementById('reopen-checklist-body');
          body.innerHTML = renderChecklist('planejamento', ITENS_PLANEJAMENTO, marcadosPl)
                         + renderChecklist('encerramento', ITENS_ENCERRAMENTO, marcadosEn);

          new bootstrap.Modal(document.getElementById('modalReopenChecklist')).show();
      });

        function normalizeMarkedIndexes(itens, marcados) {
          if (!Array.isArray(marcados)) {
            return [];
          }

          const normalizedTextToIndex = new Map(
            itens.map((item, index) => [String(item).trim().toLowerCase(), index])
          );

          return [...new Set(
            marcados
              .map((valor) => {
                if (Number.isInteger(valor)) {
                  return valor;
                }

                const asNumber = Number(valor);
                if (Number.isInteger(asNumber)) {
                  return asNumber;
                }

                const textKey = String(valor).trim().toLowerCase();
                return normalizedTextToIndex.has(textKey) ? normalizedTextToIndex.get(textKey) : null;
              })
              .filter((index) => Number.isInteger(index) && index >= 0 && index < itens.length)
          )];
        }

      function renderChecklist(tipo, itens, marcados) {
          let html = `<h6 class="fw-bold mt-2" style="color: #421944;">${tipo === 'planejamento' ? '📋 Planejamento' : '✅ Encerramento'}</h6><div class="vstack gap-2 mb-4">`;
          itens.forEach((item, i) => {
              const checked = marcados.includes(i) ? 'checked' : '';
              html += `<label class="checklist-card d-flex align-items-center gap-3 ${checked ? 'checked' : ''}" style="cursor:pointer;border:2px solid #dee2e6;border-radius:10px;padding:12px 16px; ${checked ? 'background-color: #421944; color: #fff; border-color: #421944;' : ''}">
                  <input type="checkbox" class="js-reopen-item" data-tipo="${tipo}" data-index="${i}" ${checked} style="display:none">
                  <span class="checklist-check-icon" style="width:22px;height:22px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#421944;font-weight:900;opacity:${checked ? 1 : 0}">✓</span>
                  <span>${item}</span>
              </label>`;
          });
          html += '</div>';
          return html;
      }

      document.getElementById('reopen-checklist-body')?.addEventListener('change', function(e) {
          if (e.target.classList.contains('js-reopen-item')) {
              const label = e.target.closest('label');
              label.classList.toggle('checked', e.target.checked);
              label.style.backgroundColor = e.target.checked ? '#421944' : '';
              label.style.color = e.target.checked ? '#fff' : '';
              label.style.borderColor = e.target.checked ? '#421944' : '#dee2e6';
              label.querySelector('.checklist-check-icon').style.opacity = e.target.checked ? 1 : 0;
          }
      });

      document.getElementById('btn-salvar-checklist-reopen')?.addEventListener('click', function () {
          if (!atividadeIdAtual) return;
          const pl = [...document.querySelectorAll('.js-reopen-item[data-tipo="planejamento"]:checked')].map(c => parseInt(c.dataset.index));
          const en = [...document.querySelectorAll('.js-reopen-item[data-tipo="encerramento"]:checked')].map(c => parseInt(c.dataset.index));

          const salvar = (tipo, itens) => fetch(`/atividades/${atividadeIdAtual}/checklist`, {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              },
              body: JSON.stringify({ tipo, itens })
          });

          Promise.all([salvar('planejamento', pl), salvar('encerramento', en)])
              .then(() => { window.location.reload(); })
              .catch(() => alert('Erro ao salvar. Tente novamente.'));
      });
  });
</script>
@endpush
