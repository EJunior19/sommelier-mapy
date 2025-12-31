<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LimparAudiosSommelier extends Command
{
    protected $signature = 'sommelier:limpar-audios';
    protected $description = 'Remove arquivos de áudio antigos do Sommelier Virtual';

    public function handle(): int
    {
        $dias = 2;
        $agora = time();
        $contador = 0;

        $pastas = [
            storage_path('app/audio'),
            storage_path('app/audios_temp'),
        ];

        foreach ($pastas as $caminho) {

            if (!is_dir($caminho)) {
                Log::warning("📁 Pasta não encontrada: {$caminho}");
                continue;
            }

            foreach (glob($caminho . '/*.{webm,mp3}', GLOB_BRACE) as $arquivo) {

                if (!is_file($arquivo)) {
                    continue;
                }

                $idadeDias = ($agora - filemtime($arquivo)) / 86400;

                if ($idadeDias > $dias) {
                    unlink($arquivo);
                    $contador++;
                }
            }
        }

        Log::info("🧹 Sommelier: {$contador} arquivos de áudio removidos");
        $this->info("🧹 {$contador} arquivos de áudio removidos.");

        return Command::SUCCESS;
    }
}
