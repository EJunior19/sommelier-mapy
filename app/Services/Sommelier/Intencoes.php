<?php

namespace App\Services\Sommelier;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class Intencoes
{
    public static function processar(string $textoOriginal): array
    {
        $textoLower = mb_strtolower(trim($textoOriginal), 'UTF-8');
        $textoSemAcento = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $textoLower);
        $textoSemAcento = preg_replace('/\s+/', ' ', $textoSemAcento);

        return [
            'categoria' => self::detectarCategoria($textoSemAcento),
            'marca'     => self::detectarMarca($textoSemAcento),
            'sensorial' => self::detectarPerfilSensorial($textoLower),
            'precoMin'  => self::detectarFaixaPreco($textoLower)['min'],
            'precoMax'  => self::detectarFaixaPreco($textoLower)['max'],
            'minMl'     => self::detectarVolume($textoLower)['minMl'],
            'maxMl'     => self::detectarVolume($textoLower)['maxMl'],
        ];
    }

    /* ---------------------------------------------------------
       CATEGORIA — OTIMIZADO COM CACHE + MAPA COMPLETO
    ----------------------------------------------------------*/
    public static function detectarCategoria(string $texto): ?string
    {
        $mapa = Cache::remember('sommelier_mapa_categorias_v2', 86400, function () {
            return [
                // VINOS
                'vinho'=>'VINOS','vinhos'=>'VINOS','vino'=>'VINOS','vinos'=>'VINOS',
                'tinto'=>'VINOS','rosado'=>'VINOS','rosados'=>'VINOS',
                'malbec'=>'VINOS','cabernet'=>'VINOS','merlot'=>'VINOS',
                'carmenere'=>'VINOS','chardonnay'=>'VINOS','moscato'=>'VINOS',

                // WHISKY
                'whisky'=>'WHISKY','whiskey'=>'WHISKY','scotch'=>'WHISKY','bourbon'=>'WHISKY',

                // CERVEJA
                'cerveja'=>'CERVEZA','cervejas'=>'CERVEZA',
                'beer'=>'CERVEZA','ipa'=>'CERVEZA','pilsen'=>'CERVEZA','lager'=>'CERVEZA',

                // GIN
                'gin'=>'GIN','london dry'=>'GIN',

                // VODKA
                'vodka'=>'VODKA',

                // LICOR
                'licor'=>'LICORES','licores'=>'LICORES',
                'amarula'=>'LICORES','baileys'=>'LICORES','cassis'=>'LICORES','triple sec'=>'LICORES',

                // ESPUMANTES
                'espumante'=>'ESPUMANTES','espumantes'=>'ESPUMANTES',
                'champagne'=>'CHAMPAGNE','prosecco'=>'ESPUMANTES','brut'=>'ESPUMANTES',

                // CACHAÇA
                'cachaca'=>'CACHAÇA','cachaça'=>'CACHAÇA','pinga'=>'CACHAÇA','caninha'=>'CACHAÇA',

                // RUM
                'rum'=>'RON','ron'=>'RON',

                // TEQUILA
                'tequila'=>'TEQUILA',

                // ENERGÉTICOS
                'energetico'=>'ENERGETICO','energético'=>'ENERGETICO',
                'monster'=>'ENERGETICO','red bull'=>'ENERGETICO',

                // ÁGUA
                'agua'=>'AGUA','água'=>'AGUA','water'=>'AGUA','mineral'=>'AGUA',
            ];
        });

        foreach (explode(' ', $texto) as $p) {
            if (isset($mapa[$p])) {
                return $mapa[$p];
            }
        }

        return null;
    }

    /* ---------------------------------------------------------
       MARCA — PRIMEIRO TRGM → DEPOIS CACHE (EVITA FULL SCAN)
    ----------------------------------------------------------*/
    public static function detectarMarca(string $textoSemAcento): ?string
    {
        // TRGM ultra rápido (usa índice idx_marca_normalizada_trgm)
        $possivel = DB::table('bebidas')
            ->whereNotNull('marca_normalizada')
            ->whereRaw("similarity(marca_normalizada, ?) > 0.35", [$textoSemAcento])
            ->orderByRaw("similarity(marca_normalizada, ?) DESC", [$textoSemAcento])
            ->limit(1)
            ->value('marca');

        if ($possivel) {
            return $possivel;
        }

        // fallback: lista cacheada REDUZIDA (somente marcas >= 4 chars)
        $todas = Cache::remember('sommelier_marcas_cache_v3', 43200, function () {
            return DB::table('bebidas')
                ->select('marca')
                ->distinct()
                ->whereNotNull('marca')
                ->where('marca', '<>', '')
                ->orderBy('marca')
                ->pluck('marca')
                ->map(fn($m) => [
                    'orig' => trim($m),
                    'norm' => trim(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($m)))
                ])
                ->filter(fn($m) => strlen($m['norm']) >= 4)
                ->values()
                ->toArray();
        });

        foreach ($todas as $m) {
            if (str_contains($textoSemAcento, $m['norm'])) {
                return $m['orig'];
            }
        }

        return null;
    }

    /* ---------------------------------------------------------
       PERFIL SENSORIAL
    ----------------------------------------------------------*/
    public static function detectarPerfilSensorial(string $texto): ?string
    {
        return match (true) {
            str_contains($texto, 'doce')  => 'doce',
            str_contains($texto, 'suave') => 'suave',
            str_contains($texto, 'leve')  => 'leve',
            str_contains($texto, 'forte') => 'forte',
            default => null,
        };
    }

    /* ---------------------------------------------------------
       FAIXA DE PREÇO — MAIS ROBUSTA E CONSISTENTE
    ----------------------------------------------------------*/
    public static function detectarFaixaPreco(string $textoLower): array
    {
        $num = fn($n) => (float)str_replace([',','.'], ['.','.'], $n);

        $min = null;
        $max = null;

        // ENTRE X E Y
        if (preg_match('/entre\s+(\d+(?:[\.,]\d+)?)\s*(e|a|até)\s*(\d+(?:[\.,]\d+)?)/iu', $textoLower, $m)) {
            $min = $num($m[1]);
            $max = $num($m[3]);
        }

        // ACIMA DE
        if (preg_match('/(acima de|maior que|superior a)\s+(\d+(?:[\.,]\d+)?)/iu', $textoLower, $m)) {
            $min = $num($m[2]);
        }

        // ATÉ X
        if (preg_match('/(até|hasta)\s+(\d+(?:[\.,]\d+)?)/iu', $textoLower, $m)) {
            $max = $num($m[2]);
        }

        // “barato” → teto padrão
        if (str_contains($textoLower, 'barato') && !$max) {
            $max = 10.0;
        }

        // “premium / reserva” → piso padrão
        if (str_contains($textoLower, 'premium') && !$min) {
            $min = 25.0;
        }

        return [
            'precoMin' => $min,
            'precoMax' => $max
        ];
    }
    public static function detectarVolume(string $texto): array
    {
        $texto = mb_strtolower($texto, 'UTF-8');

        // Padrões:
        // - acima de 500 ml
        // - maior que 700 ml
        // - até 330 ml
        // - 500 a 1000 ml
        // - entre 500 e 900 ml

        // Faixa: entre X e Y ml
        if (preg_match('/entre\s+(\d+)\s*(?:ml)?\s*(e|a)\s*(\d+)\s*ml/', $texto, $m)) {
            return ['minMl' => (int)$m[1], 'maxMl' => (int)$m[3]];
        }

        // acima / maior que
        if (preg_match('/(acima de|maior que|mais de)\s+(\d+)\s*ml/', $texto, $m)) {
            return ['minMl' => (int)$m[2], 'maxMl' => null];
        }

        // até X ml
        if (preg_match('/(até|menor que)\s+(\d+)\s*ml/', $texto, $m)) {
            return ['minMl' => null, 'maxMl' => (int)$m[2]];
        }

        // valor único → mínimo
        if (preg_match('/(\d+)\s*ml/', $texto, $m)) {
            return ['minMl' => (int)$m[1], 'maxMl' => null];
        }

        return ['minMl' => null, 'maxMl' => null];
    }

}
