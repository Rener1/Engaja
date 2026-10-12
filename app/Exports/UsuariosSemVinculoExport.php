<?php

namespace App\Exports;

use App\Models\User;
use App\Services\UsuariosSemVinculoService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsuariosSemVinculoExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private User $viewer) {}

    public function collection(): Collection
    {
        return app(UsuariosSemVinculoService::class)
            ->query($this->viewer)
            ->get();
    }

    public function headings(): array
    {
        return [
            'Nome',
            'Email',
            'CPF',
            'Telefone',
            'Sexo',
            'Idade',
            'Município',
            'Instituição',
            'Tipo de instituição',
            'Vínculo no projeto',
        ];
    }

    public function map($user): array
    {
        $idade = $user->participante?->idade;

        return [
            $user->name,
            $user->email,
            $user->participante?->cpf,
            $user->participante?->telefone,
            $user->participante?->sexo,
            $idade !== null ? "{$idade} anos" : null,
            $this->formatMunicipioEstado(
                $user->participante?->municipio?->nome,
                $user->participante?->municipio?->estado?->sigla
            ),
            $user->participante?->escola_unidade,
            $user->participante?->tipo_organizacao,
            $user->participante?->tag,
        ];
    }

    private function formatMunicipioEstado(?string $municipio, ?string $estado): ?string
    {
        if (! $municipio && ! $estado) {
            return null;
        }

        if ($municipio && $estado) {
            return $municipio.' - '.$estado;
        }

        return $municipio ?: $estado;
    }
}
