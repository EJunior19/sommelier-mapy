<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\NLP\Intencoes;
use App\Helpers\SommelierLog;

class RegraCategoriaMacro
{
    /**
     * Detecta categorias amplas e humanas
     * (vinhos, cervejas, destilados, etc)
     *
     * ❗ Nunca sobrescreve categoria específica já detectada
     */
    public static function aplicar(string $mensagem, Intencoes $int): void
    {
        // Se NLP ou contexto já definiu categoria, respeita
        if (!empty($int->categoria)) {
            return;
        }

        $texto = mb_strtolower($mensagem, 'UTF-8');

        $mapaCategorias = [
            // 🍷 VINHOS
            'vinho'       => 'VINOS',
            'vinhos'      => 'VINOS',

            // 🍺 CERVEJAS
            'cerveja'     => 'CERVEZA',
            'cervejas'    => 'CERVEZA',
            'chopp'       => 'CERVEZA',

            // 🥂 ESPUMANTES
            'espumante'   => 'ESPUMANTES',
            'espumantes'  => 'ESPUMANTES',
            'champanhe'   => 'ESPUMANTES',
            'champagne'   => 'ESPUMANTES',

            // 🥃 DESTILADOS
            'destilado'        => 'DESTILADOS',
            'destilados'       => 'DESTILADOS',
            'bebida forte'     => 'DESTILADOS',
            'bebidas fortes'   => 'DESTILADOS',
            'alcool'           => 'DESTILADOS',
            'alcoólicas'       => 'DESTILADOS',

            // 🍸 LICORES
            'licor'       => 'LICORES',
            'licores'     => 'LICORES',

            // 🚫 SEM ÁLCOOL
            'sem alcool'  => 'SEM_ALCOOL',
            'sem álcool'  => 'SEM_ALCOOL',
            'não alcoólica' => 'SEM_ALCOOL',
            'nao alcoolica' => 'SEM_ALCOOL',
        ];

        foreach ($mapaCategorias as $palavra => $categoria) {
            if (str_contains($texto, $palavra)) {
                $int->categoria = $categoria;

                SommelierLog::info(
                    "🧠 [RegraCategoriaMacro] Categoria macro detectada",
                    [
                        'palavra'   => $palavra,
                        'categoria' => $categoria
                    ]
                );

                return;
            }
        }
    }
}
