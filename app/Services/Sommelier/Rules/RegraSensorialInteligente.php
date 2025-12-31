<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\NLP\Intencoes;

class RegraSensorialInteligente
{
    protected static array $mapaSensorial = [

        // intensos / marcantes
        'marcante'   => 'INTENSO',
        'marcantes'  => 'INTENSO',
        'intenso'    => 'INTENSO',
        'intensos'   => 'INTENSO',
        'forte'      => 'INTENSO',
        'fortes'     => 'INTENSO',
        'encorpado'  => 'INTENSO',
        'encorpados' => 'INTENSO',

        // suaves / leves
        'suave'      => 'SUAVE',
        'suaves'     => 'SUAVE',
        'leve'       => 'SUAVE',
        'leves'      => 'SUAVE',
        'delicado'   => 'SUAVE',
        'delicados'  => 'SUAVE',

        // equilibrados
        'equilibrado'  => 'EQUILIBRADO',
        'equilibrados' => 'EQUILIBRADO',
        'balanceado'   => 'EQUILIBRADO',
        'balanceados'  => 'EQUILIBRADO',
    ];

    public static function aplicar(string $mensagem, Intencoes $int): void
    {
        $msg = mb_strtolower($mensagem);

        foreach (self::$mapaSensorial as $palavra => $sensorial) {
            if (preg_match('/\b' . preg_quote($palavra, '/') . '\b/u', $msg)) {

                // só aplica se ainda não existir (não sobrescreve escolha explícita)
                if (empty($int->sensorial)) {
                    $int->sensorial = $sensorial;

                    SommelierLog::info("🎨 [RegraSensorialInteligente] Sensorial definido", [
                        'sensorial' => $sensorial,
                        'palavra'   => $palavra
                    ]);
                }

                return;
            }
        }
    }
}
