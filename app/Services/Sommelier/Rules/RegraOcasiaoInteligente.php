<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\NLP\Intencoes;

/**
 * ==========================================================
 * 🧠 REGRA — OCASIÃO INTELIGENTE (NLP)
 * ----------------------------------------------------------
 * Detecta contexto de uso da bebida:
 * - refeições
 * - jantar
 * - carne / churrasco
 * - momentos cotidianos
 *
 * NÃO responde ao cliente
 * NÃO usa IA
 * Apenas enriquece $int->ocasiao
 * ==========================================================
 */
class RegraOcasiaoInteligente
{
    public static function aplicar(string $mensagem, Intencoes $int): void
    {
        $msg = mb_strtolower($mensagem);

        if (preg_match('/\b(carne|churrasco|jantar|almo[cç]o|refei[cç][aã]o)\b/i', $msg)) {

            // Não sobrescreve se já existir
            if (!$int->ocasiao) {
                $int->ocasiao = 'acompanhar_refeicao';
            }

            SommelierLog::info("🥩 [RegraOcasiaoInteligente] Ocasião definida", [
                'ocasiao' => $int->ocasiao
            ]);
        }
    }
}
