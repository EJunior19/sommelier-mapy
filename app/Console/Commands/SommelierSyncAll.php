<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SommelierSyncAll extends Command
{
    protected $signature = 'sommelier:sync-all
        {--dry-run : Simula la ejecución sin realizar cambios}';

    protected $description = 'Ejecuta el pipeline completo del Sommelier (importación, normalización y enriquecimiento)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🚀 Pipeline Sommelier iniciado');

        // 1️⃣ Normalizar nombres / volumen
        $this->info('🧼 Normalizando bebidas...');
        $dryRun
            ? $this->line('🧪 DRY-RUN → sommelier:normalize')
            : $this->call('sommelier:normalize');

        // 2️⃣ Enriquecer metadata
        $this->info('🧠 Enriqueciendo metadata...');
        $dryRun
            ? $this->line('🧪 DRY-RUN → sommelier:enriquecer-bebidas')
            : $this->call('sommelier:enriquecer-bebidas');

        $this->info('✅ Pipeline finalizado');

        return self::SUCCESS;
    }
}
