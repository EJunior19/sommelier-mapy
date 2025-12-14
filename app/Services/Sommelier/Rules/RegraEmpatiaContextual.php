<?php

namespace App\Services\Sommelier\Rules;

class RegraEmpatiaContextual
{
    /**
     * --------------------------------------------------
     * 🎭 Aplica tom humano à resposta final
     * --------------------------------------------------
     */
    public static function aplicar(string $mensagem, string $resposta): string
    {
        $t = mb_strtolower($mensagem, 'UTF-8');

        // 🔹 extremos de preço
        if (preg_match('/mais caro|mais barato/i', $t)) {
            return self::empatiaExtremos($t) . "\n\n" . $resposta;
        }

        // 🔹 pergunta estatística
        if (preg_match('/m[eé]dia|em geral|normalmente/i', $t)) {
            return "Para você ter uma noção geral 🍷\n\n" . $resposta;
        }

        // 🔹 continuação simples
        if (preg_match('/\boutro\b|\bmais um\b|\bseguinte\b/i', $t)) {
            return "Claro, te mostro outra opção 😊\n\n" . $resposta;
        }

        return $resposta;
    }

    protected static function empatiaExtremos(string $t): string
    {
        if (str_contains($t, 'mais caro')) {
            return "Se você busca algo realmente especial e premium 🍷";
        }

        if (str_contains($t, 'mais barato')) {
            return "Para uma opção simples e acessível 👌";
        }

        return '';
    }
}
