<?php

namespace App\Services\Sommelier;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class Buscador
{
    /**
     * Busca rápida usando TRGM + índices existentes
     */
    public static function buscar(string $textoOriginal): ?string
    {
        if (strlen(trim($textoOriginal)) < 2) {
            return null;
        }

        // Normalização TRGM
        $texto = mb_strtolower($textoOriginal, 'UTF-8');
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        // ⚠️ Sanitiza apóstrofos para não quebrar queries
        $textoSan = str_replace("'", " ", $texto);

        // ============================================================
        // 1) MATCH DIRETO — usando campo pré-indexado: nome_limpo
        // ============================================================
        $matchDireto = DB::table('bebidas')
            ->select('*', DB::raw("similarity(nome_limpo, '" . addslashes($textoSan) . "') AS rel"))
            ->where('stock', '>', 0)
            ->whereRaw("similarity(nome_limpo, '" . addslashes($textoSan) . "') > 0.45")
            ->orderByRaw("similarity(nome_limpo, '" . addslashes($textoSan) . "') DESC")
            ->limit(1)
            ->first();

        if ($matchDireto) {
            $preco = number_format($matchDireto->precio, 2, ',', '.');
            $emoji = \App\Services\Sommelier\Emojis::tipo($matchDireto->tipo);

            return "{$emoji} O **{$matchDireto->nome_limpo}** está por **{$preco} dólares**.";
        }

        // ============================================================
        // 2) MATCH POR MARCA NORMALIZADA (índice TRGM)
        // ============================================================
        $matchMarca = DB::table('bebidas')
            ->select('*', DB::raw("similarity(marca_normalizada, ?) AS rel"))
            ->where('stock', '>', 0)
            ->whereNotNull('marca_normalizada')
            ->whereRaw("similarity(marca_normalizada, ?) > 0.40", [$textoSan, $textoSan])
            ->orderByRaw("similarity(marca_normalizada, ?) DESC", [$textoSan])
            ->limit(1)
            ->first();

        if ($matchMarca) {
            $preco = number_format($matchMarca->precio, 2, ',', '.');
            $emoji = \App\Services\Sommelier\Emojis::tipo($matchMarca->tipo);

            return "{$emoji} Tenho opções da marca **{$matchMarca->marca}**.  
            Uma delas custa **{$preco} dólares**.  
            Quer ver mais opções da mesma marca?";
        }

        // ============================================================
        // 3) FALLBACK — MATCH LEVE usando TRGM composto
        // ============================================================
        $campoFull = "
            nome_limpo || ' ' || coalesce(marca_normalizada,'') ||
            ' ' || coalesce(tipo,'')
        ";

        $fallback = DB::table('bebidas')
            ->select('*', DB::raw("similarity($campoFull, ?) AS rel"))
            ->where('stock', '>', 0)
            ->whereRaw("similarity($campoFull, ?) > 0.20", [$textoSan, $textoSan])
            ->orderByRaw("similarity($campoFull, ?) DESC", [$textoSan])
            ->limit(10)
            ->get();

        if ($fallback->isEmpty()) {
            return null;
        }

        // Memoriza IDs
        Session::put('ultimo_resultado_bebidas', $fallback->pluck('id')->toArray());

        // Lista resumida com EMOJIS
        $textoLista = $fallback->take(6)->map(function ($b) use ($textoOriginal) {

            $emojiTipo   = \App\Services\Sommelier\Emojis::tipo($b->tipo);
            $emojiEmocao = \App\Services\Sommelier\Emojis::emocao($textoOriginal);

            $preco = number_format($b->precio, 2, ',', '.');

            return "• {$emojiEmocao} {$emojiTipo} {$b->nome_limpo} — {$preco} dólares";
        })->join("\n");

        return "Encontrei algumas opções próximas do que você pediu:\n\n{$textoLista}\n\nQuer refinar por categoria, marca ou preço?";
    }


    // ============================================================
    // 2) Busca por intenções extraídas
    // ============================================================

    public static function buscarPorIntencoes(array $int, string $textoOriginal): ?string
    {
        $query = DB::table('bebidas')
            ->where('stock', '>', 0)
            ->where('precio', '>', 0.5);

        if ($int['categoria']) {
            $query->whereRaw("upper(tipo) = ?", [strtoupper($int['categoria'])]);
        }

        if ($int['marca']) {
            $query->where('marca', 'ilike', "%{$int['marca']}%");
        }

        if ($int['precoMin'] !== null) {
            $query->where('precio', '>=', $int['precoMin']);
        }

        if ($int['precoMax'] !== null) {
            $query->where('precio', '<=', $int['precoMax']);
        }

        $lista = $query->orderBy('precio')->limit(10)->get();

        if ($lista->isEmpty()) {
            return null;
        }

        Session::put('ultimo_resultado_bebidas', $lista->pluck('id')->toArray());

        return $lista->take(6)
            ->map(function ($b) use ($textoOriginal) {

                $emojiTipo = \App\Services\Sommelier\Emojis::tipo($b->tipo);
                $emojiEmocao = \App\Services\Sommelier\Emojis::emocao($textoOriginal);

                $preco = number_format($b->precio, 2, ',', '.');

                return "• {$emojiEmocao} {$emojiTipo} {$b->nome_limpo} — {$preco} dólares";
            })
            ->join("\n");
    }
}
