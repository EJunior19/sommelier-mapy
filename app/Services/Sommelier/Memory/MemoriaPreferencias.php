<?php

namespace App\Services\Sommelier\Memory;

use Illuminate\Support\Facades\DB;
use App\Helpers\SommelierLog;

/**
 * ==========================================================
 * 🧠 MEMÓRIA DE PREFERÊNCIAS — SOMMELIER MAPY
 * ----------------------------------------------------------
 * Registra histórico leve do cliente para:
 * - Melhorar recomendações futuras
 * - Aprendizado simples (sem IA)
 * ==========================================================
 */
class MemoriaPreferencias
{
    /**
     * --------------------------------------------------
     * 📝 Registra interação simples
     * --------------------------------------------------
     */
    public static function registrar(string $mensagem): void
    {
        try {
            SommelierLog::info("🧠 [Memoria] Registrando preferência", [
                'mensagem' => $mensagem
            ]);

            DB::table('interacoes_clientes')->insert([
                'mensagem'   => $mensagem,
                'created_at' => now(),
            ]);

        } catch (\Throwable $e) {

            // ⚠️ memória NUNCA pode quebrar o fluxo
            SommelierLog::warning("⚠️ [Memoria] Falha ao registrar", [
                'erro' => $e->getMessage()
            ]);
        }
    }
}
