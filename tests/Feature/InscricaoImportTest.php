<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Eixo;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Participante;
use App\Models\User;
use App\Services\ParticipanteImportIdentityResolver;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InscricaoImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Evento $evento;

    private Atividade $atividade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $eixo = Eixo::create(['nome' => 'Eixo importação']);
        $this->evento = Evento::factory()->create(['user_id' => $this->admin->id, 'eixo_id' => $eixo->id]);
        $this->atividade = Atividade::factory()->create(['evento_id' => $this->evento->id]);
        $this->actingAs($this->admin);
    }

    public function test_upload_informa_todas_as_linhas_invalidas_e_ignora_vazias_e_abas_auxiliares(): void
    {
        $counts = [User::count(), Participante::count()];
        $response = $this->upload([
            'Participantes' => [
                ['Pessoa válida', 'valida@example.com', '', ''],
                ['', '', '', ''],
                ['', 'sem.nome@example.com', '', ''],
                ['Sem identificação', '', '', ''],
            ],
            'Outra aba' => [['', '', '01234567890', '']],
            '_valid' => [['Organização', '', '', ''], ['Lista auxiliar', '', '', '']],
        ]);
        $response->assertSessionHasErrors(['rows.1.nome', 'rows.2.identificacao', 'rows.3.nome']);
        $errors = implode(' ', session('errors')->all());
        $this->assertStringContainsString('Linha 4', $errors);
        $this->assertStringContainsString('Linha 5', $errors);
        $this->assertStringContainsString('Linha 2', $errors);
        $this->assertStringNotContainsString('_valid', $errors);
        $this->assertStringNotContainsString('Aba "', $errors);
        $this->assertSame($counts, [User::count(), Participante::count()]);
        $this->get(route('inscricoes.import', $this->evento))
            ->assertOk()->assertSee('Linha 4')->assertSee('Linha 5');
    }

    #[DataProvider('formats')]
    public function test_upload_normaliza_identificadores_e_aceita_cpf_sem_email(string $format): void
    {
        $response = $this->upload(['Participantes' => [
            [' Maria Ana Alves ', '', '012.345.678-90', ''],
            ['', '', '', ''],
            ['Outra pessoa', ' OUTRA@EXAMPLE.COM ', '', ''],
        ]], $format);
        $response->assertSessionHasNoErrors();
        $key = $this->sessionKey($response);
        $payload = session($key);
        $this->assertSame('Maria Ana Alves', $payload['rows'][0]['nome']);
        $this->assertSame('01234567890', $payload['rows'][0]['cpf']);
        $this->assertSame('outra@example.com', $payload['rows'][1]['email']);
        $this->assertSame(4, $payload['rows'][1]['linha_original']);
        $this->assertSame($this->evento->id, $payload['evento_id']);
        $this->assertSame($this->admin->id, $payload['user_id']);
        $counts = [User::count(), Participante::count()];
        $preview = $this->get($response->headers->get('Location'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($preview->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertFalse($xpath->query('//input[@name="rows[0][email]"]')->item(0)->hasAttribute('required'));
        $this->assertSame($counts, [User::count(), Participante::count()]);
        $this->confirm($key)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'maria.ana.alves@ficticio.org.br', 'name' => 'Maria Ana Alves']);
        $this->assertSame(2, Inscricao::count());
    }

    public static function formats(): array
    {
        return [['Xlsx'], ['Xls'], ['Csv']];
    }

    #[DataProvider('auxiliarySheets')]
    public function test_abas_ocultas_e_valid_nao_sao_participantes(string $title, string $state): void
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('Participantes');
        $sheet->fromArray([['nome', 'email', 'cpf', 'data_nascimento'], ['Maria', 'maria@example.com', '', '1990-03-25']]);
        $auxiliary = $workbook->createSheet()->setTitle($title);
        $auxiliary->setSheetState($state);
        $auxiliary->fromArray([['nome', 'email', 'cpf'], ['Não importar', '', '']]);
        $response = $this->uploadWorkbook($workbook);
        $response->assertSessionHasNoErrors();
        $rows = session($this->sessionKey($response))['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame('Participantes', $rows[0]['aba_original']);
    }

    public static function auxiliarySheets(): array
    {
        return [
            ['_valid', Worksheet::SHEETSTATE_VISIBLE],
            ['_VALID', Worksheet::SHEETSTATE_VISIBLE],
            ['Auxiliar', Worksheet::SHEETSTATE_HIDDEN],
            ['Auxiliar', Worksheet::SHEETSTATE_VERYHIDDEN],
        ];
    }

    public function test_cabecalho_deslocado_preserva_numero_real_da_linha(): void
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet()->setTitle('Participantes');
        $sheet->setCellValue('A1', 'Lista de inscrições');
        $sheet->fromArray([['nome', 'email', 'cpf', 'data_nascimento'], ['Sem identificação', '', '', '1990-03-25']], null, 'A5');
        $this->uploadWorkbook($workbook)->assertSessionHasErrors('rows.0.identificacao');
        $this->assertStringContainsString('Linha 6', session('errors')->first('rows.0.identificacao'));
    }

    public function test_modelo_real_de_inscricoes_pode_ser_lido(): void
    {
        $workbook = IOFactory::load(public_path('modelos/modelo_inscricoes_engaja.xlsx'));
        $sheet = $workbook->getActiveSheet();
        $this->assertSame(['tag', 'data_nascimento', 'sexo', 'identidade_genero'], $sheet->rangeToArray('I1:L1')[0]);
        $this->assertSame([null, null], $sheet->rangeToArray('J2:K2')[0]);
        for ($line = 2; $line <= 3; $line++) {
            $sheet->setCellValue('J'.$line, '1990-03-25');
        }
        $response = $this->uploadWorkbook($workbook);
        $response->assertSessionHasNoErrors();
        $this->assertNotEmpty(session($this->sessionKey($response))['rows']);
    }

    public function test_email_existente_e_identificado_sem_diferenca_de_caixa_e_cpf_vazio_e_preservado(): void
    {
        $user = $this->profile('MARIA@EXAMPLE.COM', '12345678901');
        $counts = [User::count(), Participante::count()];
        $key = $this->preview([$this->row(['email' => ' maria@example.com ', 'telefone' => '(11) 99999-0000'])]);
        $this->confirm($key)->assertSessionHasNoErrors();
        $this->assertSame($counts, [User::count(), Participante::count()]);
        $this->assertSame($user->participante->id, Inscricao::first()->participante_id);
        $this->assertSame('12345678901', $user->participante->fresh()->cpf);
        $this->assertSame('11999990000', $user->participante->fresh()->telefone);
    }

    public function test_cpf_utiliza_perfil_ativo_mais_recente_e_desempata_pelo_id(): void
    {
        $cpf = '01234567890';
        $this->profile('antigo@example.com', $cpf, ['created_at' => now()->subYears(2)]);
        $this->profile('recente@example.com', $cpf, ['created_at' => now()->subYear()]);
        $latest = $this->profile('mais.recente@example.com', $cpf, ['created_at' => now()->subYear()]);
        $deleted = $this->profile('excluido@example.com', $cpf);
        $deleted->delete();
        $counts = [User::withTrashed()->count(), Participante::withTrashed()->count()];
        $this->confirm($this->preview([$this->row(['email' => '', 'cpf' => '012.345.678-90'])]))
            ->assertSessionHasNoErrors();
        $this->assertSame($latest->participante->id, Inscricao::first()->participante_id);
        $this->assertSame($counts, [User::withTrashed()->count(), Participante::withTrashed()->count()]);
    }

    public function test_cpf_novo_gera_ficticio_pelo_nome_e_trata_colisao(): void
    {
        $this->profile('maria.ana.alves@ficticio.org.br', '99999999999');
        $counts = [User::count(), Participante::count()];
        $key = $this->preview([$this->row(['email' => '', 'cpf' => '12345678901'])]);
        $this->get($this->previewUrl($key))->assertOk()->assertViewHas('usuariosNovosCount', 1);
        $this->assertSame($counts, [User::count(), Participante::count()]);
        $this->confirm($key)->assertSessionHasNoErrors();
        $this->assertSame($counts[0] + 1, User::count());
        $this->assertSame(1, Inscricao::count());
        $user = Inscricao::first()->participante->user;
        $this->assertMatchesRegularExpression('/^maria[.]ana[.]alves[.][a-z0-9]+@ficticio[.]org[.]br$/', $user->email);
        $this->assertSame('Maria Ana Alves', $user->name);
    }

    public function test_email_tem_prioridade_sobre_cpf_mesmo_quando_email_e_novo(): void
    {
        $emailUser = $this->profile('email@example.com', '11111111111');
        $cpfUser = $this->profile('cpf@example.com', '22222222222');
        $this->confirm($this->preview([$this->row(['email' => $emailUser->email, 'cpf' => $cpfUser->participante->cpf])]))
            ->assertSessionHasNoErrors();
        $this->assertSame($emailUser->participante->id, Inscricao::first()->participante_id);
        $counts = User::count();
        $this->confirm($this->preview([$this->row(['email' => 'novo@example.com', 'cpf' => '22222222222'])]))
            ->assertSessionHasNoErrors();
        $this->assertSame($counts + 1, User::count());
    }

    #[DataProvider('duplicateIdentifiers')]
    public function test_duplicacoes_bloqueiam_confirmacao_sem_gravar(array $first, array $second, string $field): void
    {
        $counts = [User::count(), Participante::count()];
        $key = $this->preview([$this->row($first), $this->row($second + ['linha_original' => 8])]);
        $this->confirm($key)->assertSessionHasErrors(['rows.0.'.$field, 'rows.1.'.$field]);
        $message = session('errors')->first('rows.0.'.$field);
        $this->assertStringContainsString('repetido na planilha', $message);
        $this->assertStringContainsString('Linha 2', $message);
        $this->assertStringContainsString('Linha 8', $message);
        $this->assertSame($counts, [User::count(), Participante::count()]);
        $this->assertSame(0, Inscricao::count());
        $this->assertNotNull(session($key));
    }

    public static function duplicateIdentifiers(): array
    {
        return [
            [['email' => ' igual@example.com ', 'cpf' => '11111111111'], ['email' => 'IGUAL@example.com', 'cpf' => '22222222222'], 'email'],
            [['email' => 'primeiro@example.com', 'cpf' => '123.456.789-01'], ['email' => 'segundo@example.com', 'cpf' => '12345678901'], 'cpf'],
            [['email' => '', 'cpf' => '12345678901'], ['email' => '', 'cpf' => '12345678901'], 'cpf'],
            [['email' => 'primeiro@example.com', 'cpf' => '12345678901'], ['email' => '', 'cpf' => '12345678901'], 'cpf'],
        ];
    }

    public function test_upload_bloqueia_duplicacoes_com_linhas_originais_e_mostra_mensagem(): void
    {
        $response = $this->upload(['Participantes' => [
            ['Primeira pessoa', 'igual@example.com', '11111111111', ''],
            ['', '', '', ''],
            ['Segunda pessoa', 'IGUAL@EXAMPLE.COM', '22222222222', ''],
        ]]);
        $response->assertSessionHasErrors(['rows.0.email', 'rows.1.email']);
        $this->get(route('inscricoes.import', $this->evento))->assertOk()
            ->assertSee('E-mail repetido na planilha')->assertSee('Linha 2')->assertSee('Linha 4');
        $this->assertSame(0, Inscricao::count());
    }

    public function test_email_e_cpf_em_linhas_distintas_do_mesmo_perfil_tambem_sao_duplicacao(): void
    {
        $user = $this->profile('existente@example.com', '11111111111');
        $key = $this->preview([
            $this->row(['email' => $user->email, 'cpf' => null]),
            $this->row(['email' => '', 'cpf' => '11111111111']),
        ]);
        $this->confirm($key)->assertSessionHasErrors(['rows.0.identificacao', 'rows.1.identificacao']);
        $this->assertSame(0, Inscricao::count());
    }

    public function test_perfil_excluido_bloqueia_sem_duplicar_e_reverte_criacoes_anteriores(): void
    {
        $user = $this->profile('excluido@example.com', '12345678901');
        $user->delete();
        $counts = [User::withTrashed()->count(), Participante::withTrashed()->count()];
        foreach ([$this->row(['email' => $user->email]), $this->row(['email' => '', 'cpf' => '12345678901'])] as $row) {
            $key = $this->preview([$this->row(['email' => 'primeiro.novo@example.com']), $row]);
            $this->get($this->previewUrl($key))->assertOk()->assertSee('excluído');
            $this->confirm($key)->assertSessionHasErrors('rows.1.identificacao');
            $this->assertNotNull(session($key));
        }
        $this->assertSame($counts, [User::withTrashed()->count(), Participante::withTrashed()->count()]);
        $this->assertSame(0, Inscricao::count());
    }

    public function test_confirmacao_valida_todo_arquivo_antes_de_gravar(): void
    {
        $counts = [User::count(), Participante::count()];
        $key = $this->preview([
            $this->row(),
            $this->row(['nome' => '', 'email' => 'segundo@example.com']),
            $this->row(['email' => '', 'cpf' => null]),
        ]);
        $this->confirm($key)->assertSessionHasErrors(['rows.1.nome', 'rows.2.identificacao']);
        $this->assertSame($counts, [User::count(), Participante::count()]);
        $this->assertSame(0, Inscricao::count());
    }

    public function test_edicao_revalida_campos_preserva_metadados_e_rejeita_linhas_desconhecidas(): void
    {
        $row = $this->row();
        $key = $this->preview([$row]);
        foreach ([['nome' => ' '], ['email' => '', 'cpf' => '---'], ['email' => 'invalido']] as $edit) {
            $this->save($key, [0 => $edit])->assertSessionHasErrors();
            $this->assertSame($row, session($key)['rows'][0]);
        }
        $this->save($key, [0 => ['linha_original' => 999]])->assertSessionHasErrors('rows.0');
        $this->save($key, [99 => ['nome' => 'Outro nome']])->assertSessionHasErrors('rows');
        $this->save($key, [0 => ['email' => '', 'cpf' => '012.345.678-90']])->assertSessionHasNoErrors();
        $this->assertSame('01234567890', session($key)['rows'][0]['cpf']);
        $this->assertSame(2, session($key)['rows'][0]['linha_original']);
        $this->assertSame('Planilha', session($key)['origem']);
    }

    public function test_previa_de_outro_evento_usuario_ou_momento_nao_pode_ser_usada(): void
    {
        $key = $this->preview([$this->row()]);
        $other = Evento::factory()->create(['user_id' => $this->admin->id, 'eixo_id' => $this->evento->eixo_id]);
        $this->post(route('inscricoes.confirmar', $other), ['session_key' => $key])->assertSessionHasErrors('rows');
        $otherActivity = Atividade::factory()->create(['evento_id' => $this->evento->id]);
        $this->post(route('inscricoes.confirmar', $this->evento), ['session_key' => $key, 'atividade_id' => $otherActivity->id])
            ->assertSessionHasErrors('atividade_id');
        $another = User::factory()->create();
        $another->assignRole('administrador');
        $this->actingAs($another);
        $this->get($this->previewUrl($key))->assertRedirect();
        $this->save($key, [0 => ['nome' => 'Outro nome']])->assertSessionHasErrors('rows');
        $this->confirm($key)->assertSessionHasErrors('rows');
        $this->assertSame(0, Inscricao::count());
    }

    public function test_inscricao_existente_e_mantida_e_excluida_e_restaurada(): void
    {
        $user = $this->profile('existente@example.com', '12345678901');
        $inscricao = Inscricao::create(['evento_id' => $this->evento->id, 'atividade_id' => $this->atividade->id, 'participante_id' => $user->participante->id]);
        $this->confirm($this->preview([$this->row(['email' => '', 'cpf' => '12345678901'])]))->assertSessionHasNoErrors();
        $this->assertSame(1, Inscricao::count());
        $inscricao->delete();
        $this->confirm($this->preview([$this->row(['email' => '', 'cpf' => '12345678901'])]))->assertSessionHasNoErrors();
        $this->assertFalse($inscricao->fresh()->trashed());
        $this->assertSame(1, Inscricao::count());
    }

    public function test_origem_e_demograficos_sao_gravados_no_perfil_resolvido_por_cpf(): void
    {
        $user = $this->profile('existente@example.com', '12345678901');
        $definition = config('engaja.demograficos.identidade_genero');
        $value = $definition['opcoes'][0];
        $key = $this->preview([$this->row(['email' => '', 'cpf' => '12345678901', 'identidade_genero' => $value])]);
        $this->confirm($key)->assertSessionHasNoErrors();
        $this->assertSame($value, $user->fresh()->identidade_genero);
        $this->assertDatabaseHas('origem_usuario', ['evento_id' => $this->evento->id, 'user_id' => $user->id, 'origem' => 'Planilha']);
    }

    public function test_cartas_nao_e_usado_como_perfil_do_engaja(): void
    {
        $cartas = $this->profile('compartilhado@example.com', '12345678901', ['sistema_origem' => User::SISTEMA_CARTAS]);
        $this->confirm($this->preview([$this->row(['email' => $cartas->email, 'cpf' => '12345678901'])]))->assertSessionHasNoErrors();
        $this->assertNotSame($cartas->id, Inscricao::first()->participante->user_id);
        $this->assertSame(User::SISTEMA_ENGAJA, Inscricao::first()->participante->user->sistema_origem);
    }

    public function test_duplicacao_introduzida_na_edicao_e_bloqueada_sem_mudar_sessao(): void
    {
        $rows = [$this->row(), $this->row(['email' => 'outro@example.com', 'linha_original' => 8])];
        $key = $this->preview($rows);
        $this->save($key, [1 => ['email' => 'MARIA@example.com']])
            ->assertSessionHasErrors(['rows.0.email', 'rows.1.email']);
        $this->assertSame($rows, session($key)['rows']);
        $this->get($this->previewUrl($key))->assertOk()->assertSee('E-mail repetido na planilha');
    }

    public function test_nomes_iguais_com_identificadores_distintos_sao_permitidos(): void
    {
        $key = $this->preview([
            $this->row(['email' => '', 'cpf' => '11111111111']),
            $this->row(['email' => '', 'cpf' => '22222222222']),
        ]);
        $this->confirm($key)->assertSessionHasNoErrors();
        $this->assertSame(2, Inscricao::count());
        $this->assertCount(2, User::where('name', 'Maria Ana Alves')->pluck('email')->unique());
    }

    public function test_falha_durante_gravacao_reverte_perfis_e_mantem_previa(): void
    {
        $counts = [User::count(), Participante::count()];
        $roleCount = DB::table('model_has_roles')->count();
        $resolver = new class extends ParticipanteImportIdentityResolver
        {
            private int $calls = 0;

            public function resolve(array $row): Participante
            {
                if (++$this->calls === 2) {
                    throw ValidationException::withMessages(['rows' => 'Falha simulada.']);
                }

                return parent::resolve($row);
            }
        };
        $this->app->instance(ParticipanteImportIdentityResolver::class, $resolver);
        $key = $this->preview([$this->row(), $this->row(['email' => 'segunda@example.com'])]);
        $this->confirm($key)->assertSessionHasErrors('rows');
        $this->assertSame($counts, [User::count(), Participante::count()]);
        $this->assertSame(0, Inscricao::count());
        $this->assertSame($roleCount, DB::table('model_has_roles')->count());
        $this->assertNotNull(session($key));
    }

    public function test_upload_bloqueia_cpf_repetido_mesmo_com_emails_diferentes(): void
    {
        $this->upload(['Participantes' => [
            ['Primeira pessoa', 'primeira@example.com', '123.456.789-01', ''],
            ['Segunda pessoa', 'segunda@example.com', '12345678901', ''],
        ]])->assertSessionHasErrors(['rows.0.cpf', 'rows.1.cpf']);
        $this->get(route('inscricoes.import', $this->evento))->assertOk()
            ->assertSee('CPF repetido na planilha')->assertSee('Linha 2')->assertSee('Linha 3');
        $this->assertSame(0, Inscricao::count());
    }

    public function test_uploads_distintos_nao_sobrescrevem_previa_anterior(): void
    {
        $first = $this->sessionKey($this->upload(['Participantes' => [['Primeira', 'primeira@example.com', '', '']]]));
        $second = $this->sessionKey($this->upload(['Participantes' => [['Segunda', 'segunda@example.com', '', '']]]));
        $this->assertNotSame($first, $second);
        $this->assertSame('primeira@example.com', session($first)['rows'][0]['email']);
        $this->assertSame('segunda@example.com', session($second)['rows'][0]['email']);
    }

    public function test_confirmacao_concorrente_usa_mesma_trava_das_presencas(): void
    {
        config(['database.connections.inscricao_lock_test' => DB::connection()->getConfig()]);
        $other = DB::connection('inscricao_lock_test');
        $other->beginTransaction();
        try {
            $other->selectOne('SELECT pg_advisory_xact_lock(?, ?)', [17012026, 1]);
            $counts = [User::count(), Participante::count()];
            $key = $this->preview([$this->row()]);
            $this->confirm($key)->assertSessionHasErrors('rows');
            $this->assertSame($counts, [User::count(), Participante::count()]);
            $this->assertSame(0, Inscricao::count());
            $this->assertNotNull(session($key));
        } finally {
            $other->rollBack();
            DB::disconnect('inscricao_lock_test');
        }
    }

    #[DataProvider('formats')]
    public function test_importa_nascimento_e_sexo_em_todos_os_formatos(string $format): void
    {
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->fromArray([
            ['nome', 'email', 'Data de nascimento', 'sexo'],
            ['Ana', 'ana@example.com', '25/03/1990', ' feminino '],
        ]);
        $response = $this->uploadWorkbook($workbook, $format)->assertSessionHasNoErrors();
        $key = $this->sessionKey($response);
        $row = session($key)['rows'][0];
        $this->assertSame('1990-03-25', $row['data_nascimento']);
        $this->assertSame('Feminino', $row['sexo']);

        $preview = $this->get($response->headers->get('Location'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($preview->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertTrue($xpath->query('//input[@name="rows[0][data_nascimento]"]')->item(0)->hasAttribute('required'));
        $this->assertSame(4, $xpath->query('//select[@name="rows[0][sexo]"]/option')->length);

        $this->confirm($key)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('participantes', [
            'user_id' => User::where('email', 'ana@example.com')->firstOrFail()->id,
            'data_nascimento' => '1990-03-25', 'sexo' => 'Feminino',
        ]);
    }

    public function test_aceita_data_real_do_excel_exibida_com_ano_de_dois_digitos(): void
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet();
        $sheet->fromArray([
            ['nome', 'email', 'data_nascimento'],
            ['Ana', 'ana@example.com', Date::PHPToExcel(new \DateTimeImmutable('1926-10-20'))],
        ]);
        $sheet->getStyle('C2')->getNumberFormat()->setFormatCode('dd/mm/yy');
        $response = $this->uploadWorkbook($workbook)->assertSessionHasNoErrors();
        $key = $this->sessionKey($response);
        $this->assertSame('1926-10-20', session($key)['rows'][0]['data_nascimento']);
        $this->confirm($key)->assertSessionHasNoErrors();
        $participant = User::where('email', 'ana@example.com')->firstOrFail()->participante;
        $this->assertSame('1926-10-20', $participant->data_nascimento->toDateString());
        $this->assertSame('Não preenchido', $participant->sexo);
    }

    public function test_coluna_de_nascimento_ausente_bloqueia_importacao_inclusive_para_perfil_existente(): void
    {
        $user = $this->profile('ana@example.com', '12345678901');
        $user->participante->update(['data_nascimento' => '1990-03-25']);
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->fromArray([
            ['nome', 'email', 'sexo'],
            ['Ana', 'ana@example.com', 'Feminino'],
        ]);
        $this->uploadWorkbook($workbook)->assertSessionHasErrors('rows.0.data_nascimento');
        $this->assertStringContainsString('Linha 2', session('errors')->first('rows.0.data_nascimento'));
        $this->assertSame(0, Inscricao::count());
        $this->assertSame('Não preenchido', $user->participante->fresh()->sexo);
    }

    public function test_upload_rejeita_nascimento_vazio_invalido_futuro_ano_curto_e_sexo_invalido(): void
    {
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->fromArray([
            ['nome', 'email', 'data_nascimento', 'sexo'],
            ['Vazio', 'vazio@example.com', '', ''],
            ['Inválido', 'invalido@example.com', '31/02/1990', ''],
            ['Futuro', 'futuro@example.com', now()->addDay()->toDateString(), ''],
            ['Ano curto', 'curto@example.com', '20/10/26', ''],
            ['Sexo inválido', 'sexo@example.com', '1990-03-25', 'invalido'],
        ]);
        $counts = [User::count(), Participante::count()];
        $this->uploadWorkbook($workbook)->assertSessionHasErrors([
            'rows.0.data_nascimento', 'rows.1.data_nascimento', 'rows.2.data_nascimento',
            'rows.3.data_nascimento', 'rows.4.sexo',
        ]);
        $this->assertSame($counts, [User::count(), Participante::count()]);
        $this->assertSame(0, Inscricao::count());
    }

    public function test_previa_revalida_nascimento_e_sexo_e_grava_edicoes(): void
    {
        $key = $this->preview([$this->row()]);
        $before = session($key)['rows'];
        foreach ([['data_nascimento' => ''], ['data_nascimento' => '31/02/1990'], ['sexo' => 'invalido']] as $edit) {
            $this->save($key, [0 => $edit])->assertSessionHasErrors();
            $this->assertSame($before, session($key)['rows']);
        }
        $this->save($key, [0 => ['data_nascimento' => '20/10/1980', 'sexo' => ' masculino ']])->assertSessionHasNoErrors();
        $this->assertSame('1980-10-20', session($key)['rows'][0]['data_nascimento']);
        $this->assertSame('Masculino', session($key)['rows'][0]['sexo']);
        $this->confirm($key)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('participantes', ['data_nascimento' => '1980-10-20', 'sexo' => 'Masculino']);
    }

    public function test_confirmacao_exige_nascimento_e_preserva_previa_sem_gravar(): void
    {
        $key = $this->preview([$this->row(), $this->row(['email' => 'segunda@example.com', 'data_nascimento' => null])]);
        $counts = [User::count(), Participante::count()];
        $this->confirm($key)->assertSessionHasErrors('rows.1.data_nascimento');
        $this->assertSame($counts, [User::count(), Participante::count()]);
        $this->assertSame(0, Inscricao::count());
        $this->assertNotNull(session($key));
    }

    public function test_reimportacao_atualiza_nascimento_preserva_sexo_vazio_e_aceita_nao_preenchido(): void
    {
        $user = $this->profile('maria@example.com', '12345678901');
        $user->participante->update(['data_nascimento' => '1980-01-01', 'sexo' => 'Feminino']);
        $this->confirm($this->preview([$this->row(['data_nascimento' => '1990-03-25', 'sexo' => ''])]))->assertSessionHasNoErrors();
        $participant = $user->participante->fresh();
        $this->assertSame('1990-03-25', $participant->data_nascimento->toDateString());
        $this->assertSame('Feminino', $participant->sexo);
        $this->confirm($this->preview([$this->row(['sexo' => ' nao preenchido '])]))->assertSessionHasNoErrors();
        $this->assertSame('Não preenchido', $participant->fresh()->sexo);
    }

    private function row(array $extra = []): array
    {
        return array_merge([
            'linha_original' => 2, 'aba_original' => 'Participantes',
            'nome' => 'Maria Ana Alves', 'email' => 'maria@example.com', 'cpf' => null,
            'telefone' => null, 'municipio' => '', 'municipio_id' => null, 'estado' => '',
            'tipo_organizacao' => '', 'tipo_organizacao_ok' => true, 'escola_unidade' => '',
            'tag' => null, 'tag_ok' => true, 'data_nascimento' => '1990-03-25', 'sexo' => null, 'data_entrada' => '',
        ], $extra);
    }

    public function test_criacao_atribui_role_de_participante_e_libera_meus_certificados(): void
    {
        $roleCount = DB::table('model_has_roles')->count();
        $key = $this->preview([
            $this->row(['nome' => 'Ana Silva', 'email' => 'ana@example.com']),
            $this->row(['nome' => 'Maria Ana Alves', 'email' => '', 'cpf' => '01234567890']),
        ]);
        $this->get($this->previewUrl($key))->assertOk();
        $this->assertSame($roleCount, DB::table('model_has_roles')->count());
        $this->confirm($key)->assertSessionHasNoErrors();

        $users = User::whereIn('email', ['ana@example.com', 'maria.ana.alves@ficticio.org.br'])->get();
        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertSame(['participante'], $user->getRoleNames()->all());
            $this->assertDatabaseHas('model_has_roles', [
                'model_type' => $user->getMorphClass(),
                'model_id' => $user->id,
                'role_id' => $user->roles->sole()->id,
            ]);
            $this->actingAs($user);
            $this->assertStringContainsString('Meus certificados', view('layouts.partials.admin-sidebar')->render());
        }
    }

    #[DataProvider('existingRoles')]
    public function test_reimportacao_preserva_roles_de_perfis_existentes(array $roles): void
    {
        $byEmail = $this->profile('email@example.com', '11111111111');
        $byCpf = $this->profile('cpf@example.com', '22222222222');
        if ($roles !== []) {
            $byEmail->assignRole($roles);
            $byCpf->assignRole($roles);
        }
        $roleCount = DB::table('model_has_roles')->count();

        $this->confirm($this->preview([
            $this->row(['email' => $byEmail->email]),
            $this->row(['email' => '', 'cpf' => '22222222222']),
        ]))->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing($roles, $byEmail->fresh()->getRoleNames()->all());
        $this->assertEqualsCanonicalizing($roles, $byCpf->fresh()->getRoleNames()->all());
        $this->assertSame($roleCount, DB::table('model_has_roles')->count());
    }

    public static function existingRoles(): array
    {
        return [
            'sem role' => [[]],
            'participante' => [['participante']],
            'multiplas roles' => [['administrador', 'gerente']],
        ];
    }

    private function profile(string $email, string $cpf, array $extra = []): User
    {
        $user = User::factory()->create(array_merge(['email' => $email], $extra));
        $user->participante->update(['cpf' => $cpf]);

        return $user;
    }

    private function preview(array $rows): string
    {
        $key = "import_preview_evento_{$this->evento->id}_".Str::uuid();
        session([$key => [
            'evento_id' => $this->evento->id, 'user_id' => $this->admin->id,
            'atividade_id' => $this->atividade->id, 'modo_todos_momentos' => false,
            'origem' => 'Planilha', 'rows' => $rows,
        ]]);

        return $key;
    }

    private function previewUrl(string $key): string
    {
        return route('inscricoes.preview', ['evento' => $this->evento, 'session_key' => $key]);
    }

    private function confirm(string $key)
    {
        return $this->post(route('inscricoes.confirmar', $this->evento), [
            'session_key' => $key, 'atividade_id' => $this->atividade->id,
        ]);
    }

    private function save(string $key, array $rows)
    {
        return $this->from($this->previewUrl($key))->post(route('inscricoes.preview.save', $this->evento), [
            'session_key' => $key, 'rows' => $rows,
        ]);
    }

    private function sessionKey($response): string
    {
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query['session_key'];
    }

    private function upload(array $sheets, string $format = 'Xlsx')
    {
        $workbook = new Spreadsheet;
        foreach ($sheets as $title => $rows) {
            $sheet = $workbook->getSheetCount() === 1 && $workbook->getActiveSheet()->getTitle() === 'Worksheet'
                ? $workbook->getActiveSheet() : $workbook->createSheet();
            $sheet->setTitle($title);
            $rows = array_map(function (array $row) {
                if (collect($row)->contains(fn ($value) => $value !== null && trim((string) $value) !== '')) {
                    $row[4] = '1990-03-25';
                }

                return $row;
            }, $rows);
            $sheet->fromArray(array_merge([['nome', 'email', 'cpf', 'telefone', 'data_nascimento']], $rows));
        }

        return $this->uploadWorkbook($workbook, $format);
    }

    private function uploadWorkbook(Spreadsheet $workbook, string $format = 'Xlsx')
    {
        $path = tempnam(sys_get_temp_dir(), 'inscricoes_');
        $writer = IOFactory::createWriter($workbook, $format);
        if ($format === 'Csv') {
            $writer->setDelimiter(',');
        }
        $writer->save($path);
        $workbook->disconnectWorksheets();
        try {
            return $this->post(route('inscricoes.cadastro', $this->evento), [
                'atividade_id' => $this->atividade->id,
                'your_file' => new UploadedFile($path, 'inscricoes.'.strtolower($format), null, null, true),
            ]);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
