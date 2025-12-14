<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\AI\OpenAISommelier;

/**
 * ==========================================================
 * ⚖️ REGRA — COMPARAÇÃO DE BEBIDAS (CONCEITUAL)
 * ----------------------------------------------------------
 * Exemplos:
 * - "qual a diferença entre Jack Daniels e Macallan?"
 * - "bourbon vs scotch"
 * - "vinho tinto ou branco?"
 *
 * Comportamento:
 * - NÃO consulta estoque
 * - NÃO lista produtos
 * - NÃO cita preços
 * - Resposta educativa e neutra
 * ==========================================================
 */
class RegraComparacaoBebidas
{
    /**
     * --------------------------------------------------
     * 🔍 Gatilhos de comparação
     * --------------------------------------------------
     */
    protected static array $gatilhos = [
        '/\b(diferença|diferenca|comparar|comparação|comparacao)\b/i',
        '/\b(vs|versus)\b/i',
        '/\b(qual é melhor|qual e melhor|melhor que)\b/i',
    ];

    /**
     * --------------------------------------------------
     * 🧪 MATCH — É pergunta comparativa?
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

        SommelierLog::info("⚖️ [RegraComparacao] Pergunta comparativa detectada", [
            'mensagem' => $mensagem
        ]);

        $bebidas = self::extrairBebidas($mensagem);

        if (count($bebidas) < 2) {
            SommelierLog::info("⚖️ [RegraComparacao] Bebidas insuficientes para comparação");
            return null;
        }

        /**
         * 🤖 Prompt controlado (anti-alucinação)
         */
        $prompt = self::promptComparacao($bebidas);

        // ✅ MÉTODO CORRETO
        $resposta = $ai->responderSommelier($prompt);

        if (!$resposta) {
            return null;
        }

        SommelierLog::info("⚖️ [RegraComparacao] Resposta gerada com sucesso", [
            'bebidas' => $bebidas
        ]);

        return $resposta;
    }

    /**
     * --------------------------------------------------
     * 🧠 Extrai bebidas da frase (heurística simples)
     * --------------------------------------------------
     */
    protected static function extrairBebidas(string $texto): array
    {
        $texto = mb_strtolower($texto, 'UTF-8');

        // separa por conectores típicos de comparação
        $partes = preg_split('/\b(vs|versus|entre|e)\b/i', $texto);

        $bebidas = [];

        foreach ($partes as $p) {
            $p = trim($p);

            // remove palavras genéricas
            $p = preg_replace(
                '/\b(qual|melhor|diferença|comparar|comparação|é|o|a|do|da)\b/i',
                '',
                $p
            );

            if (mb_strlen($p) >= 4) {
                $bebidas[] = ucfirst($p);
            }
        }

        return array_values(array_unique($bebidas));
    }

    /**
     * --------------------------------------------------
     * ✍️ Prompt base de comparação
     * --------------------------------------------------
     */
    protected static function promptComparacao(array $bebidas): string
    {
        $lista = implode(' e ', $bebidas);

        return <<<PROMPT
Você é um sommelier profissional.

Explique de forma clara, objetiva e educativa a diferença entre:
{$lista}

REGRAS OBRIGATÓRIAS:
- NÃO cite preços
- NÃO cite estoque
- NÃO recomende marcas
- NÃO faça propaganda

Explique:
- origem
- estilo
- processo de produção
- perfil de sabor
- para que tipo de pessoa cada opção é indicada

Use linguagem simples, profissional e amigável.
PROMPT;
    }
}
