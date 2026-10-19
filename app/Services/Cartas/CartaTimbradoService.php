<?php

namespace App\Services\Cartas;

use App\Models\Cartas\CartaMensagem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\FpdiException;

class CartaTimbradoService
{
    /**
     * DPI usado para rasterizar o anexo em imagem (ver `renderUpload()`).
     * O parser gratuito do FPDI não lê PDFs com recursos comuns em geradores
     * modernos (ex.: cross-reference stream comprimida), então o conteúdo do
     * anexo é convertido em imagem via poppler-utils (pdfinfo/pdftoppm) em vez
     * de importado diretamente — só o modelo do papel timbrado, que é nosso e
     * conhecido, passa pelo parser do FPDI.
     */
    private const RASTER_DPI = 200;

    /**
     * Gera o PDF final aplicando o texto digitado sobre o papel timbrado
     * e persiste os metadados do arquivo final na mensagem.
     */
    public function aplicar(CartaMensagem $mensagem): void
    {
        $texto = trim($this->normalizarTexto((string) $mensagem->texto));

        if ($texto === '') {
            throw new RuntimeException('Não há texto para aplicar ao timbrado.');
        }

        $conteudo = $this->render($texto);

        $this->persistirFinal($mensagem, $conteudo);
    }

    /**
     * Gera o PDF final envolvendo o PDF anexado (carta enviada como upload, não
     * digitada) com o papel timbrado — cada página do anexo é escalada e
     * centralizada dentro da mesma área útil usada para o texto digitado — e
     * persiste os metadados do arquivo final na mensagem.
     *
     * Com `$forcar` (edição pelo gestor/admin marcando "aplicar timbrado"), as
     * regras automáticas são ignoradas: o timbrado é aplicado em qualquer carta.
     */
    public function aplicarAnexo(CartaMensagem $mensagem, bool $forcar = false): void
    {
        if (! $forcar) {
            // Cartas de educandos já chegam com o timbrado no próprio PDF; aplicar
            // de novo duplicaria o papel timbrado. Só as dos voluntários são timbradas.
            // E respeita o gestor/admin que desligou o timbrado na edição.
            if ($mensagem->tipo_remetente !== CartaMensagem::TIPO_REMETENTE_VOLUNTARIO || $mensagem->timbrado_desativado) {
                return;
            }
        }

        $original = $mensagem->anexo_original_path;

        if (! $original) {
            throw new RuntimeException('Não há anexo original para aplicar ao timbrado.');
        }

        try {
            $conteudo = $this->renderUpload(Storage::disk('local')->path($original));
        } catch (FpdiException|AnexoIncompativelException $e) {
            // Não travamos o envio da carta se o anexo não puder ser
            // processado (ex.: PDF corrompido, ou poppler-utils indisponível
            // no ambiente) — ela segue sem o timbrado, exibindo o anexo
            // original (mesmo comportamento de antes desta funcionalidade
            // existir).
            Log::warning('Não foi possível aplicar o timbrado ao anexo da carta.', [
                'carta_mensagem_id' => $mensagem->id,
                'erro' => $e->getMessage(),
            ]);

            return;
        }

        $this->persistirFinal($mensagem, $conteudo);
    }

    /**
     * Descarta o PDF final timbrado (arquivo e metadados): a carta volta a ser
     * exibida/baixada pelo anexo original (ou pelo texto, se digitada).
     */
    public function removerTimbrado(CartaMensagem $mensagem): void
    {
        if ($mensagem->arquivo_final_path) {
            Storage::disk('local')->delete($mensagem->arquivo_final_path);
        }

        $mensagem->forceFill([
            'arquivo_final_path' => null,
            'arquivo_final_nome' => null,
            'arquivo_final_mime' => null,
            'arquivo_final_tamanho' => null,
            'timbrado_aplicado_em' => null,
        ])->save();
    }

    private function persistirFinal(CartaMensagem $mensagem, string $conteudo): void
    {
        $path = "cartas/{$mensagem->carta_id}/finais/{$mensagem->id}.pdf";
        Storage::disk('local')->put($path, $conteudo);

        $mensagem->forceFill([
            'arquivo_final_path' => $path,
            'arquivo_final_nome' => "carta-{$mensagem->carta_id}-rodada-{$mensagem->rodada}.pdf",
            'arquivo_final_mime' => 'application/pdf',
            'arquivo_final_tamanho' => strlen($conteudo),
            'timbrado_aplicado_em' => now(),
        ])->save();
    }

