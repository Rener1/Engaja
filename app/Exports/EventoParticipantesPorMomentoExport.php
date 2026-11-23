<?php

namespace App\Exports;

use App\Models\Evento;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EventoParticipantesPorMomentoExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    private Evento $evento;
    private bool $semOuvintes;

    public function __construct(Evento $evento, bool $semOuvintes = false)
    {
        $this->evento = $evento;
        $this->semOuvintes = $semOuvintes;
    }

    public function collection(): Collection
    {
        return $this->baseQuery()
            ->orderBy('atividades.dia')
            ->orderBy('atividades.hora_inicio')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Ação pedagógica',
            'Momento',
            'Data do momento',
            'ID do usuário',
            'Nome',
            'Email',
            'Data de cadastro',
            'CPF',
            'Telefone',
            'Município',
            'Organização',
            'Tipo de organização',
            'Tag',
            'Status',
        ];
    }

    public function map($row): array
    {
        return [
            $this->evento->nome,
            $row->momento,
            $row->data_momento ? Carbon::parse($row->data_momento)->format('d/m/Y') : null,
            $row->usuario_id,
            $row->nome,
            $row->email,
            $row->data_cadastro ? Carbon::parse($row->data_cadastro)->format('d/m/y') : null,
            $row->cpf,
            $row->telefone,
            $this->formatMunicipioEstado($row->municipio, $row->estado),
            $row->escola_unidade,
            $row->tipo_organizacao,
            $row->tag,
            $row->ouvinte ? 'Ouvinte' : 'Presente',
        ];
    }

    private function baseQuery()
    {
        return DB::table('presencas')
            ->join('inscricaos', 'inscricaos.id', '=', 'presencas.inscricao_id')
            ->join('atividades', 'atividades.id', '=', 'presencas.atividade_id')
            ->join('participantes', 'participantes.id', '=', 'inscricaos.participante_id')
            ->leftJoin('users', 'users.id', '=', 'participantes.user_id')
            ->leftJoin('municipios', 'municipios.id', '=', 'participantes.municipio_id')
            ->leftJoin('estados', 'estados.id', '=', 'municipios.estado_id')
            ->where('atividades.evento_id', $this->evento->id)
            ->where('presencas.status', 'presente')
            ->whereNull('presencas.deleted_at')
            ->whereNull('inscricaos.deleted_at')
            ->when($this->semOuvintes, fn ($q) => $q->where('inscricaos.ouvinte', false))
            ->select([
                'atividades.id as atividade_id',
                'atividades.descricao as momento',
                'atividades.dia as data_momento',
                'users.id as usuario_id',
                'users.name as nome',
                'users.email',
                'users.created_at as data_cadastro',
                'participantes.cpf',
                'participantes.telefone',
                'municipios.nome as municipio',
                'estados.sigla as estado',
                'participantes.escola_unidade',
                'participantes.tipo_organizacao',
                'participantes.tag',
                'inscricaos.ouvinte',
            ]);
    }

    private function formatMunicipioEstado(?string $municipio, ?string $estado): ?string
    {
        if (!$municipio && !$estado) {
            return null;
        }

        if ($municipio && $estado) {
            return $municipio . ' - ' . $estado;
        }

        return $municipio ?: $estado;
    }
}
