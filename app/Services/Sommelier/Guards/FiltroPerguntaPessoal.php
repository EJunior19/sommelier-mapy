<?php

namespace App\Services\Sommelier\Guards;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\Domain\CategoriaMap;
use App\Services\Sommelier\Support\Normalizador;

/**
 * ==========================================================
 * 🛑 GUARD — PERGUNTA PESSOAL / FORA DE ESCOPO
 * ----------------------------------------------------------
 * Bloqueia APENAS perguntas sobre:
 * - a IA / bot
 * - vida pessoal
 * - identidade
 * - funcionamento interno
 *
 * ⚠️ REGRA DE OURO:
 * Se a mensagem fala de BEBIDA → NÃO bloquear
 * ==========================================================
 */
class FiltroPerguntaPessoal
{
    /**
     * --------------------------------------------------
     * 🔍 Detectar pergunta pessoal
     * --------------------------------------------------
     */
    public static function detectar(string $texto): bool
    {
        $t = Normalizador::textoLimpo($texto);

        if ($t === '') {
            return false;
        }

        /**
         * ----------------------------------------------
         * 🚨 EXCEÇÃO CRÍTICA
         * Se mencionar bebida/categoria → NÃO bloquear
         * ----------------------------------------------
         */
        if (CategoriaMap::detectar($t)) {
            SommelierLog::info("✅ [FiltroPerguntaPessoal] Categoria detectada, não é pergunta pessoal", [
                'texto' => $texto
            ]);
            return false;
        }

        /**
         * ----------------------------------------------
         * 🛑 Gatilhos reais de pergunta pessoal
         * ----------------------------------------------
         */
        $gatilhos = [
            // identidade
            '/\b(quem é você|quem e voce|quem é vc|quem e vc)\b/i',
            '/\b(seu nome|teu nome)\b/i',

            // vida pessoal
            '/\b(idade|quantos anos|namora|casado|solteiro)\b/i',

            // trabalho / sistema
            '/\b(trabalha|onde trabalha|o que você faz|o que vc faz)\b/i',
            '/\b(ia|inteligência artificial|openai|chatgpt)\b/i',

            // funcionamento interno
            '/\b(como você funciona|como vc funciona|seu sistema)\b/i',
        ];

        foreach ($gatilhos as $rx) {
            if (preg_match($rx, $t)) {
                SommelierLog::warning("🚫 [FiltroPerguntaPessoal] Pergunta pessoal bloqueada", [
                    'texto' => $texto
                ]);
                return true;
            }
        }

        return false;
    }
}