    /**
     * Renderiza o texto sobre o papel timbrado e devolve o conteúdo binário
     * do PDF, sem persistir nada. Usado por `aplicar()` e pelo comando
     * `cartas:preview-timbrado` para testar o layout sem passar pelo fluxo
     * completo de carta.
     */
    public function render(string $texto): string
    {
        $texto = $this->normalizarTexto($texto);

        $config = config('cartas.timbrado');
        $modelo = $config['path'];

        if (! is_file($modelo)) {
            throw new RuntimeException("Modelo de papel timbrado não encontrado em: {$modelo}");
        }

        // Reaplica o timbrado em cada página (inclusive nas quebras automáticas)
        // via Header(), que o FPDF chama a cada AddPage().
        $pdf = new class('P', 'mm', 'A5') extends Fpdi
        {
            public $fundoTemplateId = null;

            public function Header(): void
            {
                if ($this->fundoTemplateId !== null) {
                    $this->useTemplate($this->fundoTemplateId, 0, 0, null, null, true);
                }
            }
        };

        $pdf->setSourceFile($modelo);
        $pdf->fundoTemplateId = $pdf->importPage(1);

        $pdf->AddFont($config['font_family'], '', $config['font_file'], $config['font_dir']);

        // Desligado de propósito: cada linha é escrita em várias Cell() (uma
        // por palavra, para permitir justificar). Se o auto page break do
        // FPDF ficasse ligado, ele podia disparar no meio dessas células —
        // cortando a linha ao meio e "vazando" o resto dela para o topo da
        // página seguinte. Por isso a quebra é feita manualmente, sempre
        // entre linhas (ver `precisaQuebrarPagina()`).
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins($config['margin_left'], $config['start_top'], $config['margin_right']);
        $pdf->AddPage();
        $pdf->SetXY($config['margin_left'], $config['start_top']);
        $pdf->SetFont($config['font_family'], '', $config['font_size']);
        $pdf->SetTextColor(45, 45, 45);

        foreach ($this->paragrafos($texto) as $paragrafo) {
            $this->escreverParagrafo($pdf, $config, $paragrafo);
        }

        return $pdf->Output('S');
    }

    /**
     * Envolve cada página de um PDF já existente (carta enviada como anexo) com
     * o papel timbrado: a página do modelo é usada como fundo (via `Header()`,
     * igual a `render()`) e cada página do anexo é rasterizada em imagem (ver
     * `RASTER_DPI`) e sobreposta, escalada mantendo a proporção e centralizada
     * dentro da mesma área útil usada para o texto digitado (`margin_left/right`,
     * `start_top`, `bottom_margin`).
     */
    public function renderUpload(string $caminhoOriginal): string
    {
        $config = config('cartas.timbrado');
        $modelo = $config['path'];

        if (! is_file($modelo)) {
            throw new RuntimeException("Modelo de papel timbrado não encontrado em: {$modelo}");
        }

        if (! is_file($caminhoOriginal)) {
            throw new RuntimeException("Anexo original não encontrado em: {$caminhoOriginal}");
        }

        $pdf = new class('P', 'mm', 'A5') extends Fpdi
        {
            public $fundoTemplateId = null;

            public function Header(): void
            {
                if ($this->fundoTemplateId !== null) {
                    $this->useTemplate($this->fundoTemplateId, 0, 0, null, null, true);
                }
            }
        };

        $pdf->setSourceFile($modelo);
        $pdf->fundoTemplateId = $pdf->importPage(1);

        // O construtor recebe 'A5' só como placeholder — o tamanho real da
        // página só é ajustado no primeiro AddPage() (Header() chama
        // useTemplate com adjustPageSize=true). GetPageWidth()/Height() antes
        // disso ainda refletiriam o placeholder, por isso a página é medida
        // aqui diretamente a partir do template do timbrado.
        $tamanhoPagina = $pdf->getTemplateSize($pdf->fundoTemplateId);
        $larguraDisponivel = $tamanhoPagina['width'] - $config['margin_left'] - $config['margin_right'];
        $alturaDisponivel = $tamanhoPagina['height'] - $config['start_top'] - $config['bottom_margin'];

        $diretorioTemp = $this->rasterizarPaginas($caminhoOriginal);

        try {
            $imagens = glob("{$diretorioTemp}/*.png");
            sort($imagens, SORT_NATURAL);

            foreach ($imagens as $imagem) {
                [$larguraPx, $alturaPx] = getimagesize($imagem);
                $larguraMm = $larguraPx / self::RASTER_DPI * 25.4;
                $alturaMm = $alturaPx / self::RASTER_DPI * 25.4;

                $escala = min($larguraDisponivel / $larguraMm, $alturaDisponivel / $alturaMm);
                $larguraFinal = $larguraMm * $escala;
                $alturaFinal = $alturaMm * $escala;

                $x = $config['margin_left'] + ($larguraDisponivel - $larguraFinal) / 2;
                $y = $config['start_top'] + ($alturaDisponivel - $alturaFinal) / 2;

                $pdf->AddPage();
                $pdf->Image($imagem, $x, $y, $larguraFinal, $alturaFinal);
            }
        } finally {
            File::deleteDirectory($diretorioTemp);
        }

        return $pdf->Output('S');
    }

