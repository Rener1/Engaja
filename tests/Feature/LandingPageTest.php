<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_ve_a_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('landing')
            ->assertSee('Bem-vindo ao', false)
            ->assertSee('Gestão de Inscrições e Presenças')
            ->assertSee('Sistema de Gestão de Participação e Engajamento.')
            ->assertSee('CNPJ 04.950.603/0001-05');
    }

    public function test_landing_page_aponta_para_login_e_cadastro(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false);
    }

    public function test_landing_page_usa_os_assets_locais(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['engaja-favicon.png', 'ipf-white.png', 'petrobras-white.png', 'landing/hero-foto.jpg'] as $asset) {
            $response->assertSee(asset('images/'.$asset), false);
            $this->assertFileExists(public_path('images/'.$asset));
        }

        $response->assertDontSee('figma.com/api/mcp/asset', false);
    }

    public function test_landing_page_apresenta_os_recursos_em_cards(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('O que você encontra no Engaja')
            ->assertSeeInOrder(['Ações pedagógicas', 'Presença', 'Relatórios'])
            ->assertSee('Organize e acompanhe inscrições para formações, encontros e reuniões.')
            ->assertSee('Controle a participação com check-in via QR Code ou registro manual.')
            ->assertSee('Visualize indicadores de engajamento por ação pedagógica, município ou região.');
    }

    public function test_foto_do_hero_usa_svg_com_mascara_da_forma(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<mask id="landing-foto-mascara"', false)
            ->assertSee('mask="url(#landing-foto-mascara)"', false)
            ->assertDontSee('clip-path="url(#landing-hero-mascara-foto)"', false);
    }

    public function test_usuario_autenticado_continua_na_pagina_inicial_da_aplicacao(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertViewIs('welcome');
    }
}
