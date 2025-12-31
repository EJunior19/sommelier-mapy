<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\NLP\Intencoes;
use App\Helpers\SommelierLog;

class RegraSubcategoriaDestilados
{
    protected static array $map = [
        'whisky' => ['whisky', 'uísque', 'uisque'],
        'gin'    => ['gin'],
        'vodka'  => ['vodka'],
        'rum'    => ['rum'],
        'tequila'=> ['tequila'],
        'licor'  => ['licor', 'licores'],
    ];

    public static function aplicar(string $mensagem, Intencoes $int): ?string
    {
        if ($int->categoria !== 'DESTILADOS') {
            return null;
        }

        $m = mb_strtolower($mensagem);

        // Se já citou subcategoria, deixa seguir
        foreach (self::$map as $sub => $palavras) {
            foreach ($palavras as $p) {
                if (str_contains($m, $p)) {
                    $int->categoria = strtoupper($sub);
                    SommelierLog::info("🥃 [RegraSubcategoriaDestilados] Subcategoria detectada", [
                        'subcategoria' => $int->categoria
                    ]);
                    return null;
                }
            }
        }

        // Se ainda não tem subcategoria → perguntar
        SommelierLog::info("🥃 [RegraSubcategoriaDestilados] Solicitando subcategoria");

        return "Perfeito 👍 Prefere whisky, gin, vodka, rum ou tequila?";
    }
}