    /**
     * Rasteriza cada página do PDF em um PNG (via poppler-utils) e devolve o
     * diretório temporário com os arquivos `pagina-NNN.png`, na ordem — chamador
     * é responsável por apagar o diretório após o uso.
     */
    private function rasterizarPaginas(string $caminhoOriginal): string
    {
        try {
            $totalPaginas = $this->totalDePaginas($caminhoOriginal);

            $diretorio = storage_path('app/tmp/cartas-anexo-'.Str::uuid());
            File::ensureDirectoryExists($diretorio);

            for ($pagina = 1; $pagina <= $totalPaginas; $pagina++) {
                $prefixo = sprintf('%s/pagina-%03d', $diretorio, $pagina);

                $resultado = Process::run([
                    'pdftoppm', '-png', '-r', (string) self::RASTER_DPI,
                    '-f', (string) $pagina, '-l', (string) $pagina, '-singlefile',
                    $caminhoOriginal, $prefixo,
                ]);

                if ($resultado->failed() || ! is_file("{$prefixo}.png")) {
                    throw new AnexoIncompativelException("Falha ao rasterizar a página {$pagina} do anexo: ".$resultado->errorOutput());
                }
            }

            return $diretorio;
        } catch (\Throwable $e) {
            if (isset($diretorio)) {
                File::deleteDirectory($diretorio);
            }

            throw $e instanceof AnexoIncompativelException
                ? $e
                : new AnexoIncompativelException('Falha ao rasterizar o anexo: '.$e->getMessage(), previous: $e);
        }
    }

    private function totalDePaginas(string $caminhoOriginal): int
    {
        $resultado = Process::run(['pdfinfo', $caminhoOriginal]);

        if ($resultado->failed()) {
            throw new AnexoIncompativelException('Não foi possível ler o anexo: '.$resultado->errorOutput());
        }

        if (! preg_match('/^Pages:\s+(\d+)/m', $resultado->output(), $match)) {
            throw new AnexoIncompativelException('Não foi possível determinar o número de páginas do anexo.');
        }

        return (int) $match[1];
    }

    /**
     * Escreve um parágrafo quebrando linhas manualmente (em vez de MultiCell)
     * para poder reduzir a largura disponível quando a linha cair sobre a
     * ilustração do modelo (ver `cartas.timbrado.obstaculo`).
     *
     * @param  array<string, mixed>  $config
     */
    private function escreverParagrafo(Fpdi $pdf, array $config, string $paragrafo): void
    {
        if ($paragrafo === '') {
            $this->quebrarPaginaSeNecessario($pdf, $config);
            $pdf->Ln($config['line_height']);

            return;
        }

        $palavrasLinha = [];
        $this->quebrarPaginaSeNecessario($pdf, $config);

        foreach (preg_split('/\s+/u', $paragrafo) as $palavra) {
            if ($palavra === '') {
                continue;
            }

            $tentativa = [...$palavrasLinha, $palavra];
            $largura = $this->larguraDisponivel($pdf, $config);

            if ($palavrasLinha !== [] && $this->larguraTexto($pdf, implode(' ', $tentativa)) > $largura) {
                $this->escreverLinha($pdf, $config, $palavrasLinha, justificar: true);
                $palavrasLinha = [$palavra];
                // Checa (e quebra, se preciso) ANTES de medir a largura da
                // próxima linha — senão o encaixe das palavras é decidido
                // com a largura de antes da quebra (da página anterior), e
                // a linha acaba curta/espalhada ao ser desenhada já na
                // largura nova da página seguinte.
                $this->quebrarPaginaSeNecessario($pdf, $config);
            } else {
                $palavrasLinha = $tentativa;
            }
        }

        if ($palavrasLinha !== []) {
            $this->escreverLinha($pdf, $config, $palavrasLinha, justificar: false);
        }
    }

