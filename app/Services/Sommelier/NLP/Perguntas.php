<?php

namespace App\Services\Sommelier\NLP;

use App\Services\Sommelier\Support\Normalizador;

class Perguntas
{
    /**
     * --------------------------------------------------
     * 🌍 DETECÇÃO DE PERGUNTA DE PROCEDÊNCIA
     * --------------------------------------------------
     * Ex:
     * - "de onde vem esse vinho?"
     * - "qual a origem do whisky?"
     * - "é de que país?"
     * - "fabricado onde?"
     * --------------------------------------------------
     */
    public static function procedencia(string $texto, object $intencao): bool
    {
        $t = mb_strtolower($texto, 'UTF-8');
        $t = Normalizador::textoLimpo($t);

        if ($t === '') {
            return false;
        }

        // Normalizações comuns de STT
        $t = str_replace([
            'pais',
            'país',
            'feito aonde',
            'feito onde',
        ], [
            'pais',
            'pais',
            'feito onde',
            'feito onde',
        ], $t);

        /**
         * 🔎 Gatilhos semânticos (PT + ES)
         */
        $regex = [
            // origem direta
            '/\b(origem|procedenc|procedência)\b/i',

            // de onde vem / é
            '/\b(de onde (vem|é)|de onde es)\b/i',

            // país
            '/\b(pais de origem|pa[ií]s)\b/i',

            // fabricação
            '/\b(feito em|fabricado em|produzido em)\b/i',

            // espanhol
            '/\b(origen|hecho en|fabricado en)\b/i',
        ];

        foreach ($regex as $rx) {
            if (preg_match($rx, $t)) {

                /**
                 * 🛡️ Proteção:
                 * Só ativa procedência se NÃO for pergunta abstrata pura
                 * Ex: "origem do whisky" (história) → abstrata
                 */
                if (preg_match('/\b(hist[oó]ria|quem inventou|quando surgiu)\b/i', $t)) {
                    return false;
                }

                $intencao->perguntaEspecifica = 'procedencia';
                return true;
            }
        }

        return false;
    }
}
