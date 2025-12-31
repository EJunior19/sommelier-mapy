<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\AI\OpenAISommelier;
use App\Helpers\SommelierLog;

class RegraPerguntaConceitual
{
    /**
     * --------------------------------------------------
     * 🔍 DETECTA PERGUNTAS CONCEITUAIS / EDUCATIVAS
     * --------------------------------------------------
     */
    public static function match(string $mensagem): bool
    {
        $t = mb_strtolower(trim($mensagem), 'UTF-8');

        /**
         * 🛑 Mensagens muito curtas não são conceituais
         * (ex: "oi", "ok", "sim")
         */
        if (mb_strlen($t) < 8) {
            return false;
        }

        // 🚫 BLOQUEIOS — catálogo / oferta
        if (preg_match('/
            quais\s+tipos\s+de|
            que\s+tipos\s+de|
            tipos\s+de\s+bebidas|
            o\s+que\s+vocês\s+tem|
            o\s+que\s+vocês\s+têm|
            o\s+que\s+tem\s+pra\s+beber|
            card[aá]pio|
            cat[aá]logo
        /ix', $t)) {
            return false;
        }

        // 🚫 BLOQUEIOS — preço / compra
        if (preg_match('/
            quanto\s+custa|
            preço|
            em\s+m[eé]dia|
            mais\s+barato|
            mais\s+caro|
            promoção
        /ix', $t)) {
            return false;
        }

        // 🚫 BLOQUEIOS — recomendação direta
        if (preg_match('/
            recomenda|
            indica|
            sugere|
            quero|
            preciso|
            me\s+mostra|
            algo\s+para|
            algo\s+pro
        /ix', $t)) {
            return false;
        }

        /**
         * ✅ PADRÕES CONCEITUAIS / EDUCATIVOS
         */
        return (bool) preg_match(
            '/\b(
                o\s+que\s+é|
                o\s+que\s+são|
                como\s+funciona|
                como\s+é\s+feito|
                como\s+se\s+faz|
                qual\s+a\s+diferença|
                diferença\s+entre|
                para\s+que\s+serve|
                história\s+do|
                história\s+da
            )\b/ix',
            $t
        );
    }

    /**
     * --------------------------------------------------
     * 🧠 RESPONDE PERGUNTAS CONCEITUAIS
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
         * 🧱 1️⃣ RESPOSTA FIXA (ANTI-ALUCINAÇÃO)
         */
        $fixa = self::respostaEducativaFixa($mensagem);

        if ($fixa) {
            SommelierLog::info("📘 [RegraPerguntaConceitual] Resposta fixa aplicada");
            return $fixa;
        }

        /**
         * 🤖 2️⃣ IA CONTROLADA (ÚLTIMO RECURSO)
         */
        if (!method_exists($ai, 'responderSommelier')) {
            return null;
        }

        $prompt = <<<PROMPT
Você é um sommelier profissional experiente.

Explique de forma EDUCATIVA, SIMPLES e CURTA a pergunta abaixo.

REGRAS OBRIGATÓRIAS:
- NÃO recomende bebidas
- NÃO cite marcas
- NÃO cite preços
- NÃO cite estoque
- NÃO faça propaganda
- NÃO invente informações
- Linguagem clara e amigável
- Máximo de 5 linhas

Pergunta do cliente:
"{$mensagem}"
PROMPT;

        $respostaIA = $ai->responderSommelier($prompt);

        if (!is_string($respostaIA) || trim($respostaIA) === '') {
            SommelierLog::warning("⚠️ [RegraPerguntaConceitual] IA não retornou resposta válida");
            return null;
        }

        SommelierLog::info("📘 [RegraPerguntaConceitual] Resposta IA gerada com sucesso");

        return trim($respostaIA);
    }

    /**
     * --------------------------------------------------
     * 📚 RESPOSTAS EDUCATIVAS FIXAS (BASE DE CONHECIMENTO)
     * --------------------------------------------------
     */
    protected static function respostaEducativaFixa(string $mensagem): ?string
    {
        $t = mb_strtolower($mensagem, 'UTF-8');

        // ================= WHISKY =================
        if (str_contains($t, 'whisky') && str_contains($t, 'como')) {
            return "O whisky é produzido a partir da fermentação de grãos como cevada, milho ou centeio.
Após a fermentação, ele é destilado e envelhecido em barris de madeira, processo que define seu sabor, aroma e cor.";
        }

        if (str_contains($t, 'diferença') && str_contains($t, 'whisky')) {
            return "As diferenças entre whiskies envolvem o país de origem, o tipo de grão utilizado,
o método de destilação e o tempo de envelhecimento, resultando em perfis mais suaves ou mais intensos.";
        }

        // ================= VINHO =================
        if (str_contains($t, 'vinho') && str_contains($t, 'como')) {
            return "O vinho é feito pela fermentação das uvas.
O tipo de uva, o clima e o processo de produção influenciam diretamente no aroma, sabor e corpo da bebida.";
        }

        if (str_contains($t, 'diferença') && str_contains($t, 'vinho')) {
            return "Os vinhos variam conforme a uva, o método de produção e o tempo de maturação,
resultando em estilos mais leves, frutados ou encorpados.";
        }

        // ================= ESPUMANTE =================
        if (str_contains($t, 'espumante')) {
            return "O espumante é um vinho que passa por uma segunda fermentação, responsável pelas bolhas.
Ele pode variar de seco a doce e costuma ser associado a celebrações.";
        }

        // ================= GIN =================
        if (str_contains($t, 'gin')) {
            return "O gin é um destilado aromatizado principalmente com zimbro e outras especiarias.
Seu perfil costuma ser fresco e herbal, muito usado em coquetéis.";
        }

        // ================= CERVEJA =================
        if (str_contains($t, 'cerveja')) {
            return "A cerveja é feita a partir de água, malte, lúpulo e fermento.
Existem diversos estilos, que variam de leves e refrescantes a mais encorpados.";
        }

        // ================= DESTILADOS =================
        if (str_contains($t, 'destilado')) {
            return "Destilados são bebidas obtidas por destilação após fermentação, como whisky, gin, vodka e rum.
Esse processo gera bebidas com maior teor alcoólico e sabores mais concentrados.";
        }

        // ================= SEM ÁLCOOL =================
        if (str_contains($t, 'sem álcool') || str_contains($t, 'sem alcool')) {
            return "Bebidas sem álcool mantêm sabor e refrescância, mas sem teor alcoólico.
São ideais para quem prefere algo leve ou não consome álcool.";
        }

        // ================= LICOR =================
        if (str_contains($t, 'licor')) {
            return "O licor é uma bebida alcoólica adocicada, feita a partir da mistura de álcool
com frutas, ervas, sementes ou especiarias, resultando em sabores mais doces e aromáticos.";
        }

        return null;
    }
}
