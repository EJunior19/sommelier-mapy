<?php

namespace App\Services\Sommelier\Support;

/**
 * ==========================================================
 * 🔧 NORMALIZADOR CENTRAL DO SOMMELIER MAPY
 * ----------------------------------------------------------
 * Responsável por:
 * - Limpar textos
 * - Remover acentos
 * - Padronizar entradas
 * - Preparar strings para TRGM, fonética e NLP
 * ==========================================================
 */
class Normalizador
{
    /**
     * --------------------------------------------------
     * 🔤 Texto limpo padrão (para TRGM e comparações)
     * --------------------------------------------------
     */
    public static function textoLimpo(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');

        // iconv pode retornar false em alguns ambientes; fallback seguro
        $conv = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($conv !== false) {
            $texto = $conv;
        }

        $texto = preg_replace('/[^a-z0-9 ]/i', ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim($texto);
    }

    /**
     * --------------------------------------------------
     * 🔤 Remove apenas acentos (mantém símbolos)
     * --------------------------------------------------
     */
    public static function semAcento(string $texto): string
    {
        $conv = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        return $conv !== false ? $conv : $texto;
    }

    /**
     * --------------------------------------------------
     * 🔠 Normalização básica (lower + trim)
     * --------------------------------------------------
     */
    public static function basico(string $texto): string
    {
        return mb_strtolower(trim($texto), 'UTF-8');
    }

    /**
     * --------------------------------------------------
     * 🧩 Tokeniza texto limpo em palavras úteis
     * --------------------------------------------------
     */
    public static function tokenizar(string $texto): array
    {
        $limpo = self::textoLimpo($texto);

        if ($limpo === '') {
            return [];
        }

        $tokens = explode(' ', $limpo);

        // remove tokens curtos demais
        return array_values(array_filter($tokens, fn ($t) => strlen($t) >= 3));
    }

    /**
     * --------------------------------------------------
     * 🚫 Remove stopwords PT + ES
     * --------------------------------------------------
     */
    public static function removerStopwords(array $tokens): array
    {
        $stopwords = [
            // Português
            'o','a','os','as','um','uma','uns','umas',
            'de','do','da','dos','das','para','pra','por','com',
            'que','qual','quais','quanto','valor','preco','preço',
            'gostaria','queria','algo','alguma','algum',
            'me','te','se','vc','voce','bom','boa','oi','ola','olá',

            // Espanhol
            'el','la','los','las','un','una','unos','unas',
            'de','del','para','por','con',
            'que','cuanto','precio','valor',
            'quiero','busco','algo','alguna','alguno',
            'hola','buenos','buenas'
        ];

        return array_values(array_diff($tokens, $stopwords));
    }

    /**
     * --------------------------------------------------
     * 🔢 Extrai números do texto (12, 750, 18…)
     * --------------------------------------------------
     */
    public static function extrairNumeros(string $texto): array
    {
        preg_match_all('/\d+/', $texto, $m);
        return array_map('intval', $m[0] ?? []);
    }

    /**
     * --------------------------------------------------
     * 🥃 Normalização forte para nome de produto
     * (melhora MUITO o TRGM)
     * --------------------------------------------------
     */
    public static function normalizarProduto(string $texto): string
    {
        $limpo = self::textoLimpo($texto);

        if ($limpo === '') {
            return '';
        }

        $tokens = explode(' ', $limpo);
        $tokens = self::removerStopwords($tokens);

        // Ruídos comuns no nome de bebidas
        $ruidos = [
            'vinho','vino','whisky','whiskey','cerveja','beer',
            'cabernet','malbec','merlot','sauvignon','tinto','branco',
            'ml','lt','litro','litrao'
        ];

        $tokens = array_diff($tokens, $ruidos);
        $tokens = array_unique($tokens);

        return trim(implode(' ', $tokens));
    }

    /**
     * --------------------------------------------------
     * 🌎 Detecta se texto fala de procedência
     * --------------------------------------------------
     */
    public static function perguntaSobreOrigem(string $texto): bool
    {
        $t = self::textoLimpo($texto);

        return (bool) preg_match(
            '/procedenc|origem|origen|de onde|pais de origem|feito em|fabricado em/i',
            $t
        );
    }

    /**
     * --------------------------------------------------
     * 💰 Remove preço/moeda do texto
     * (ex: "— 4,50 dólares", "$ 12.90", "120 dolares")
     * --------------------------------------------------
     */
    public static function removerPrecoMoeda(string $texto): string
    {
        // remove trechos do tipo "— 4,50 dólares" até o fim
        $texto = preg_replace('/[—–]\s*\$?\s*\d+([.,]\d+)?\s*(d[oó]lares?)?.*$/iu', '', $texto);

        // remove padrões de preço em qualquer parte
        $texto = preg_replace('/\$\s*\d+([.,]\d+)?/iu', ' ', $texto);
        $texto = preg_replace('/\d+([.,]\d+)?\s*d[oó]lares?/iu', ' ', $texto);

        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * --------------------------------------------------
     * 🧠 Normaliza texto do cliente para tentar achar produto
     * (remove perguntas + preço + pontuação, preserva nome)
     *
     * Ex:
     * "de que procedencia vem o quinta do morgado vino 1 lits — 4,50 dólares?"
     * -> "quinta do morgado vino 1 lits"
     * --------------------------------------------------
     */
    public static function normalizarTextoProduto(string $texto): string
    {
        $t = self::basico($texto);
        $t = self::semAcento($t);

        // remove preço/moeda
        $t = self::removerPrecoMoeda($t);

        // remove frases comuns de pergunta (PT/ES)
        $t = str_ireplace([
            'de que procedencia vem',
            'qual a procedencia de',
            'qual a procedencia',
            'qual a origem de',
            'qual a origem',
            'de onde vem',
            'de onde e',
            'de onde é',
            'procedencia',
            'origem',
            'origen',
            'pais de origem',
            'país de origem',
            'feito em',
            'fabricado em',
            'viene de',
            'de donde viene',
            'de donde es',
            'de dónde viene',
            'de dónde es',
        ], ' ', $t);

        // tira pontuação, mas mantém letras/números/espaço
        $t = preg_replace('/[^a-z0-9 ]/i', ' ', $t);

        // remove artigos soltos e conectores comuns que ficam sobrando
        $t = preg_replace('/\b(o|a|os|as|um|uma|uns|umas|do|da|dos|das|de|del|la|el|los|las)\b/i', ' ', $t);

        // normaliza espaços
        $t = preg_replace('/\s+/', ' ', $t);

        return trim($t);
    }
}
