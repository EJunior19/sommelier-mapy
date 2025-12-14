<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Models\Bebida;
use App\Services\Sommelier\Domain\CategoriaMap;

class RegraEstatisticaPreco
{
    public static function match(string $mensagem): bool
    {
        return (bool) preg_match(
            '/\b(m[eé]dia|em geral|normalmente|faixa de pre[cç]o)\b/i',
            $mensagem
        );
    }

    public static function responder(string $mensagem): ?string
    {
        SommelierLog::info("📊 [RegraEstatisticaPreco] Pergunta estatística detectada", [
            'mensagem' => $mensagem
        ]);

        $categoria = CategoriaMap::detectar($mensagem);

        if (!$categoria) {
            return null;
        }

        $query = Bebida::where('tipo', $categoria);

        $dados = $query->selectRaw('
            MIN(precio) as minimo,
            AVG(precio) as media,
            MAX(precio) as maximo
        ')->first();

        if (!$dados || !$dados->media) {
            return null;
        }

        $min = number_format($dados->minimo, 2, ',', '.');
        $med = number_format($dados->media, 2, ',', '.');
        $max = number_format($dados->maximo, 2, ',', '.');

        return "Em geral, os {$categoria} ficam entre {$min} e {$max} dólares. 
A média costuma girar em torno de {$med} dólares 🍷";
    }
}
