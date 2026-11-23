<?php

namespace Tests\Feature\Cartas;

use App\Models\Estado;
use App\Models\Evento;
use App\Models\Municipio;
use App\Models\Regiao;

class MunicipiosPrioritariosTest extends CartasBaseTest
{
    public function test_municipio_is_prioritario_identifica_municipios_por_nome_e_uf(): void
    {
        $regiaoNorte = Regiao::firstOrCreate(['nome' => 'Norte']);
        $regiaoNordesteI = Regiao::firstOrCreate(['nome' => 'Nordeste I']);
        $regiaoNordesteII = Regiao::firstOrCreate(['nome' => 'Nordeste II']);
        $regiaoOutras = Regiao::firstOrCreate(['nome' => 'Outras']);

        $estadoNorte = Estado::factory()->create(['sigla' => 'AM', 'regiao_id' => $regiaoNorte->id]);
        $estadoNordesteI = Estado::factory()->create(['sigla' => 'CE', 'regiao_id' => $regiaoNordesteI->id]);
        $estadoNordesteII = Estado::factory()->create(['sigla' => 'BA', 'regiao_id' => $regiaoNordesteII->id]);
        $estadoOutras = Estado::factory()->create(['sigla' => 'MA', 'regiao_id' => $regiaoOutras->id]);

        $munNorte = Municipio::factory()->create(['nome' => 'Manaus', 'estado_id' => $estadoNorte->id]);
        $munNordesteI = Municipio::factory()->create(['nome' => 'Fortaleza', 'estado_id' => $estadoNordesteI->id]);
        $munNordesteII = Municipio::factory()->create(['nome' => 'Araçás', 'estado_id' => $estadoNordesteII->id]);
        $munOutras = Municipio::factory()->create(['nome' => 'São Luís', 'estado_id' => $estadoOutras->id]);
        $naoPrioritario = Municipio::factory()->create(['nome' => 'Sobral', 'estado_id' => $estadoNordesteI->id]);

        $this->assertTrue($munNorte->isPrioritario());
        $this->assertTrue($munNordesteI->isPrioritario());
        $this->assertTrue($munNordesteII->isPrioritario());
        $this->assertTrue($munOutras->isPrioritario());
        $this->assertFalse($naoPrioritario->isPrioritario());
    }

    public function test_gestor_dashboard_displays_priority_badge_for_prioritario_municipality(): void
    {
        $this->withoutExceptionHandling();

        Evento::where('id', '!=', $this->eventoCartas->id)->update(['is_cartas' => false]);

        $regiaoNorte = Regiao::firstOrCreate(['nome' => 'Norte']);
        $estadoNorte = Estado::factory()->create(['sigla' => 'AM', 'regiao_id' => $regiaoNorte->id]);
        $munPrioritario = Municipio::factory()->create(['nome' => 'Manaus', 'estado_id' => $estadoNorte->id]);

        $this->educando->update(['municipio_id' => $munPrioritario->id]);
        $this->educando->refresh();

        $carta = $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->gestor)
            ->get(route('cartas.dashboard'));
        
        $response->assertOk();
        $response->assertSee('★ Prioritário');

        $responseShow = $this->actingAs($this->gestor)
            ->get(route('cartas.cartas.show', $carta));

        $responseShow->assertOk();
        $responseShow->assertSee('★ Prioritário');
    }
}
