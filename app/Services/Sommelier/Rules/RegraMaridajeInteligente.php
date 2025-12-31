<?php

namespace App\Services\Sommelier\Rules;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\NLP\Intencoes;

/**
 * ==========================================================
 * 🍽️ REGRA DE MARIDAJE INTELIGENTE
 * ----------------------------------------------------------
 * - Detecta comidas / preparos
 * - Soporta plural, español, portugués
 * - Define maridaje
 * - Sugere categoria (vinho) se não houver
 * - Detecta pergunta de harmonização
 * ==========================================================
 */
class RegraMaridajeInteligente
{
    /**
     * --------------------------------------------------
     * 🧠 Mapa semântico de comidas
     * --------------------------------------------------
     */
    protected static array $mapaComidas = [

        // 🥩 CARNES
        'asado'        => 'carnes',
        'parrilla'     => 'carnes',
        'carne'        => 'carnes',
        'carnes'       => 'carnes',
        'picanha'      => 'carnes',
        'costela'      => 'carnes',
        'bife'         => 'carnes',
        'churrasco'    => 'carnes',

        // 🍔 COMIDAS INFORMALES
        'pizza'        => 'comidas informales',
        'hamburguesa'  => 'comidas informales',
        'hamburguer'   => 'comidas informales',
        'empanada'     => 'comidas informales',
        'sandwich'     => 'comidas informales',
        'lanche'       => 'comidas informales',

        // 🐟 PESCADOS / MARISCOS
        'pescado'      => 'pescados',
        'pescados'     => 'pescados',
        'peixe'        => 'pescados',
        'peixes'       => 'pescados',
        'marisco'      => 'pescados',
        'mariscos'     => 'pescados',
        'frutos do mar'=> 'pescados',
        'sushi'        => 'pescados',

        // 🧀 QUEIJOS
        'queso'        => 'quesos',
        'queijo'       => 'quesos',
        'tabla'        => 'quesos',
        'tabla de quesos' => 'quesos',

        // 🍰 POSTRES
        'postre'       => 'postres',
        'sobremesa'    => 'postres',
        'dulce'        => 'postres',
        'doce'         => 'postres',
        'chocolate'    => 'postres',

        // 🧆 PICADAS
        'picada'       => 'picadas',
        'picar'        => 'picadas',
        'aperitivo'    => 'picadas',
    ];

    /**
     * --------------------------------------------------
     * 🎯 APLICA REGRA
     * --------------------------------------------------
     */
    public static function aplicar(string $mensaje, Intencoes $int): void
    {
        $msg = mb_strtolower($mensaje, 'UTF-8');

        foreach (self::$mapaComidas as $palabra => $categoria) {

            // match por palavra inteira (mais seguro)
            if (preg_match('/\b' . preg_quote($palabra, '/') . '\b/u', $msg)) {

                // define maridaje
                $int->maridaje = $categoria;

                // se ainda não tem categoria de bebida, sugere VINOS
                if (empty($int->categoria)) {
                    $int->categoria = 'VINOS';
                }

                // detecta pergunta típica de harmonização
                if (self::ehPerguntaDeMaridaje($msg)) {
                    $int->perguntaEspecifica = 'maridaje';
                }

                SommelierLog::info("🍽️ [RegraMaridajeInteligente] Maridaje detectado", [
                    'palavra'   => $palabra,
                    'maridaje'  => $categoria,
                    'categoria' => $int->categoria,
                ]);

                return;
            }
        }
    }

    /**
     * --------------------------------------------------
     * ❓ Detecta pergunta de harmonização
     * --------------------------------------------------
     */
    protected static function ehPerguntaDeMaridaje(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(qual|cual|que|o que|qué|recomenda|recomendado|combina|acompanha|vai bem|ideal)\b/u',
            $msg
        );
    }
}
