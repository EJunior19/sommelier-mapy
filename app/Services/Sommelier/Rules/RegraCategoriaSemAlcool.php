<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\NLP\Intencoes;

class RegraCategoriaSemAlcool
{
    public static function aplicar(string $mensagem, Intencoes $int): void
    {
        $msg = mb_strtolower($mensagem, 'UTF-8');

        if (preg_match('/\b(sem álcool|sem alcool|bebidas sem álcool|bebidas sem alcool)\b/i', $msg)) {
            SommelierLog::info("🚫🍺 [RegraCategoriaSemAlcool] Categoria sem álcool detectada");

            // define categoria clara
            $int->categoria = 'SEM_ALCOOL';

            // limpa filtros incompatíveis
            $int->sensorial = null;
            $int->precoMin  = null;
            $int->precoMax  = null;
            $int->ocasiao   = null;
        }
    }
}