    /**
     * Escreve uma linha já quebrada. Quando justificada, distribui o espaço
     * sobrando entre as palavras para a linha preencher toda a largura
     * disponível — útil para deixar visível, de relance, onde a largura
     * muda por causa do obstáculo (ver `larguraDisponivel()`).
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $palavras
     */
    private function escreverLinha(Fpdi $pdf, array $config, array $palavras, bool $justificar): void
    {
        $largura = $this->larguraDisponivel($pdf, $config);
        $pdf->SetX($config['margin_left']);

        if (! $justificar || count($palavras) === 1) {
            $pdf->Cell($largura, $config['line_height'], $this->encode(implode(' ', $palavras)));
            $pdf->Ln($config['line_height']);

            return;
        }

        $largurasPalavras = array_map(
            fn (string $palavra) => $this->larguraTexto($pdf, $palavra),
            $palavras
        );
        $gaps = count($palavras) - 1;
        $espacoPorGap = max(($largura - array_sum($largurasPalavras)) / $gaps, 0);

        foreach ($palavras as $i => $palavra) {
            $larguraCelula = $largurasPalavras[$i] + ($i < $gaps ? $espacoPorGap : 0);
            $pdf->Cell($larguraCelula, $config['line_height'], $this->encode($palavra));
        }

        $pdf->Ln($config['line_height']);
    }

    /**
     * Quebra a página manualmente (nunca no meio de uma linha) quando a
     * próxima linha não couber mais no espaço reservado pelo rodapé.
     *
     * @param  array<string, mixed>  $config
     */
    private function quebrarPaginaSeNecessario(Fpdi $pdf, array $config): void
    {
        $limite = $pdf->GetPageHeight() - $config['bottom_margin'];

        if ($pdf->GetY() + $config['line_height'] > $limite) {
            $pdf->AddPage();
        }
    }

    private function larguraTexto(Fpdi $pdf, string $texto): float
    {
        return $pdf->GetStringWidth($this->encode($texto));
    }

    /**
     * Largura disponível para a linha na posição Y atual: reduzida enquanto
     * o cursor estiver dentro da faixa vertical da ilustração do modelo.
     *
     * @param  array<string, mixed>  $config
     */
    private function larguraDisponivel(Fpdi $pdf, array $config): float
    {
        $obstaculo = $config['obstaculo'] ?? null;
        $y = $pdf->GetY();

        if ($obstaculo && $y >= $obstaculo['top'] && $y <= $obstaculo['bottom']) {
            return $obstaculo['right_edge'] - $config['margin_left'];
        }

        return $pdf->GetPageWidth() - $config['margin_left'] - $config['margin_right'];
    }

    /**
     * @return array<int, string>
     */
    private function paragrafos(string $texto): array
    {
        return explode("\n", $texto);
    }

    /**
     * Normaliza e sanitiza o texto da carta antes da renderização:
     * - Garante sequência UTF-8 válida, descartando bytes órfãos ou malformados;
     * - Remove caracteres de largura zero e invisíveis (ZWSP, ZWNJ, ZWJ, BOM);
     * - Converte espaços não quebráveis e espaços Unicode especiais para espaço comum;
     * - Converte quebras de linha Unicode (LS \u2028, PS \u2029) e retornos de carro para \n.
     */
    public function normalizarTexto(string $texto): string
    {
        // 1. Descarta bytes que não formam sequências UTF-8 válidas (evita erro no iconv)
        $texto = @iconv('UTF-8', 'UTF-8//IGNORE', $texto) ?: mb_convert_encoding($texto, 'UTF-8', 'UTF-8');

        // 2. Remove caracteres de largura zero e invisíveis (evita '?' no FPDF)
        $texto = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $texto) ?? $texto;

        // 3. Normaliza espaços não quebráveis e espaços Unicode especiais para espaço comum
        $texto = preg_replace('/[\x{00A0}\x{202F}\x{2000}-\x{200A}\x{205F}\x{3000}]/u', ' ', $texto) ?? $texto;

        // 4. Normaliza quebras de linha Unicode (LS \u2028, PS \u2029) e retornos de carro
        return str_replace(["\r\n", "\r", "\u{2028}", "\u{2029}"], "\n", $texto);
    }

    /**
     * As fontes core do FPDF usam Windows-1252. Converte o UTF-8 mantendo acentos,
     * aspas tipográficas e travessões, e transliterando ou ignorando caracteres
     * incompatíveis sem emitir avisos ou exceções fatais.
     */
    public function encode(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $texto);

        if ($convertido !== false) {
            return $convertido;
        }

        return mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }
}
