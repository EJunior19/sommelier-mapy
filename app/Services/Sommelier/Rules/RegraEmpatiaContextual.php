<?php

namespace App\Services\Sommelier\Rules;

class RegraEmpatiaContextual
{
    /**
     * --------------------------------------------------
     * 🎭 Aplica tom humano e empático à resposta
     * --------------------------------------------------
     */
    public static function aplicar(string $mensagem, string $resposta): string
    {
        $t = mb_strtolower($mensagem, 'UTF-8');

        // ===============================
        // 🥩 COMIDA / OCASIÃO
        // ===============================
        if (preg_match('/\b(peixe|pescado|mariscos)\b/i', $t)) {
            return "Que legal 😄 Peixe é uma ótima escolha.\n\n" . $resposta;
        }

        if (preg_match('/\b(churrasco|asado)\b/i', $t)) {
            return "Ah, churrasco é sempre um bom momento 🔥\n\n" . $resposta;
        }

        if (preg_match('/\b(janta|jantar|cena)\b/i', $t)) {
            return "Boa! Um jantar pede algo que combine bem com a comida 🍽️\n\n" . $resposta;
        }

        // ===============================
        // 💲 EXTREMOS DE PREÇO
        // ===============================
        if (preg_match('/mais caro|mais barato/i', $t)) {
            return self::empatiaExtremos($t) . "\n\n" . $resposta;
        }

        // ===============================
        // 📊 PERGUNTA GERAL / MÉDIA
        // ===============================
        if (preg_match('/m[eé]dia|em geral|normalmente/i', $t)) {
            return "Pra você ter uma noção geral 🍷\n\n" . $resposta;
        }

        // ===============================
        // 🔁 CONTINUAÇÃO / REFINAMENTO
        // ===============================
        if (preg_match('/\b(outro|outra|mais um|mais uma|seguinte)\b/i', $t)) {
            return "Claro 😊 Vamos refinar um pouco mais.\n\n" . $resposta;
        }

        if (preg_match('/\b(especial|diferente|melhor)\b/i', $t)) {
            return "Perfeito 😌 Vamos pensar em algo mais especial então.\n\n" . $resposta;
        }

        // ===============================
        // 🤝 PADRÃO HUMANO SUAVE
        // ===============================
        return $resposta;
    }

    /**
     * --------------------------------------------------
     * 💲 Empatia para extremos de preço
     * --------------------------------------------------
     */
    protected static function empatiaExtremos(string $t): string
    {
        if (str_contains($t, 'mais caro')) {
            return "Se a ideia é algo realmente especial e marcante 🍷";
        }

        if (str_contains($t, 'mais barato')) {
            return "Se você quer algo simples e em conta, sem erro 😉";
        }

        return '';
    }
}
