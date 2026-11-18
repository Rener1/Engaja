<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\Cartas\CadastroRealizadoComSucessoNotification;
use App\Support\SistemaContext;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.engaja');
        Paginator::defaultSimpleView('vendor.pagination.engaja-simple');

        Event::listen(Verified::class, function (Verified $event) {
            if ($event->user instanceof User && $event->user->isCartasUser()) {
                $event->user->notify(new CadastroRealizadoComSucessoNotification);
            }
        });

        /*
         * O middleware 'guest' padrão redireciona usuários já autenticados para
         * route('dashboard') (a home do app principal). Um usuário do sistema
         * Cartas já logado que acesse uma rota guest (ex: cartas.login,
         * cartas.register via os links da apresentacao) cairia lá e seria
         * barrado com 403 pelo EnsureSistemaAccess, por estar fora do prefixo
         * /cartas. Redireciona para o dashboard correto conforme o sistema.
         */
        RedirectIfAuthenticated::redirectUsing(fn ($request) => SistemaContext::homeRoute($request->user()));

        $this->registerPdfMacros();
        $this->configureRemotePdfRendering();
    }

    /**
     * Em produção, aponta o Browsershot para o Chromium remoto (browserless) e
     * aplica um timeout amplo (config dashboard.pdf.timeout, padrão 120s) para
     * suportar relatórios extensos sem estourar o timeout default (~60s).
     *
     * Ativado apenas quando LARAVEL_PDF_REMOTE_HOST está definido (via .env de
     * produção); ausente em local → Browsershot local, comportamento inalterado.
     *
     * Usa o builder default do spatie/laravel-pdf, herdado por todos os Pdf::view(),
     * cobrindo todos os call sites sem alterá-los nem o macro withAlfaEjaBrand().
     */
    private function configureRemotePdfRendering(): void
    {
        $host = config('laravel-pdf.browsershot.remote_instance.host');

        if (! $host) {
            return;
        }

        $port = (int) config('laravel-pdf.browsershot.remote_instance.port', 3000);
        $timeout = (int) config('dashboard.pdf.timeout');

        Pdf::default()->withBrowsershot(function (Browsershot $browsershot) use ($host, $port, $timeout) {
            $browsershot
                ->setRemoteInstance($host, $port)
                ->noSandbox()
                ->timeout($timeout);
        });
    }

    private function registerPdfMacros(): void
    {
        /*
         * Aplica o cabeçalho e rodapé institucionais via API nativa do PdfBuilder
         * (lida pelo BrowsershotDriver como headerTemplate/footerTemplate do Puppeteer)
         * e define as margens que reservam o espaço para eles.
         *
         * Uso:
         *   Portrait  → ->withAlfaEjaBrand()                   (margens padrão: 28 14 22 14)
         *   Landscape → ->withAlfaEjaBrand(35, 10, 25, 10)
         */
        PdfBuilder::macro('withAlfaEjaBrand', function (
            int $marginTop = 28,
            int $marginRight = 14,
            int $marginBottom = 22,
            int $marginLeft = 14,
        ): PdfBuilder {
            /** @var PdfBuilder $this */
            $this->margins($marginTop, $marginRight, $marginBottom, $marginLeft);

            $headerPath = public_path('images/Alfa-Eja Header.png');

            if (! file_exists($headerPath)) {
                return $this;
            }

            $headerB64 = 'data:image/png;base64,'.base64_encode(file_get_contents($headerPath));
            $footerPath = public_path('images/Alfa-Eja Footer.png');

            /*
             * Posicionamento absoluto garante que o header fique colado ao topo
             * e o footer centralizado, independente do espaçamento padrão do
             * contexto isolado em que o Puppeteer renderiza header e footer.
             */
            $reset = '<style>'
                .'*{margin:0;padding:0;box-sizing:border-box}'
                .'html,body{width:100%;height:100%;font-size:0;line-height:0;overflow:hidden}'
                .'</style>';

            $this->headerHtml(
                "<html><head>{$reset}</head>"
                ."<body style='position:relative'>"
                ."<img src='{$headerB64}' style='position:absolute;top:0;left:0;width:100%;display:block'>"
                .'</body></html>'
            );

            if (file_exists($footerPath)) {
                $footerB64 = 'data:image/png;base64,'.base64_encode(file_get_contents($footerPath));
                $this->footerHtml(
                    "<html><head>{$reset}</head>"
                    ."<body style='position:relative;width:100%;height:100%'>"
                    ."<img src='{$footerB64}' style='position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:61mm;display:block'>"
                    .'</body></html>'
                );
            }

            return $this;
        });
    }
}
