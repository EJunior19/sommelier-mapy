<?php

namespace App\Services\Sommelier;

class Normalizador
{
    public static function lower(string $t): string
    {
        return mb_strtolower(trim($t), 'UTF-8');
    }

    public static function noAcento(string $t): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
    }

    public static function textoLimpo(string $t): string
    {
        $t = self::lower($t);
        $t = self::noAcento($t);
        return trim(preg_replace('/[^a-z0-9 ]/', ' ', $t));
    }

    public static function removerStopwords(array $palavras): array
    {
        $stop = [
            'o','a','os','as','um','uma','uns','umas',
            'para','pra','por','com','no','na','nos','nas',
            'que','qual','quais','quanto','valor','preco','preço',
            'de','do','da','dos','das','sobre',
            'gostaria','queria','algo','alguma','algum',
            'me','te','se','la','lo','las','los','yo','tu','vc','voce',
            'bom','boa','oi','ola','olá','tudo','bem','ae','eae','salve'
        ];

        return array_values(array_filter(array_diff($palavras, $stop)));
    }

    public static function numero(string $numStr): float
    {
        return (float) str_replace([','], ['.'], $numStr);
    }
}
