<?php

namespace App\Services\Sommelier\Rules;

class RegraEmpatiaCurta
{
    protected static array $frases = [
        'Boa escolha 🍷',
        'Essa é uma ótima opção 👌',
        'Essa é bem procurada 😊',
        'Excelente pergunta!',
        'Posso te ajudar com isso 😉',
    ];

    /**
     * --------------------------------------------------
     * ✨ Aplica empatia leve ao texto
     * --------------------------------------------------
     */
    public static function aplicar(string $resposta): string
    {
        if (rand(1, 100) > 40) {
            return $resposta; // nem sempre aplica
        }

        $empatia = self::$frases[array_rand(self::$frases)];

        return "{$empatia}\n\n{$resposta}";
    }
}
