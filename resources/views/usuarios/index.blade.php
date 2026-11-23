@extends('layouts.app')

@section('content')
<div class="mb-4">
    <x-ui.page-hero eyebrow="Administração" eyebrow-icon="people" title="Gerenciar Usuários">
        <x-slot:actions>
            @hasanyrole('administrador|gerente|eq_pedagogica|articulador')
            <div class="d-flex gap-2">
                <button type="button" class="eg-hero-btn" data-bs-toggle="modal" data-bs-target="#modalExportarUsuarios">
                    Baixar planilha de usuários
                </button>
                @can('viewAny', \App\Models\AutorizacaoImagem::class)
                <a href="{{ route('autorizacoes-imagem.index') }}" class="eg-hero-btn">Autorizações por ação</a>
                @endcan
                <a href="{{ route('usuarios.autorizacoes.import') }}" class="eg-hero-btn">Importar autorizações globais (legado)</a>
                @role('administrador')
                <a href="{{ route('usuarios.notificacoes-agendamento.index') }}" class="eg-hero-btn">Notificações de Agendamento</a>
                @endrole
                <a href="{{ route('usuarios.create') }}" class="eg-hero-btn eg-hero-btn--solid">Cadastrar Usuário</a>
            </div>
            @endhasanyrole
        </x-slot:actions>
    </x-ui.page-hero>

    <div class="eg-filter-card">
        <form action="{{ route('usuarios.index') }}" method="GET" class="row g-3 align-items-center">

            {{-- campo de busca --}}
            <div class="col-12 col-md-3">
                <x-ui.input type="text" name="q" placeholder="Buscar nome, e-mail ou CPF..." value="{{ $search }}" />
            </div>

            {{-- select de região --}}
            <div class="col-12 col-md-2">
                <x-ui.select name="regiao" id="filtro_regiao">
                    <option value="">Todas as Regiões</option>
                    @foreach($regioes as $regiao)
                        <option value="{{ $regiao->id }}" {{ $regiao_id == $regiao->id ? 'selected' : '' }}>
                            {{ $regiao->nome }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            {{-- select de estado --}}
            <div class="col-12 col-md-2">
                <x-ui.select name="estado" id="filtro_estado" :disabled="empty($regiao_id) && empty($estado_id)">
                    <option value="">Todos os Estados</option>
                    @foreach($estados as $estado)
                        <option value="{{ $estado->id }}" data-regiao="{{ $estado->regiao_id }}" {{ $estado_id == $estado->id ? 'selected' : '' }}>
                            {{ $estado->nome }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            {{-- select de município --}}
            <div class="col-12 col-md-3">
                <x-ui.select name="municipio" id="filtro_municipio" :disabled="empty($estado_id) && empty($municipio_id)">
                    <option value="">Todos os Municípios</option>
                    @foreach($municipios as $municipio)
                        <option value="{{ $municipio->id }}" data-estado="{{ $municipio->estado_id }}" {{ $municipio_id == $municipio->id ? 'selected' : '' }}>
                            {{ $municipio->nome }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-engaja w-100">Filtrar</button>
                @if($search || $regiao_id || $estado_id || $municipio_id)
                    <a href="{{ route('usuarios.index') }}" class="btn btn-light border w-100">Limpar</a>
                @endif
            </div>
        </form>
    </div>
</div>

@if ($users->isEmpty())
  <x-ui.empty-state icon="inbox" muted>
    @if (!empty($search))
      Nenhum usuario encontrado para "{{ $search }}".
    @else
      Nao ha usuarios editaveis no momento.
    @endif
  </x-ui.empty-state>
@else
  <form method="POST" action="{{ route('usuarios.certificados.emitir') }}" id="form-emitir-certificados">
    @csrf
    <input type="hidden" name="modelo_id" id="modelo_id_hidden">
    <div id="participantes-hidden-inputs"></div>

    @php
        $columns = [
            ['field' => 'nome', 'headerName' => 'Nome', 'flex' => 2],
            ['field' => 'email', 'headerName' => 'E-mail', 'flex' => 2],
            ['field' => 'cpf', 'headerName' => 'CPF', 'flex' => 1],
            ['field' => 'telefone', 'headerName' => 'Telefone', 'flex' => 1],
            ['field' => 'papel', 'headerName' => 'Papel / Acesso no Sistema', 'flex' => 1.5],
            ['field' => 'acoes', 'headerName' => 'Ação', 'flex' => 1, 'html' => true],
        ];

        $rows = $users->map(function ($u) {
            $cpfRaw = $u->participante->cpf ?? null;
            $cpfFmt = $cpfRaw ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfRaw) : '--';
            $telRaw = $u->participante->telefone ?? null;
            $telFmt = $telRaw
                ? preg_replace('/(\d{2})(\d{4,5})(\d{4})/', '($1) $2-$3', $telRaw)
                : '--';

            $roleNames = [
                'administrador' => 'Administrador',
                'gerente' => 'Gerente',
                'eq_pedagogica' => 'Equipe Pedagógica',
                'articulador' => 'Articulador',
                'participante' => 'Participante',
                'SME' => 'SME',
                'cartas_admin' => 'Admin. Cartas',
                'cartas_gestao' => 'Gestor Cartas',
                'cartas_voluntario' => 'Voluntário Cartas',
            ];

            $roles = $u->roles->pluck('name')->map(fn($r) => $roleNames[$r] ?? ucwords(str_replace('_', ' ', $r)))->implode(', ');
            $papelFmt = $roles ?: 'Sem acesso definido';

            $acoesHtml = '<div class="dropdown d-inline-block">'
                . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Gerenciar</button>'
                . '<ul class="dropdown-menu dropdown-menu-end shadow-sm">';

            if (auth()->user()->hasAnyRole(['administrador', 'gerente'])) {
                $acoesHtml .= '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalParticipacoesUsuario' . $u->id . '">Ver participações</button></li>';
            }

            $acoesHtml .= '<li><a href="' . route('usuarios.edit', $u) . '" class="dropdown-item">Editar</a></li>';

            if (auth()->user()->hasRole('administrador')) {
                $acoesHtml .= '<li><button type="button" class="dropdown-item js-reset-password" data-bs-toggle="modal" data-bs-target="#modalRedefinirSenha" data-action="' . route('usuarios.password.reset', $u) . '" data-user-name="' . e($u->name) . '">Redefinir senha</button></li>';
            }

            if (auth()->user()->hasAnyRole(['administrador', 'gerente']) && ! $u->is(auth()->user())) {
                $acoesHtml .= '<li><hr class="dropdown-divider"></li>'
                    . '<li><button type="button" class="dropdown-item text-danger js-delete-user" data-bs-toggle="modal" data-bs-target="#modalExcluirUsuario" data-action="' . e(route('usuarios.destroy', $u)) . '" data-user-name="' . e($u->name) . '">Deletar usuário</button></li>';
            }

            $acoesHtml .= '</ul></div>';

            return [
                'id' => 'u-' . $u->id,
                'participante_id' => $u->participante->id ?? null,
                '_selectable' => (bool) $u->participante,
                'nome' => $u->name,
                'email' => $u->email,
                'cpf' => $cpfFmt,
                'telefone' => $telFmt,
                'papel' => $papelFmt,
                'acoes' => $acoesHtml,
            ];
        })->values();
    @endphp

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <x-data-table
            id="grid-usuarios"
            :columns="$columns"
            :rows="$rows"
            :pagination="false"
            row-selection="multiple"
            row-selectable-field="_selectable"
            :paginator="$users"
        />
      </div>
    </div>

      <div class="mt-3 text-end d-flex flex-wrap justify-content-end gap-2">
          {{--
          @hasanyrole('administrador|gerente')
            <button type="button" class="btn btn-outline-secondary" id="btn-select-all-page">Selecionar todos da página</button>
            <button type="button" class="btn btn-outline-secondary" id="btn-select-all-global">Selecionar todos (todas as páginas)</button>
            <button type="button" class="btn btn-engaja" id="btn-open-modal">Emitir certificados</button>
          @endhasanyrole
          --}}
      </div>
  </form>

  @hasanyrole('administrador|gerente')
  @foreach ($users as $u)
    @php
      $participante = $u->participante;
      $todasInscricoes = $participante?->inscricoes ?? collect();
      $participacoes = collect();

      foreach ($todasInscricoes as $inscricao) {
        $atividadeInscricao = $inscricao->atividade;
        $eventoInscricao = $inscricao->evento ?? $atividadeInscricao?->evento;
        $temPresencaEmMomentoValido = $inscricao->presencas->contains(function ($presenca) use ($inscricao) {
          $atividadePresenca = $presenca->atividade;
          $eventoPresenca = $atividadePresenca?->evento ?? $inscricao->evento;

          return $atividadePresenca && $eventoPresenca;
        });

        if ($eventoInscricao && ($atividadeInscricao || ! $temPresencaEmMomentoValido)) {
          $presencaDaInscricao = $inscricao->presencas
            ->first(fn ($item) => (int) $item->atividade_id === (int) ($inscricao->atividade_id ?? 0));

          $participacoes->push([
            'key' => 'inscricao-' . $inscricao->id,
            'evento' => $eventoInscricao,
            'atividade' => $atividadeInscricao,
            'inscricao' => $inscricao,
            'presenca' => $presencaDaInscricao,
            'ouvinte' => (bool) $inscricao->ouvinte,
          ]);
        }

        foreach ($inscricao->presencas as $presenca) {
          $atividadePresenca = $presenca->atividade;
          $eventoPresenca = $atividadePresenca?->evento ?? $inscricao->evento;

          if (! $eventoPresenca || ($presenca->atividade_id && ! $atividadePresenca)) {
            continue;
          }

          if ($inscricao->atividade_id && (int) $presenca->atividade_id === (int) $inscricao->atividade_id) {
            continue;
          }

          $participacoes->push([
            'key' => 'presenca-' . $presenca->id,
            'evento' => $eventoPresenca,
            'atividade' => $atividadePresenca,
            'inscricao' => $inscricao,
            'presenca' => $presenca,
            'ouvinte' => (bool) $inscricao->ouvinte,
          ]);
        }
      }

      $participacoes = $participacoes
        ->unique('key')
        ->sortBy([
          fn ($a, $b) => strcmp((string) ($a['evento']->nome ?? ''), (string) ($b['evento']->nome ?? '')),
          fn ($a, $b) => strcmp((string) ($a['atividade']->dia ?? ''), (string) ($b['atividade']->dia ?? '')),
          fn ($a, $b) => strcmp((string) ($a['atividade']->hora_inicio ?? ''), (string) ($b['atividade']->hora_inicio ?? '')),
        ])
        ->values();

      $primeiraInscricao = $todasInscricoes->sortBy('created_at')->first();
      $dataInscricaoSistema = $u->created_at
        ?? $participante?->created_at
        ?? $primeiraInscricao?->created_at;
    @endphp

    <div class="modal fade" id="modalParticipacoesUsuario{{ $u->id }}" tabindex="-1" aria-labelledby="modalParticipacoesUsuario{{ $u->id }}Label" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <x-ui.modal-header :title="'Participações de '.$u->name" :title-id="'modalParticipacoesUsuario'.$u->id.'Label'">
            <x-slot:subtitle>{{ $u->email }}</x-slot:subtitle>
          </x-ui.modal-header>
          <div class="modal-body">
            <div class="row g-3 mb-4">
              <div class="col-12 col-md-4">
                <div class="border rounded p-3 h-100 bg-light">
                  <div class="text-muted small">Data de inscrição no sistema</div>
                  <div class="fw-semibold">
                    {{ $dataInscricaoSistema ? $dataInscricaoSistema->format('d/m/Y H:i') : 'Não informada' }}
                  </div>
                </div>
              </div>
              <div class="col-12 col-md-4">
                <div class="border rounded p-3 h-100 bg-light">
                  <div class="text-muted small">Total de participações</div>
                  <div class="fw-semibold">{{ $participacoes->count() }}</div>
                </div>
              </div>
              <div class="col-12 col-md-4">
                <div class="border rounded p-3 h-100 bg-light">
                  <div class="text-muted small">Participações como ouvinte</div>
                  <div class="fw-semibold">{{ $participacoes->where('ouvinte', true)->count() }}</div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle table-bordered mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="min-width: 220px;">Ação pedagógica</th>
                    <th style="min-width: 220px;">Momento</th>
                    <th style="min-width: 140px;">Data do momento</th>
                    <th style="min-width: 150px;">Inscrição no momento</th>
                    <th style="min-width: 130px;">Status de presença</th>
                    <th style="min-width: 100px;">Ouvinte</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($participacoes as $participacao)
                    @php
                      $evento = $participacao['evento'];
                      $atividade = $participacao['atividade'];
                      $inscricao = $participacao['inscricao'];
                      $presenca = $participacao['presenca'];
                      $statusPresenca = $presenca?->status;
                      $statusLabel = match ($statusPresenca) {
                        'presente' => 'Presente',
                        'justificado' => 'Justificado',
                        'ausente' => 'Ausente',
                        default => 'Sem presença',
                      };
                      $momentoData = $atividade?->dia
                        ? \Illuminate\Support\Carbon::parse($atividade->dia)->format('d/m/Y')
                        : null;
                      $horaInicio = $atividade?->hora_inicio
                        ? \Illuminate\Support\Carbon::parse($atividade->hora_inicio)->format('H:i')
                        : null;
                      $horaFim = $atividade?->hora_fim
                        ? \Illuminate\Support\Carbon::parse($atividade->hora_fim)->format('H:i')
                        : null;
                    @endphp
                    <tr>
                      <td>{{ $evento->nome ?? '-' }}</td>
                      <td>{{ $atividade->descricao ?? '-' }}</td>
                      <td>
                        {{ $momentoData ?? '-' }}
                        @if($horaInicio || $horaFim)
                          <div class="text-muted small">{{ $horaInicio ?? '--:--' }} às {{ $horaFim ?? '--:--' }}</div>
                        @endif
                      </td>
                      <td>{{ $inscricao->created_at ? $inscricao->created_at->format('d/m/Y H:i') : '-' }}</td>
                      <td>
                        <span class="badge {{ $statusPresenca === 'presente' ? 'bg-success' : ($statusPresenca === 'justificado' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                          {{ $statusLabel }}
                        </span>
                      </td>
                      <td>
                        @if($inscricao->ouvinte)
                          <span class="badge bg-info">Sim</span>
                        @else
                          <span class="badge bg-light text-dark border">Não</span>
                        @endif
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="6"><x-ui.empty-state compact muted icon="inbox" title="Nenhuma participação encontrada para este usuário." /></td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
          </div>
        </div>
      </div>
    </div>
  @endforeach
  @endhasanyrole

  <input type="hidden" name="select_all_pages" id="select_all_pages_hidden" form="form-emitir-certificados" value="">

  {{-- Modal seleção de modelo --}}
  <div class="modal fade" id="modalModeloCertificado" tabindex="-1" aria-labelledby="modalModeloCertificadoLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <x-ui.modal-header title-id="modalModeloCertificadoLabel" title="Emitir certificados" />
        <div class="modal-body">
          <div class="mb-3">
            <label for="select-modelo" class="form-label">Modelo de certificado</label>
            <select id="select-modelo" class="form-select">
              <option value="" selected disabled>Selecione um modelo</option>
              @foreach ($modelosCertificado as $modelo)
                <option value="{{ $modelo->id }}">{{ $modelo->nome }}</option>
              @endforeach
            </select>
            <small class="text-muted d-block mt-2">Um certificado será gerado por evento, somando as horas das presenças pendentes do participante.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-engaja" id="btn-confirmar-emissao">Confirmar emissão</button>
        </div>
      </div>
    </div>
  </div>

  {{-- Modal de Exportação com Filtros --}}
  <div class="modal fade" id="modalExportarUsuarios" tabindex="-1" aria-labelledby="modalExportarUsuariosLabel" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <x-ui.modal-header title-id="modalExportarUsuariosLabel" title="Baixar Usuários Cadastrados" />
              <form action="{{ route('usuarios.export') }}" method="GET">
                  <div class="modal-body">
                      <p class="text-muted small mb-3">Selecione os filtros abaixo para baixar uma listagem específica ou deixe em branco para baixar toda a base de usuários.</p>

                      <div class="mb-3">
                          <label class="form-label">Região</label>
                          <select name="regiao" id="export_regiao" class="form-select text-muted">
                              <option value="">Todas as Regiões</option>
                              @foreach($regioes as $regiao)
                                  <option value="{{ $regiao->id }}">{{ $regiao->nome }}</option>
                              @endforeach
                          </select>
                      </div>

                      <div class="mb-3">
                          <label class="form-label">Estado</label>
                          <select name="estado" id="export_estado" class="form-select text-muted" disabled>
                              <option value="">Todos os Estados</option>
                              @foreach($estados as $estado)
                                  <option value="{{ $estado->id }}" data-regiao="{{ $estado->regiao_id }}">{{ $estado->nome }}</option>
                              @endforeach
                          </select>
                      </div>

                      <div class="mb-3">
                          <label class="form-label">Município</label>
                          <select name="municipio" id="export_municipio" class="form-select text-muted" disabled>
                              <option value="">Todos os Municípios</option>
                              @foreach($municipios as $municipio)
                                  <option value="{{ $municipio->id }}" data-estado="{{ $municipio->estado_id }}">{{ $municipio->nome }}</option>
                              @endforeach
                          </select>
                      </div>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" name="formato" value="docx" class="btn btn-outline-primary"><i class="bi bi-file-earmark-word"></i> Word</button>
                      <button type="submit" class="btn btn-engaja">Baixar Planilha</button>
                  </div>
              </form>
          </div>
      </div>
  </div>

  @role('administrador')
  <div class="modal fade" id="modalRedefinirSenha" tabindex="-1" aria-labelledby="modalRedefinirSenhaLabel" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <x-ui.modal-header title-id="modalRedefinirSenhaLabel" title="Redefinir senha" />
              <form method="POST" id="form-redefinir-senha" action="{{ session('reset_password_action', '') }}">
                  @csrf
                  <div class="modal-body">
                      <p class="text-muted small mb-3">
                          A senha será alterada agora. No próximo acesso, o usuário será obrigado a definir uma nova senha.
                      </p>
                      <p class="fw-semibold mb-3" id="reset-password-user-name">{{ session('reset_password_user_name') }}</p>

                      <div class="mb-3">
                          <label for="reset_password" class="form-label">Nova senha</label>
                          <input id="reset_password"
                                 type="password"
                                 name="password"
                                 class="form-control @error('password', 'resetPassword') is-invalid @enderror"
                                 required
                                 autocomplete="new-password">
                          @error('password', 'resetPassword')
                            <div class="invalid-feedback">{{ $message }}</div>
                          @enderror
                      </div>

                      <div class="mb-3">
                          <label for="reset_password_confirmation" class="form-label">Confirmar nova senha</label>
                          <input id="reset_password_confirmation"
                                 type="password"
                                 name="password_confirmation"
                                 class="form-control"
                                 required
                                 autocomplete="new-password">
                      </div>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-engaja">Redefinir senha</button>
                  </div>
              </form>
          </div>
      </div>
  </div>
  @endrole

  @hasanyrole('administrador|gerente')
  <div class="modal fade" id="modalExcluirUsuario" tabindex="-1" aria-labelledby="modalExcluirUsuarioLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
              <x-ui.modal-header title-id="modalExcluirUsuarioLabel" title="Deletar usuário" />
              <form method="POST" id="form-excluir-usuario" action="">
                  @csrf
                  @method('DELETE')
                  <div class="modal-body">
                      <p class="mb-2">
                          Você tem ciência de que está deletando este usuário e todos os dados vinculados a ele?
                      </p>
                      <p class="fw-semibold mb-3" id="delete-user-name"></p>
                      <div class="alert alert-warning mb-0" role="alert">
                          O usuário, suas inscrições, presenças e certificados serão desativados.
                      </div>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-engaja">Confirmar exclusão</button>
                  </div>
              </form>
          </div>
      </div>
  </div>
  @endhasanyrole
@endif
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const gridEl = document.getElementById('grid-usuarios');
    const hiddenInputs = document.getElementById('participantes-hidden-inputs');
    const btnOpen = document.getElementById('btn-open-modal');
    const btnConfirm = document.getElementById('btn-confirmar-emissao');
    const selectModelo = document.getElementById('select-modelo');
    const modeloHidden = document.getElementById('modelo_id_hidden');
    const form = document.getElementById('form-emitir-certificados');
    const resetPasswordForm = document.getElementById('form-redefinir-senha');
    const resetPasswordUserName = document.getElementById('reset-password-user-name');
    const deleteUserForm = document.getElementById('form-excluir-usuario');
    const deleteUserName = document.getElementById('delete-user-name');

    gridEl?.addEventListener('datatable:selection-changed', (event) => {
      hiddenInputs.innerHTML = '';
      event.detail.rows.forEach((row) => {
        if (!row.participante_id) return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'participantes[]';
        input.value = row.participante_id;
        hiddenInputs.appendChild(input);
      });
    });

    if (btnOpen) {
      btnOpen.addEventListener('click', () => {
        const selecionados = gridEl?._agGridApi?.getSelectedRows() || [];
        if (selecionados.length === 0) {
          alert('Selecione ao menos um participante.');
          return;
        }
        const modal = new bootstrap.Modal(document.getElementById('modalModeloCertificado'));
        modal.show();
      });
    }

    if (btnConfirm && selectModelo && modeloHidden && form) {
      btnConfirm.addEventListener('click', () => {
        const modeloId = selectModelo.value;
        if (!modeloId) {
          alert('Selecione um modelo de certificado.');
          return;
        }
        modeloHidden.value = modeloId;
        form.submit();
      });
    }

    // Delegação de eventos: as ações são renderizadas pelo AG Grid de forma
    // assíncrona, depois do DOMContentLoaded.
    document.addEventListener('click', (event) => {
      const resetPasswordButton = event.target.closest('.js-reset-password');
      if (resetPasswordButton) {
        if (resetPasswordForm) {
          resetPasswordForm.action = resetPasswordButton.dataset.action || '';
          resetPasswordForm.reset();
        }
        if (resetPasswordUserName) {
          resetPasswordUserName.textContent = resetPasswordButton.dataset.userName || '';
        }
      }

      const deleteUserButton = event.target.closest('.js-delete-user');
      if (deleteUserButton) {
        if (deleteUserForm) {
          deleteUserForm.action = deleteUserButton.dataset.action || '';
        }
        if (deleteUserName) {
          deleteUserName.textContent = deleteUserButton.dataset.userName || '';
        }
      }
    });

    @if($errors->resetPassword->any() && session('reset_password_action'))
      const resetPasswordModal = document.getElementById('modalRedefinirSenha');
      if (resetPasswordModal) {
        new bootstrap.Modal(resetPasswordModal).show();
      }
    @endif
  });


  //função que uso para gerenciar as seleções e hierarquia do filtro de regiao/estado/municipio
  const regiaoSelect = document.getElementById('filtro_regiao');
  const estadoSelect = document.getElementById('filtro_estado');
  const municipioSelect = document.getElementById('filtro_municipio');

  function filterOptions(parentSelect, childSelect, dataAttribute) {
      const parentId = parentSelect.value;
      Array.from(childSelect.options).forEach(option => {
          if (option.value === "") return;

          if (option.getAttribute(dataAttribute) === parentId) {
              option.style.display = '';
          } else {
              option.style.display = 'none';
          }
      });
  }

  if (regiaoSelect && estadoSelect && municipioSelect) {

      regiaoSelect.addEventListener('change', function() {
          estadoSelect.value = '';
          municipioSelect.value = '';

          municipioSelect.disabled = true;

          if (this.value) {
              estadoSelect.disabled = false;
              filterOptions(this, estadoSelect, 'data-regiao');
          } else {
              estadoSelect.disabled = true;
          }
      });

      estadoSelect.addEventListener('change', function() {
          municipioSelect.value = '';

          if (this.value) {
              municipioSelect.disabled = false;
              filterOptions(this, municipioSelect, 'data-estado');
          } else {
              municipioSelect.disabled = true;
          }
      });

      if (regiaoSelect.value) {
          estadoSelect.disabled = false;
          filterOptions(regiaoSelect, estadoSelect, 'data-regiao');
      } else {
          estadoSelect.disabled = true;
      }

      if (estadoSelect.value) {
          municipioSelect.disabled = false;
          filterOptions(estadoSelect, municipioSelect, 'data-estado');
      } else {
          municipioSelect.disabled = true;
      }
  }

  //logica para exportação de usuários usando filtragem
  const expRegiaoSelect = document.getElementById('export_regiao');
  const expEstadoSelect = document.getElementById('export_estado');
  const expMunicipioSelect = document.getElementById('export_municipio');

  if (expRegiaoSelect && expEstadoSelect && expMunicipioSelect) {
      expRegiaoSelect.addEventListener('change', function() {
          expEstadoSelect.value = '';
          expMunicipioSelect.value = '';
          expMunicipioSelect.disabled = true;

          if (this.value) {
              expEstadoSelect.disabled = false;
              filterOptions(this, expEstadoSelect, 'data-regiao');
          } else {
              expEstadoSelect.disabled = true;
          }
      });

      expEstadoSelect.addEventListener('change', function() {
          expMunicipioSelect.value = '';
          if (this.value) {
              expMunicipioSelect.disabled = false;
              filterOptions(this, expMunicipioSelect, 'data-estado');
          } else {
              expMunicipioSelect.disabled = true;
          }
      });
  }
</script>
@endpush
