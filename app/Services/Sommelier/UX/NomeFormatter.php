<?php

namespace App\Services\Sommelier\UX;

use App\Helpers\SommelierLog;

/**
 * ==========================================================
 * ✨ NOME FORMATTER — SOMMELIER MAPY
 * ----------------------------------------------------------
 * Padroniza nomes de bebidas para exibição humana:
 * - Corrige encoding quebrado (a¥os → anos)
 * - Title Case inteligente
 * - Preserva siglas (ML, XO, VSOP, etc.)
 * - Preserva números
 * - Ajusta nomes para TTS
 * - LOGA todo o processo
 * ==========================================================
 */
class NomeFormatter
{
    /**
     * --------------------------------------------------
     * 🧹 Corrige problemas clássicos de encoding
     * --------------------------------------------------
     */
    protected static function corrigirEncoding(string $texto): string
    {
        $map = [
            // anos
            'a¥os' => 'anos',
            'a¤os' => 'anos',
            'a�os' => 'anos',

            // acentos comuns quebrados
            'Ã¡' => 'á',
            'Ã ' => 'à',
            'Ã£' => 'ã',
            'Ã¢' => 'â',
            'Ã©' => 'é',
            'Ãª' => 'ê',
            'Ã­' => 'í',
            'Ã³' => 'ó',
            'Ã´' => 'ô',
            'Ãµ' => 'õ',
            'Ãº' => 'ú',
            'Ã§' => 'ç',

            // maiúsculas
            'Ã�' => 'Á',
            'Ã‰' => 'É',
            'Ã“' => 'Ó',
            'Ãš' => 'Ú',
            'Ã‡' => 'Ç',
        ];

        return str_replace(
            array_keys($map),
            array_values($map),
            $texto
        );
    }

    /**
     * --------------------------------------------------
     * 🎯 Formata nome de produto ou marca
     * --------------------------------------------------
     */
    public static function formatar(string $nome): string
    {
        $original = $nome;
        $nome = trim($nome);

        SommelierLog::info("✨ [NomeFormatter] Iniciando formatação", [
            'entrada' => $original
        ]);

        if ($nome === '') {
            return '';
        }

        // 🧹 Corrige encoding antes de tudo
        $nome = self::corrigirEncoding($nome);

        // Normaliza espaços
        $nome = preg_replace('/\s+/', ' ', $nome);

        $palavras = explode(' ', $nome);

        $formatado = array_map(function ($p) {

            $p = trim($p);
            if ($p === '') {
                return '';
            }

            // 🔢 números
            if (is_numeric($p)) {
                return $p;
            }

            $upper = strtoupper($p);

            // 🧾 siglas
            $siglas = [
                'ML', 'LT', 'L', 'CL',
                'XO', 'VS', 'VSOP', 'V.S.O.P', 'V.S.',
                'IPA', 'APA',
                'DOC', 'IGT',
                'AGED', 'RESERVA', 'GRAN', 'GRAND',
                'BRUT', 'SEC', 'DEMI', 'DEMI-SEC',
                'SINGLE', 'MALT',
            ];

            if (in_array($upper, $siglas, true)) {
                return $upper;
            }

            // 🥃 termos específicos
            $mapa = [
                'whisky'    => 'Whisky',
                'whiskey'   => 'Whiskey',
                'vino'      => 'Vino',
                'vinho'     => 'Vinho',
                'champagne' => 'Champagne',
                'espumante' => 'Espumante',
                'cachaca'   => 'Cachaça',
                'cachaça'   => 'Cachaça',
                'anos'      => 'Anos',
            ];

            $lower = mb_strtolower($p, 'UTF-8');

            if (isset($mapa[$lower])) {
                return $mapa[$lower];
            }

            // 🔠 capitalização padrão
            return mb_convert_case($p, MB_CASE_TITLE, 'UTF-8');

        }, $palavras);

        $resultado = implode(' ', array_filter($formatado));

        SommelierLog::info("✅ [NomeFormatter] Nome formatado", [
            'entrada' => $original,
            'saida'   => $resultado
        ]);

        return $resultado;
    }

    /**
     * --------------------------------------------------
     * 🧃 Formata lista de bebidas
     * --------------------------------------------------
     */
    public static function formatarLista(array $bebidas): array
    {
        return array_map(function ($b) {

            if (isset($b['nome_limpo'])) {
                $b['nome_limpo'] = self::formatar($b['nome_limpo']);
            }

            if (isset($b['marca'])) {
                $b['marca'] = self::formatar($b['marca']);
            }

            return $b;

        }, $bebidas);
    }

    /**
     * --------------------------------------------------
     * 🔊 Versão amigável para TTS
     * --------------------------------------------------
     */
    public static function paraVoz(string $nome): string
    {
        $nome = self::formatar($nome);

        $substituicoes = [
            'ML'   => 'mililitros',
            'LT'   => 'litros',
            'L '   => 'litros ',
            'VSOP' => 'vê és ó pê',
            'XO'   => 'xis ó',
            'IPA'  => 'i p a',
        ];

        return str_replace(
            array_keys($substituicoes),
            array_values($substituicoes),
            $nome
        );
    }

    /**
     * --------------------------------------------------
     * ✨ Embeleza TEXTO FINAL
     * --------------------------------------------------
     */
    public static function embelezar(string $texto): string
    {
        if (trim($texto) === '') {
            return "Posso te ajudar a escolher uma boa bebida 🍷";
        }

        // não quebra listas
        $texto = preg_replace('/[ \t]+/', ' ', $texto);
        $texto = preg_replace('/\s+([,.!?])/', '$1', $texto);
        $texto = preg_replace("/\n{3,}/", "\n\n", $texto);

        $texto = implode(
            "\n",
            array_map('trim', explode("\n", $texto))
        );

        return trim($texto);
    }
}
