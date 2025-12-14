<?php

namespace App\Services\Sommelier\Guards;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\Memory\MemoriaContextualCurta;
use App\Services\Sommelier\Support\Normalizador;

/**
 * ==========================================================
 * 🔐 GUARD — PERGUNTA VAGA / CONTEXTO IMPLÍCITO
 * ----------------------------------------------------------
 * Detecta mensagens vagas como:
 * - "me recomenda algum"
 * - "outros"
 * - "mais opções"
 * - "algum bom?"
 *
 * ⚠️ IMPORTANTE:
 * - NÃO intercepta mensagens com NOVOS filtros
 *   (ex: preço, volume, faixa)
 * - NÃO responde diretamente
 * - NÃO chama IA
 * - Apenas reutiliza contexto válido
 *
 * Executado ANTES das Rules.
 * ==========================================================
 */
class PerguntaVaga
{
    /**
     * --------------------------------------------------
     * 🧠 Expressões humanas vagas (PT + ES)
     * --------------------------------------------------
     */
    protected static array $gatilhos = [

            // genéricos
            '/\b(algo|algum|alguma|outro|outra|outros)\b/i',
            '/\b(me recomenda|me indica|me sugere)\b/i',
            '/\b(qualquer um|qualquer coisa)\b/i',

            // continuação / refinamento implícito (SEM ORDENAÇÃO)
            '/\b(mais opções|outras opções|seguinte|próximo)\b/i',
            '/\b(mais|outras|outros)\b/i',

            // confirmação vaga
            '/\b(pode ser|tanto faz|daí mesmo|assim mesmo)\b/i',

            // respostas curtas típicas
            '/^\b(sim|ok|dale|isso|esse|essa)\b$/i',
        ];


    /**
     * --------------------------------------------------
     * 🔍 MATCH — É pergunta vaga?
     * --------------------------------------------------
     */
    public static function match(string $mensagem): bool
    {
        $t = Normalizador::textoLimpo($mensagem);

        if ($t === '') {
            return false;
        }

        /**
         * 🚫 REGRA DE OURO
         * Se houver NOVO FILTRO (preço, volume, faixa),
         * NÃO é pergunta vaga.
         */
        if (self::temFiltroNovo($t)) {
            return false;
        }

        foreach (self::$gatilhos as $rx) {
            if (preg_match($rx, $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * --------------------------------------------------
     * 🚦 HANDLE — Decide o fluxo
     * --------------------------------------------------
     *
     * @return array|null
     * - array → intenções herdadas (Brain pula NLP)
     * - null  → Brain segue fluxo normal
     */
    public static function handle(string $mensagem): ?array
    {
        SommelierLog::info("🔐 [GuardPerguntaVaga] Mensagem vaga detectada", [
            'mensagem' => $mensagem
        ]);

        /**
         * ----------------------------------------------
         * 🧠 Recupera contexto curto
         * ----------------------------------------------
         */
        $contexto = MemoriaContextualCurta::recuperar();

        if (!$contexto) {
            SommelierLog::info("🧠 [GuardPerguntaVaga] Nenhum contexto salvo");
            return null;
        }

        /**
         * ----------------------------------------------
         * ✅ Contexto é reaproveitável?
         * ----------------------------------------------
         */
        if (!self::contextoValido($contexto)) {
            SommelierLog::info("🧠 [GuardPerguntaVaga] Contexto inválido", [
                'contexto' => $contexto
            ]);
            return null;
        }

        /**
         * ----------------------------------------------
         * ♻️ Injeta intenções herdadas
         * ----------------------------------------------
         */
        SommelierLog::info("♻️ [GuardPerguntaVaga] Reutilizando contexto anterior", [
            'categoria' => $contexto['categoria'] ?? null,
            'sensorial' => $contexto['sensorial'] ?? null,
            'precoMin'  => $contexto['precoMin'] ?? null,
            'precoMax'  => $contexto['precoMax'] ?? null,
            'minMl'     => $contexto['minMl'] ?? null,
            'maxMl'     => $contexto['maxMl'] ?? null,
        ]);

        return [
            'categoria' => $contexto['categoria'] ?? null,
            'sensorial' => $contexto['sensorial'] ?? null,
            'ocasiao'   => $contexto['ocasiao'] ?? null,
            'precoMin'  => $contexto['precoMin'] ?? null,
            'precoMax'  => $contexto['precoMax'] ?? null,
            'minMl'     => $contexto['minMl'] ?? null,
            'maxMl'     => $contexto['maxMl'] ?? null,
        ];
    }

    /**
     * --------------------------------------------------
     * 🚫 Detecta NOVOS FILTROS explícitos
     * --------------------------------------------------
     * Ex:
     * - "acima de 100"
     * - "menos de 50"
     * - "entre 30 e 80"
     */
    protected static function temFiltroNovo(string $mensagem): bool
    {
        return (bool) preg_match(
            '/\b(acima de|mais de|menos de|até|entre)\s*\d+/i',
            $mensagem
        );
    }

    /**
     * --------------------------------------------------
     * ✅ Contexto é suficiente para continuar?
     * --------------------------------------------------
     */
    protected static function contextoValido(array $c): bool
    {
        return (bool) (
            !empty($c['categoria']) ||
            !empty($c['sensorial']) ||
            !empty($c['precoMin']) ||
            !empty($c['precoMax']) ||
            !empty($c['ocasiao'])
        );
    }
}
