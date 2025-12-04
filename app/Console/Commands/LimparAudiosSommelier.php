<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LimparAudiosSommelier extends Command
{
    protected $signature = 'sommelier:limpar-audios';
    protected $description = 'Remove arquivos de áudio antigos do Sommelier Virtual';

    public function handle()
    {
        $dias = 2; // Quantidade de dias para manter
        $agora = time();
        $contador = 0;

        // 🟣 Pastas reais onde o sistema salva os áudios
        $pastas = [
            storage_path('app/audio'),
            storage_path('app/audios_temp'),
        ];

        foreach ($pastas as $caminho) {

            if (!is_dir($caminho)) {
                Log::warning("📁 Pasta não encontrada: $caminho");
                continue;
            }

            // Limpa arquivos .webm e .mp3
            foreach (glob($caminho . '/*.{webm,mp3}', GLOB_BRACE) as $arquivo) {
                $modificado = filemtime($arquivo);
                $idadeDias = ($agora - $modificado) / 86400;

                if ($idadeDias > $dias) {
                    unlink($arquivo);
                    $contador++;
                }
            }
        }

        Log::info("🧹 Sommelier: $contador arquivos de áudio antigos removidos das pastas storage/app/audio e audios_temp");

        return Command::SUCCESS;
    }
}
