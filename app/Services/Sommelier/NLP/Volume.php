<?php

namespace App\Services\Sommelier\NLP;

use App\Services\Sommelier\Support\Normalizador;

class Volume
{
    /**
     * --------------------------------------------------
     * 🧴 DETECTA FAIXA DE VOLUME EM ML
     * --------------------------------------------------
     * Retorna:
     * [minMl, maxMl]
     *
     * Exemplos:
     * - "750ml"           → [750, null]
     * - "entre 500 e 750" → [500, 750]
     * - "1 litro"         → [1000, null]
     * - "long neck"       → [330, 355]
     * --------------------------------------------------
     */
    public static function detectar(string $texto): array
    {
        if (trim($texto) === '') {
            return [null, null];
        }

        // Normalização forte (STT + humano)
        $t = mb_strtolower($texto, 'UTF-8');
        $t = Normalizador::textoLimpo($t);

        /**
         * --------------------------------------------------
         * 🔢 FAIXA EXPLÍCITA (entre X e Y)
         * --------------------------------------------------
         */
        if (preg_match('/\bentre\s+(\d+(?:[.,]\d+)?)\s*(ml)?\s*(e|a)\s*(\d+(?:[.,]\d+)?)\s*(ml)?\b/i', $t, $m)) {
            $min = self::toMl($m[1], $m[2] ?? 'ml');
            $max = self::toMl($m[4], $m[5] ?? 'ml');

            return self::ordenar($min, $max);
        }

        /**
         * --------------------------------------------------
         * 🔢 FAIXA "ATÉ / NO MÁXIMO"
         * --------------------------------------------------
         */
        if (preg_match('/\b(at[eé]|no\s+m[aá]ximo|menos\s+de)\s+(\d+(?:[.,]\d+)?)\s*(ml|l|litro|litros)?\b/i', $t, $m)) {
            $max = self::toMl($m[2], $m[3] ?? 'ml');
            return [null, $max];
        }

        /**
         * --------------------------------------------------
         * 🔢 FAIXA "ACIMA DE"
         * --------------------------------------------------
         */
        if (preg_match('/\b(acima\s+de|mais\s+de|superior\s+a)\s+(\d+(?:[.,]\d+)?)\s*(ml|l|litro|litros)?\b/i', $t, $m)) {
            $min = self::toMl($m[2], $m[3] ?? 'ml');
            return [$min, null];
        }

        /**
         * --------------------------------------------------
         * 🧴 VOLUME DIRETO EM ML
         * --------------------------------------------------
         */
        if (preg_match('/\b(\d{2,4})\s*ml\b/i', $t, $m)) {
            return [(int)$m[1], null];
        }

        /**
         * --------------------------------------------------
         * 🧴 VOLUME EM LITROS
         * --------------------------------------------------
         */
        if (preg_match('/\b(\d+(?:[.,]\d+)?)\s*(l|litro|litros)\b/i', $t, $m)) {
            $ml = self::toMl($m[1], 'l');
            return [$ml, null];
        }

        /**
         * --------------------------------------------------
         * 🍺 FORMATOS HUMANOS
         * --------------------------------------------------
         */

        // Long neck
        if (preg_match('/\b(long\s*neck|longneck)\b/i', $t)) {
            return [330, 355];
        }

        // Lata / latinha
        if (preg_match('/\b(lata|latinha)\b/i', $t)) {
            return [269, 473]; // padrão mundial
        }

        // Garrafa padrão (vinho)
        if (preg_match('/\b(garrafa\s+padr[aã]o|vinho\s+normal)\b/i', $t)) {
            return [750, null];
        }

        // Litrão / garrafão
        if (preg_match('/\b(litr[aã]o|garraf[aã]o)\b/i', $t)) {
            return [1000, null];
        }

        // Mini / dose
        if (preg_match('/\b(mini|dose|shot)\b/i', $t)) {
            return [30, 60];
        }

        return [null, null];
    }

    /**
     * --------------------------------------------------
     * 🔧 CONVERTE PARA ML
     * --------------------------------------------------
     */
    protected static function toMl(string $valor, string $unidade): int
    {
        $n = (float) str_replace(',', '.', $valor);

        if (str_starts_with($unidade, 'l')) {
            return (int) round($n * 1000);
        }

        return (int) round($n);
    }

    /**
     * --------------------------------------------------
     * 🔁 GARANTE ORDEM CORRETA
     * --------------------------------------------------
     */
    protected static function ordenar(?int $a, ?int $b): array
    {
        if ($a !== null && $b !== null && $a > $b) {
            return [$b, $a];
        }

        return [$a, $b];
    }
}
