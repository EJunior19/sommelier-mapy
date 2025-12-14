<?php

namespace App\Services\Sommelier\UX;

use Illuminate\Support\Facades\Session;

/**
 * ==========================================================
 * 👋 SAUDAÇÃO BUILDER — SOMMELIER MAPY
 * ----------------------------------------------------------
 * Responsável por:
 * - Gerar saudação inicial humana
 * - Respeitar horário
 * - Evitar repetição
 * - Adaptar idioma (PT / ES)
 * ==========================================================
 */
class SaudacaoBuilder
{
    /**
     * --------------------------------------------------
     * 🎯 Retorna saudação OU null (se não deve saudar)
     * --------------------------------------------------
     */
    public static function obter(string $mensagem, bool $forcar = false): ?string
    {
        // Já cumprimentou nesta sessão?
        if (!$forcar && Session::get('sommelier_cumprimentou', false)) {
            return null;
        }

        // Detecta idioma
        $idioma = self::detectarIdioma($mensagem);

        // Hora atual
        $hora = now()->hour;

        // Monta saudação
        $texto = match ($idioma) {
            'es' => self::saudacaoES($hora),
            default => self::saudacaoPT($hora),
        };

        // Marca como já cumprimentado
        Session::put('sommelier_cumprimentou', true);

        return $texto;
    }

    /**
     * --------------------------------------------------
     * 🇧🇷 Saudação PT-BR
     * --------------------------------------------------
     */
    protected static function saudacaoPT(int $hora): string
    {
        return match (true) {
            $hora < 12 =>
                "Ótimo dia ☀️! Sou sua Sommelier Virtual do Shopping Mapy 🍷.",

            $hora < 18 =>
                "Ótima tarde 🌤️! Sou sua Sommelier Virtual do Shopping Mapy 🍷.",

            default =>
                "Ótima noite 🌙! Sou sua Sommelier Virtual do Shopping Mapy 🍷.",
        };
    }

    /**
     * --------------------------------------------------
     * 🇪🇸 Saudación ES
     * --------------------------------------------------
     */
    protected static function saudacaoES(int $hora): string
    {
        return match (true) {
            $hora < 12 =>
                "¡Muy buenos días ☀️! Soy tu Sommelier Virtual del Shopping Mapy 🍷.",

            $hora < 18 =>
                "¡Muy buenas tardes 🌤️! Soy tu Sommelier Virtual del Shopping Mapy 🍷.",

            default =>
                "¡Muy buenas noches 🌙! Soy tu Sommelier Virtual del Shopping Mapy 🍷.",
        };
    }

    /**
     * --------------------------------------------------
     * 🌎 Detecção simples de idioma
     * --------------------------------------------------
     */
    protected static function detectarIdioma(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');

        if (preg_match('/\b(hola|quiero|busco|precio|bebida|recomienda|opción)\b/i', $t)) {
            return 'es';
        }

        return 'pt';
    }

    /**
     * --------------------------------------------------
     * 🔄 Reset manual da saudação
     * --------------------------------------------------
     */
    public static function resetar(): void
    {
        Session::forget('sommelier_cumprimentou');
    }
}
