<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;

class RegraSaudacao
{
    /**
     * --------------------------------------------------
     * 👋 GATILHOS HUMANOS DE SAUDAÇÃO
     * --------------------------------------------------
     * - Curto
     * - Natural
     * - Tolerante a variações
     */
    protected static array $gatilhos = [
        'oi',
        'olá',
        'ola',
        'oie',
        'bom dia',
        'boa tarde',
        'boa noite',
    ];

    /**
     * --------------------------------------------------
     * 🔍 DETECÇÃO DE SAUDAÇÃO
     * --------------------------------------------------
     */
    public static function match(string $mensagem): bool
    {
        $texto = mb_strtolower(trim($mensagem), 'UTF-8');

        if ($texto === '') {
            return false;
        }

        // Normaliza pontuação simples
        $texto = preg_replace('/[^\p{L}\p{N}\s]/u', '', $texto);

        foreach (self::$gatilhos as $g) {

            // Igualdade direta
            if ($texto === $g) {
                return true;
            }

            // Saudação no início da frase
            if (str_starts_with($texto, $g . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * --------------------------------------------------
     * 🗣 RESPOSTA PADRÃO DE SAUDAÇÃO
     * --------------------------------------------------
     */
    public static function responder(): string
    {
        $hora = (int) now()
            ->setTimezone('America/Asuncion')
            ->format('H');

        if ($hora < 12) {
            $saudacao = 'Ótimo dia';
        } elseif ($hora < 18) {
            $saudacao = 'Ótima tarde';
        } else {
            $saudacao = 'Ótima noite';
        }

        SommelierLog::info("👋 [RegraSaudacao] Saudação aplicada", [
            'hora'      => $hora,
            'saudacao'  => $saudacao
        ]);

        return "{$saudacao}! 🍷 Posso te ajudar a escolher uma bebida?";
    }
}
