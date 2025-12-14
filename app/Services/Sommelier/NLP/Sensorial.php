<?php

namespace App\Services\Sommelier\NLP;

use App\Services\Sommelier\Support\Normalizador;

class Sensorial
{
    /**
     * --------------------------------------------------
     * 👅 DETECTA PERFIL SENSORIAL
     * --------------------------------------------------
     * Retorna:
     * - doce
     * - seco
     * - suave
     * - forte
     * - amargo
     * - frutado
     * --------------------------------------------------
     */
    public static function detectar(string $texto): ?string
    {
        if (trim($texto) === '') {
            return null;
        }

        // Normalização forte (STT + humano)
        $t = mb_strtolower($texto, 'UTF-8');
        $t = Normalizador::textoLimpo($t);

        // Correções comuns de voz
        $t = str_replace([
            'docinho',
            'bem doce',
            'pouco doce',
            'nao muito doce',
            'nao doce',
            'extra seco',
            'extra-brut',
        ], [
            'doce',
            'doce',
            'doce',
            'seco',
            'seco',
            'extra brut',
            'extra brut',
        ], $t);

        /**
         * --------------------------------------------------
         * ⚠️ PRIORIDADE ABSOLUTA
         * --------------------------------------------------
         * Se for BRUT / EXTRA BRUT → é seco, sempre
         */
        if (preg_match('/\b(extra\s+brut|brut|dry)\b/i', $t)) {
            return 'seco';
        }

        /**
         * --------------------------------------------------
         * 🍯 DOCE
         * --------------------------------------------------
         */
        if (preg_match('/\b(doce|dulce|sweet|adocicado|meloso|licoroso)\b/i', $t)) {
            return 'doce';
        }

        /**
         * --------------------------------------------------
         * 🌙 SUAVE / LEVE
         * --------------------------------------------------
         */
        if (preg_match('/\b(suave|leve|ligero|liviano|tranquilo|f[aá]cil de beber)\b/i', $t)) {
            return 'suave';
        }

        /**
         * --------------------------------------------------
         * 🔥 FORTE / ENCORPADO
         * --------------------------------------------------
         */
        if (preg_match('/\b(forte|fuerte|encorpado|intenso|potente|pesado)\b/i', $t)) {
            return 'forte';
        }

        /**
         * --------------------------------------------------
         * 🍃 AMARGO
         * --------------------------------------------------
         * IPA, lúpulo, bitter → amargo
         */
        if (preg_match('/\b(amargo|amargor|bitter|ipa|l[uú]pulo)\b/i', $t)) {
            return 'amargo';
        }

        /**
         * --------------------------------------------------
         * 🍓 FRUTADO / AROMÁTICO
         * --------------------------------------------------
         */
        if (preg_match('/\b(frutado|frutas|arom[aá]tico|c[ií]trico|cítrico)\b/i', $t)) {
            return 'frutado';
        }

        return null;
    }
}
