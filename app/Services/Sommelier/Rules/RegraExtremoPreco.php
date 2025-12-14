<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Models\Bebida;
use App\Services\Sommelier\Domain\CategoriaMap;
use App\Services\Sommelier\UX\NomeFormatter;

/**
 * ==========================================================
 * 💎 REGRA — EXTREMO DE PREÇO
 * ----------------------------------------------------------
 * Ex:
 * - "vino mais caro"
 * - "whisky mais barato"
 * - "bebida mais cara que vc tem"
 *
 * PERFORMANCE:
 * - 1 query
 * - índice btree (precio)
 * - resposta em ms
 * ==========================================================
 */
class RegraExtremoPreco
{
    /**
     * --------------------------------------------------
     * 🔍 MATCH
     * --------------------------------------------------
     */
    public static function match(string $mensagem): bool
    {
        return (bool) preg_match(
            '/\b(mais caro|mais barata|mais barato|pre[cç]o mais alto|pre[cç]o mais baixo)\b/i',
            $mensagem
        );
    }

    /**
     * --------------------------------------------------
     * 🧠 RESPONDER
     * --------------------------------------------------
     */
    public static function responder(string $mensagem): ?string
    {
        SommelierLog::info("💎 [RegraExtremoPreco] Pergunta de extremo detectada", [
            'mensagem' => $mensagem
        ]);

        // Detecta categoria (VINOS, WHISKY, etc.)
        $categoria = CategoriaMap::detectar(mb_strtolower($mensagem));

        // Decide ordem
        $ordem = preg_match('/mais barato|mais baixa/i', $mensagem)
            ? 'asc'
            : 'desc';

        $query = Bebida::query()
            ->where('stock', '>', 0);

        if ($categoria) {
            $query->where('tipo', $categoria);
        }

        $bebida = $query
            ->orderBy('precio', $ordem)
            ->limit(1)
            ->first();

        if (!$bebida) {
            return "No momento não encontrei bebidas disponíveis para essa consulta 🍷";
        }

        $nome = NomeFormatter::formatar($bebida->nome_limpo);
        $preco = number_format($bebida->precio, 2, ',', '.');

        SommelierLog::info("💎 [RegraExtremoPreco] Bebida encontrada", [
            'nome' => $nome,
            'preco' => $preco
        ]);

        if ($ordem === 'desc') {
            return "O {$nome} é o mais caro disponível no momento, custando {$preco} dólares.";
        }

        return "O {$nome} é o mais barato disponível no momento, custando {$preco} dólares.";
    }
}
