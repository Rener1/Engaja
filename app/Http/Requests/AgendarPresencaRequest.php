<?php

namespace App\Http\Requests;

use App\Support\AgendamentoPresenca;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class AgendarPresencaRequest extends FormRequest
{
    /** Bag separado para a view reabrir o modal de agendamento quando houver erro. */
    protected $errorBag = 'agendamentoPresenca';

    public function authorize(): bool
    {
        return $this->user()?->can('presenca.abrir') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $ativo = ! $this->exists('agendamento_presenca_ativo') || $this->boolean('agendamento_presenca_ativo');
        $regras = ['agendamento_presenca_ativo' => ['sometimes', 'boolean']];
        foreach (AgendamentoPresenca::regras(fechamentoNoFuturo: true, exigirAoMenosUm: $ativo) as $campo => $validacoes) {
            $regras[$campo] = ['exclude_if:agendamento_presenca_ativo,0', ...$validacoes];
        }

        return $regras;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return AgendamentoPresenca::mensagens();
    }

    /** @return array{presenca_abre_em: ?Carbon, presenca_fecha_em: ?Carbon} */
    public function horariosUtc(): array
    {
        if ($this->exists('agendamento_presenca_ativo') && ! $this->boolean('agendamento_presenca_ativo')) {
            return ['presenca_abre_em' => null, 'presenca_fecha_em' => null];
        }

        return [
            'presenca_abre_em' => AgendamentoPresenca::paraUtc($this->validated('presenca_abre_em')),
            'presenca_fecha_em' => AgendamentoPresenca::paraUtc($this->validated('presenca_fecha_em')),
        ];
    }
}
