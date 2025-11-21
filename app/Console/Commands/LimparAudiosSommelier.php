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
        $dias = 2; // 🔧 QUANTOS DIAS MANTER — você pode alterar

        $caminho = public_path('audio');
        $agora = time();
        $contador = 0;

        if (!is_dir($caminho)) {
            Log::warning("📁 Pasta de áudio não encontrada: $caminho");
            return Command::SUCCESS;
        }

        foreach (glob($caminho . '/*.mp3') as $arquivo) {
            $modificado = filemtime($arquivo);
            $idadeDias = ($agora - $modificado) / 86400;

            if ($idadeDias > $dias) {
                unlink($arquivo);
                $contador++;
            }
        }

        Log::info("🧹 Sommelier: $contador áudios antigos removidos da pasta /public/audio");

        return Command::SUCCESS;
    }
}
