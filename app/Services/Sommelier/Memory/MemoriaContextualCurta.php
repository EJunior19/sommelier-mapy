<?php

namespace App\Services\Sommelier\Memory;

use App\Helpers\SommelierLog;

class MemoriaContextualCurta
{
    protected const SESSION_KEY = 'sommelier_contexto_curto';
    protected const MAX_ITENS   = 5;

    /**
     * --------------------------------------------------
     * 💾 REGISTRAR CONTEXTO (INTENÇÕES IMPORTANTES)
     * --------------------------------------------------
     */
    public static function registrar(array $dados): void
    {
        if (empty($dados)) {
            return;
        }

        $memoria = session(self::SESSION_KEY, []);

        $memoria[] = [
            'dados' => $dados,
            'ts'    => now()->timestamp,
        ];

        // Limita tamanho da memória
        $memoria = array_slice($memoria, -self::MAX_ITENS);

        session([self::SESSION_KEY => $memoria]);

        SommelierLog::info("🧠 [MemoriaContextualCurta] Contexto registrado", $dados);
    }

    /**
     * --------------------------------------------------
     * 🔄 RECUPERAR ÚLTIMO CONTEXTO ÚTIL
     * --------------------------------------------------
     */
    public static function recuperar(): ?array
    {
        $memoria = session(self::SESSION_KEY, []);

        if (empty($memoria)) {
            SommelierLog::info("🧠 [MemoriaContextualCurta] Nenhum contexto salvo");
            return null;
        }

        // Último item válido
        $ultimo = end($memoria);

        SommelierLog::info("🧠 [MemoriaContextualCurta] Contexto recuperado", $ultimo['dados']);

        return $ultimo['dados'] ?? null;
    }

    /**
     * --------------------------------------------------
     * 🧹 RESETAR MEMÓRIA
     * --------------------------------------------------
     */
    public static function resetar(): void
    {
        session()->forget(self::SESSION_KEY);

        SommelierLog::info("🧹 [MemoriaContextualCurta] Memória resetada");
    }

    /**
     * --------------------------------------------------
     * 🧪 DEBUG — VISUALIZAR MEMÓRIA
     * --------------------------------------------------
     */
    public static function dump(): array
    {
        return session(self::SESSION_KEY, []);
    }
}
