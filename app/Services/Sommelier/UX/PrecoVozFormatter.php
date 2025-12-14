<?php

namespace App\Services\Sommelier\UX;

use App\Helpers\SommelierLog;
use NumberFormatter;

/**
 * ==========================================================
 * 💰 PREÇO → VOZ (TTS)
 * ----------------------------------------------------------
 * Converte valores numéricos em texto falado correto:
 * - Corrige singular/plural
 * - Suporta centavos
 * - Evita "1 dólares"
 * - Ideal para TTS
 * - Log detalhado para debug
 * ==========================================================
 */
class PrecoVozFormatter
{
    /**
     * --------------------------------------------------
     * 🔊 Converte preço numérico para texto falado
     * --------------------------------------------------
     * Ex:
     *  1.00  → "um dólar"
     *  2.50  → "dois dólares e cinquenta centavos"
     *  0.75  → "setenta e cinco centavos"
     */
    public static function paraVoz(float $preco): string
    {
        SommelierLog::info("💰 [PrecoVozFormatter] Iniciando conversão", [
            'preco_original' => $preco
        ]);

        // Segurança
        if ($preco < 0) {
            SommelierLog::warning("⚠️ [PrecoVozFormatter] Preço negativo recebido", [
                'valor' => $preco
            ]);
            return '';
        }

        // Arredonda corretamente
        $preco = round($preco, 2);

        $dolares  = (int) floor($preco);
        $centavos = (int) round(($preco - $dolares) * 100);

        SommelierLog::info("🔢 [PrecoVozFormatter] Valores calculados", [
            'dolares'  => $dolares,
            'centavos' => $centavos
        ]);

        $fmt = new NumberFormatter('pt_BR', NumberFormatter::SPELLOUT);

        // --------------------------------------------------
        // 💲 Apenas centavos
        // --------------------------------------------------
        if ($dolares === 0 && $centavos > 0) {
            $texto = $fmt->format($centavos) . ' centavos';

            SommelierLog::info("🗣️ [PrecoVozFormatter] Apenas centavos", [
                'saida' => $texto
            ]);

            return $texto;
        }

        // --------------------------------------------------
        // 💲 Apenas dólares
        // --------------------------------------------------
        if ($centavos === 0) {
            if ($dolares === 1) {
                $texto = 'um dólar';
            } else {
                $texto = $fmt->format($dolares) . ' dólares';
            }

            SommelierLog::info("🗣️ [PrecoVozFormatter] Apenas dólares", [
                'saida' => $texto
            ]);

            return $texto;
        }

        // --------------------------------------------------
        // 💲 Dólares + centavos
        // --------------------------------------------------
        $textoDolar = ($dolares === 1)
            ? 'um dólar'
            : $fmt->format($dolares) . ' dólares';

        $textoCentavo = ($centavos === 1)
            ? 'um centavo'
            : $fmt->format($centavos) . ' centavos';

        $final = "{$textoDolar} e {$textoCentavo}";

        SommelierLog::info("🗣️ [PrecoVozFormatter] Valor completo", [
            'saida' => $final
        ]);

        return $final;
    }

    /**
     * --------------------------------------------------
     * 📦 Converte preços em lista de produtos
     * --------------------------------------------------
     */
    public static function aplicarEmLista(array $bebidas): array
    {
        SommelierLog::info("📦 [PrecoVozFormatter] Aplicando em lista", [
            'total' => count($bebidas)
        ]);

        return array_map(function ($b) {

            if (isset($b['precio'])) {
                $b['preco_voz'] = self::paraVoz((float) $b['precio']);
            }

            return $b;

        }, $bebidas);
    }
}
