<?php

namespace App\Services\Sommelier;

class Normalizador
{
    /**
     * ---------------------------------------------------
     * 🔤 LOWERCASE
     * ---------------------------------------------------
     */
    public static function lower(string $t): string
    {
        return mb_strtolower(trim($t), 'UTF-8');
    }

    /**
     * ---------------------------------------------------
     * 🔤 REMOVER ACENTOS
     * ---------------------------------------------------
     */
    public static function noAcento(string $t): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    }

    /**
     * ---------------------------------------------------
     * 🧹 LIMPAR TEXTO (para TRGM)
     * ---------------------------------------------------
     */
    public static function textoLimpo(string $t): string
    {
        $t = self::lower($t);
        $t = self::noAcento($t);
        return trim(preg_replace('/[^a-z0-9 ]/', ' ', $t));
    }

    /**
     * ---------------------------------------------------
     * 🚫 STOPWORDS PT + ES
     * ---------------------------------------------------
     */
    public static function removerStopwords(array $palavras): array
    {
        $stop = [
            // PT
            'o','a','os','as','um','uma','uns','umas',
            'para','pra','por','com','no','na','nos','nas',
            'que','qual','quais','quanto','valor','preco','preço',
            'de','do','da','dos','das','sobre',
            'gostaria','queria','algo','alguma','algum',
            'me','te','se','la','lo','las','los','yo','tu','vc','voce',
            'bom','boa','oi','ola','olá','tudo','bem','ae','eae','salve',

            // ES
            'el','la','los','las','un','una','unos','unas',
            'para','por','con','sin','del','de',
            'que','cuanto','precio','valor',
            'quiero','busco','algo','alguna','alguno',
            'hola','buenos','buenas','como','esta','estás'
        ];

        return array_values(array_filter(array_diff($palavras, $stop)));
    }

    /**
     * ---------------------------------------------------
     * 🔢 NORMALIZAR NÚMEROS
     * ---------------------------------------------------
     */
    public static function numero(string $numStr): float
    {
        return (float) str_replace(',', '.', $numStr);
    }

    /**
     * ---------------------------------------------------
     * 🥃 NORMALIZAÇÃO PARA DETECÇÃO DE PRODUTO
     * (melhora o TRGM ridiculously)
     * ---------------------------------------------------
     */
    public static function normalizarProduto(string $t): string
    {
        // 1) limpar
        $t = self::textoLimpo($t);

        // 2) quebrar
        $p = explode(' ', $t);

        // 3) remover stopwords
        $p = self::removerStopwords($p);

        if (!$p) {
            return $t;
        }

        // 4) remover ruídos comuns em bebidas
        $ruido = [
            'vino','vinho','whisky','whiskey','cerveja','beer',
            'cabernet','malbec','merlot','sauvignon','tinto','branco',
            'ml','lt','litro','litrao'
        ];

        $p = array_diff($p, $ruido);

        // 5) remover duplicados
        $p = array_unique($p);

        return trim(implode(' ', $p));
    }

    /**
     * ---------------------------------------------------
     * 🌎 Perguntas sobre procedência
     * ---------------------------------------------------
     */
    public static function perguntaSobreOrigem(string $t): bool
    {
        $t = self::lower($t);

        return (bool) preg_match(
            '/(procedenc|origen|origem|de onde vem|de que pais|pais de origem|feito em|fabricado em)/i',
            $t
        );
    }
}
