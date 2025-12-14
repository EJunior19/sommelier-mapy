<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\AI\OpenAISommelier;
use App\Helpers\SommelierLog;

/**
 * ==========================================================
 * 🤖 REGRA FALLBACK IA
 * ----------------------------------------------------------
 * Usada SOMENTE quando:
 * - Nenhuma regra respondeu
 * - Nenhuma busca no banco teve resultado
 *
 * ⚠️ REGRA DE OURO:
 * - Nunca inventar bebidas
 * - Nunca responder fora do domínio bebidas
 * ==========================================================
 */
class RegraFallbackIA
{
    /**
     * --------------------------------------------------
     * 🧠 Resposta fallback via IA
     * --------------------------------------------------
     */
    public static function responder(string $mensagem, OpenAISommelier $ai): ?string
    {
        $mensagem = trim($mensagem);

        if ($mensagem === '') {
            return null;
        }

        SommelierLog::info("🤖 [FALLBACK IA] Ativado", [
            'mensagem' => $mensagem
        ]);

        // Detectar idioma básico
        $idioma = self::detectarIdioma($mensagem);

        // Prompt forte, com limites claros
        $prompt = $idioma === 'es'
            ? self::promptES($mensagem)
            : self::promptPT($mensagem);

        $resposta = $ai->responderSommelier($prompt);

        if (!is_string($resposta) || trim($resposta) === '') {
            SommelierLog::warning("🤖 [FALLBACK IA] IA não retornou resposta válida");
            return null;
        }

        // Bloqueia respostas genéricas
        if (self::respostaInvalida($resposta)) {
            SommelierLog::warning("🤖 [FALLBACK IA] Resposta genérica bloqueada", [
                'resposta' => $resposta
            ]);
            return null;
        }

        SommelierLog::info("🤖 [FALLBACK IA] Resposta aceita");
        return $resposta;
    }

    /**
     * --------------------------------------------------
     * 🌎 Detecta idioma PT / ES
     * --------------------------------------------------
     */
    protected static function detectarIdioma(string $t): string
    {
        if (preg_match('/\b(quiero|busco|precio|opcion|recomienda|bebida)\b/i', $t)) {
            return 'es';
        }

        return 'pt';
    }

    /**
     * --------------------------------------------------
     * 🇧🇷 Prompt PT-BR
     * --------------------------------------------------
     */
    protected static function promptPT(string $mensagem): string
    {
        return <<<PROMPT
Você é a Sommelier Virtual do Shopping Mapy.

REGRAS ABSOLUTAS:
- Fale SOMENTE sobre bebidas.
- NÃO invente produtos, marcas, preços ou volumes.
- Se a pergunta não for clara, peça mais detalhes.
- Responda curto, humano e simpático.
- Máximo de 2 emojis.
- NÃO faça a saudação padrão do shopping.

Pergunta do cliente:
"{$mensagem}"
PROMPT;
    }

    /**
     * --------------------------------------------------
     * 🇪🇸 Prompt ES
     * --------------------------------------------------
     */
    protected static function promptES(string $mensagem): string
    {
        return <<<PROMPT
Eres el Sommelier Virtual del Shopping Mapy.

REGLAS ABSOLUTAS:
- Habla SOLO sobre bebidas.
- NO inventes productos, marcas, precios ni volúmenes.
- Si la pregunta no es clara, pide más detalles.
- Responde de forma breve, humana y amable.
- Máximo 2 emojis.
- NO hagas el saludo estándar del shopping.

Pregunta del cliente:
"{$mensagem}"
PROMPT;
    }

    /**
     * --------------------------------------------------
     * 🚫 Bloqueia respostas ruins
     * --------------------------------------------------
     */
    protected static function respostaInvalida(string $txt): bool
    {
        $t = mb_strtolower($txt, 'UTF-8');

        $invalidas = [
            'não tenho informações',
            'nao tenho informacoes',
            'como uma ia',
            'não posso ajudar',
            'nao posso ajudar',
            'não encontrei',
            'nao encontrei',
            'sou uma ia',
            'como assistente',
        ];

        foreach ($invalidas as $bad) {
            if (str_contains($t, $bad)) {
                return true;
            }
        }

        return false;
    }
}
