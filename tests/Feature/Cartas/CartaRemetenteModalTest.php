<?php

namespace Tests\Feature\Cartas;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Regiao;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

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

        $regiao = Regiao::create(['nome' => 'Nordeste']);
        $estado = Estado::create(['nome' => 'Pernambuco', 'sigla' => 'PE', 'regiao_id' => $regiao->id]);
        $municipio = Municipio::create([
            'nome' => 'Recife',
            'estado_id' => $estado->id,
            'regiao_id' => $regiao->id,
        ]);

        $this->voluntario->update([
            'name' => 'João Voluntário',
            'identidade_genero' => 'Homem Transsexual',
            'faixa_etaria' => 'Idoso (a partir dos 60 anos)',
        ]);
        $this->voluntario->participante->update(['municipio_id' => $municipio->id]);
    }

    public function test_gestor_ve_dados_do_educando_e_do_voluntario_no_modal(): void
    {
        $this->prepararDadosDosRemetentes();
        $carta = $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->gestor)->get(route('cartas.cartas.show', $carta));

        $response->assertOk();
        $response->assertSee('id="remetentesModal"', false);
        $response->assertSee('Ver dados dos remetentes');

        $response->assertSeeInOrder([
            'Maria Educanda',
            'São Paulo (SP)',
            'Mulher Cisgênero',
            'João Voluntário',
            'Recife',
            'Pernambuco (PE)',
            'Homem Transsexual',
        ], false);
        $response->assertSee('Identidade de gênero');
        $response->assertDontSee('Adulto (18 a 59 anos)');
        $response->assertDontSee('Idoso (a partir dos 60 anos)');
    }

    public function test_voluntario_ve_somente_os_dados_do_educando(): void
    {
        $this->prepararDadosDosRemetentes();
        $carta = $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->voluntario)->get(route('cartas.cartas.show', $carta));

        $response->assertOk();
        $response->assertSee('id="remetentesModal"', false);
        $response->assertSee('Ver dados de Maria');
        $response->assertSee('Mulher Cisgênero');

        $response->assertDontSee('Ver dados dos remetentes');
        $response->assertDontSee('<h3>Voluntário</h3>', false);
        $response->assertDontSee('Homem Transsexual');
        $response->assertDontSee('Pernambuco (PE)');
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

    public function test_modal_funciona_sem_data_nascimento_e_mostra_nao_informado_na_idade(): void
    {
        $carta = $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->gestor)->get(route('cartas.cartas.show', $carta));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '#<dt>Idade</dt>\s*<dd>Não informado</dd>#',
            $response->getContent()
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function tabelasComDataNascimento(): array
    {
        return [
            'coluna em users' => ['users'],
            'coluna em participantes' => ['participantes'],
        ];
    }

    #[DataProvider('tabelasComDataNascimento')]
    public function test_modal_mostra_idade_calculada_quando_a_coluna_data_nascimento_existe(string $tabela): void
    {
        // O DDL do Postgres é transacional: o RefreshDatabase desfaz a coluna ao fim do teste.
        Schema::table($tabela, function (Blueprint $table) {
            $table->date('data_nascimento')->nullable();
        });

        $nascimento = now()->subYears(30)->subDay()->toDateString();

        if ($tabela === 'users') {
            $this->remetente->update(['name' => 'Maria Educanda']);
            $this->remetente->newQuery()->whereKey($this->remetente->id)->update(['data_nascimento' => $nascimento]);
        } else {
            $this->educando->newQuery()->whereKey($this->educando->id)->update(['data_nascimento' => $nascimento]);
        }

        $carta = $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->voluntario)->get(route('cartas.cartas.show', $carta));

        $response->assertOk();
        $this->assertMatchesRegularExpression('#<dt>Idade</dt>\s*<dd>30 anos</dd>#', $response->getContent());
    }
}
