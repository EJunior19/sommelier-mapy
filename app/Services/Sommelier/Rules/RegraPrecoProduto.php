<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\Search\Buscador;
use App\Services\Sommelier\UX\NomeFormatter;

/**
 * ==========================================================
 * 💲 REGRA — PREÇO DE PRODUTO
 * ----------------------------------------------------------
 * Detecta perguntas de preço como:
 * - "preço do whisky macallan"
 * - "quanto custa o Jack Daniels?"
 * - "valor do Chivas 12"
 *
 * Comportamento:
 * - Busca produto específico
 * - Retorna preço exato do banco
 * - Formata nome corretamente (UX + TTS)
 * ==========================================================
 */
class RegraPrecoProduto
{
    /**
     * --------------------------------------------------
     * 🔍 MATCH — pergunta de preço
     * --------------------------------------------------
     */
    public static function match(string $mensagem): bool
    {
        $t = mb_strtolower($mensagem, 'UTF-8');

        return (bool) preg_match(
            '/\b(pre[cç]o|precio|quanto custa|cuanto cuesta|valor|price|custa)\b/i',
            $t
        );
    }

    /**
     * --------------------------------------------------
     * 🧠 RESPONDER
     * --------------------------------------------------
     */
    public static function responder(string $mensagem): ?string
    {
        SommelierLog::info("💲 [RegraPrecoProduto] Pergunta de preço detectada");

        $produto = Buscador::buscarProdutoPorTexto($mensagem);

        if (!$produto || empty($produto['precio'])) {
            SommelierLog::warning("💲 [RegraPrecoProduto] Produto ou preço não encontrado", [
                'produto' => $produto
            ]);

            return "Não encontrei o preço desse produto no momento 😕 Posso te mostrar opções semelhantes?";
        }

        // ✅ FORMATAÇÃO CORRETA
        $nomeFormatado = NomeFormatter::formatar($produto['nome_limpo']);
        $preco = number_format((float) $produto['precio'], 2, ',', '.');

        SommelierLog::info("💲 [RegraPrecoProduto] Preço encontrado", [
            'produto' => $nomeFormatado,
            'preco'   => $preco
        ]);

        return "O {$nomeFormatado} custa {$preco} dólares.";
    }
}
