<?php

namespace App\Services\Sommelier\Rules;

use Illuminate\Support\Facades\DB;
use App\Services\Sommelier\Domain\CategoriaMap;
use App\Services\Sommelier\UX\NomeFormatter;
use App\Helpers\SommelierLog;

class RegraMediaPreco
{
    /**
     * --------------------------------------------------
     * 🔍 MATCH — pergunta de média de preço
     * --------------------------------------------------
     */
    public static function match(string $mensagem): bool
    {
        return (bool) preg_match(
            '/\b(media|m[eé]dio|em m[eé]dia|prom[eé]dio)\b/i',
            $mensagem
        );
    }

    /**
     * --------------------------------------------------
     * 🧠 RESPONDER
     * --------------------------------------------------
     */
    public static function responder(string $mensagem): ?string
    {
        SommelierLog::info("📊 [RegraMediaPreco] Pergunta de média detectada", [
            'mensagem' => $mensagem
        ]);

        $categoria = CategoriaMap::detectar($mensagem);

        if (!$categoria) {
            return "Você quer saber a média de qual tipo de bebida? 🍷";
        }

        $media = DB::table('bebidas')
            ->where('tipo', $categoria)
            ->where('stock', '>', 0)
            ->avg('precio');

        if (!$media) {
            return "No momento não encontrei dados suficientes para calcular essa média 😕";
        }

        $mediaFormatada = number_format($media, 2, ',', '.');
        $categoriaHumana = NomeFormatter::formatar(strtolower($categoria));

        SommelierLog::info("📊 [RegraMediaPreco] Média calculada", [
            'categoria' => $categoria,
            'media' => $mediaFormatada
        ]);

        return "Em média, os {$categoriaHumana} custam cerca de {$mediaFormatada} dólares.";
    }
}
