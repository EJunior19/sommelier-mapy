<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\AI\OpenAISommelier;
use App\Helpers\SommelierLog;

class RegraPerguntaConceitual
{
    /**
     * --------------------------------------------------
     * 🔍 Detecta perguntas conceituais / educativas
     * --------------------------------------------------
     */
    public static function match(string $mensagem): bool
    {
        $t = mb_strtolower($mensagem, 'UTF-8');

        return (bool) preg_match(
            '/\b(o que é|como funciona|como é feito|qual a diferença|para que serve|história do|história da|origem do|origem da)\b/i',
            $t
        );
    }

    /**
     * --------------------------------------------------
     * 🧠 Responde perguntas conceituais
     * --------------------------------------------------
     */
    public static function responder(
        string $mensagem,
        OpenAISommelier $ai
    ): ?string {
        SommelierLog::info("📘 [RegraPerguntaConceitual] Pergunta conceitual detectada", [
            'mensagem' => $mensagem
        ]);

        /**
         * 🧱 1️⃣ Resposta fixa (ANTI-ALUCINAÇÃO)
         */
        $fixa = self::respostaEducativaFixa($mensagem);

        if ($fixa) {
            SommelierLog::info("📘 [RegraPerguntaConceitual] Resposta fixa aplicada");
            return $fixa;
        }

        /**
         * 🤖 2️⃣ Fallback IA CONTROLADO
         */
        $prompt = <<<PROMPT
Você é um sommelier profissional.

Explique de forma educativa, clara e curta a pergunta abaixo.

REGRAS OBRIGATÓRIAS:
- NÃO cite preços
- NÃO cite estoque
- NÃO recomende produtos
- NÃO invente marcas
- NÃO faça propaganda
- Use linguagem simples e amigável

Pergunta do cliente:
"{$mensagem}"
PROMPT;

        // ✅ MÉTODO CORRETO DO SEU OpenAISommelier
        $respostaIA = $ai->responderSommelier($prompt);

        if (!$respostaIA) {
            return null;
        }

        SommelierLog::info("📘 [RegraPerguntaConceitual] Resposta IA gerada com sucesso");

        return $respostaIA;
    }

    /**
     * --------------------------------------------------
     * 📚 Respostas educativas fixas
     * --------------------------------------------------
     */
    protected static function respostaEducativaFixa(string $mensagem): ?string
    {
        $t = mb_strtolower($mensagem, 'UTF-8');

        if (str_contains($t, 'whisky') && str_contains($t, 'como')) {
            return "O whisky é produzido a partir da fermentação de grãos, como cevada, milho ou centeio. 
Depois de fermentado, ele é destilado e envelhecido em barris de madeira, o que influencia diretamente seu sabor e aroma.";
        }

        if (str_contains($t, 'vinho') && str_contains($t, 'como')) {
            return "O vinho é feito pela fermentação das uvas. 
O tipo de uva, o clima e o tempo de maturação influenciam no aroma, sabor e corpo da bebida.";
        }

        if (str_contains($t, 'diferença') && str_contains($t, 'whisky')) {
            return "As diferenças entre whiskies estão na origem, no tipo de grão utilizado, 
no método de destilação e no tempo de envelhecimento, resultando em perfis mais suaves ou mais intensos.";
        }

        return null;
    }
}
