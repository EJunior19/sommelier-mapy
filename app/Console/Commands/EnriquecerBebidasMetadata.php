<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnriquecerBebidasMetadata extends Command
{
    protected $signature = 'sommelier:enriquecer-bebidas';
    protected $description = 'Agrega metadatos inteligentes a las bebidas existentes';

    public function handle()
    {
        $this->info('🍷 Iniciando enriquecimiento de bebidas...');

        DB::table('bebidas')
            ->orderBy('id')
            ->chunk(100, function ($bebidas) {

                foreach ($bebidas as $bebida) {

                    // 🔒 No sobrescribir si ya fue enriquecida
                    if (
                        $bebida->subtipo !== null ||
                        $bebida->perfil_sabor !== null ||
                        $bebida->maridaje_sugerido !== null ||
                        $bebida->ocasion_sugerida !== null
                    ) {
                        continue;
                    }

                    $dados = $this->inferirMetadata(
                        $bebida->tipo ?? '',
                        $bebida->nome_limpo ?? ''
                    );

                    // 🛑 Si no se infirió nada, no actualizar
                    if ($dados === []) {
                        continue;
                    }

                    DB::table('bebidas')
                        ->where('id', $bebida->id)
                        ->update($dados);
                }
            });

        $this->info('✅ Proceso finalizado');
    }

    /**
     * --------------------------------------------------
     * 🧠 INFERENCIA INTELIGENTE
     * --------------------------------------------------
     */
    protected function inferirMetadata(string $tipo, string $nome): array
    {
        $tipo = strtoupper(trim($tipo));
        $nome = mb_strtolower($nome);

        // 🎯 Default seguro (no inventa)
        $data = [];

        switch ($tipo) {

            /** 🍺 CERVEZA */
            case 'CERVEZA':
                return [
                    'subtipo'           => str_contains($nome, 'ale') ? 'ale' : 'lager',
                    'perfil_sabor'      => 'refrescante',
                    'maridaje_sugerido' => 'pizzas, picadas',
                    'ocasion_sugerida'  => 'social',
                ];

            /** 🍷 VINOS */
            case 'VINOS':
                return [
                    'subtipo'           => $this->detectarVariedadVino($nome),
                    'perfil_sabor'      => 'equilibrado',
                    'maridaje_sugerido' => 'carnes, pastas',
                    'ocasion_sugerida'  => 'comida',
                ];

            /** 🍾 ESPUMANTES / CHAMPAGNE */
            case 'ESPUMANTES':
            case 'CHAMPAGNE':
                return [
                    'subtipo'           => str_contains($nome, 'demi') ? 'demi sec' : 'brut',
                    'perfil_sabor'      => 'fresco',
                    'maridaje_sugerido' => 'aperitivos, postres',
                    'ocasion_sugerida'  => 'brindis',
                ];

            /** 🥃 WHISKY */
            case 'WHISKY':
                return [
                    'subtipo'           => 'whisky',
                    'perfil_sabor'      => 'intenso',
                    'maridaje_sugerido' => 'chocolate, quesos',
                    'ocasion_sugerida'  => 'after',
                ];

            /** 🍸 GIN */
            case 'GIN':
                return [
                    'subtipo'           => 'gin',
                    'perfil_sabor'      => 'aromatico',
                    'maridaje_sugerido' => 'snacks',
                    'ocasion_sugerida'  => 'social',
                ];

            /** 🍹 LICORES / APERITIVOS */
            case 'LICORES':
            case 'APERITIVOS':
                return [
                    'subtipo'           => 'licor',
                    'perfil_sabor'      => 'dulce-amargo',
                    'maridaje_sugerido' => 'postres',
                    'ocasion_sugerida'  => 'sobremesa',
                ];

            /** 🥃 DESTILADOS */
            case 'RON':
            case 'TEQUILA':
            case 'VODKA':
                return [
                    'subtipo'           => strtolower($tipo),
                    'perfil_sabor'      => 'alcoholico',
                    'maridaje_sugerido' => 'cocteleria',
                    'ocasion_sugerida'  => 'fiesta',
                ];
        }

        return $data;
    }

    /**
     * --------------------------------------------------
     * 🍇 VARIEDADES DE VINO
     * --------------------------------------------------
     */
    protected function detectarVariedadVino(string $nome): string
    {
        $mapa = [
            'malbec'     => 'malbec',
            'cabernet'   => 'cabernet sauvignon',
            'merlot'     => 'merlot',
            'syrah'      => 'syrah',
            'pinot'      => 'pinot noir',
            'bonarda'    => 'bonarda',
            'carmenere'  => 'carmenere',
            'torrontes'  => 'torrontes',
            'chardonnay' => 'chardonnay',
            'sauvignon'  => 'sauvignon blanc',
        ];

        foreach ($mapa as $key => $variedad) {
            if (str_contains($nome, $key)) {
                return $variedad;
            }
        }

        return 'blend';
    }
}
