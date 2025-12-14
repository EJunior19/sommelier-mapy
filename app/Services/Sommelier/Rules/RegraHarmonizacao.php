<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\AI\OpenAISommelier;
use App\Helpers\SommelierLog;

/**
 * ==========================================================
 * ⚖️ REGRA — COMPARAÇÃO DE BEBIDAS
 * ----------------------------------------------------------
 * Detecta perguntas como:
 * - whisky vs vodka
 * - vinho ou espumante
 * - diferença entre bourbon e scotch
 *
 * Comportamento:
 * - NÃO cita preços
 * - NÃO cita estoque
 * - NÃO recomenda marcas
 * - Explicação conceitual e educativa
 * ==========================================================
 */
class RegraComparacaoBebidas
{
    /**
     * --------------------------------------------------
     * 🔍 MATCH — É uma comparação?
     * --------------------------------------------------
     */
    public static function match(string $mensagem): bool
    {
        $t = mb_strtolower($mensagem, 'UTF-8');

        return (bool) preg_match(
            '/\b(vs|versus|ou|diferença|diferença entre|qual é melhor|comparar|comparação)\b/i',
            $t
        );
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

        SommelierLog::info("⚖️ [RegraComparacao] Analisando comparação", [
            'mensagem' => $mensagem
        ]);

        $bebidas = self::extrairBebidas($mensagem);

        if (count($bebidas) < 2) {
            SommelierLog::info("⚖️ [RegraComparacao] Bebidas insuficientes para comparação");
            return null;
        }

        /**
         * 🧱 Prompt CONTROLADO (anti-alucinação)
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
     * ✍️ Prompt controlado para IA
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

Foque em:
- origem
- estilo
- método de produção
- perfil de sabor
- para que tipo de pessoa cada opção é mais indicada

Use linguagem simples, profissional e amigável.
PROMPT;
    }

    /**
     * --------------------------------------------------
     * 🧪 Extrai possíveis bebidas da frase
     * --------------------------------------------------
     */
    protected static function extrairBebidas(string $texto): array
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $encontradas = [];

        // separa por conectores comuns
        $partes = preg_split('/\b(vs|versus|ou|e|,)\b/i', $texto);

        foreach ($partes as $p) {
            $p = trim($p);

            // remove palavras genéricas
            $p = preg_replace(
                '/\b(qual|melhor|diferença|entre|comparar|comparação|é|o|a)\b/i',
                '',
                $p
            );

            if (mb_strlen($p) >= 4) {
                $encontradas[] = ucfirst(trim($p));
            }
        }

        return array_values(array_unique($encontradas));
    }
}
