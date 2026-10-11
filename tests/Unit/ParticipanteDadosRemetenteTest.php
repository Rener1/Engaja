<?php

namespace Tests\Unit;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class ParticipanteDadosRemetenteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $participante
     * @param  array<string, mixed>  $user
     */
    private function participante(array $participante = [], array $user = [], bool $comMunicipio = true): Participante
    {
        $model = new Participante;
        $model->setRawAttributes($participante);

        $usuario = new User;
        $usuario->setRawAttributes(array_merge(['name' => 'Maria da Silva'], $user));
        $model->setRelation('user', $usuario);

        if ($comMunicipio) {
            $estado = new Estado;
            $estado->setRawAttributes(['nome' => 'Pernambuco', 'sigla' => 'PE']);

            $municipio = new Municipio;
            $municipio->setRawAttributes(['nome' => 'Recife']);
            $municipio->setRelation('estado', $estado);

            $model->setRelation('municipio', $municipio);
        } else {
            $model->setRelation('municipio', null);
        }

        return $model;
    }

    public function test_idade_e_calculada_a_partir_de_data_nascimento_do_participante(): void
    {
        $this->assertSame(36, $this->participante(['data_nascimento' => '1990-10-10'])->idade);
    }

    public function test_idade_nao_conta_aniversario_que_ainda_nao_ocorreu(): void
    {
        $this->assertSame(35, $this->participante(['data_nascimento' => '1990-10-11'])->idade);
    }

    public function test_idade_le_data_nascimento_do_usuario_quando_participante_nao_tem_a_coluna(): void
    {
        $participante = $this->participante([], ['data_nascimento' => '2000-01-01']);

        $this->assertSame(26, $participante->idade);
        $this->assertSame('26 anos', $participante->dadosRemetente()['idade']);
    }

    public function test_idade_singular_para_um_ano(): void
    {
        $dados = $this->participante(['data_nascimento' => '2025-06-01'])->dadosRemetente();

        $this->assertSame('1 ano', $dados['idade']);
    }

    public function test_sem_data_nascimento_a_idade_e_nao_informado_mesmo_com_faixa_etaria(): void
    {
        $participante = $this->participante([], ['faixa_etaria' => 'Adulto (18 a 59 anos)']);

        $this->assertNull($participante->idade);
        $this->assertSame('Não informado', $participante->dadosRemetente()['idade']);
    }

    public function test_sem_nenhuma_coluna_de_nascimento_nao_gera_erro(): void
    {
        $this->assertSame('Não informado', $this->participante()->dadosRemetente()['idade']);
    }

    public function test_data_nascimento_futura_ou_invalida_vira_nao_informado(): void
    {
        $futura = $this->participante(['data_nascimento' => '2030-01-01']);
        $invalida = $this->participante(['data_nascimento' => 'isto-nao-e-data']);

        $this->assertNull($futura->idade);
        $this->assertSame('Não informado', $futura->dadosRemetente()['idade']);
        $this->assertNull($invalida->idade);
        $this->assertSame('Não informado', $invalida->dadosRemetente()['idade']);
    }

    public function test_sexo_usa_identidade_de_genero_e_o_texto_livre_quando_outro(): void
    {
        $comum = $this->participante([], ['identidade_genero' => 'Mulher Cisgênero']);
        $outro = $this->participante([], [
            'identidade_genero' => 'Outro',
            'identidade_genero_outro' => 'Agênero',
        ]);
        $vazio = $this->participante();

        $this->assertSame('Mulher Cisgênero', $comum->dadosRemetente()['sexo']);
        $this->assertSame('Agênero', $outro->dadosRemetente()['sexo']);
        $this->assertSame('Não informado', $vazio->dadosRemetente()['sexo']);
    }

    public function test_cidade_e_estado_vem_do_municipio(): void
    {
        $dados = $this->participante()->dadosRemetente();

        $this->assertSame('Recife', $dados['cidade']);
        $this->assertSame('Pernambuco (PE)', $dados['estado']);
    }

    public function test_sem_municipio_cidade_e_estado_ficam_como_nao_informado(): void
    {
        $dados = $this->participante(comMunicipio: false)->dadosRemetente();

        $this->assertSame('Não informado', $dados['cidade']);
        $this->assertSame('Não informado', $dados['estado']);
    }

    public function test_dados_remetente_expoe_somente_os_cinco_campos(): void
    {
        $participante = $this->participante(
            ['cpf' => '12345678901', 'telefone' => '81999999999'],
            [
                'email' => 'maria@example.com',
                'raca_cor' => 'Parda',
                'pcd' => 'Visual',
                'orientacao_sexual' => 'Bissexual',
            ]
        );

        $dados = $participante->dadosRemetente();

        $this->assertSame(['nome', 'cidade', 'estado', 'idade', 'sexo'], array_keys($dados));
        $this->assertSame('Maria da Silva', $dados['nome']);

        $json = json_encode($dados);
        foreach (['12345678901', '81999999999', 'maria@example.com', 'Parda', 'Bissexual'] as $sensivel) {
            $this->assertStringNotContainsString($sensivel, $json);
        }
    }

    public function test_dados_remetente_aceita_usuario_explicito_para_voluntario(): void
    {
        $voluntario = new User;
        $voluntario->setRawAttributes(['name' => 'João Voluntário', 'identidade_genero' => 'Homem Cisgênero']);

        $dados = (new Participante)->dadosRemetente($voluntario);

        $this->assertSame('João Voluntário', $dados['nome']);
        $this->assertSame('Homem Cisgênero', $dados['sexo']);
    }
}
