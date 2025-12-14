<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\Memory\MemoriaContextualCurta;
use App\Services\Sommelier\Domain\CategoriaMap;
use App\Helpers\SommelierLog;

class RegraAtualizaContextoAposResposta
{
    /**
     * --------------------------------------------------
     * ♻️ Atualiza contexto curto
     * --------------------------------------------------
     */
    public static function aplicar(string $mensagem): void
    {
        $categoria = CategoriaMap::detectar($mensagem);

        if (!$categoria) {
            return;
        }

        SommelierLog::info("♻️ [RegraAtualizaContexto] Atualizando contexto", [
            'categoria' => $categoria
        ]);

        MemoriaContextualCurta::registrar([
            'categoria' => $categoria,
            'sensorial' => null,
            'precoMin'  => null,
            'precoMax'  => null,
            'minMl'     => null,
            'maxMl'     => null,
            'ocasiao'   => null,
        ]);
    }
}
