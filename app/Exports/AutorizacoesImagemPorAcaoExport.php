<?php

namespace App\Exports;

use App\Enums\OrigemAutorizacaoImagem;
use App\Enums\SituacaoAutorizacaoImagem;
use App\Models\Evento;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class AutorizacoesImagemPorAcaoExport extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(private readonly Evento $evento, private readonly Builder $participantes) {}

    public function query(): Builder
    {
        return (clone $this->participantes)->with(['user', 'autorizacoesImagem' => fn ($q) => $q->where('evento_id', $this->evento->id)->with('registradoPor')])->orderBy('id');
    }

    public function headings(): array
    {
        return ['Ação ID', 'Ação pedagógica', 'Participante ID', 'Nome', 'E-mail', 'CPF', 'Situação', 'Respondido em', 'Origem', 'Registrado por'];
    }

    public function map($participante): array
    {
        $resposta = $participante->autorizacoesImagem->first();

        return [
            (string) $this->evento->id, $this->evento->nome, (string) $participante->id,
            $participante->user->name, $participante->user->email, $participante->cpf,
            SituacaoAutorizacaoImagem::daResposta($resposta)->rotulo(),
            $resposta?->respondido_em?->format('d/m/Y H:i:s'),
            $resposta ? OrigemAutorizacaoImagem::from($resposta->origem)->rotulo() : '',
            $resposta?->registradoPor?->name ?? '',
        ];
    }
}
