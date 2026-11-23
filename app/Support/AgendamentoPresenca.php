<?php

namespace App\Support;

use App\Models\Atividade;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Helpers do agendamento de abertura/fechamento da confirmação de presença.
 *
 * Os horários são digitados/exibidos no fuso de Brasília e armazenados em UTC
 * (timezone da aplicação).
 */
class AgendamentoPresenca
{
    public const FUSO = 'America/Sao_Paulo';

    /** Formato usado por <input type="datetime-local">. */
    public const FORMATO_INPUT = 'Y-m-d\TH:i';

    public const MENSAGEM_SEM_HORARIO = 'Não é possível salvar: informe ao menos um horário em "Abrir presença em" ou "Fechar presença em", pois a opção "Ativar agendamento de presença" está marcada.';

    /**
     * Regras de validação dos campos de agendamento.
     *
     * @param  bool  $fechamentoNoFuturo  exige que o fechamento seja posterior ao momento atual
     * @param  bool  $exigirAoMenosUm  exige abertura ou fechamento preenchido quando o agendamento está ativo
     * @return array<string, array<int, mixed>>
     */
    public static function regras(bool $fechamentoNoFuturo = false, bool $exigirAoMenosUm = false): array
    {
        $abre = ['nullable', 'date_format:'.self::FORMATO_INPUT];
        if ($exigirAoMenosUm) {
            array_unshift($abre, 'required_without:presenca_fecha_em');
        }

        $fecha = ['nullable', 'date_format:'.self::FORMATO_INPUT, 'after:presenca_abre_em'];

        if ($fechamentoNoFuturo) {
            $fecha[] = function (string $attribute, mixed $value, \Closure $fail) {
                $data = is_string($value) ? self::paraUtc($value) : null;
                if ($data && $data->lte(now())) {
                    $fail('O horário de fechamento deve estar no futuro.');
                }
            };
        }

        return [
            'presenca_abre_em' => $abre,
            'presenca_fecha_em' => $fecha,
        ];
    }

    /** @return array<string, string> */
    public static function mensagens(): array
    {
        return [
            'presenca_abre_em.required_without' => self::MENSAGEM_SEM_HORARIO,
            'presenca_abre_em.date_format' => 'Informe uma data e hora válidas para a abertura.',
            'presenca_fecha_em.date_format' => 'Informe uma data e hora válidas para o fechamento.',
            'presenca_fecha_em.after' => 'O fechamento deve ser posterior à abertura.',
        ];
    }

    /** Converte o valor do input (horário de Brasília) para Carbon em UTC. Retorna null se vazio/inválido. */
    public static function paraUtc(?string $valor): ?Carbon
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat(self::FORMATO_INPUT, $valor, self::FUSO)
                ->seconds(0)
                ->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Menor valor aceito pelos inputs datetime-local (agora, no horário de Brasília). */
    public static function minimoInput(): string
    {
        return now()->timezone(self::FUSO)->format(self::FORMATO_INPUT);
    }

    /** Converte um instante (UTC) para o valor do input no horário de Brasília. */
    public static function paraInput(?CarbonInterface $data): ?string
    {
        return $data?->copy()->timezone(self::FUSO)->format(self::FORMATO_INPUT);
    }

    /** Formata um instante para exibição (ex.: "06/10/2026 às 08:00"). */
    public static function formatar(?CarbonInterface $data): ?string
    {
        return $data?->copy()->timezone(self::FUSO)->format('d/m/Y \à\s H:i');
    }

    /**
     * Sugestão de abertura/fechamento a partir do dia e horário do momento
     * (valores no formato do input). Cada valor é null quando não for possível montar
     * ou quando o horário já passou.
     *
     * @return array{abre: ?string, fecha: ?string}
     */
    public static function sugestao(Atividade $atividade): array
    {
        $montar = function (?string $hora) use ($atividade): ?string {
            if (! $atividade->dia || ! $hora) {
                return null;
            }

            try {
                $data = Carbon::parse(substr((string) $atividade->dia, 0, 10).' '.substr($hora, 0, 5), self::FUSO);

                // Horário que já passou não serve de sugestão: o fechamento precisa estar no futuro.
                return $data->lte(now()) ? null : $data->format(self::FORMATO_INPUT);
            } catch (\Throwable) {
                return null;
            }
        };

        return [
            'abre' => $montar($atividade->hora_inicio),
            'fecha' => $montar($atividade->hora_fim),
        ];
    }
}
