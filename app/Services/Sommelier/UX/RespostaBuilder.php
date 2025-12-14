<?php

namespace App\Services\Sommelier\UX;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\Presentation\Emojis;

class RespostaBuilder
{
    /**
     * --------------------------------------------------
     * 🧠 Lista principal de bebidas
     * --------------------------------------------------
     */
    public static function listaBebidas(
        array $bebidas,
        string $textoOriginal,
        int $limite = 6
    ): string {
        SommelierLog::info("🧩 [RespostaBuilder] Montando lista de bebidas", [
            'total_recebido' => count($bebidas),
            'limite'         => $limite
        ]);

        if (empty($bebidas)) {
            return self::nenhumResultado();
        }

        $bebidas = array_slice($bebidas, 0, $limite);

        $introducoes = [
            "Encontrei algumas opções que combinam com o que você procura 🍷",
            "Separei algumas boas opções para você 🍇",
            "Esses rótulos podem ser uma ótima escolha 🍷",
        ];

        $introducao = $introducoes[array_rand($introducoes)];
        $linhas = [];

        foreach ($bebidas as $b) {

            $emojiTipo = Emojis::tipo($b['tipo'] ?? '');

            $nome = mb_convert_case(
                trim($b['nome_limpo'] ?? 'Produto'),
                MB_CASE_TITLE,
                'UTF-8'
            );

            $preco = $b['preco_voz'] ?? ($b['precio'] ?? null);

            if (is_numeric($preco)) {
                $preco = number_format((float)$preco, 2, ',', '.') . ' dólares';
            } else {
                $preco = 'consulte valor';
            }

            // ✅ UMA LINHA POR PRODUTO
            $linhas[] = "👉 {$emojiTipo} {$nome} — {$preco}";
        }

        $resposta =
            "{$introducao}\n\n" .
            implode("\n", $linhas) .
            "\n\nQuer refinar por marca, tipo, preço ou volume?";

        SommelierLog::info("🤖 [RespostaBuilder] Resposta final montada");

        return $resposta;
    }

    /**
     * --------------------------------------------------
     * 🎯 Resposta quando filtros já foram aplicados
     * --------------------------------------------------
     */
    public static function respostaComFiltro(array $bebidas): string
    {
        SommelierLog::info("🎯 [RespostaBuilder] Resposta com filtros", [
            'total' => count($bebidas)
        ]);

        if (empty($bebidas)) {
            return self::nenhumResultado();
        }

        $linhas = [];

        foreach (array_slice($bebidas, 0, 6) as $b) {

            $emojiTipo = Emojis::tipo($b['tipo'] ?? '');

            $nome = mb_convert_case(
                trim($b['nome_limpo'] ?? 'Produto'),
                MB_CASE_TITLE,
                'UTF-8'
            );

            $preco = $b['preco_voz']
                ?? number_format((float)$b['precio'], 2, ',', '.') . ' dólares';

            $linhas[] =
                "👉 {$emojiTipo} {$nome}\n" .
                "   💲 {$preco}";
        }

        return
            "Aqui estão opções dentro do que você descreveu:\n\n" .
            implode("\n\n", $linhas) .
            "\n\nQuer ajustar algum detalhe?";
    }

    /**
     * --------------------------------------------------
     * 🔍 Produto único
     * --------------------------------------------------
     */
    public static function produtoUnico(array $produto): string
    {
        SommelierLog::info("🔍 [RespostaBuilder] Produto único", [
            'produto' => $produto['nome_limpo'] ?? null
        ]);

        $emojiTipo = Emojis::tipo($produto['tipo'] ?? '');

        $nome = mb_convert_case(
            trim($produto['nome_limpo'] ?? 'Produto'),
            MB_CASE_TITLE,
            'UTF-8'
        );

        $preco = $produto['preco_voz']
            ?? number_format((float)$produto['precio'], 2, ',', '.') . ' dólares';

        return
            "{$emojiTipo} {$nome}\n" .
            "Preço: {$preco}\n\n" .
            "Quer saber procedência, volume ou ver opções similares?";
    }

    /**
     * --------------------------------------------------
     * ❌ Nenhum resultado
     * --------------------------------------------------
     */
    public static function nenhumResultado(): string
    {
        SommelierLog::info("❌ [RespostaBuilder] Nenhum resultado encontrado");

        return
            "Não encontrei nenhuma bebida com essas características 😕\n\n" .
            "Você pode tentar outra marca, tipo de bebida ou faixa de preço.";
    }
}
