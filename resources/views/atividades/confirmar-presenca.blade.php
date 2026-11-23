@extends('layouts.app')

@section('content')

  <x-header-atividade hero :atividade="$atividade" class="mb-0" />

  <div class="py-4">
    <div class="row justify-content-center">
      <div class="col-12">
        <div class="card ev-card">
          <div class="card-body">
            {{-- Erro genérico / retorno de bloqueio --}}
            @if (session('error') && !session('demograficos_pendentes'))
              <div class="alert alert-danger" role="alert">
                {{ session('error') }}
              </div>
            @endif

            @php
              $estaAberta = isset($presencaAberta) ? $presencaAberta : $atividade->presencaEstaAberta();
              $proximoAgendamento = $atividade->proximoAgendamentoPresenca();
            @endphp

            @if(!$estaAberta)
              <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-lock-fill flex-shrink-0" viewBox="0 0 16 16">
                  <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                </svg>
                <div>
                  <strong>Confirmação de presença encerrada</strong><br>
                  A confirmação de presença para este momento não está aberta.
                  @if($proximoAgendamento && $proximoAgendamento['tipo'] === 'abre')
                    <div class="mt-1 small">Abertura prevista para: <strong>{{ \App\Support\AgendamentoPresenca::formatar($proximoAgendamento['em']) }}</strong>.</div>
                  @endif
                </div>
              </div>
            @else
              <h2 class="h5 fw-bold mb-3">Olá, seja bem vindo(a)!</h2>
              <p class="mb-3">Para confirmar a sua presença nesta atividade e/ou responder a avaliação, preencha o campo com
                o seu e-mail, CPF ou
                telefone e clique no botão.<br /></p>

              {{-- Formulário principal de busca --}}
              <form method="POST" action="{{ route('presenca.store', $atividade) }}" id="form-busca-presenca">
                @csrf
                <div class="mb-3">
                  <label for="campo" class="form-label">E-mail, CPF ou telefone</label>
                  <input type="text"
                         class="form-control @error('campo') is-invalid @enderror"
                         id="campo"
                         name="campo"
                         value="{{ old('campo', session('demograficos_campo_input')) }}"
                         required>
                  @error('campo')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <div id="autorizacao-presenca" class="mb-3" hidden>
                  <fieldset>
                    <legend class="fs-6"><strong>Você autoriza o uso de imagem, voz e nome nesta ação?</strong> </legend>
                    <button type="button" class="btn btn-link p-0 mb-2" data-bs-toggle="modal" data-bs-target="#termoAutorizacaoImagemModal">Ler o termo de autorização</button>
                    <div class="form-check mb-2">
                      <input class="form-check-input" type="radio" name="autorizado" value="1" id="autorizacaoPresencaSim" disabled @checked(in_array(old('autorizado'), [true, 1, '1'], true))>
                      <label class="form-check-label" for="autorizacaoPresencaSim">Sim, autorizo.</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="autorizado" value="0" id="autorizacaoPresencaNao" disabled @checked(in_array(old('autorizado'), [false, 0, '0'], true))>
                      <label class="form-check-label" for="autorizacaoPresencaNao">Não autorizo.</label>
                    </div>
                  </fieldset>
                </div>
                <p id="status-identificacao-presenca" class="small text-muted mb-2" role="status" aria-live="polite"></p>
                <button type="button" id="tentar-identificacao-presenca" class="btn btn-link p-0 mb-2" hidden>Tentar novamente</button>
                <div class="d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary" id="confirmar-presenca" disabled>Confirmar Presença / Realizar Avaliação</button>
                </div>
                <noscript><p class="small text-muted mt-2">Ative o JavaScript para verificar o cadastro e confirmar sua presença.</p></noscript>

                @if (session('show_register_button') && session('error'))
                  <a class="btn btn-outline-primary float-end mt-2"
                    href="{{ route('evento.cadastro_inscricao', ['evento_id' => $atividade->evento->id, 'atividade_id' => $atividade->id]) }}">
                    Cadastre-se
                  </a>
                @endif
              </form>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ================================================================
       MODAL: Dados Demográficos Pendentes
       Abre automaticamente quando o usuário é encontrado mas não tem
       os dados demográficos preenchidos.
  ================================================================ --}}
  <div class="modal fade"
       id="modalDemograficos"
       tabindex="-1"
       aria-labelledby="modalDemograficosLabel"
       aria-modal="true"
       role="dialog"
       data-bs-backdrop="static"
       data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">

        <x-ui.modal-header icon="clipboard-data" title="Dados demográficos pendentes" title-id="modalDemograficosLabel" :dismissible="false" />

        <div class="modal-body">
          {{-- Banner identificando o usuário --}}
          <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-person-fill flex-shrink-0" viewBox="0 0 16 16">
              <path d="M3 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
            </svg>
            <div>
              Preenchendo dados de: <strong>{{ session('demograficos_user_nome') }}</strong><br>
              <small class="text-muted">Para confirmar a presença deste participante, preencha as informações abaixo.</small>
            </div>
          </div>

          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form method="POST"
                action="{{ route('presenca.demograficos', $atividade) }}"
                id="form-demograficos-presenca">
            @csrf

            {{-- Token seguro do usuário (criptografado) --}}
            <input type="hidden"
                   name="user_token"
                   value="{{ session('demograficos_user_token', old('user_token')) }}">

            @php
              $escolhaImagem = old('autorizado', session('demograficos_autorizado'));
            @endphp
            @if(in_array($escolhaImagem, [true, false, 1, 0, '1', '0'], true))
              <input type="hidden" name="autorizado" value="{{ (int) $escolhaImagem }}">
            @endif

            {{-- 1. Identidade de Gênero --}}
            <div class="mb-3">
              <label for="identidade_genero_qr" class="form-label fw-semibold">
                Identidade de Gênero <span class="text-danger">*</span>
              </label>
              <select name="identidade_genero" id="identidade_genero_qr"
                      class="form-select @error('identidade_genero') is-invalid @enderror"
                      required onchange="toggleOutroQr(this, 'ig_outro_wrap_qr')">
                <option value="" disabled selected>Selecione...</option>
                <option value="Mulher Cisgênero"   {{ old('identidade_genero') == 'Mulher Cisgênero'   ? 'selected' : '' }}>Mulher Cisgênero</option>
                <option value="Mulher Transsexual" {{ old('identidade_genero') == 'Mulher Transsexual' ? 'selected' : '' }}>Mulher Transsexual</option>
                <option value="Homem Cisgênero"    {{ old('identidade_genero') == 'Homem Cisgênero'    ? 'selected' : '' }}>Homem Cisgênero</option>
                <option value="Homem Transsexual"  {{ old('identidade_genero') == 'Homem Transsexual'  ? 'selected' : '' }}>Homem Transsexual</option>
                <option value="Travesti"           {{ old('identidade_genero') == 'Travesti'           ? 'selected' : '' }}>Travesti</option>
                <option value="Não binárie"        {{ old('identidade_genero') == 'Não binárie'        ? 'selected' : '' }}>Não binárie</option>
                <option value="Prefiro não responder" {{ old('identidade_genero') == 'Prefiro não responder' ? 'selected' : '' }}>Prefiro não responder</option>
                <option value="Outro"              {{ old('identidade_genero') == 'Outro'              ? 'selected' : '' }}>Outro</option>
              </select>
              @error('identidade_genero')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <div id="ig_outro_wrap_qr" class="mt-2" style="display:none">
                <input type="text" name="identidade_genero_outro"
                       class="form-control @error('identidade_genero_outro') is-invalid @enderror"
                       placeholder="Especifique sua identidade de gênero"
                       value="{{ old('identidade_genero_outro') }}">
                @error('identidade_genero_outro')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

            {{-- 2. Raça / Cor --}}
            <div class="mb-3">
              <label for="raca_cor_qr" class="form-label fw-semibold">
                Raça / Cor <span class="text-danger">*</span>
              </label>
              <select name="raca_cor" id="raca_cor_qr"
                      class="form-select @error('raca_cor') is-invalid @enderror"
                      required>
                <option value="" disabled selected>Selecione...</option>
                <option value="Preta"                {{ old('raca_cor') == 'Preta'                ? 'selected' : '' }}>Preta</option>
                <option value="Parda"                {{ old('raca_cor') == 'Parda'                ? 'selected' : '' }}>Parda</option>
                <option value="Branca"               {{ old('raca_cor') == 'Branca'               ? 'selected' : '' }}>Branca</option>
                <option value="Amarela"              {{ old('raca_cor') == 'Amarela'              ? 'selected' : '' }}>Amarela</option>
                <option value="Indígena"             {{ old('raca_cor') == 'Indígena'             ? 'selected' : '' }}>Indígena</option>
                <option value="Prefere não declarar" {{ old('raca_cor') == 'Prefere não declarar' ? 'selected' : '' }}>Prefere não declarar</option>
              </select>
              @error('raca_cor')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            {{-- 3. Comunidade Tradicional --}}
            <div class="mb-3">
              <label for="comunidade_tradicional_qr" class="form-label fw-semibold">
                Pertencimento a Povos ou Comunidades Tradicionais <span class="text-danger">*</span>
              </label>
              <select name="comunidade_tradicional" id="comunidade_tradicional_qr"
                      class="form-select @error('comunidade_tradicional') is-invalid @enderror"
                      required onchange="toggleOutroQr(this, 'ct_outro_wrap_qr')">
                <option value="" disabled selected>Selecione...</option>
                <option value="Não"                    {{ old('comunidade_tradicional') == 'Não'                    ? 'selected' : '' }}>Não</option>
                <option value="Povos indígenas"        {{ old('comunidade_tradicional') == 'Povos indígenas'        ? 'selected' : '' }}>Povos indígenas</option>
                <option value="Comunidades Quilombolas" {{ old('comunidade_tradicional') == 'Comunidades Quilombolas' ? 'selected' : '' }}>Comunidades Quilombolas</option>
                <option value="Povos Ciganos"          {{ old('comunidade_tradicional') == 'Povos Ciganos'          ? 'selected' : '' }}>Povos Ciganos</option>
                <option value="Ribeirinhos"            {{ old('comunidade_tradicional') == 'Ribeirinhos'            ? 'selected' : '' }}>Ribeirinhos</option>
                <option value="Extrativistas"          {{ old('comunidade_tradicional') == 'Extrativistas'          ? 'selected' : '' }}>Extrativistas</option>
                <option value="Outro"                  {{ old('comunidade_tradicional') == 'Outro'                  ? 'selected' : '' }}>Outro</option>
              </select>
              @error('comunidade_tradicional')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <div id="ct_outro_wrap_qr" class="mt-2" style="display:none">
                <input type="text" name="comunidade_tradicional_outro"
                       class="form-control @error('comunidade_tradicional_outro') is-invalid @enderror"
                       placeholder="Especifique a comunidade tradicional"
                       value="{{ old('comunidade_tradicional_outro') }}">
                @error('comunidade_tradicional_outro')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

            {{-- 4. Faixa Etária --}}
            <div class="mb-3">
              <label for="faixa_etaria_qr" class="form-label fw-semibold">
                Faixa Etária <span class="text-danger">*</span>
              </label>
              <select name="faixa_etaria" id="faixa_etaria_qr"
                      class="form-select @error('faixa_etaria') is-invalid @enderror"
                      required>
                <option value="" disabled selected>Selecione...</option>
                <option value="Primeira infância (0 a 6 anos)"  {{ old('faixa_etaria') == 'Primeira infância (0 a 6 anos)'  ? 'selected' : '' }}>Primeira infância (0 a 6 anos)</option>
                <option value="Criança (7 a 11 anos)"           {{ old('faixa_etaria') == 'Criança (7 a 11 anos)'           ? 'selected' : '' }}>Criança (7 a 11 anos)</option>
                <option value="Adolescente (12 a 17 anos)"      {{ old('faixa_etaria') == 'Adolescente (12 a 17 anos)'      ? 'selected' : '' }}>Adolescente (12 a 17 anos)</option>
                <option value="Adulto (18 a 59 anos)"           {{ old('faixa_etaria') == 'Adulto (18 a 59 anos)'           ? 'selected' : '' }}>Adulto (18 a 59 anos)</option>
                <option value="Idoso (a partir dos 60 anos)"    {{ old('faixa_etaria') == 'Idoso (a partir dos 60 anos)'    ? 'selected' : '' }}>Idoso (a partir dos 60 anos)</option>
              </select>
              @error('faixa_etaria')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            {{-- 5. PcD --}}
            <div class="mb-3">
              <label for="pcd_qr" class="form-label fw-semibold">
                Pessoa com Deficiência (PcD) <span class="text-danger">*</span>
              </label>
              <select name="pcd" id="pcd_qr"
                      class="form-select @error('pcd') is-invalid @enderror"
                      required>
                <option value="" disabled selected>Selecione...</option>
                <option value="Não"         {{ old('pcd') == 'Não'         ? 'selected' : '' }}>Não</option>
                <option value="Física"      {{ old('pcd') == 'Física'      ? 'selected' : '' }}>Física</option>
                <option value="Auditiva"    {{ old('pcd') == 'Auditiva'    ? 'selected' : '' }}>Auditiva</option>
                <option value="Visual"      {{ old('pcd') == 'Visual'      ? 'selected' : '' }}>Visual</option>
                <option value="Intelectual" {{ old('pcd') == 'Intelectual' ? 'selected' : '' }}>Intelectual</option>
                <option value="Múltipla"    {{ old('pcd') == 'Múltipla'    ? 'selected' : '' }}>Múltipla</option>
              </select>
              @error('pcd')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            {{-- 6. Orientação Sexual --}}
            <div class="mb-3">
              <label for="orientacao_sexual_qr" class="form-label fw-semibold">
                Orientação Sexual <span class="text-danger">*</span>
              </label>
              <select name="orientacao_sexual" id="orientacao_sexual_qr"
                      class="form-select @error('orientacao_sexual') is-invalid @enderror"
                      required onchange="toggleOutroQr(this, 'os_outra_wrap_qr')">
                <option value="" disabled selected>Selecione...</option>
                <option value="Lésbica"              {{ old('orientacao_sexual') == 'Lésbica'              ? 'selected' : '' }}>Lésbica</option>
                <option value="Gay"                  {{ old('orientacao_sexual') == 'Gay'                  ? 'selected' : '' }}>Gay</option>
                <option value="Bissexual"            {{ old('orientacao_sexual') == 'Bissexual'            ? 'selected' : '' }}>Bissexual</option>
                <option value="Heterossexual"        {{ old('orientacao_sexual') == 'Heterossexual'        ? 'selected' : '' }}>Heterossexual</option>
                <option value="Prefere não declarar" {{ old('orientacao_sexual') == 'Prefere não declarar' ? 'selected' : '' }}>Prefere não declarar</option>
                <option value="Outra"                {{ old('orientacao_sexual') == 'Outra'                ? 'selected' : '' }}>Outra</option>
              </select>
              @error('orientacao_sexual')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <div id="os_outra_wrap_qr" class="mt-2" style="display:none">
                <input type="text" name="orientacao_sexual_outra"
                       class="form-control @error('orientacao_sexual_outra') is-invalid @enderror"
                       placeholder="Especifique sua orientação sexual"
                       value="{{ old('orientacao_sexual_outra') }}">
                @error('orientacao_sexual_outra')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

          </form>
        </div>

        <div class="modal-footer flex-column gap-2">
          <button type="submit" form="form-demograficos-presenca" class="btn btn-engaja w-100 fw-bold">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check2-circle me-1" viewBox="0 0 16 16">
              <path d="M2.5 8a5.5 5.5 0 0 1 8.25-4.764.5.5 0 0 0 .5-.866A6.5 6.5 0 1 0 14.5 8a.5.5 0 0 0-1 0 5.5 5.5 0 1 1-11 0"/>
              <path d="M15.354 3.354a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0z"/>
            </svg>
            Salvar dados e confirmar presença
          </button>
          <small class="text-muted text-center">
            Esses dados são utilizados apenas para fins estatísticos e de políticas públicas.
          </small>
        </div>

      </div>
    </div>
  </div>

  {{-- Modal de confirmação de presença bem-sucedida --}}
  <div class="modal fade" id="confirmacaoPresencaModal" tabindex="-1" aria-labelledby="confirmacaoPresencaModalLabel"
    aria-hidden="true">
    @php
      $avaliacao = \App\Models\Avaliacao::where('atividade_id', $atividade->id)->first();
    @endphp

    <div class="modal-dialog modal modal-dialog-centered mt-1">
        <div class="modal-content">
        <x-ui.modal-header :title="session('status_presenca_label', 'Presença')" title-id="confirmacaoPresencaModalLabel" />

        <div class="modal-body">
          <div class="text-center pt-2 pb-3">
            <p class="mb-0 mt-2">
              Parabéns, <strong>{{ session('usuario_nome') }}</strong>!<br>
              Você confirmou {{ session('artigo_status_presenca', 'sua presença') }} no momento
              <strong>{{ session('atividade_nome') }}</strong>, ação pedagógica
              <strong>{{ session('evento_nome') }}</strong>,
              realizada na <strong>{{ session('dia') }}</strong>.
            </p>
          </div>

          @php $avaliacaoDisponivel = session('avaliacao_token') && session('avaliacao_disponivel', true); @endphp
          @if(isset($avaliacao) && $avaliacaoDisponivel)
            <div class="text-center py-1">
              <p class="mb-0 mt-2">Para acessar e responder o formulário de avaliação, clique no botão abaixo.
              </p>
            </div>
          @endif
        </div>

        @if(isset($avaliacao) && $avaliacaoDisponivel)
          <div class="modal-footer justify-content-center">
            <a class="btn btn-outline-primary"
              href="{{ session('avaliacao_token') ? route('avaliacao.formulario', ['avaliacao' => $avaliacao, 'token' => session('avaliacao_token')]) : route('avaliacao.formulario', $avaliacao) }}">Formulário
              de Avaliação</a>
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- Modal de cadastro no sistema --}}
  <div class="modal fade" id="cadastroSistemaModal" tabindex="-1" aria-labelledby="cadastroSistemaModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal modal-dialog-centered mt-1">
      <div class="modal-content">
        <x-ui.modal-header title-id="confirmacaoPresencaModalLabel" title="Dados não encontrados no sistema!" />

        <div class="modal-body">
          <div class="text-center pt-2 pb-3">
            <p class="mb-0 mt-2">
              Seu registro não foi identificado no sistema.
            </p>
          </div>

          <div class="text-center py-1">
            <p class="mb-0 mt-2">Faça o seu cadastro clicando no botão abaixo para conseguir registrar a sua presença e avaliar a atividade. </p>
          </div>
        </div>


        <div class="modal-footer justify-content-center">
          <a class="btn btn-outline-primary float-end mt-2"
            href="{{ route('evento.cadastro_inscricao', ['evento_id' => $atividade->evento->id, 'atividade_id' => $atividade->id]) }}">
            Cadastre-se
          </a>
        </div>
      </div>
    </div>
  </div>

  @if($estaAberta)
    <div class="modal fade" id="termoAutorizacaoImagemModal" tabindex="-1" aria-labelledby="termoAutorizacaoImagemTitulo" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <x-ui.modal-header icon="file-earmark-text" title="Termo de autorização de uso de imagem, voz e nome" title-id="termoAutorizacaoImagemTitulo" />
          <div class="modal-body eg-autorizacao-termo-texto">{{ config('autorizacao_imagem.texto') }}</div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
        </div>
      </div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('form-busca-presenca');
        const campo = form.querySelector('[name="campo"]');
        const bloco = document.getElementById('autorizacao-presenca');
        const opcoes = Array.from(bloco.querySelectorAll('[name="autorizado"]'));
        const confirmar = document.getElementById('confirmar-presenca');
        const status = document.getElementById('status-identificacao-presenca');
        const tentar = document.getElementById('tentar-identificacao-presenca');
        let identificado = null;
        let pendente = false;
        let enviando = false;
        let consulta = 0;
        let controle;
        let atraso;

        const podeConfirmar = () => !enviando && identificado === campo.value.trim() &&
          identificado !== '' && (!pendente || opcoes.some(opcao => opcao.checked));
        const atualizarBotao = () => { confirmar.disabled = !podeConfirmar(); };
        const invalidar = () => {
          consulta++;
          controle?.abort();
          clearTimeout(atraso);
          identificado = null;
          pendente = false;
          bloco.hidden = true;
          tentar.hidden = true;
          status.textContent = '';
          opcoes.forEach(opcao => { opcao.checked = false; opcao.required = false; opcao.disabled = true; });
          atualizarBotao();
        };
        const verificar = async () => {
          const valor = campo.value.trim();
          if (!valor || identificado === valor) return;
          controle?.abort();
          controle = new AbortController();
          const atual = ++consulta;
          confirmar.disabled = true;
          tentar.hidden = true;
          status.textContent = 'Verificando cadastro…';
          try {
            const response = await fetch(@json(route('presenca.identificar', $atividade)), {
              method: 'POST', credentials: 'same-origin', signal: controle.signal,
              headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
              body: JSON.stringify({ campo: valor })
            });
            const data = await response.json().catch(() => ({}));
            if (atual !== consulta || campo.value.trim() !== valor) return;
            if (!response.ok || typeof data.autorizacao_pendente !== 'boolean') {
              throw new Error(data.errors?.campo?.[0] || data.message || 'Não foi possível verificar o cadastro. Tente novamente.');
            }
            identificado = valor;
            pendente = data.autorizacao_pendente;
            bloco.hidden = !pendente;
            opcoes.forEach(opcao => {
              opcao.disabled = !pendente;
              opcao.required = pendente;
              if (!pendente) opcao.checked = false;
            });
            status.textContent = '';
            atualizarBotao();
          } catch (error) {
            if (error.name === 'AbortError' || atual !== consulta) return;
            identificado = null;
            status.textContent = error.message || 'Não foi possível verificar o cadastro. Tente novamente.';
            tentar.hidden = false;
            atualizarBotao();
          }
        };

        campo.addEventListener('input', () => {
          invalidar();
          if (campo.value.trim()) atraso = setTimeout(verificar, 350);
        });
        campo.addEventListener('blur', () => { clearTimeout(atraso); verificar(); });
        tentar.addEventListener('click', verificar);
        opcoes.forEach(opcao => opcao.addEventListener('change', atualizarBotao));
        form.addEventListener('submit', event => {
          if (!podeConfirmar()) { event.preventDefault(); return; }
          enviando = true;
          atualizarBotao();
        });
        if (campo.value.trim()) verificar();
      });
    </script>
  @endif

  {{-- Scripts de controle dos modais --}}
  @if(session('success-presenca'))
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('confirmacaoPresencaModal');
        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
      });
    </script>
  @endif

  @if(session('show_register_button'))
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('cadastroSistemaModal');
        if (modalEl) {
          const modal = new bootstrap.Modal(modalEl);
          modal.show();
        }
      });
    </script>
  @endif

  @if(session('demograficos_pendentes'))
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('modalDemograficos');
        if (modalEl) {
          const modal = new bootstrap.Modal(modalEl);
          modal.show();
          restoreOutroFieldsQr();
        }
      });
    </script>
  @endif

  {{-- Também abre o modal se houve erro de validação no formulário demográfico --}}
  @if($errors->any() && old('user_token'))
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('modalDemograficos');
        if (modalEl) {
          const modal = new bootstrap.Modal(modalEl);
          modal.show();
          restoreOutroFieldsQr();
        }
      });
    </script>
  @endif

  <script>
    function toggleOutroQr(select, wrapId) {
      const wrap = document.getElementById(wrapId);
      if (!wrap) return;
      const mostrar = select.value === 'Outro' || select.value === 'Outra';
      wrap.style.display = mostrar ? 'block' : 'none';
      const input = wrap.querySelector('input');
      if (input) input.required = mostrar;
    }

    function restoreOutroFieldsQr() {
      [
        { selectId: 'identidade_genero_qr',      wrapId: 'ig_outro_wrap_qr' },
        { selectId: 'comunidade_tradicional_qr',  wrapId: 'ct_outro_wrap_qr' },
        { selectId: 'orientacao_sexual_qr',       wrapId: 'os_outra_wrap_qr' },
      ].forEach(({ selectId, wrapId }) => {
        const select = document.getElementById(selectId);
        if (select && (select.value === 'Outro' || select.value === 'Outra')) {
          const wrap = document.getElementById(wrapId);
          if (wrap) wrap.style.display = 'block';
        }
      });
    }
  </script>

@endsection
