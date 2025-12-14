<?php

namespace App\Services\Sommelier\Rules;

class RegraConfianca
{
    /**
     * --------------------------------------------------
     * 🛡️ Aplica tom de confiança à resposta
     * --------------------------------------------------
     */
    public static function aplicar(string $mensagem, string $resposta): string
    {
        $t = mb_strtolower($mensagem, 'UTF-8');

        if (self::expressaDuvida($t)) {
            return self::fraseConfianca($t) . "\n\n" . $resposta;
        }

        return $resposta;
    }

    /**
     * --------------------------------------------------
     * 🔍 Detecta sinais de dúvida / validação
     * --------------------------------------------------
     */
    protected static function expressaDuvida(string $t): bool
    {
        return (bool) preg_match(
            '/\b(bom|boa|vale a pena|recomenda|indica|conf[ií]avel|seguro|melhor op[cç][aã]o|qual escolher)\b/i',
            $t
        );
    }

    /**
     * --------------------------------------------------
     * 🗣️ Frases humanas de confiança
     * --------------------------------------------------
     */
    protected static function fraseConfianca(string $t): string
    {
        if (str_contains($t, 'vale')) {
            return "Vale sim — é uma opção bem consistente 🍷";
        }

        if (str_contains($t, 'recomenda') || str_contains($t, 'indica')) {
            return "Se você quer ir sem erro, essa é uma escolha bem segura 👍";
        }

        if (str_contains($t, 'bom') || str_contains($t, 'boa')) {
            return "É sim, bastante apreciado por quem costuma escolher esse estilo 🍷";
        }

        return "É uma opção bem confiável e equilibrada 👌";
    }
}
