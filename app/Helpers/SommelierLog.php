<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

/**
 * ==========================================================
 * 🧾 SOMMELIER LOG — LOGGER CENTRAL
 * ----------------------------------------------------------
 * Centraliza logs do Sommelier Virtual
 * - info
 * - warning
 * - error
 * - debug
 * ==========================================================
 */
class SommelierLog
{
    public static function info(string $mensagem, array $contexto = []): void
    {
        Log::info($mensagem, $contexto);
    }

    public static function warning(string $mensagem, array $contexto = []): void
    {
        Log::warning($mensagem, $contexto);
    }

    public static function error(string $mensagem, array $contexto = []): void
    {
        Log::error($mensagem, $contexto);
    }

    public static function debug(string $mensagem, array $contexto = []): void
    {
        Log::debug($mensagem, $contexto);
    }
}
