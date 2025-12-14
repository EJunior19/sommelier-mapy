<?php

namespace App\Services\Sommelier\NLP;

use App\Services\Sommelier\Support\Normalizador;

class Preco
{
    /**
     * --------------------------------------------------
     * 💲 DETECÇÃO DE FAIXA DE PREÇO
     * --------------------------------------------------
     * Retorna: [min, max]
     * Ambos podem ser null
     * --------------------------------------------------
     */
    public static function detectar(string $texto): array
    {
        $min = null;
        $max = null;

        if (trim($texto) === '') {
            return [$min, $max];
        }

        // Normalização base
        $t = mb_strtolower($texto, 'UTF-8');
        $t = Normalizador::textoLimpo($t);

        // Normalizações comuns de STT
        $t = str_replace([
            'a mais de',
            'mais de',
            'por menos de',
            'menos do que',
            'ate',
            'usd',
            'us$',
            'u$s',
        ], [
            'acima de',
            'acima de',
            'menos de',
            'menos de',
            'até',
            'dólares',
            'dólares',
            'dólares',
        ], $t);

        /**
         * ----------------------------------------------
         * 🔢 FAIXA EXPLÍCITA
         * ----------------------------------------------
         * "entre 20 e 60"
         * "de 20 a 60"
         */
        if (preg_match('/\b(entre|de)\s+(\d+(?:[.,]\d+)?)\s*(a|e|até)\s*(\d+(?:[.,]\d+)?)\b/i', $t, $m)) {
            $a = self::toFloat($m[2]);
            $b = self::toFloat($m[4]);
            $min = min($a, $b);
            $max = max($a, $b);

            return [$min, $max];
        }

        /**
         * ----------------------------------------------
         * ⬆️ ACIMA DE / A PARTIR DE
         * ----------------------------------------------
         */
        if (preg_match('/\b(acima de|a partir de|superior a|maior que)\s+(\d+(?:[.,]\d+)?)\b/i', $t, $m)) {
            $min = self::toFloat($m[2]);
        }

        /**
         * ----------------------------------------------
         * ⬇️ ATÉ / MENOS DE
         * ----------------------------------------------
         */
        if (preg_match('/\b(at[eé]|menos de|abaixo de|inferior a|no m[aá]ximo)\s+(\d+(?:[.,]\d+)?)\b/i', $t, $m)) {
            $max = self::toFloat($m[2]);
        }

        /**
         * ----------------------------------------------
         * 💵 VALOR ISOLADO COM MOEDA
         * ----------------------------------------------
         * "200 dólares", "$200"
         */
        if ($min === null && $max === null) {
            if (preg_match('/\b(\d+(?:[.,]\d+)?)\s*(d[oó]lares|\$)\b/i', $t, $m)) {
                // Interpretação segura: valor como teto
                $max = self::toFloat($m[1]);
            }
        }

        /**
         * ----------------------------------------------
         * 🧠 PALAVRAS HUMANAS
         * ----------------------------------------------
         */
        if (preg_match('/\b(barato|econ[oô]mico|em conta)\b/i', $t)) {
            $max ??= 10.0;
        }

        if (preg_match('/\b(caro|premium|top|especial|importado)\b/i', $t)) {
            $min ??= 25.0;
        }

        /**
         * ----------------------------------------------
         * 🛡️ SANIDADE
         * ----------------------------------------------
         */
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [$min, $max];
    }

    /**
     * --------------------------------------------------
     * 🔢 CONVERSÃO SEGURA PARA FLOAT
     * --------------------------------------------------
     */
    protected static function toFloat(string $n): float
    {
        $n = trim($n);

        // Se tem vírgula e ponto → assume formato latino
        if (str_contains($n, ',') && str_contains($n, '.')) {
            $n = str_replace('.', '', $n);
            $n = str_replace(',', '.', $n);
            return (float) $n;
        }

        // Se só tem vírgula → vírgula decimal
        if (str_contains($n, ',')) {
            return (float) str_replace(',', '.', $n);
        }

        return (float) $n;
    }
}
