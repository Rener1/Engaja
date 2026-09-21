<?php

use App\Http\Controllers\AgendamentoController;
use App\Http\Controllers\AgendamentoEfetivacaoController;
use App\Http\Controllers\AgendamentoNotificacaoController;
use App\Http\Controllers\AgendamentoParticipanteController;
use App\Http\Controllers\AtividadeAcaoController;
use App\Http\Controllers\AtividadeController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\AutorizacaoImagemImportController;
use App\Http\Controllers\AvaliacaoAtividadeController;
use App\Http\Controllers\AvaliacaoConsolidadaController;
use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\Cartas\AuthController as CartasAuthController;
use App\Http\Controllers\Cartas\CartaController as CartasCartaController;
use App\Http\Controllers\Cartas\CartaViewerDiagnosticController as CartasViewerDiagnosticController;
use App\Http\Controllers\Cartas\UserManagementController as CartasUserManagementController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DimensaoController;
use App\Http\Controllers\EscalaController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\IndicadorController;
use App\Http\Controllers\InscricaoController;
use App\Http\Controllers\ModeloCertificadoController;
use App\Http\Controllers\MunicipioController;
use App\Http\Controllers\PainelGerencialController;
use App\Http\Controllers\ParticipantesExclusivosController;
use App\Http\Controllers\PresencaController;
use App\Http\Controllers\PresencaImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestaoController;
use App\Http\Controllers\RegiaoController;
use App\Http\Controllers\RelatorioQuantitativoController;
use App\Http\Controllers\TemplateAvaliacaoController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\UsuariosSemVinculoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('cartas')->name('cartas.')->group(function () {
    Route::get('/localidades/estados', [CartasAuthController::class, 'estados'])->name('localidades.estados');
    Route::get('/localidades/estados/{estadoId}/municipios', [CartasAuthController::class, 'municipios'])->whereNumber('estadoId')->name('localidades.municipios');
    Route::get('/termos', [CartasAuthController::class, 'terms'])->name('terms');
    Route::post('/termos', [CartasAuthController::class, 'acceptTerms'])->name('terms.accept');

    Route::get('/', [CartasAuthController::class, 'apresentacao'])->name('apresentacao');

    Route::get('/formulario-avaliacao/{avaliacao}', [AvaliacaoController::class, 'formularioAvaliacao'])->name('avaliacao.formulario');
    Route::post('/formulario-avaliacao/{avaliacao}', [AvaliacaoController::class, 'responderFormulario'])->name('avaliacao.formulario.responder');
    Route::get('/formulario-avaliacao/{avaliacao}/obrigado', [AvaliacaoController::class, 'formularioAvaliacaoObrigado'])->name('avaliacao.formulario.obrigado');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [CartasAuthController::class, 'login'])->name('login');
        Route::post('/login', [CartasAuthController::class, 'authenticate'])->name('login.store');
        Route::get('/cadastro', [CartasAuthController::class, 'register'])->name('register');
        Route::post('/cadastro', [CartasAuthController::class, 'storeRegister'])
            ->middleware('throttle:6,1')
            ->name('register.store');
        Route::get('/cadastro/verificar-reativacao', [CartasAuthController::class, 'reactivationPending'])
            ->name('register.reactivate.pending');
        Route::get('/recuperar-senha', [CartasAuthController::class, 'forgotPassword'])->name('password.request');
        Route::post('/recuperar-senha', [CartasAuthController::class, 'sendResetLink'])->name('password.email');
        Route::get('/resetar-senha/{token}', [CartasAuthController::class, 'resetPassword'])->name('password.reset');
        Route::post('/resetar-senha', [CartasAuthController::class, 'storeNewPassword'])->name('password.store');
    });

    /*
     * Fora dos grupos 'guest' e 'auth': o link enviado por e-mail costuma ser
     * aberto em outro navegador/celular, sem sessão. A identidade vem da URL
     * assinada (id + sha1 do e-mail), validada pelo middleware 'signed'.
     */
    Route::get('/verificar-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    /*
     * Confirmação de reativação de conta desativada (ver AuthController::
     * requestReactivation). Aberta e sem sessão pelo mesmo motivo da rota
     * acima: a identidade vem só da assinatura da URL.
     */
    Route::get('/cadastro/reativar/{user}', [CartasAuthController::class, 'confirmReactivation'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('register.reactivate');

    Route::middleware('auth')->group(function () {
        Route::get('/verificar-email', [CartasAuthController::class, 'verificationNotice'])->name('verification.notice');
        Route::post('/verificar-email/reenviar', [CartasAuthController::class, 'resendVerification'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
        Route::middleware('cartas.verified')->group(function () {
            Route::post('/welcome-seen', [CartasAuthController::class, 'markWelcomeSeen'])->name('welcome.seen');
            Route::get('/dashboard', [CartasCartaController::class, 'dashboard'])->name('dashboard');
            Route::get('/usuarios', [CartasUserManagementController::class, 'index'])->name('usuarios.index');
            Route::get('/usuarios/{managedUser}/editar', [CartasUserManagementController::class, 'edit'])->name('usuarios.edit');
            Route::put('/usuarios/{managedUser}', [CartasUserManagementController::class, 'update'])->name('usuarios.update');
            Route::post('/cartas', [CartasCartaController::class, 'store'])->name('cartas.store');
            Route::post('/voluntario/cartas', [CartasCartaController::class, 'storeVolunteerLetter'])->name('voluntario.cartas.store');
            Route::get('/cartas/download-lote', [CartasCartaController::class, 'downloadBatch'])->name('download-batch');
            Route::get('/cartas/{carta}', [CartasCartaController::class, 'show'])->name('cartas.show');
            Route::post('/cartas/{carta}/mensagens', [CartasCartaController::class, 'storeMessage'])->name('cartas.mensagens.store');
            Route::post('/cartas/{carta}/responder', [CartasCartaController::class, 'respond'])->name('cartas.respond');
            Route::delete('/cartas/{carta}', [CartasCartaController::class, 'destroy'])->name('cartas.destroy');
            Route::post('/mensagens/{mensagem}/aprovar', [CartasCartaController::class, 'approveMessage'])->name('mensagens.approve');
            Route::post('/mensagens/{mensagem}/solicitar-ajuste', [CartasCartaController::class, 'requestMessageAdjustment'])->name('mensagens.adjustment');
            Route::put('/mensagens/{mensagem}/ajustar', [CartasCartaController::class, 'updateAdjustedMessage'])->name('mensagens.update-adjustment');
            Route::get('/mensagens/{mensagem}/preview', [CartasCartaController::class, 'preview'])->name('mensagens.preview');
            Route::get('/mensagens/{mensagem}/download', [CartasCartaController::class, 'download'])->name('mensagens.download');
            Route::post('/diagnostico/visualizador', [CartasViewerDiagnosticController::class, 'store'])->name('diagnostico.visualizador');
            Route::post('/usuarios/{managedUser}/enviar-avaliacao', [CartasUserManagementController::class, 'enviarAvaliacao'])->name('usuarios.enviar-avaliacao');
        });
    });
});

Route::middleware(['auth', 'role:administrador|gerente|eq_pedagogica|articulador'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'home'])->middleware(['auth', 'verified'])->name('dashboard');
    Route::get('/dashboards/presencas', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboards.presencas');
    Route::get('/dashboards/presencas/{atividade}/detalhes', [DashboardController::class, 'presencasDetalhes'])->middleware(['auth', 'verified'])->name('dashboards.presencas.detalhes');
    Route::get('/dashboard/export', [DashboardController::class, 'export'])->middleware(['auth', 'verified'])->name('dashboard.export');
    Route::get('/dashboard/export-excel', [DashboardController::class, 'exportExcel'])->middleware(['auth', 'verified'])->name('dashboard.export.excel');
    Route::get('/dashboards/avaliacoes', [DashboardController::class, 'avaliacoes'])->middleware(['auth', 'verified'])->name('dashboards.avaliacoes');
    Route::get('/dashboards/avaliacoes/dados', [DashboardController::class, 'avaliacoesData'])->middleware(['auth', 'verified'])->name('dashboards.avaliacoes.data');
    Route::get('/dashboards/avaliacoes/pdf', [DashboardController::class, 'avaliacoesPdf'])->middleware(['auth', 'verified'])->name('dashboards.avaliacoes.pdf');
    Route::get('/dashboards/avaliacoes/dados/limesurvey/list-questions', [DashboardController::class, 'limesurveyListQuestions'])->middleware(['auth', 'verified'])->name('dashboards.avaliacoes.limesurvey.list-questions');
    Route::get('/dashboards/avaliacoes/dados/limesurvey/list-participants', [DashboardController::class, 'limesurveyListParticipants'])->middleware(['auth', 'verified'])->name('dashboards.avaliacoes.limesurvey.list-participants');
    Route::get('/dashboards/leitura-mundo', [DashboardController::class, 'leituraMundo'])->middleware(['auth', 'verified'])->name('dashboards.leitura-mundo');
    Route::get('/dashboards/bi', [DashboardController::class, 'bi'])->middleware(['auth', 'verified'])->name('dashboards.bi');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/profile/demographics', [ProfileController::class, 'completeDemographics'])->name('profile.complete-demographics');
    Route::post('/profile/photo-prompt', [ProfileController::class, 'storeProfilePhotoPrompt'])->name('profile.photo-prompt.store');
    Route::post('/profile/photo-prompt/skip', [ProfileController::class, 'skipProfilePhotoPrompt'])->name('profile.photo-prompt.skip');

    Route::post('/eventos/{evento}/inscrever', [InscricaoController::class, 'inscrever'])->name('inscricoes.inscrever');
    Route::delete('/eventos/{evento}/cancelar', [InscricaoController::class, 'cancelar'])->name('inscricoes.cancelar');

    Route::post('/atividades/{atividade}/presenca/checkin', [AtividadeController::class, 'checkin'])->name('atividades.presenca.checkin');
});

Route::middleware(['auth', 'permission:presenca.abrir'])->group(function () {
    Route::patch('/atividades/{atividade}/presenca/toggle', [AtividadeController::class, 'togglePresenca'])->name('atividades.presenca.toggle');
});

Route::middleware(['auth', 'role:administrador|gerente|eq_pedagogica|articulador'])->group(function () {
    Route::get('/atividades/{atividade}/presencas/import', [PresencaImportController::class, 'import'])->name('atividades.presencas.import');
    Route::post('/atividades/{atividade}/presencas/import', [PresencaImportController::class, 'cadastro'])->name('atividades.presencas.cadastro');

    Route::get('/atividades/{atividade}/presencas/preview', [PresencaImportController::class, 'preview'])->name('atividades.presencas.preview');
    Route::post('/atividades/{atividade}/presencas/savepage', [PresencaImportController::class, 'savePage'])->name('atividades.presencas.savepage');
    Route::post('/atividades/{atividade}/presencas/confirmar', [PresencaImportController::class, 'confirmar'])->name('atividades.presencas.confirmar');

    Route::post('/atividades/{atividade}/checklist', [AtividadeController::class, 'saveChecklist'])->name('atividades.checklist.save');

    Route::get('/meus-certificados', [ProfileController::class, 'certificados'])->name('profile.certificados');

    Route::get('/atividades/{atividade}/lista-presenca-pdf', [AtividadeController::class, 'downloadListaPresencaPdf'])
        ->name('atividades.lista-presenca.pdf');

    Route::get('/atividades/{atividade}/lista-presenca-simples-pdf', [AtividadeController::class, 'downloadListaPresencaSimplesView'])
        ->name('atividades.lista-presenca-simples.pdf');

    Route::get('/atividades/{atividade}/lista-autorizacao-pdf', [AtividadeController::class, 'downloadListaAutorizacaoImagemPdf'])
        ->name('atividades.lista-autorizacao.pdf');

    Route::get('/atividades/{atividade}/diario', [AtividadeController::class, 'diario'])->name('atividades.diario');
    Route::post('/atividades/{atividade}/diario', [AtividadeController::class, 'salvarDiario'])->name('atividades.diario.salvar');
});

Route::middleware(['auth', 'role:administrador|gerente|eq_pedagogica|articulador'])->group(function () {
    Route::get('/eventos/{evento}/inscricoes/import', [InscricaoController::class, 'import'])->name('inscricoes.import');
    Route::post('/eventos/{evento}/inscricoes/import', [InscricaoController::class, 'cadastro'])->name('inscricoes.cadastro');
    Route::get('/eventos/{evento}/inscricoes/import-moodle', [InscricaoController::class, 'moodleImport'])->name('inscricoes.moodle.import');
    Route::get('/eventos/{evento}/inscricoes/import-moodle/modelo-momentos', [InscricaoController::class, 'moodleMomentTemplateDownload'])->name('inscricoes.moodle.template.momentos');
    Route::post('/eventos/{evento}/inscricoes/import-moodle', [InscricaoController::class, 'moodleUpload'])->name('inscricoes.moodle.upload');
    Route::get('/eventos/{evento}/inscricoes/import-moodle/preview', [InscricaoController::class, 'moodlePreview'])->name('inscricoes.moodle.preview');
    Route::post('/eventos/{evento}/inscricoes/import-moodle/confirmar', [InscricaoController::class, 'moodleConfirm'])->name('inscricoes.moodle.confirm');
    Route::get('/eventos/{evento}/inscricoes/preview', [InscricaoController::class, 'preview'])->name('inscricoes.preview');
    Route::post('/eventos/{evento}/inscricoes/preview/save', [InscricaoController::class, 'savePage'])->name('inscricoes.preview.save');
    Route::post('/eventos/{evento}/inscricoes/confirmar', [InscricaoController::class, 'confirmar'])->name('inscricoes.confirmar');
    Route::get('/eventos/{evento}/inscricoes/selecionar', [InscricaoController::class, 'selecionar'])->name('inscricoes.selecionar');
    Route::post('/eventos/{evento}/inscricoes/selecionar', [InscricaoController::class, 'selecionarStore'])->name('inscricoes.selecionar.store');
});

Route::middleware(['auth', 'permission:inscricao.ver'])->group(function () {
    Route::get('/eventos/{evento}/inscritos', [InscricaoController::class, 'inscritos'])->name('inscricoes.inscritos');
});

Route::middleware(['auth'])->group(function () {
    // rotas que SME não pode acessar
    Route::middleware(['role:administrador|gerente|eq_pedagogica|articulador'])->group(function () {
        Route::resource('dimensaos', DimensaoController::class);
        Route::resource('indicadors', IndicadorController::class);
        Route::post('evidencias/{evidencia}/duplicar', [EvidenciaController::class, 'duplicar'])->name('evidencias.duplicar');
        Route::resource('evidencias', EvidenciaController::class);
        Route::resource('escalas', EscalaController::class);
        Route::resource('questaos', QuestaoController::class);
        Route::resource('templates-avaliacao', TemplateAvaliacaoController::class)
            ->parameters(['templates-avaliacao' => 'template']);
        Route::prefix('avaliacoes-universais')
            ->name('avaliacoes-universais.')
            ->controller(AvaliacaoController::class)
            ->group(function () {
                Route::get('/', 'universaisIndex')->name('index');
                Route::get('/create', 'universaisCreate')->name('create');
                Route::post('/', 'universaisStore')->name('store');
                Route::get('/{avaliacao}', 'universaisShow')->name('show');
                Route::get('/{avaliacao}/edit', 'universaisEdit')->name('edit');
                Route::put('/{avaliacao}', 'universaisUpdate')->name('update');
                Route::delete('/{avaliacao}', 'universaisDestroy')->name('destroy');
                Route::patch('/{avaliacao}/status-formulario', 'universaisToggleFormulario')->name('toggle-formulario');
                Route::get('/{avaliacao}/link-qrcode', 'universaisLinkQrCode')->name('link-qrcode');
            });
        Route::resource('avaliacoes', AvaliacaoController::class)
            ->parameters(['avaliacoes' => 'avaliacao']);
        Route::get('avaliacoes/{avaliacao}/transcricao', [AvaliacaoController::class, 'transcricao'])->name('avaliacoes.transcricao');
        Route::post('avaliacoes/{avaliacao}/transcricao', [AvaliacaoController::class, 'transcricaoBusca'])->name('avaliacoes.transcricao.busca');
        Route::post('avaliacoes/{avaliacao}/transcricao/cadastrar', [AvaliacaoController::class, 'transcricaoCadastrar'])->name('avaliacoes.transcricao.cadastrar');
        Route::get('avaliacoes-usuarios/sugestoes', [AvaliacaoController::class, 'usuariosSugestao'])->name('avaliacoes.usuarios.sugestoes');
        Route::get('avaliacoes/{avaliacao}/respostas', [AvaliacaoController::class, 'respostas'])->name('avaliacoes.respostas');
        Route::get('avaliacoes/{avaliacao}/respostas/{submissao}', [AvaliacaoController::class, 'respostasMostrar'])->name('avaliacoes.respostas.mostrar');
        Route::get('avaliacoes/{avaliacao}/ficha-pdf', [AvaliacaoController::class, 'downloadFichaPdf'])->name('avaliacoes.ficha-pdf');
        Route::get('atividades/{atividade}/avaliacoes', [AvaliacaoController::class, 'resultadosAtividade'])->name('atividades.avaliacoes');
        Route::get('atividades/{atividade}/avaliacoes/pdf', [AvaliacaoController::class, 'downloadResultadosPdf'])->name('atividades.avaliacoes.pdf');
    });

    Route::middleware(['role:administrador|gerente|eq_pedagogica'])->group(function () {
        Route::get('agendamentos/efetivacoes', [AgendamentoEfetivacaoController::class, 'index'])->name('agendamentos.efetivacoes.index');
        Route::get('agendamentos/efetivados', [AgendamentoEfetivacaoController::class, 'efetivados'])->name('agendamentos.efetivados.index');
        Route::get('agendamentos/{agendamento}/efetivar', [AgendamentoEfetivacaoController::class, 'create'])->name('agendamentos.efetivacoes.create');
        Route::post('agendamentos/{agendamento}/efetivar/confirmar', [AgendamentoEfetivacaoController::class, 'confirm'])->name('agendamentos.efetivacoes.confirm');
        Route::post('agendamentos/{agendamento}/efetivar', [AgendamentoEfetivacaoController::class, 'store'])->name('agendamentos.efetivacoes.store');

    });

    // ROTAS ABAIXO SME PODEM ACESSAR, POR ISSO A DIVISÃO
    Route::middleware(['role:administrador|gerente|eq_pedagogica|articulador|SME'])->group(function () {
        Route::resource('agendamentos', AgendamentoController::class);
        Route::prefix('agendamentos/{agendamento}/participantes')
            ->name('agendamentos.participantes.')
            ->group(function () {
                Route::get('/', [AgendamentoParticipanteController::class, 'index'])->name('index');
                Route::get('/create', [AgendamentoParticipanteController::class, 'create'])->name('create');
                Route::post('/', [AgendamentoParticipanteController::class, 'store'])->name('store');
                Route::get('/{participante}/edit', [AgendamentoParticipanteController::class, 'edit'])->name('edit');
                Route::put('/{participante}', [AgendamentoParticipanteController::class, 'update'])->name('update');
                Route::delete('/{participante}', [AgendamentoParticipanteController::class, 'destroy'])->name('destroy');

                Route::get('/importar', [AgendamentoParticipanteController::class, 'import'])->name('import');
                Route::post('/importar', [AgendamentoParticipanteController::class, 'upload'])->name('upload');
                Route::get('/importar/preview', [AgendamentoParticipanteController::class, 'preview'])->name('import.preview');
                Route::post('/importar/confirmar', [AgendamentoParticipanteController::class, 'confirm'])->name('import.confirm');
            });
        Route::resource('atividade-acoes', AtividadeAcaoController::class)
            ->parameters(['atividade-acoes' => 'atividadeAcao']);
    });
});

Route::middleware(['auth', 'role:administrador|gerente'])
    ->prefix('certificados')
    ->name('certificados.')
    ->group(function () {
        Route::resource('modelos', ModeloCertificadoController::class)
            ->parameters(['modelos' => 'modelo']);
        Route::post('emitir/preparar', [CertificadoController::class, 'prepararEmissao'])->name('emitir.preparar');
        Route::get('emitir/preview-lista', [CertificadoController::class, 'previewLista'])->name('emitir.preview_lista');
        Route::post('emitir', [CertificadoController::class, 'emitir'])->name('emitir');
    });

Route::middleware(['auth', 'role:administrador'])
    ->group(function () {
        Route::resource('regioes', RegiaoController::class)
            ->parameters(['regioes' => 'regiao'])
            ->except(['create', 'edit', 'show']);
        Route::resource('estados', EstadoController::class)
            ->parameters(['estados' => 'estado'])
            ->except(['create', 'edit', 'show']);
        Route::resource('municipios', MunicipioController::class)
            ->parameters(['municipios' => 'municipio'])
            ->except(['create', 'edit', 'show']);
    });

Route::middleware(['auth', 'role:administrador|gerente|eq_pedagogica|articulador'])
    ->prefix('usuarios')
    ->name('usuarios.')
    ->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('cadastrar', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('verificar', [UserManagementController::class, 'verificarIndex'])->name('verificar.index');
        Route::post('verificar', [UserManagementController::class, 'verificarProcessar'])->name('verificar.processar');
        Route::get('verificar/exportar/{format}', [UserManagementController::class, 'verificarExportar'])->name('verificar.exportar');
        Route::middleware('role:administrador|gerente')->group(function () {
            Route::get('participantes-exclusivos', [ParticipantesExclusivosController::class, 'index'])->name('participantes-exclusivos.index');
            Route::get('participantes-exclusivos/resultado', [ParticipantesExclusivosController::class, 'resultado'])->name('participantes-exclusivos.resultado');
            Route::get('participantes-exclusivos/exportar', [ParticipantesExclusivosController::class, 'exportar'])->name('participantes-exclusivos.exportar');
            Route::get('sem-vinculo', [UsuariosSemVinculoController::class, 'index'])->name('sem-vinculo.index');
            Route::get('sem-vinculo/exportar', [UsuariosSemVinculoController::class, 'exportar'])->name('sem-vinculo.exportar');
        });
        Route::middleware('role:administrador')->group(function () {
            Route::get('notificacoes-agendamento', [AgendamentoNotificacaoController::class, 'index'])->name('notificacoes-agendamento.index');
            Route::post('{managedUser}/notificacoes-agendamento', [AgendamentoNotificacaoController::class, 'toggle'])->name('notificacoes-agendamento.toggle');
        });
        Route::get('{managedUser}/editar', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('{managedUser}', [UserManagementController::class, 'update'])->name('update');
        Route::delete('{managedUser}', [UserManagementController::class, 'destroy'])
            ->middleware('role:administrador|gerente')
            ->name('destroy');
        Route::post('{managedUser}/redefinir-senha', [UserManagementController::class, 'resetPassword'])
            ->middleware('role:administrador')
            ->name('password.reset');
        Route::post('certificados/emitir', [CertificadoController::class, 'emitirPorParticipantes'])
            ->middleware('role:administrador|gerente')
            ->name('certificados.emitir');
        Route::get('exportar', [UserManagementController::class, 'export'])->name('export');
        Route::get('autorizacoes-imagem/importar', [AutorizacaoImagemImportController::class, 'import'])->name('autorizacoes.import');
        Route::post('autorizacoes-imagem/importar', [AutorizacaoImagemImportController::class, 'upload'])->name('autorizacoes.upload');
        Route::get('autorizacoes-imagem/preview', [AutorizacaoImagemImportController::class, 'preview'])->name('autorizacoes.preview');
        Route::post('autorizacoes-imagem/confirmar', [AutorizacaoImagemImportController::class, 'confirmar'])->name('autorizacoes.confirmar');
    });

Route::middleware(['auth', 'role:administrador|gerente|eq_pedagogica|articulador'])->group(function () {
    Route::get('/avaliacoes-consolidadas', [AvaliacaoConsolidadaController::class, 'index'])
        ->name('avaliacoes-consolidadas.index');
    Route::get('/avaliacoes-consolidadas/pdf', [AvaliacaoConsolidadaController::class, 'pdf'])
        ->name('avaliacoes-consolidadas.pdf');
    Route::get('/eventos/{evento}/relatorios', [EventoController::class, 'relatorios'])
        ->name('eventos.relatorios');
    Route::get('/eventos/{evento}/avaliacoes/consolidado', [EventoController::class, 'avaliacoesConsolidadas'])
        ->name('eventos.avaliacoes.consolidado');
});

Route::middleware(['auth', 'role:administrador|gerente|eq_pedagogica|articulador'])->group(function () {
    Route::controller(AvaliacaoAtividadeController::class)
        ->prefix('atividades/{atividade}/relatorio')
        ->name('avaliacao-atividade.')
        ->group(function () {
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/edit', 'edit')->name('edit');
            Route::put('/', 'update')->name('update');
            Route::get('/pdf', 'downloadOwn')->name('download-own');
        });

    Route::get('/relatorios-avaliacao/{relatorio}/pdf', [AvaliacaoAtividadeController::class, 'download'])
        ->name('avaliacao-atividade.download');
});

Route::middleware(['auth', 'role:administrador|gerente|eq_pedagogica|articulador'])->group(function () {
    Route::get('/relatorios-avaliacao', [AvaliacaoAtividadeController::class, 'index'])
        ->name('avaliacao-atividade.index');

    Route::get('/relatorios-avaliacao/pdf-consolidado-geral', [AvaliacaoAtividadeController::class, 'baixarConsolidadoFiltro'])
        ->name('avaliacao-atividade.download-consolidated');

    Route::get('/relatorios-avaliacao/{relatorio}', [AvaliacaoAtividadeController::class, 'show'])
        ->name('avaliacao-atividade.show');

    Route::get('/atividades/{atividade}/relatorios-avaliacao/pdf-consolidado', [AvaliacaoAtividadeController::class, 'baixarTodosPorAtividade'])
        ->name('avaliacao-atividade.download-all');
});

Route::middleware(['auth', 'role:administrador|gerente|eq_pedagogica|articulador'])->group(function () {
    Route::get('/relatorio-quantitativo', [RelatorioQuantitativoController::class, 'index'])
        ->name('relatorio-quantitativo.index');
    Route::get('/relatorio-quantitativo/momentos', [RelatorioQuantitativoController::class, 'momentos'])
        ->name('relatorio-quantitativo.momentos');
    Route::get('/relatorio-quantitativo/exportar-momento', [RelatorioQuantitativoController::class, 'exportarMomento'])
        ->name('relatorio-quantitativo.exportar-momento');
    Route::get('/relatorio-quantitativo/exportar-total-geral', [RelatorioQuantitativoController::class, 'exportarTotalGeral'])
        ->name('relatorio-quantitativo.exportar-total-geral');

    Route::get('/painel-gerencial', [PainelGerencialController::class, 'index'])
        ->name('painel-gerencial.index');
    Route::get('/painel-gerencial/dados', [PainelGerencialController::class, 'dados'])
        ->name('painel-gerencial.dados');
    Route::get('/painel-gerencial/momentos', [PainelGerencialController::class, 'momentos'])
        ->name('painel-gerencial.momentos');
    Route::get('/painel-gerencial/exportar', [PainelGerencialController::class, 'exportar'])
        ->name('painel-gerencial.exportar');
});

Route::middleware(['auth', 'role:administrador|gerente|eq_pedagogica|articulador'])->group(function () {
    Route::resource('eventos', EventoController::class);
    Route::get('eventos/{evento}', [EventoController::class, 'show'])->name('eventos.show');
    Route::get('eventos/{evento}/planejamento/pdf', [EventoController::class, 'gerarPdfPlanejamento'])->name('eventos.planejamento.pdf');
    Route::get('eventos/{evento}/cronograma/pdf', [EventoController::class, 'gerarPdfCronograma'])->name('eventos.cronograma.pdf');
    Route::post('eventos/{evento}/duplicate', [EventoController::class, 'duplicate'])->name('eventos.duplicate');
});

Route::resource('eventos.atividades', AtividadeController::class)
    ->parameters(['atividades' => 'atividade'])
    ->shallow();

Route::get('/eventos/{evento_id}/{atividade_id}/cadastro-e-inscricao', [EventoController::class, 'cadastro_inscricao'])->name('evento.cadastro_inscricao');
Route::post('/eventos/cadastro-e-inscricao/store', [EventoController::class, 'store_cadastro_inscricao'])->name('evento.store_cadastro_inscricao');

Route::get('/presenca/{atividade}/confirmar', [PresencaController::class, 'confirmarPresenca'])->name('presenca.confirmar');
Route::post('/presenca/{atividade}/confirmar', [PresencaController::class, 'store'])->name('presenca.store');
Route::post('/presenca/{atividade}/demograficos', [PresencaController::class, 'salvarDemograficosEConfirmar'])->name('presenca.demograficos');

Route::middleware(['auth'])->group(function () {
    Route::get('/meus-certificados', [ProfileController::class, 'certificados'])->name('profile.certificados');
    Route::get('/certificados/preview', [CertificadoController::class, 'preview'])->name('certificados.preview');
    Route::get('/certificados/{certificado}', [CertificadoController::class, 'show'])
        ->whereNumber('certificado')
        ->name('certificados.show');
    Route::get('/certificados/{certificado}/download', [CertificadoController::class, 'download'])
        ->whereNumber('certificado')
        ->name('certificados.download');
    Route::get('/minhas-presencas', [ProfileController::class, 'presencas'])->name('profile.presencas');
});

Route::middleware(['auth', 'permission:certificado.baixar'])->group(function () {
    Route::get('/certificados/emitidos', [CertificadoController::class, 'emitidos'])->name('certificados.emitidos');
    Route::get('/certificados/emitidos/zip', [CertificadoController::class, 'downloadZipEmitidos'])->name('certificados.emitidos.zip');
});

Route::middleware(['auth', 'role:administrador|gerente'])->group(function () {
    Route::get('/certificados/{certificado}/edit', [CertificadoController::class, 'edit'])
        ->whereNumber('certificado')
        ->name('certificados.edit');
    Route::put('/certificados/{certificado}', [CertificadoController::class, 'update'])
        ->whereNumber('certificado')
        ->name('certificados.update');

});

Route::get('/formulario-avaliacao/{avaliacao}', [AvaliacaoController::class, 'formularioAvaliacao'])->name('avaliacao.formulario');
Route::post('/formulario-avaliacao/{avaliacao}', [AvaliacaoController::class, 'responderFormulario'])->name('avaliacao.formulario.responder');
Route::get('/formulario-avaliacao/{avaliacao}/obrigado', [AvaliacaoController::class, 'formularioAvaliacaoObrigado'])->name('avaliacao.formulario.obrigado');
Route::get('/validacao/{codigo}', [CertificadoController::class, 'validar'])->name('certificados.validacao');

require __DIR__.'/auth.php';
