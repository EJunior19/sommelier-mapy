<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\AI\OpenAISommelier;

/**
 * ==========================================================
 * 🎉 REGRA — OCASIÃO ESPECIAL
 * ----------------------------------------------------------
 * Detecta perguntas relacionadas a ocasiões como:
 * - aniversário
 * - presente
 * - jantar especial
 * - comemoração
 * - encontro / date
 * - casamento
 * - celebração
 *
 * Comportamento:
 * - NÃO lista produtos
 * - NÃO cita preços
 * - NÃO cita estoque
 * - Dá orientação consultiva (estilo / tipo / perfil)
 * ==========================================================
 */
class RegraOcasiãoEspecial
{
    /**
     * --------------------------------------------------
     * 🔍 Gatilhos de ocasião
     * --------------------------------------------------
     */
    protected static array $gatilhos = [
        '/\b(anivers[aá]rio|presente|comemora[cç][aã]o|celebra[cç][aã]o)\b/i',
        '/\b(jantar especial|jantar rom[aâ]ntico|date|encontro)\b/i',
        '/\b(casamento|noivado|bodas|formatura)\b/i',
        '/\b(ocasi[aã]o especial|algo especial|algo diferente)\b/i',
    ];

    /**
     * --------------------------------------------------
     * 🎯 MATCH
     * --------------------------------------------------
     */
    public static function match(string $mensagem): bool
    {
        foreach (self::$gatilhos as $rx) {
            if (preg_match($rx, $mensagem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * --------------------------------------------------
     * 🧠 RESPONDER
     * --------------------------------------------------
     */
    public static function responder(
        string $mensagem,
        OpenAISommelier $ai
    ): ?string {

        SommelierLog::info("🎉 [RegraOcasiãoEspecial] Ocasião especial detectada", [
            'mensagem' => $mensagem
        ]);

        /**
         * 🧱 Prompt CONTROLADO (anti-alucinação)
         */
        $prompt = <<<PROMPT
Você é um sommelier profissional e experiente.

Explique de forma clara, elegante e consultiva como escolher uma bebida
para a ocasião descrita abaixo.

REGRAS OBRIGATÓRIAS:
- NÃO cite preços
- NÃO cite estoque
- NÃO recomende marcas
- NÃO faça propaganda
- NÃO liste produtos
- Use linguagem simples e amigável

Pergunta do cliente:
"{$mensagem}"

Explique:
- quais estilos de bebidas combinam com a ocasião
- diferenças entre opções leves, elegantes e marcantes
- quando faz sentido escolher vinho, espumante ou destilado
PROMPT;

        // ✅ MÉTODO CORRETO
        $resposta = $ai->responderSommelier($prompt);

        if (!$resposta) {
            return null;
        }

        SommelierLog::info("🎉 [RegraOcasiãoEspecial] Resposta gerada com sucesso");

        return $resposta;
    }
}
