<?php

namespace App\Services\Sommelier\Domain;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\Sommelier\Support\Normalizador;

class CategoriaMap
{
    /**
     * ==================================================
     * 🎯 DETECTOR PRINCIPAL
     * ==================================================
     */
    public static function detectar(string $texto): ?string
    {
        $textoOriginal = $texto;
        $texto = Normalizador::textoLimpo($texto);

        if ($texto === '') {
            return null;
        }

        // Normalizações extras para STT
        $texto = self::normalizarSTT($texto);

        $aliases = self::aliases();

        /**
         * --------------------------------------------------
         * 1️⃣ FRASES COMPOSTAS (PRIORIDADE ABSOLUTA)
         * --------------------------------------------------
         */
        foreach ($aliases as $alias => $categoria) {
            if (str_contains($texto, $alias)) {
                return $categoria;
            }
        }

        /**
         * --------------------------------------------------
         * 2️⃣ TOKENIZAÇÃO INTELIGENTE
         * --------------------------------------------------
         */
        $tokens = preg_split('/\s+/', $texto);

        foreach ($tokens as $t) {
            if (isset($aliases[$t])) {
                return $aliases[$t];
            }
        }

        /**
         * --------------------------------------------------
         * 3️⃣ CONTEXTO SEMÂNTICO (ANOS, ESTILOS, IDIOMA)
         * --------------------------------------------------
         */

        // Whisky por idade
        if (preg_match('/\b(10|12|15|18|21|25|30)\s*anos?\b/', $texto)) {
            return 'WHISKY';
        }

        // Vinho por uva
        if (preg_match('/malbec|merlot|cabernet|syrah|tempranillo|sauvignon/i', $texto)) {
            return 'VINOS';
        }

        // Cerveja por estilo
        if (preg_match('/ipa|pilsen|lager|stout|weiss|ale/i', $texto)) {
            return 'CERVEZA';
        }

        // Espumante por método
        if (preg_match('/brut|demi\s?sec|extra\s?dry|champagne|prosecco/i', $texto)) {
            return 'ESPUMANTES';
        }

        /**
         * --------------------------------------------------
         * 4️⃣ FALLBACK VIA BANCO (SEGURO)
         * --------------------------------------------------
         */
        return self::fallbackBanco($textoOriginal);
    }

    /**
     * ==================================================
     * 🔧 NORMALIZAÇÃO EXTRA PARA STT
     * ==================================================
     */
    protected static function normalizarSTT(string $texto): string
    {
        $map = [
            'uisque'   => 'whisky',
            'uísque'   => 'whisky',
            'uiski'    => 'whisky',
            'viski'    => 'whisky',
            'wisky'    => 'whisky',
            'cervejas' => 'cerveja',
            'vinhos'   => 'vinho',
            'licores'  => 'licor',
            'espumantes' => 'espumante',
        ];

        return str_replace(array_keys($map), array_values($map), $texto);
    }

    /**
     * ==================================================
     * 📚 MAPA DE ALIASES HUMANOS
     * ==================================================
     */
    protected static function aliases(): array
    {
        return Cache::remember('sommelier_alias_categoria_v3', 86400, function () {

            $map = [];

            /**
             * 🍷 VINHOS
             */
            foreach ([
                'vinho','vino','vinos','tinto','tintos',
                'branco','brancos','rose','rosado','rosé',
                'malbec','merlot','cabernet','syrah',
                'sauvignon','tempranillo','blend'
            ] as $a) {
                $map[$a] = 'VINOS';
            }

            /**
             * 🥃 WHISKY
             */
            foreach ([
                'whisky','whiskey','whiskies','bourbon',
                'scotch','single malt','malt','blended'
            ] as $a) {
                $map[$a] = 'WHISKY';
            }

            /**
             * 🍺 CERVEJA
             */
            foreach ([
                'cerveja','cerveza','beer','breja',
                'ipa','pilsen','lager','stout','weiss',
                'long neck','lata','garrafa'
            ] as $a) {
                $map[$a] = 'CERVEZA';
            }

            /**
             * 🍾 ESPUMANTES
             */
            foreach ([
                'espumante','champagne','champanhe',
                'prosecco','brut','demi sec','extra dry'
            ] as $a) {
                $map[$a] = 'ESPUMANTES';
            }

            /**
             * 🍸 GIN
             */
            foreach ([
                'gin','gim','gintonic','gin tonica','tonica'
            ] as $a) {
                $map[$a] = 'GIN';
            }

            /**
             * 🥂 VODKA
             */
            foreach ([
                'vodka','vodkas','ice','vodka ice'
            ] as $a) {
                $map[$a] = 'VODKA';
            }

            /**
             * 🍮 LICORES
             */
            foreach ([
                'licor','licores','amarula','baileys',
                'cointreau','triple sec'
            ] as $a) {
                $map[$a] = 'LICORES';
            }

            /**
             * 🧉 CACHAÇA
             */
            foreach ([
                'cachaça','cachaca','pinga','caninha'
            ] as $a) {
                $map[$a] = 'CACHAÇA';
            }

            /**
             * 🍹 RUM
             */
            foreach ([
                'rum','ron','rhum'
            ] as $a) {
                $map[$a] = 'RON';
            }

            /**
             * ⚡ ENERGÉTICO
             */
            foreach ([
                'energetico','energy','red bull','monster'
            ] as $a) {
                $map[$a] = 'ENERGÉTICO';
            }

            /**
             * 💧 ÁGUA
             */
            foreach ([
                'agua','água','mineral','sem gas','com gas'
            ] as $a) {
                $map[$a] = 'AGUA';
            }

            return $map;
        });
    }

    /**
     * ==================================================
     * 🛡 FALLBACK PELO BANCO (ANTI-INVENÇÃO)
     * ==================================================
     */
    protected static function fallbackBanco(string $texto): ?string
    {
        $res = DB::table('bebidas')
            ->select('tipo')
            ->selectRaw("similarity(tipo, ?) AS score", [$texto])
            ->whereRaw("similarity(tipo, ?) > 0.35", [$texto])
            ->orderByDesc('score')
            ->limit(1)
            ->first();

        return $res?->tipo;
    }
}
