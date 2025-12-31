<?php

namespace App\Services\Sommelier\NLP;

use Illuminate\Support\Facades\DB;

class ProdutoDetector
{
    /**
     * Detecta produto usando busca_composta (trigram)
     */
    public static function detectar(string $mensagem): ?array
    {
        $texto = mb_strtolower(trim($mensagem), 'UTF-8');

        // Limpieza básica
        $texto = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);

        if (mb_strlen($texto) < 4) {
            return null;
        }

        // 🔥 Busca trigram com score
        $produto = DB::table('bebidas')
            ->select('id', 'nome_limpo')
            ->whereRaw('busca_composta % ?', [$texto]) // operador trigram
            ->orderByRaw('similarity(busca_composta, ?) DESC', [$texto])
            ->limit(1)
            ->first();

        if (!$produto) {
            return null;
        }

        return [
            'id'         => $produto->id,
            'nome_limpo' => $produto->nome_limpo,
        ];
    }
}
