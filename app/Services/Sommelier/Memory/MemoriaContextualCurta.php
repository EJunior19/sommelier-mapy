<?php

namespace App\Services\Sommelier\Memory;

use App\Helpers\SommelierLog;

class MemoriaContextualCurta
{
    protected const SESSION_KEY = 'sommelier_contexto_curto';
    protected const MAX_ITENS   = 5;

    /**
     * Tempo máximo (em segundos) para considerar o contexto válido
     * Ex: 5 minutos
     */
    protected const TIMEOUT = 300;

    /**
     * --------------------------------------------------
     * 💾 REGISTRAR CONTEXTO (ACEITA CONTEXTO PARCIAL)
     * --------------------------------------------------
     */
    public static function registrar(array $dados): void
    {
        if (empty($dados)) {
            return;
        }

        // Remove chaves totalmente vazias
        $dadosFiltrados = array_filter(
            $dados,
            fn ($v) => $v !== null
        );

        if (empty($dadosFiltrados)) {
            return;
        }

        $memoria = session(self::SESSION_KEY, []);

        $memoria[] = [
            'dados' => $dadosFiltrados,
            'ts'    => now()->timestamp,
        ];

        // Mantém somente os últimos N contextos
        $memoria = array_slice($memoria, -self::MAX_ITENS);

        session([self::SESSION_KEY => $memoria]);

        SommelierLog::info(
            "🧠 [MemoriaContextualCurta] Contexto registrado",
            $dadosFiltrados
        );
    }

    /**
     * --------------------------------------------------
     * 🔄 RECUPERAR ÚLTIMO CONTEXTO ÚTIL (COM TIMEOUT)
     * --------------------------------------------------
     */
    public static function recuperar(): ?array
    {
        $memoria = session(self::SESSION_KEY, []);

        if (empty($memoria)) {
            SommelierLog::info(
                "🧠 [MemoriaContextualCurta] Nenhum contexto salvo"
            );
            return null;
        }

        $ultimo = end($memoria);

        // Verifica timeout
        $agora = now()->timestamp;
        if (
            isset($ultimo['ts']) &&
            ($agora - $ultimo['ts']) > self::TIMEOUT
        ) {
            self::limpar(true);
            SommelierLog::info(
                "🧹 [MemoriaContextualCurta] Contexto expirado por timeout"
            );
            return null;
        }

        SommelierLog::info(
            "🧠 [MemoriaContextualCurta] Contexto recuperado",
            $ultimo['dados'] ?? []
        );

        return $ultimo['dados'] ?? null;
    }

    /**
     * --------------------------------------------------
     * ✅ VERIFICA SE EXISTE CONTEXTO ATIVO
     * --------------------------------------------------
     */
    public static function temContexto(): bool
    {
        $ctx = self::recuperar();

        if (!is_array($ctx)) {
            return false;
        }

        return !empty($ctx);
    }

    /**
     * --------------------------------------------------
     * 🧹 LIMPAR MEMÓRIA
     * --------------------------------------------------
     * @param bool $forcar Limpa mesmo que haja contexto
     */
    public static function limpar(bool $forcar = false): void
    {
        if (!$forcar) {
            // Evita limpar contexto ativo sem necessidade
            if (self::temContexto()) {
                SommelierLog::info(
                    "🧠 [MemoriaContextualCurta] Limpeza ignorada (contexto ativo)"
                );
                return;
            }
        }

        session()->forget(self::SESSION_KEY);

        SommelierLog::info(
            "🧹 [MemoriaContextualCurta] Contexto limpo"
        );
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
