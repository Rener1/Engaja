@props(['id', 'ativo' => false])

<div data-agendamento-presenca>
    <input type="hidden" name="agendamento_presenca_ativo" value="0">
    <div class="form-check mb-2">
        <input type="checkbox" class="form-check-input" name="agendamento_presenca_ativo"
               id="{{ $id }}" value="1" @checked($ativo) data-agendamento-toggle>
        <label class="form-check-label fw-semibold" for="{{ $id }}">Ativar agendamento de presença</label>
    </div>
    <div class="form-text mb-3">
        Marque para definir a abertura e/ou o fechamento automático (horário de Brasília).
        Desmarque e salve para limpar as datas e manter o controle manual da presença.
    </div>
    <fieldset @disabled(! $ativo) data-agendamento-campos>
        {{ $slot }}
        <div class="alert alert-danger py-2 small mt-3 mb-0 d-none" role="alert" data-agendamento-erro>
            {{ \App\Support\AgendamentoPresenca::MENSAGEM_SEM_HORARIO }}
        </div>
    </fieldset>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mensagemErro = @json(\App\Support\AgendamentoPresenca::MENSAGEM_SEM_HORARIO);

            document.querySelectorAll('[data-agendamento-presenca]').forEach(function (container) {
                const toggle = container.querySelector('[data-agendamento-toggle]');
                const campos = container.querySelector('[data-agendamento-campos]');
                const avisoErro = container.querySelector('[data-agendamento-erro]');
                const inputs = Array.from(campos.querySelectorAll('input[type="datetime-local"]'));
                const form = container.closest('form');

                function temHorarioPreenchido() {
                    return inputs.some(input => input.value.trim() !== '');
                }

                function validarAgendamento(mostrarAviso = false) {
                    const invalido = toggle.checked && !temHorarioPreenchido();

                    inputs.forEach((input, index) => {
                        input.setCustomValidity(invalido && index === 0 ? mensagemErro : '');
                        if (invalido && mostrarAviso) {
                            input.classList.add('is-invalid');
                            input.dataset.agendamentoInvalido = '1';
                        } else if (!invalido && input.dataset.agendamentoInvalido === '1') {
                            input.classList.remove('is-invalid');
                            delete input.dataset.agendamentoInvalido;
                        }
                    });

                    if (avisoErro) {
                        avisoErro.classList.toggle('d-none', !(invalido && mostrarAviso));
                    }

                    return !invalido;
                }

                function atualizar() {
                    campos.disabled = !toggle.checked;
                    if (!toggle.checked) {
                        inputs.forEach(input => { input.value = ''; });
                    }
                    validarAgendamento(false);
                }

                toggle.addEventListener('change', atualizar);
                inputs.forEach(input => {
                    input.addEventListener('input', () => validarAgendamento(false));
                    input.addEventListener('change', () => validarAgendamento(false));
                    input.addEventListener('invalid', () => {
                        if (toggle.checked && !temHorarioPreenchido()) {
                            validarAgendamento(true);
                        }
                    });
                });

                form?.addEventListener('submit', function (e) {
                    if (!validarAgendamento(true)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        inputs[0]?.reportValidity();
                    }
                }, true);

                atualizar();
            });
        });
    </script>
@endonce
