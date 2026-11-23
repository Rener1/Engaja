<?php

namespace Tests\Concerns;

use App\Enums\OrigemAutorizacaoImagem;
use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Presenca;
use App\Models\User;
use App\Services\AutorizacaoImagemService;
use Database\Seeders\RolesPermissionsSeeder;

trait InteractsWithAutorizacaoImagem
{
    private User $pessoa;

    private User $gestor;

    private Evento $acao;

    private Atividade $momento;

    private function prepararAutorizacoes(): void
    {
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
        $this->pessoa = $this->pessoaComDados(['name' => 'Participante desta ação']);
        $this->pessoa->participante->update(['cpf' => '01234567890']);
        $this->gestor = $this->pessoaComDados();
        $this->gestor->assignRole('administrador');
        $this->acao = Evento::factory()->create(['user_id' => $this->gestor->id, 'nome' => 'Ação específica de teste']);
        $this->momento = Atividade::factory()->create(['evento_id' => $this->acao->id, 'presenca_ativa' => true]);
    }

    private function pessoaComDados(array $dados = []): User
    {
        return User::factory()->create([
            'force_password_change' => false,
            'identidade_genero' => 'Homem Cisgênero', 'raca_cor' => 'Parda', 'comunidade_tradicional' => 'Não',
            'faixa_etaria' => 'Adulto (18 a 59 anos)', 'pcd' => 'Não', 'orientacao_sexual' => 'Heterossexual',
            ...$dados,
        ]);
    }

    private function vinculo(User $usuario, ?Atividade $momento = null, bool $presente = true): ?Presenca
    {
        $momento ??= $this->momento;
        $inscricao = Inscricao::create(['participante_id' => $usuario->participante->id, 'evento_id' => $momento->evento_id, 'atividade_id' => $momento->id]);

        return $presente ? Presenca::create(['inscricao_id' => $inscricao->id, 'atividade_id' => $momento->id, 'status' => 'presente']) : null;
    }

    private function respostaDaGestao(User $pessoa, bool $decisao): void
    {
        app(AutorizacaoImagemService::class)->registrar($pessoa->participante, $this->acao, $decisao,
            OrigemAutorizacaoImagem::Gestao, $this->gestor, versaoEsperada: 0);
    }
}
