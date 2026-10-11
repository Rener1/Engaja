<?php

namespace Tests\Feature;

use App\Models\Municipio;
use App\Models\Participante;
use App\Models\User;
use App\Services\AtualizacaoCadastralService;
use App\Services\ParticipanteImportIdentityResolver;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AtualizacaoCadastralTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->actingAs($this->admin);
    }

    public function test_mensagens_explicam_cpf_e_localizam_valores_divergentes(): void
    {
        $service = app(AtualizacaoCadastralService::class);
        foreach (['123' => 'tem 3 dígitos'] as $cpf => $reason) {
            $analysis = $service->analyze([['cpf' => (string) $cpf, 'aba_original' => 'Plan1', 'linha_original' => 6]]);
            $this->assertStringContainsString('Linha 6', $analysis['errors'][0]);
            $this->assertStringContainsString($reason, $analysis['errors'][0]);
            $this->assertStringContainsString('Confira o CPF no documento', $analysis['errors'][0]);
        }
        $analysis = $service->analyze([
            ['cpf' => '52998224725', 'nome' => 'Ana Silva', 'aba_original' => 'Plan1', 'linha_original' => 7],
            ['cpf' => '52998224725', 'nome' => 'Ana Souza', 'aba_original' => 'Plan2', 'linha_original' => 8],
        ]);
        $this->assertCount(2, $analysis['errors']);
        $this->assertStringContainsString('Nome diferente', $analysis['errors'][0]);
        $this->assertStringContainsString('Linha 7 informa “Ana Silva”', $analysis['errors'][0]);
        $this->assertStringContainsString('Linha 8 informa “Ana Souza”', $analysis['errors'][0]);
        $this->assertStringContainsString('Se forem pessoas diferentes, corrija o CPF ou e-mail', $analysis['errors'][0]);
        $this->assertEmpty($analysis['groups']);
    }

    public function test_aceita_cpf_sem_validacao_matematica_na_criacao_e_atualizacao_por_email(): void
    {
        foreach (['11111111111', '52998224724'] as $cpf) {
            $email = 'cpf'.$cpf.'@example.org';
            $this->confirm($this->preview([['nome' => 'Pessoa Teste', 'email' => $email, 'cpf' => $cpf]]))->assertSessionHasNoErrors();
            $user = User::where('email', $email)->firstOrFail();
            $this->assertSame($cpf, $user->participante->cpf);
            $this->confirm($this->preview([['email' => $email, 'data_nascimento' => '1980-01-01']]))->assertSessionHasNoErrors();
            $this->assertSame('1980-01-01', $user->participante->fresh()->data_nascimento->format('Y-m-d'));
        }
    }

    public function test_atualiza_tres_cadastros_por_cpf_preservando_emails_e_campos_vazios(): void
    {
        $users = collect(['José da Silva', 'JOSE DA SILVA', 'Jose  da Silva'])->map(fn ($name) => $this->person($name));
        $users[0]->participante->update(['data_nascimento' => '1980-01-01', 'telefone' => '81999999999']);
        $emails = $users->pluck('email')->all();
        $count = User::count();
        $key = $this->preview([['cpf' => '529.982.247-25', 'data_nascimento' => '15/04/1985']]);
        $this->confirm($key)->assertSessionHasNoErrors()->assertRedirect(route('usuarios.atualizacao-cadastral.index'));
        foreach ($users as $user) {
            $this->assertSame('1985-04-15', $user->participante->fresh()->data_nascimento->format('Y-m-d'));
        }
        $this->assertSame('81999999999', $users[0]->participante->fresh()->telefone);
        $this->assertSame($emails, $users->map(fn ($user) => $user->fresh()->email)->all());
        $this->assertSame($count, User::count());
        $this->assertDatabaseCount('inscricaos', 0);
        $this->assertDatabaseCount('presencas', 0);
        $this->assertNull(session($key));
    }

    public function test_email_expande_para_todos_do_mesmo_cpf_e_vazio_nao_apaga_nascimento(): void
    {
        $a = $this->person();
        $b = $this->person();
        $a->participante->update(['data_nascimento' => '1970-03-12']);
        $key = $this->preview([['email' => strtoupper($a->email), 'telefone' => '(81) 98888-7777', 'data_nascimento' => '']]);
        $this->confirm($key)->assertSessionHasNoErrors();
        foreach ([$a, $b] as $user) {
            $this->assertSame('81988887777', $user->participante->fresh()->telefone);
        }
        $this->assertSame('1970-03-12', $a->participante->fresh()->data_nascimento->format('Y-m-d'));
        $this->assertNull($b->participante->fresh()->data_nascimento);
    }

    public function test_cpf_pode_preencher_perfil_sem_cpf_encontrado_por_email(): void
    {
        $user = $this->person(cpf: null);
        $this->confirm($this->preview([['email' => $user->email, 'cpf' => '52998224725', 'data_nascimento' => '1985-04-15']]))->assertSessionHasNoErrors();
        $this->assertSame('52998224725', $user->participante->fresh()->cpf);
    }

    public function test_nomes_divergentes_bloqueiam_todo_lote_sem_gravacao(): void
    {
        $a = $this->person();
        $this->person('Outra Pessoa');
        $isolated = $this->person('Ana', null);
        $key = $this->preview([
            ['email' => $isolated->email, 'data_nascimento' => '1981-01-01'],
            ['cpf' => '52998224725', 'data_nascimento' => '1985-04-15'],
        ]);
        $this->confirm($key)->assertSessionHasErrors('rows');
        $this->assertNull($a->participante->fresh()->data_nascimento);
        $this->assertNull($isolated->participante->fresh()->data_nascimento);
        $this->assertNotNull(session($key));
    }

    public function test_conflito_email_cpf_datas_invalidas_e_valores_conflitantes_sao_bloqueados(): void
    {
        $a = $this->person();
        $b = $this->person('Ana', '11144477735');
        foreach ([
            [['email' => $b->email, 'cpf' => '52998224725', 'data_nascimento' => '1980-01-01']],
            [['cpf' => '52998224725', 'data_nascimento' => '31/02/1980']],
            [['cpf' => '52998224725', 'data_nascimento' => '2999-01-01']],
            [['cpf' => '52998224725', 'data_nascimento' => '1980-01-01'], ['email' => $a->email, 'data_nascimento' => '1981-01-01']],
        ] as $rows) {
            $this->confirm($this->preview($rows))->assertSessionHasErrors('rows');
            $this->assertNull($a->participante->fresh()->data_nascimento);
        }
    }

    public function test_isola_cartas_excluidos_e_exige_nome_para_novo_cadastro(): void
    {
        $engaja = $this->person();
        $cartas = $this->person();
        $cartas->update(['sistema_origem' => 'cartas']);
        $deleted = $this->person();
        $deleted->delete();
        $deletedParticipant = $this->person();
        $deletedParticipant->participante->delete();
        $this->confirm($this->preview([['cpf' => '52998224725', 'data_nascimento' => '1980-01-01']]))->assertSessionHasNoErrors();
        $this->assertNotNull($engaja->participante->fresh()->data_nascimento);
        foreach ([$cartas, $deleted, $deletedParticipant] as $user) {
            $this->assertNull(Participante::withTrashed()->where('user_id', $user->id)->first()->data_nascimento);
        }
        $count = User::withTrashed()->count();
        $this->confirm($this->preview([['email' => 'desconhecido@example.com', 'data_nascimento' => '1980-01-01']]))->assertSessionHasErrors('rows');
        $this->assertSame($count, User::withTrashed()->count());
    }

    public function test_previa_edicao_e_confirmacao_sao_isoladas_por_usuario(): void
    {
        $user = $this->person();
        $key = $this->preview([['email' => $user->email, 'data_nascimento' => '31/02/1980']]);
        $this->get(route('usuarios.atualizacao-cadastral.preview', ['session_key' => $key]))->assertOk()->assertSee('Pendências de todo o arquivo: 1 linha(s)');
        $this->post(route('usuarios.atualizacao-cadastral.save'), ['session_key' => $key, 'rows' => [0 => ['data_nascimento' => '02/03/1980']]])->assertSessionHasNoErrors();
        $this->assertNull($user->participante->fresh()->data_nascimento);
        $other = User::factory()->create();
        $other->assignRole('administrador');
        $this->actingAs($other);
        $this->confirm($key)->assertSessionHasErrors('session_key');
        $this->actingAs($this->admin);
        $this->confirm($key)->assertSessionHasNoErrors();
        $this->assertSame('1980-03-02', $user->participante->fresh()->data_nascimento->format('Y-m-d'));
    }

    public function test_upload_modelo_datas_excel_cabecalho_deslocado_e_aba_oculta(): void
    {
        $user = $this->person();
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(['email', 'data_nascimento'], null, 'A3');
        $sheet->fromArray([$user->email, 29221], null, 'A4');
        $book->createSheet()->setTitle('_valid')->fromArray([['email'], ['ignorar@example.com']]);
        $path = tempnam(sys_get_temp_dir(), 'cadastro_');
        try {
            (new Xlsx($book))->save($path);
            $response = $this->post(route('usuarios.atualizacao-cadastral.upload'), ['your_file' => new UploadedFile($path, 'dados.xlsx', null, null, true)]);
            $response->assertSessionHasNoErrors()->assertRedirect();
            parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
            $rows = session($query['session_key'])['rows'];
            $this->assertCount(1, $rows);
            $this->assertSame(4, $rows[0]['linha_original']);
            $this->get($response->headers->get('Location'))->assertOk()->assertSee($user->email);
            $this->confirm($query['session_key'])->assertSessionHasNoErrors();
            $this->assertNotNull($user->participante->fresh()->data_nascimento);
            $this->get(route('usuarios.atualizacao-cadastral.modelo'))->assertOk()->assertDownload('modelo_atualizacao_cadastral.xlsx');
            $this->assertSame('data_nascimento', array_key_last(AtualizacaoCadastralService::columns()));
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_participante_e_cartas_nao_acessam_fluxo(): void
    {
        $user = $this->person();
        $user->assignRole('participante');
        $this->actingAs($user)->get(route('usuarios.atualizacao-cadastral.index'))->assertForbidden();
        $user->syncRoles('administrador');
        $user->update(['sistema_origem' => 'cartas']);
        $this->get(route('usuarios.atualizacao-cadastral.index'))->assertForbidden();
    }

    public function test_trava_compartilhada_impede_confirmacao_concorrente(): void
    {
        $user = $this->person();
        config(['database.connections.cadastro_lock' => DB::connection()->getConfig()]);
        $other = DB::connection('cadastro_lock');
        $other->beginTransaction();
        try {
            $other->selectOne('SELECT pg_advisory_xact_lock(?, ?)', [17012026, 1]);
            $this->confirm($this->preview([['email' => $user->email, 'data_nascimento' => '1980-01-01']]))->assertSessionHasErrors('rows');
            $this->assertNull($user->participante->fresh()->data_nascimento);
        } finally {
            $other->rollBack();
            DB::purge('cadastro_lock');
        }
    }

    public function test_atualiza_localidade_organizacao_demografia_e_preserva_campos_omitidos(): void
    {
        $user = $this->person();
        $municipio = Municipio::factory()->create();
        $user->update(['pcd' => 'Não']);
        $user->participante->update(['escola_unidade' => 'Organização anterior']);
        $rows = [[
            'email' => $user->email, 'municipio' => $municipio->nome,
            'estado' => $municipio->estado->sigla, 'escola_unidade' => 'Nova organização',
            'raca_cor' => 'parda', 'identidade_genero' => 'Identidade informada',
            'data_entrada' => '20/02/2020', 'data_nascimento' => '1980-01-01',
        ]];
        $this->confirm($this->preview($rows))->assertSessionHasNoErrors();
        $this->assertSame($municipio->id, $user->participante->fresh()->municipio_id);
        $this->assertSame('Nova organização', $user->participante->fresh()->escola_unidade);
        $this->assertSame('Parda', $user->fresh()->raca_cor);
        $this->assertSame('Outro', $user->fresh()->identidade_genero);
        $this->assertSame('Identidade informada', $user->fresh()->identidade_genero_outro);
        $this->assertSame('Não', $user->fresh()->pcd);
    }

    public function test_revalida_identidade_na_confirmacao_e_rejeita_edicao_de_metadados(): void
    {
        $user = $this->person();
        $key = $this->preview([['email' => $user->email, 'data_nascimento' => '1980-01-01']]);
        $this->get(route('usuarios.atualizacao-cadastral.preview', ['session_key' => $key]))->assertOk();
        $this->person('Identidade diferente');
        $this->confirm($key)->assertSessionHasErrors('rows');
        $this->assertNull($user->participante->fresh()->data_nascimento);
        $this->post(route('usuarios.atualizacao-cadastral.save'), [
            'session_key' => $key, 'rows' => [0 => ['linha_original' => 999]],
        ])->assertSessionHasErrors('rows.0');
        $this->post(route('usuarios.atualizacao-cadastral.save'), [
            'session_key' => $key, 'rows' => [999 => ['nome' => 'Teste']],
        ])->assertSessionHasErrors('rows');
    }

    public function test_previa_pagina_dois_preserva_indices_e_escapa_html(): void
    {
        $user = $this->person();
        $key = $this->preview([
            ['email' => $user->email], ['email' => $user->email, 'escola_unidade' => '<img src=x onerror=alert(1)>'],
        ]);
        $response = $this->get(route('usuarios.atualizacao-cadastral.preview', ['session_key' => $key, 'per_page' => 1, 'page' => 2]));
        $response->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $grid = $xpath->query('//*[@id="atualizacao-cadastral-grid"]')->item(0);
        $data = json_decode($grid->getAttribute('data-rows'), true);
        $this->assertCount(1, $data);
        $this->assertSame(1, $data[0]['id']);
        $this->assertStringContainsString('rows[1][data_nascimento]', $data[0]['data_nascimento']);
        $this->assertStringNotContainsString('<img', $data[0]['escola_unidade']);
        $this->assertStringContainsString('&lt;img', $data[0]['escola_unidade']);
    }

    public function test_linhas_complementares_do_mesmo_perfil_e_reenvio_nao_apagam_dados(): void
    {
        $user = $this->person();
        $rows = [
            ['email' => $user->email, 'data_nascimento' => '1980-01-01'],
            ['cpf' => '52998224725', 'telefone' => '81988887777'],
        ];
        $this->confirm($this->preview($rows))->assertSessionHasNoErrors();
        $this->confirm($this->preview($rows))->assertSessionHasNoErrors();
        $this->assertSame('1980-01-01', $user->participante->fresh()->data_nascimento->format('Y-m-d'));
        $this->assertSame('81988887777', $user->participante->fresh()->telefone);
    }

    public function test_cria_inexistentes_com_email_e_cpf_ou_apenas_um_identificador(): void
    {
        $rows = [
            ['nome' => 'Nova Pessoa', 'email' => 'nova@example.com', 'cpf' => '52998224725', 'data_nascimento' => '15/04/1985'],
            ['nome' => 'Pessoa com Email', 'email' => 'email@example.com', 'telefone' => '81988887777'],
            ['nome' => 'Pessoa sem Email', 'cpf' => '11144477735', 'data_nascimento' => '1980-01-01'],
        ];
        $before = User::count();
        $key = $this->preview($rows);
        $this->get(route('usuarios.atualizacao-cadastral.preview', ['session_key' => $key]))
            ->assertOk()->assertSee('3 nova(s) pessoa(s)')->assertSee('Novo cadastro');
        $this->assertSame($before, User::count());
        $this->confirm($key)->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, '0 pessoa(s) com dados atualizados e 3 nova(s) cadastrada(s)'));
        $this->assertSame($before + 3, User::count());
        $users = User::whereIn('email', ['nova@example.com', 'email@example.com', 'pessoa.sem.email@ficticio.org.br'])->get();
        $this->assertCount(3, $users);
        foreach ($users as $user) {
            $this->assertTrue($user->hasRole('participante'));
            $this->assertSame('engaja', $user->sistema_origem);
            $this->assertNotNull($user->participante);
        }
        $this->assertSame('1985-04-15', User::where('email', 'nova@example.com')->first()->participante->data_nascimento->format('Y-m-d'));
        $this->assertDatabaseCount('inscricaos', 0);
        $this->assertDatabaseCount('presencas', 0);
        $this->confirm($this->preview($rows))->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($message) => str_contains($message, '0 nova(s) cadastrada(s)') && str_contains($message, '3 pessoa(s) sem alterações'));
        $this->assertSame($before + 3, User::count());
    }

    public function test_resumo_separa_pessoas_de_cadastros_e_atualiza_cpf_com_email_novo(): void
    {
        $users = collect(range(1, 3))->map(fn () => $this->person());
        $key = $this->preview([
            ['nome' => 'Jose da Silva', 'cpf' => '52998224725', 'email' => 'outro@example.com', 'data_nascimento' => '1980-01-01'],
            ['nome' => 'Nova Pessoa', 'email' => 'nova@example.com'],
        ]);
        $before = User::count();
        $this->confirm($key)->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, '1 pessoa(s) com dados atualizados e 1 nova(s) cadastrada(s)')
            && str_contains($message, '3 cadastro(s) existente(s) alterado(s)'));
        $this->assertSame($before + 1, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'outro@example.com']);
        foreach ($users as $user) {
            $this->assertNotNull($user->participante->fresh()->data_nascimento);
        }
    }

    public function test_linhas_novas_repetidas_e_complementares_criam_uma_pessoa(): void
    {
        $rows = [
            ['nome' => 'Nova Pessoa', 'email' => 'primeiro@example.com', 'data_nascimento' => '1980-01-01'],
            ['nome' => 'Nova Pessoa', 'email' => 'primeiro@example.com', 'cpf' => '52998224725'],
            ['nome' => 'NOVA PESSOA', 'cpf' => '52998224725', 'email' => 'segundo@example.com', 'telefone' => '81988887777'],
        ];
        $before = User::count();
        $this->confirm($this->preview($rows))->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($message) => str_contains($message, '1 nova(s) cadastrada(s)'));
        $this->assertSame($before + 1, User::count());
        $person = User::where('email', 'primeiro@example.com')->first()->participante;
        $this->assertSame('52998224725', $person->cpf);
        $this->assertSame('81988887777', $person->telefone);
        $this->assertSame('1980-01-01', $person->data_nascimento->format('Y-m-d'));
    }

    public function test_conflitos_em_novos_cadastros_revertem_todo_lote(): void
    {
        $before = User::count();
        foreach ([
            [['nome' => 'Ana', 'email' => 'nova@example.com', 'cpf' => '52998224725'], ['nome' => 'Ana', 'email' => 'nova@example.com', 'cpf' => '11144477735']],
            [['nome' => 'Ana', 'cpf' => '52998224725'], ['nome' => 'Beatriz', 'cpf' => '52998224725']],
            [['nome' => 'Ana', 'email' => 'nova@example.com', 'data_nascimento' => '1980-01-01'], ['nome' => 'Ana', 'email' => 'nova@example.com', 'data_nascimento' => '1981-01-01']],
            [['nome' => 'Ana']],
        ] as $rows) {
            $rows[] = ['nome' => 'Pessoa válida', 'email' => 'valida@example.com'];
            $key = $this->preview($rows);
            $this->confirm($key)->assertSessionHasErrors('rows');
            $this->assertSame($before, User::count());
            $this->assertNotNull(session($key));
        }
    }

    public function test_excluidos_e_usuario_sem_participante_impedem_novo_cadastro(): void
    {
        $deleted = $this->person();
        $deleted->delete();
        $withoutParticipant = $this->person('Sem participante', null);
        $withoutParticipant->participante->forceDelete();
        $before = User::withTrashed()->count();
        foreach ([
            ['nome' => 'Jose da Silva', 'email' => $deleted->email],
            ['nome' => 'Jose da Silva', 'cpf' => '52998224725'],
            ['nome' => 'Sem participante', 'email' => $withoutParticipant->email],
        ] as $row) {
            $this->confirm($this->preview([$row]))->assertSessionHasErrors('rows');
            $this->assertSame($before, User::withTrashed()->count());
        }
    }

    public function test_identificadores_apenas_no_cartas_permitem_novo_engaja(): void
    {
        $cartas = $this->person();
        $cartas->update(['sistema_origem' => 'cartas']);
        $this->confirm($this->preview([['nome' => 'Jose da Silva', 'email' => $cartas->email, 'cpf' => '52998224725']]))->assertSessionHasNoErrors();
        $this->assertSame(2, User::where('email', $cartas->email)->count());
        $this->assertSame('cartas', $cartas->fresh()->sistema_origem);
    }

    public function test_perfil_criado_apos_previa_e_atualizado_sem_duplicacao(): void
    {
        $key = $this->preview([['nome' => 'Jose da Silva', 'email' => 'novo@example.com', 'cpf' => '52998224725', 'data_nascimento' => '1980-01-01']]);
        $this->get(route('usuarios.atualizacao-cadastral.preview', ['session_key' => $key]))->assertOk()->assertSee('1 nova(s) pessoa(s)');
        $existing = $this->person();
        $before = User::count();
        $this->confirm($key)->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, '1 pessoa(s) com dados atualizados e 0 nova(s) cadastrada(s)'));
        $this->assertSame($before, User::count());
        $this->assertNotNull($existing->participante->fresh()->data_nascimento);
    }

    public function test_falha_ao_criar_reverte_usuarios_participantes_roles_e_atualizacoes(): void
    {
        $existing = $this->person();
        $before = [User::count(), Participante::count(), DB::table('model_has_roles')->count()];
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
        $key = $this->preview([
            ['cpf' => '52998224725', 'data_nascimento' => '1980-01-01'],
            ['nome' => 'Primeira Nova', 'email' => 'primeira@example.com'],
            ['nome' => 'Segunda Nova', 'email' => 'segunda@example.com'],
        ]);
        $this->confirm($key)->assertSessionHasErrors('rows');
        $this->assertSame($before, [User::count(), Participante::count(), DB::table('model_has_roles')->count()]);
        $this->assertNull($existing->participante->fresh()->data_nascimento);
        $this->assertNotNull(session($key));
    }

    public function test_salvar_pagina_mantem_pendencias_de_outras_paginas_ate_correcao(): void
    {
        $key = $this->preview([
            ['nome' => 'Pessoa Um', 'email' => 'um@example.org', 'cpf' => '123'],
            ['nome' => 'Pessoa Dois', 'email' => 'dois@example.org', 'cpf' => '456'],
        ]);
        $url = route('usuarios.atualizacao-cadastral.preview', ['session_key' => $key, 'page' => 1, 'per_page' => 1]);
        $response = $this->post(route('usuarios.atualizacao-cadastral.save', ['page' => 1, 'per_page' => 1]), [
            'session_key' => $key, 'rows' => [0 => ['cpf' => '11111111111']],
        ])->assertSessionHasNoErrors()->assertRedirect($url.'#pendencias-cadastrais');
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee('Pendências de todo o arquivo: 1 linha(s)')
            ->assertSee('Linha 3')
            ->assertSee('Corrigir na página 2')
            ->assertViewHas('analysis', fn ($analysis) => array_keys($analysis['errors']) === [1]);
        $this->get($url)->assertSee('Pendências de todo o arquivo: 1 linha(s)');
        $this->assertSame('456', session($key)['rows'][1]['cpf']);
        $this->confirm($key)->assertSessionHasErrors('rows');
        $this->post(route('usuarios.atualizacao-cadastral.save', ['page' => 2, 'per_page' => 1]), [
            'session_key' => $key, 'rows' => [1 => ['cpf' => '22222222222']],
        ])->assertRedirect();
        $this->get($url)->assertSee('Nenhuma pendência encontrada.')
            ->assertViewHas('analysis', fn ($analysis) => $analysis['errors'] === []);
    }

    public function test_previa_oferece_selects_e_preserva_valores_fora_das_opcoes(): void
    {
        $key = $this->preview([['nome' => 'Pessoa Teste', 'email' => 'select@example.org', 'tipo_organizacao' => 'Valor legado', 'identidade_genero' => 'Outro', 'identidade_genero_outro' => 'Descrição pessoal']]);
        $response = $this->get(route('usuarios.atualizacao-cadastral.preview', ['session_key' => $key]));
        $response->assertOk()->assertViewHas('selectOptions', fn ($options) => $options['tipo_organizacao'] === config('engaja.organizacoes') && $options['raca_cor'] === config('engaja.demograficos.raca_cor.opcoes'));
        $data = $response->viewData('analysis');
        foreach (['tipo_organizacao', 'tag', 'identidade_genero', 'municipio'] as $field) {
            $html = view('usuarios.atualizacao-cadastral._field', [
                'field' => $field, 'label' => $field, 'index' => 0, 'row' => $data['rows'][0],
                'selectOptions' => $response->viewData('selectOptions'), 'municipios' => $response->viewData('municipios'),
            ])->render();
            $this->assertStringContainsString('<select', $html);
            $this->assertStringContainsString('form="editar-cadastro"', $html);
            if ($field === 'tipo_organizacao') {
                $this->assertStringContainsString('value="Valor legado" selected', $html);
            }
            if ($field === 'identidade_genero') {
                $this->assertStringContainsString('value="Descrição pessoal"', $html);
                $this->assertStringContainsString('data-outro-input', $html);
            }
        }
        $this->post(route('usuarios.atualizacao-cadastral.save'), [
            'session_key' => $key, 'rows' => [0 => ['tipo_organizacao' => 'Universidade', 'raca_cor' => 'Parda']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Universidade', session($key)['rows'][0]['tipo_organizacao']);
        $this->assertSame('Parda', session($key)['rows'][0]['raca_cor']);
        $this->assertSame('Descrição pessoal', session($key)['rows'][0]['identidade_genero_outro']);
    }

    public function test_previa_compartilha_municipios_sem_repetir_lista_em_cada_linha(): void
    {
        $key = $this->preview([['nome' => 'Pessoa', 'email' => 'memoria@example.org']]);
        $response = $this->get(route('usuarios.atualizacao-cadastral.preview', ['session_key' => $key]))->assertOk();
        $municipios = collect(range(1, 5600))->map(fn ($id) => (object) [
            'id' => $id, 'nome' => 'Municipio '.$id, 'nome_com_estado' => 'Municipio '.$id.' / CE',
            'estado' => (object) ['sigla' => 'CE', 'nome' => 'Ceará'],
        ]);
        $analysis = $response->viewData('analysis');
        $pageRows = array_fill(0, 50, $analysis['rows'][0]);
        $html = view('usuarios.atualizacao-cadastral.preview', [
            'rows' => new LengthAwarePaginator($pageRows, 50, 50),
            'analysis' => $analysis, 'sessionKey' => $key, 'columns' => AtualizacaoCadastralService::columns(),
            'municipios' => $municipios, 'selectOptions' => $response->viewData('selectOptions'),
        ])->render();
        $this->assertSame(1, substr_count($html, 'data-id="5600"'));
        $this->assertLessThan(3000000, strlen($html));
        $this->assertStringContainsString('id="opcoes-municipios-cadastro"', $html);
    }

    public function test_sexo_atualiza_grupo_preserva_vazio_e_valida_opcoes(): void
    {
        $a = $this->person();
        $b = $this->person();
        $this->assertSame('Não preenchido', $a->participante->fresh()->sexo);
        $key = $this->preview([['cpf' => '52998224725', 'sexo' => ' feminino ']]);
        $this->get(route('usuarios.atualizacao-cadastral.preview', ['session_key' => $key]))
            ->assertOk()->assertViewHas('selectOptions', fn ($options) => $options['sexo'] === Participante::SEXOS);
        $this->confirm($key)->assertSessionHasNoErrors();
        foreach ([$a, $b] as $user) {
            $this->assertSame('Feminino', $user->participante->fresh()->sexo);
        }
        $this->confirm($this->preview([['cpf' => '52998224725', 'sexo' => '']]))->assertSessionHasNoErrors();
        $this->assertSame('Feminino', $a->participante->fresh()->sexo);
        $this->confirm($this->preview([['cpf' => '52998224725', 'sexo' => 'invalido']]))->assertSessionHasErrors('rows');
        $this->assertSame('Feminino', $a->participante->fresh()->sexo);
        $this->confirm($this->preview([['cpf' => '52998224725', 'sexo' => 'Não preenchido']]))->assertSessionHasNoErrors();
        $this->assertSame('Não preenchido', $b->participante->fresh()->sexo);
        $this->confirm($this->preview([['nome' => 'Novo', 'email' => 'sexo@example.org', 'sexo' => 'Masculino']]))->assertSessionHasNoErrors();
        $this->assertSame('Masculino', User::where('email', 'sexo@example.org')->firstOrFail()->participante->sexo);
    }

    private function person(string $name = 'Jose da Silva', ?string $cpf = '52998224725'): User
    {
        $user = User::factory()->create(['name' => $name, 'sistema_origem' => 'engaja']);
        $user->participante->update(['cpf' => $cpf]);

        return $user;
    }

    private function preview(array $rows): string
    {
        $key = 'atualizacao_cadastral_'.Str::uuid();
        session([$key => ['user_id' => $this->admin->id, 'rows' => $rows]]);

        return $key;
    }

    private function confirm(string $key): TestResponse
    {
        return $this->post(route('usuarios.atualizacao-cadastral.confirmar'), ['session_key' => $key]);
    }
}
