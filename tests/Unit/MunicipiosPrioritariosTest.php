<?php

namespace Tests\Unit;

use App\Models\Estado;
use App\Models\Municipio;
use Tests\TestCase;

class MunicipiosPrioritariosTest extends TestCase
{
    public function test_lista_prioritaria_corresponde_a_municipios_da_base_ibge(): void
    {
        $lista = config('municipios_prioritarios');
        $ibge = collect(json_decode(file_get_contents(database_path('data/ibge_municipios.json')), true));

        $this->assertSame(77, collect($lista)->sum(fn ($nomes) => count($nomes)));

        foreach ($lista as $uf => $nomes) {
            foreach ($nomes as $nome) {
                $this->assertTrue($ibge->contains(fn ($item) => $item['uf'] === $uf && $item['nome'] === $nome), "$nome - $uf não está na base IBGE");
                $municipio = new Municipio(['nome' => $nome]);
                $municipio->setRelation('estado', new Estado(['sigla' => $uf]));
                $this->assertTrue($municipio->isPrioritario());
            }
        }
    }

    public function test_prioridade_depende_do_municipio_e_da_uf(): void
    {
        $municipio = new Municipio(['nome' => 'Conde']);
        $municipio->setRelation('estado', new Estado(['sigla' => 'BA']));
        $this->assertTrue($municipio->isPrioritario());

        $municipio->setRelation('estado', new Estado(['sigla' => 'PB']));
        $this->assertFalse($municipio->isPrioritario());

        $municipio->setRelation('estado', null);
        $this->assertFalse($municipio->isPrioritario());
    }
}
