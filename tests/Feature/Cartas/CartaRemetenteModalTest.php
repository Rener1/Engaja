<?php

namespace Tests\Feature\Cartas;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Regiao;

class CartaRemetenteModalTest extends CartasBaseTest
{
    private function prepararDadosDosRemetentes(): void
    {
        $this->remetente->update([
            'name' => 'Maria Educanda',
            'email' => 'maria.educanda@example.com',
            'identidade_genero' => 'Mulher Cisgênero',
            'faixa_etaria' => 'Adulto (18 a 59 anos)',
            'raca_cor' => 'Indígena',
            'pcd' => 'Auditiva',
            'orientacao_sexual' => 'Lésbica',
            'comunidade_tradicional' => 'Povos Ciganos',
        ]);

        $this->educando->update([
            'sexo' => 'Feminino',
            'data_nascimento' => now()->subYears(30)->subDay()->toDateString(),
        ]);

        $regiao = Regiao::create(['nome' => 'Nordeste']);
        $estado = Estado::create(['nome' => 'Pernambuco', 'sigla' => 'PE', 'regiao_id' => $regiao->id]);
        $municipio = Municipio::create([
            'nome' => 'Recife',
            'estado_id' => $estado->id,
            'regiao_id' => $regiao->id,
        ]);

        $this->voluntario->update(['name' => 'João Voluntário']);
        $this->voluntario->participante->update([
            'municipio_id' => $municipio->id,
            'sexo' => 'Masculino',
            'data_nascimento' => now()->subYears(65)->toDateString(),
        ]);
    }

    public function test_gestor_ve_somente_os_dados_do_remetente_no_modal(): void
    {
        $this->prepararDadosDosRemetentes();
        $carta = $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->gestor)->get(route('cartas.cartas.show', $carta));

        $response->assertOk();
        $response->assertSee('id="remetentesModal"', false);
        $response->assertSee('Ver dados de Maria');
        $response->assertSeeInOrder([
            'Maria Educanda',
            'São Paulo',
            'SP',
            '30 anos',
            'Feminino',
        ], false);

        $response->assertDontSee('Ver dados dos remetentes');
        $response->assertDontSee('<h3>Voluntário</h3>', false);
        $response->assertDontSee('65 anos');
        $response->assertDontSee('Pernambuco (PE)');
        $response->assertDontSee('<dd>Masculino</dd>', false);
    }

    public function test_voluntario_ve_o_mesmo_modal_do_remetente(): void
    {
        $this->prepararDadosDosRemetentes();
        $carta = $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->voluntario)->get(route('cartas.cartas.show', $carta));

        $response->assertOk();
        $response->assertSee('id="remetentesModal"', false);
        $response->assertSee('Ver dados de Maria');
        $response->assertSee('30 anos');
        $response->assertSee('Feminino');

        $response->assertDontSee('65 anos');
        $response->assertDontSee('Pernambuco (PE)');
    }

    public function test_modal_usa_o_rotulo_sexo_e_nao_identidade_de_genero(): void
    {
        $this->prepararDadosDosRemetentes();
        $carta = $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->gestor)->get(route('cartas.cartas.show', $carta));

        $response->assertSee('<dt>Sexo</dt>', false);
        $response->assertDontSee('Identidade de gênero');
        $response->assertDontSee('Mulher Cisgênero');
    }

    public function test_modal_nao_expoe_dados_pessoais_nem_demograficos_sensiveis(): void
    {
        $this->prepararDadosDosRemetentes();
        $carta = $this->criarCartaParaVoluntario();

        foreach ([$this->gestor, $this->voluntario] as $usuario) {
            $response = $this->actingAs($usuario)->get(route('cartas.cartas.show', $carta));

            $response->assertOk();

            foreach ([
                '12345678901',
                '11999999999',
                'maria.educanda@example.com',
                'Indígena',
                'Auditiva',
                'Lésbica',
                'Povos Ciganos',
            ] as $sensivel) {
                $response->assertDontSee($sensivel);
            }
        }
    }

    public function test_dados_ausentes_aparecem_como_nao_informado(): void
    {
        $carta = $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->gestor)->get(route('cartas.cartas.show', $carta));

        $response->assertOk();
        $this->assertMatchesRegularExpression('#<dt>Idade</dt>\s*<dd>Não informado</dd>#', $response->getContent());
        $this->assertMatchesRegularExpression('#<dt>Sexo</dt>\s*<dd>Não informado</dd>#', $response->getContent());
    }
}
