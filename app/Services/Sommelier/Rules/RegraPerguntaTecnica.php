<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\AI\OpenAISommelier;

/**
 * ==========================================================
 * 🧪 REGRA — PERGUNTA TÉCNICA SOBRE BEBIDAS
 * ----------------------------------------------------------
 * Exemplos:
 * - "como é feito o whisky?"
 * - "o que significa single malt?"
 * - "quanto tempo fica no barril?"
 * - "qual o teor alcoólico médio?"
 *
 * Comportamento:
 * - NÃO lista produtos
 * - NÃO consulta estoque
 * - Resposta educativa / técnica
 * - IA usada de forma controlada
 * ==========================================================
 */
class RegraPerguntaTecnica
{
    /**
     * --------------------------------------------------
     * 🔍 Gatilhos técnicos
     * --------------------------------------------------
     */
    protected static array $gatilhos = [
        // processo
        '/\b(como é feito|como se faz|processo|produção|destilação|destilado)\b/i',

        // envelhecimento
        '/\b(barril|envelhecid|maturação|anos?|idade)\b/i',

        // graduação alcoólica
        '/\b(teor alcoólico|graduação|álcool|percentual)\b/i',

        // conceitos técnicos
        '/\b(o que é|significa|quer dizer|conceito)\b/i',

        // termos técnicos comuns
        '/\b(single malt|blended|bourbon|scotch|irish|tennessee)\b/i',
    ];

    /**
     * --------------------------------------------------
     * 🧪 MATCH — É pergunta técnica?
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

        SommelierLog::info("🧪 [RegraPerguntaTecnica] Pergunta técnica detectada", [
            'mensagem' => $mensagem
        ]);

        $prompt = self::montarPrompt($mensagem);

        // ✅ método correto
        $resposta = $ai->responderSommelier($prompt);

        if (!$resposta) {
            return null;
        }

        SommelierLog::info("🧪 [RegraPerguntaTecnica] Resposta técnica gerada com sucesso");

        return $resposta;
    }

    /**
     * --------------------------------------------------
     * ✍️ Prompt técnico controlado
     * --------------------------------------------------
     */
    protected static function montarPrompt(string $mensagem): string
    {
        return <<<PROMPT
Você é um sommelier profissional e educador.

Responda de forma clara, objetiva e técnica à pergunta abaixo:

"{$mensagem}"

REGRAS:
- NÃO cite preços
- NÃO cite marcas comerciais
- NÃO fale de estoque ou promoções
- Use linguagem simples e educativa
- Evite jargões excessivos

Explique como se estivesse ensinando um cliente curioso.
PROMPT;
    }
}
