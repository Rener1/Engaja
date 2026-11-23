<?php

namespace App\Exports;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Regiao;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class UsersExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $regiaoId;
    protected $estadoId;
    protected $municipioId;

    public function __construct($regiaoId = null, $estadoId = null, $municipioId = null)
    {
        $this->regiaoId = $regiaoId;
        $this->estadoId = $estadoId;
        $this->municipioId = $municipioId;
    }

    //aqui retorna a colecao de usuarios p fzer a exportação
    public function collection()
    {
        return User::with('participante.municipio.estado.regiao')
            ->whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['administrador', 'gestor']))
            ->when($this->municipioId, function ($q) {
                $q->whereHas('participante', fn($sub) => $sub->where('municipio_id', $this->municipioId));
            })
            ->when($this->estadoId && !$this->municipioId, function ($q) {
                $q->whereHas('participante.municipio', fn($sub) => $sub->where('estado_id', $this->estadoId));
            })
            ->when($this->regiaoId && !$this->estadoId && !$this->municipioId, function ($q) {
                $q->whereHas('participante.municipio.estado', fn($sub) => $sub->where('regiao_id', $this->regiaoId));
            })
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Nome',
            'Email',
            'CPF',
            'Telefone',
            'Município',
            'Tipo de organização',
            'Organização',
            'Vínculo',
            'Autorização de Imagem (legado global)'
        ];
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            $user->participante->cpf ?? null,
            $user->participante->telefone ?? null,
            $user->participante->municipio->nome ?? null,
            $user->participante->tipo_organizacao ?? null,
            $user->participante->escola_unidade ?? null,
            $user->participante->tag ?? null,
            $user->participante->autorizacao_imagem ? "Autorizado" : "Não Autorizado",
        ];
    }
}
