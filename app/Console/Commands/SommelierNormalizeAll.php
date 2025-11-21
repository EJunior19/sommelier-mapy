<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SommelierNormalizeAll extends Command
{
    protected $signature = 'sommelier:normalize {--ids=* : IDs específicos (opcional)}';
    protected $description = 'Reexecuta a normalização apenas sobre volume_ml e nome_limpo';

    public function handle(): int
    {
        $ids = $this->option('ids');

        // Normalização específica
        if (!empty($ids)) {
            $in = implode(',', array_map('intval', $ids));

            DB::unprepared("
                WITH norm AS (
                  SELECT 
                      id,
                      (nb).volume_ml AS volume_ml,
                      (nb).nome_limpo AS nome_limpo
                  FROM (
                      SELECT b.id, normalize_bebida(b.nombre) AS nb
                      FROM public.bebidas b
                      WHERE b.id IN ({$in})
                  ) s
                )
                UPDATE public.bebidas AS b
                SET 
                    volume_ml = n.volume_ml,
                    nome_limpo = n.nome_limpo
                FROM norm n
                WHERE b.id = n.id;
            ");

            $this->info('Normalização aplicada aos IDs informados.');
            return self::SUCCESS;
        }

        // Normalização completa
        DB::unprepared("
            WITH norm AS (
              SELECT 
                  id,
                  (nb).volume_ml AS volume_ml,
                  (nb).nome_limpo AS nome_limpo
              FROM (
                  SELECT b.id, normalize_bebida(b.nombre) AS nb
                  FROM public.bebidas b
              ) s
            )
            UPDATE public.bebidas AS b
            SET 
                volume_ml = n.volume_ml,
                nome_limpo = n.nome_limpo
            FROM norm n
            WHERE b.id = n.id;
        ");

        $this->info('Normalização aplicada a todas as bebidas.');
        return self::SUCCESS;
    }
}
