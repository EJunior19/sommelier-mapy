<?php

namespace App\Services\Sommelier\Guards;

use App\Helpers\SommelierLog;
use App\Services\Sommelier\Memory\MemoriaContextualCurta;
use App\Services\Sommelier\Support\Normalizador;

/**
 * ==========================================================
 * 🔐 GUARD — PERGUNTA VAGA / CONTEXTO IMPLÍCITO
 * ----------------------------------------------------------
 * Só atua quando o usuário está claramente CONTINUANDO
 * uma conversa já existente.
 * ==========================================================
 */
class PerguntaVaga
{
    /**
     * --------------------------------------------------
     * 🧠 Expressões humanas vagas (continuação)
     * --------------------------------------------------
     */
    protected static array $gatilhos = [

        // genéricos de continuação
        '/\b(algo|algum|alguma|outro|outra|outros)\b/i',
        '/\b(e outra|outro também|mais algum)\b/i',
        '/\b(qualquer um|qualquer coisa)\b/i',

        // refinamento implícito
        '/\b(mais opções|outras opções|seguinte|próximo)\b/i',

        // confirmação vaga
        '/\b(pode ser|tanto faz|assim mesmo)\b/i',

        // respostas curtas típicas
        '/^(sim|ok|dale|isso|esse|essa)$/i',
    ];

    /**
     * --------------------------------------------------
     * 🔍 MATCH — É realmente pergunta vaga?
     * --------------------------------------------------
     */
    public static function match(string $mensagem): bool
    {
        // 🚫 Sem contexto anterior, NÃO é vaga
        $contexto = MemoriaContextualCurta::recuperar();
        if (!$contexto) {
            return false;
        }

        $t = Normalizador::textoLimpo($mensagem);
        if ($t === '') {
            return false;
        }

        // 🚫 Negação explícita encerra contexto
        if (preg_match('/^(nao|não|nenhum|nenhuma)$/i', $t)) {
            SommelierLog::info("🔐 [GuardPerguntaVaga] Negação detectada, contexto encerrado");
            MemoriaContextualCurta::limpar();
            return false;
        }

        // 🚫 Categoria pura NÃO é continuação vaga
        if (self::ehCategoriaPura($t)) {
            return false;
        }

        // 🚫 Se há contexto novo explícito, NÃO é vaga
        if (self::temContextoNovo($t)) {
            return false;
        }

        // 🚫 Se há filtro explícito novo, NÃO é vaga
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
     * 🚦 HANDLE — Reutiliza contexto válido
     * --------------------------------------------------
     */
    public static function handle(string $mensagem): ?array
    {
        SommelierLog::info("🔐 [GuardPerguntaVaga] Continuação detectada", [
            'mensagem' => $mensagem
        ]);

        $contexto = MemoriaContextualCurta::recuperar();
        if (!$contexto || !self::contextoValido($contexto)) {
            return null;
        }

        // 🚫 Bloqueio semântico simples (proteção lógica)
        if (
            ($contexto['categoria'] ?? null) === 'WHISKY' &&
            preg_match('/\b(peixe|peixes|frutos do mar)\b/i', $mensagem)
        ) {
            SommelierLog::info("🚫 [GuardPerguntaVaga] Contexto incompatível descartado");
            MemoriaContextualCurta::limpar();
            return null;
        }

        SommelierLog::info("♻️ [GuardPerguntaVaga] Reutilizando contexto", $contexto);

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
     * 🚫 Detecta filtros explícitos
     * --------------------------------------------------
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
     * 🚫 Detecta contexto NOVO (refeição / ocasião)
     * --------------------------------------------------
     */
    protected static function temContextoNovo(string $mensagem): bool
    {
        return (bool) preg_match(
            '/\b(churrasco|asco|carne|picanha|costela|frango|peixe|peixes|frutos do mar|sushi|jantar|almo[cç]o|refei[cç][aã]o|massa|pizza|queijo|sobremesa)\b/i',
            $mensagem
        );
    }

    /**
     * --------------------------------------------------
     * 🍷 Detecta categoria pura
     * --------------------------------------------------
     */
    protected static function ehCategoriaPura(string $mensagem): bool
    {
        return (bool) preg_match(
            '/^(vinho|vinhos|cerveja|cervezas?|espumante|espumantes|whisky|whiskey|vodka|gin|licor|tequila)$/i',
            $mensagem
        );
    }

    /**
     * --------------------------------------------------
     * ✅ Contexto mínimo válido
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
