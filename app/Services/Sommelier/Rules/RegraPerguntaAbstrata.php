<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\AI\OpenAISommelier;
use App\Services\Sommelier\Memory\MemoriaContextualCurta;
use App\Services\Sommelier\Support\Normalizador;
use App\Helpers\SommelierLog;

class RegraPerguntaAbstrata
{
    /**
     * ==================================================
     * 🎯 GATILHOS DE PERGUNTA ABSTRATA (PT + ES)
     * ==================================================
     */
    protected static array $regex = [

        // Melhor / ranking
        '/\b(qual|cu[aá]l)\s+(é\s+)?o\s+melhor\b/i',
        '/\b(mejor)\s+(vino|whisky|licor|cerveja)\b/i',
        '/\b(do|del)\s+mundo\b/i',

        // Origem / criação / história
        '/\b(quem|qu[ií]en)\s+(criou|inventou|inventó)\b/i',
        '/\b(hist[oó]ria)\b/i',
        '/\b(de\s+onde\s+(vem|surgiu)|origen)\b/i',

        // Pedido explicativo
        '/\b(explica|explique|me\s+conta|cuéntame)\b/i',

        // Conceitos gerais
        '/\b(o\s+que\s+é|qué\s+es)\b/i',
    ];

    /**
     * ==================================================
     * 🔍 DETECÇÃO DA PERGUNTA
     * ==================================================
     */
    public static function match(string $mensagem): bool
    {
        $t = Normalizador::textoLimpo($mensagem);

        if ($t === '') {
            return false;
        }

        foreach (self::$regex as $rx) {
            if (preg_match($rx, $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ==================================================
     * 🧠 RESPOSTA EDUCATIVA + CONTEXTO
     * ==================================================
     */
    public static function responder(
        string $mensagem,
        OpenAISommelier $ai
    ): ?string {

        SommelierLog::info("🧠 [RegraPerguntaAbstrata] Pergunta abstrata detectada", [
            'mensagem' => $mensagem
        ]);

        // ===============================
        // 🧠 DETECTAR CONTEXTO IMPLÍCITO
        // ===============================
        self::salvarContexto($mensagem);

        /**
         * ==================================================
         * 🔒 PROMPT BLINDADO (ANTI-HALLUCINATION)
         * ==================================================
         */
        $prompt = <<<PROMPT
Você é a Sommelier Mapy, assistente oficial do Shopping Mapy.

Objetivo:
Responder de forma educativa e clara sobre bebidas, SEM vender produtos.

REGRAS OBRIGATÓRIAS:
- Seja breve (máx. 5 linhas)
- NÃO cite preços
- NÃO liste produtos
- NÃO mencione marcas comerciais
- NÃO invente dados históricos
- NÃO use emojis excessivos
- NÃO diga apenas "depende do gosto" — explique

ESTILO:
- Linguagem simples
- Tom profissional e amigável
- Conteúdo correto e verificável

FINALIZAÇÃO OBRIGATÓRIA:
Convide o cliente a escolher uma bebida do Shopping Mapy.

Pergunta do cliente:
{$mensagem}
PROMPT;

        return $ai->responderSommelier($prompt);
    }

    /**
     * ==================================================
     * 🧠 SALVAR CONTEXTO CURTO (categoria implícita)
     * ==================================================
     */
    protected static function salvarContexto(string $mensagem): void
    {
        $t = Normalizador::textoLimpo($mensagem);

        $map = [
            'VINOS'      => '/vinho|vino|vinhos|vinos/i',
            'WHISKY'     => '/whisky|whiskey|u[ií]sque/i',
            'CERVEZA'    => '/cerveja|cerveza|beer/i',
            'GIN'        => '/\bgin\b/i',
            'VODKA'      => '/vodka/i',
            'LICORES'    => '/licor|licores/i',
            'ESPUMANTES' => '/espumante|champagne|prosecco/i',
        ];

        foreach ($map as $categoria => $rx) {
            if (preg_match($rx, $t)) {
                MemoriaContextualCurta::registrar([
                    'categoria' => $categoria
                ]);

                SommelierLog::info("🧠 [ContextoCurto] Categoria inferida", [
                    'categoria' => $categoria
                ]);

                break;
            }
        }
    }
}
