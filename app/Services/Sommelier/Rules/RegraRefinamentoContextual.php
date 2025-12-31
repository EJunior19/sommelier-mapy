<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\NLP\Intencoes;
use App\Helpers\SommelierLog;

class RegraRefinamentoContextual
{
    /**
     * --------------------------------------------------
     * 🧠 Refina intenção com base em respostas humanas
     * --------------------------------------------------
     * Exemplos:
     * - "algo mais especial"
     * - "mais em conta"
     * - "mais barato"
     * - "mais leve"
     *
     * ❗ Nunca sobrescreve escolhas explícitas anteriores
     * --------------------------------------------------
     */
    public static function aplicar(string $mensagem, Intencoes $int): void
    {
        $msg = mb_strtolower($mensagem, 'UTF-8');

        /**
         * ==================================================
         * 🌟 Preferência por algo MAIS ESPECIAL / PREMIUM
         * ==================================================
         */
        if (
            preg_match('/\b(especial|premium|top|melhorzinho|mais especial)\b/u', $msg) &&
            $int->precoMin === null &&
            $int->precoMax === null
        ) {
            $int->precoMin = 50; // ajuste conforme mercado

            SommelierLog::info("✨ [RegraRefinamentoContextual] Preferência por opção especial", [
                'precoMin' => $int->precoMin,
                'categoria' => $int->categoria
            ]);

            return;
        }

        /**
         * ==================================================
         * 💸 Preferência por algo MAIS BARATO / EM CONTA
         * ==================================================
         */
        if (
            preg_match(
                '/\b(mais em conta|em conta|barato|barata|baratos|econ[oô]mico|mais barato)\b/u',
                $msg
            ) &&
            $int->precoMin === null &&
            $int->precoMax === null
        ) {
            // teto simples, pode ajustar depois por categoria
            $int->precoMax = 50;

            SommelierLog::info("💸 [RegraRefinamentoContextual] Preferência por opção econômica", [
                'precoMax' => $int->precoMax,
                'categoria' => $int->categoria
            ]);

            return;
        }

        /**
         * ==================================================
         * 🌬️ Preferência SENSORIAL LEVE / SUAVE
         * ==================================================
         */
        if (
            preg_match('/\b(leve|leves|suave|suaves|mais leve)\b/u', $msg) &&
            empty($int->sensorial)
        ) {
            $int->sensorial = 'leve';

            SommelierLog::info("🌬️ [RegraRefinamentoContextual] Preferência sensorial leve detectada", [
                'sensorial' => $int->sensorial,
                'categoria' => $int->categoria
            ]);

            return;
        }

        /**
         * ==================================================
         * 🔥 Preferência SENSORIAL INTENSA / FORTE
         * ==================================================
         */
        if (
            preg_match('/\b(intenso|intensa|forte|marcante|encorpado|encorpada)\b/u', $msg) &&
            empty($int->sensorial)
        ) {
            $int->sensorial = 'intenso';

            SommelierLog::info("🔥 [RegraRefinamentoContextual] Preferência sensorial intensa detectada", [
                'sensorial' => $int->sensorial,
                'categoria' => $int->categoria
            ]);

            return;
        }

        /**
         * ==================================================
         * 🟡 Palavras AMBÍGUAS (não forçam nada)
         * ==================================================
         */
        if (preg_match('/\b(melhor|diferente|outro|outra)\b/u', $msg)) {
            SommelierLog::info("🟡 [RegraRefinamentoContextual] Palavra ambígua detectada (sem forçar filtros)", [
                'mensagem' => $mensagem,
                'categoria' => $int->categoria
            ]);
        }
    }
}
