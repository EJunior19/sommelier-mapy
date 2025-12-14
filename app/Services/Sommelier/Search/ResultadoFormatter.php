<?php

namespace App\Services\Sommelier\Search;

use App\Services\Sommelier\Presentation\Emojis;

class ResultadoFormatter
{
    public static function lista(iterable $bebidas): string
    {
        return collect($bebidas)->map(function ($b) {
            $emoji = Emojis::tipo($b->tipo);
            $preco = number_format($b->precio, 2, ',', '.');

            return "• {$emoji} {$b->nome_limpo} — {$preco} dólares";
        })->implode("\n");
    }
}
