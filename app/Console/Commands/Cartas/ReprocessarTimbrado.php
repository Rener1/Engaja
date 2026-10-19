<?php

namespace App\Console\Commands\Cartas;

use App\Models\Cartas\CartaMensagem;
use App\Services\Cartas\CartaTimbradoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReprocessarTimbrado extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cartas:reprocessar-timbrado
        {--id=* : Restringe o reprocessamento a ids específicos de carta_mensagens}
        {--dry-run : Apenas lista as mensagens pendentes, sem aplicar o timbrado}
        {--force : Não pede confirmação antes de aplicar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reaplica o papel timbrado nas cartas enviadas como anexo PDF que ficaram sem timbrado (ex.: poppler-utils ausente no servidor na época do envio). Só cartas de voluntários recebem timbrado (as dos educandos já vêm timbradas)';

    public function handle(CartaTimbradoService $timbrado): int
    {
        $ids = array_map('intval', array_filter($this->option('id')));

        $query = CartaMensagem::query()
            ->whereNotNull('anexo_original_path')
            ->whereNull('timbrado_aplicado_em')
            ->where('timbrado_desativado', false)
            ->where('tipo_remetente', CartaMensagem::TIPO_REMETENTE_VOLUNTARIO)
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id');

        $total = $query->count();

        if ($total === 0) {
            $this->info('Nenhuma carta pendente de timbrado encontrada.');

            return self::SUCCESS;
        }

        $this->info("{$total} carta(s) sem timbrado encontrada(s) (anexo enviado, mas timbrado_aplicado_em ainda nulo).");

        if ($this->option('dry-run')) {
            $query->get(['id', 'carta_id', 'anexo_original_path'])->each(
                fn (CartaMensagem $m) => $this->line(" - mensagem #{$m->id} (carta #{$m->carta_id}): {$m->anexo_original_path}")
            );

            $this->info('Modo --dry-run: nada foi alterado.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Aplicar o timbrado em {$total} carta(s) agora? O anexo original (anexo_original_path) nunca é alterado ou removido; apenas o PDF final timbrado é (re)gerado em arquivo separado."
        )) {
            $this->warn('Operação cancelada. Nenhuma carta foi alterada.');

            return self::SUCCESS;
        }

        $sucesso = 0;
        $falhas = [];

        $query->chunkById(50, function ($mensagens) use ($timbrado, &$sucesso, &$falhas) {
            foreach ($mensagens as $mensagem) {
                try {
                    $timbrado->aplicarAnexo($mensagem);
                } catch (Throwable $e) {
                    // aplicarAnexo() já trata FpdiException/AnexoIncompativelException
                    // internamente (biblioteca ausente, PDF corrompido etc.) sem lançar;
                    // chegar aqui é uma falha inesperada (disco, banco) — registra e
                    // segue para não travar o lote por causa de uma carta.
                    Log::error('Falha inesperada ao reprocessar timbrado de carta.', [
                        'carta_mensagem_id' => $mensagem->id,
                        'erro' => $e->getMessage(),
                    ]);
                    $falhas[] = $mensagem->id;

                    continue;
                }

                $mensagem->refresh();

                if ($mensagem->timbrado_aplicado_em) {
                    $sucesso++;
                    $this->line(" - mensagem #{$mensagem->id}: timbrado aplicado.");
                } else {
                    $falhas[] = $mensagem->id;
                    $this->warn(" - mensagem #{$mensagem->id}: continua sem timbrado (ver log).");
                }
            }
        });

        $this->info("Timbrado aplicado com sucesso em {$sucesso} de {$total} carta(s).");

        if ($falhas !== []) {
            $this->warn(count($falhas).' carta(s) continuam sem timbrado: #'.implode(', #', $falhas));
            $this->warn('Motivo registrado no log (Log::warning em CartaTimbradoService, ou Log::error acima).');
        }

        return $falhas === [] ? self::SUCCESS : self::FAILURE;
    }
}
