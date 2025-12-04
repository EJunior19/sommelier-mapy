<?php

namespace App\Services\Sommelier;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Helpers\SommelierLog;
use App\Services\Sommelier\Emojis;

class Buscador
{
    /**
     * ==========================================================
     *  🔥 BUSCADOR PRINCIPAL (rápido + humano + sem IA inventar)
     * ==========================================================
     */
    public static function buscar(string $textoOriginal): ?string
    {
        SommelierLog::info("📥 [BUSCADOR] Texto original recebido: {$textoOriginal}");

        // 1) Sanitiza entrada
        $textoOriginal = trim($textoOriginal);
        if (mb_strlen($textoOriginal, 'UTF-8') < 2) {
            SommelierLog::info("⚠️ [BUSCADOR] Texto muito curto, ignorado.");
            return null;
        }

        // 2) Se contém parâmetros de intenção → deixa para o módulo certo
        if (
            preg_match('/\d+\s*ml/i', $textoOriginal) ||
            preg_match('/\d+\s*(litro|litros|lt|lts|l)\b/i', $textoOriginal) ||
            preg_match('/\d+\s*(dolar|dólar|usd)/i', $textoOriginal) ||
            preg_match('/acima de|a cima de|menor que|maior que|até|ate|entre/i', $textoOriginal)
        ) {
            SommelierLog::info("➡️ [BUSCADOR] Texto contém intenções → encaminhado ao módulo de intenções.");
            return null;
        }

        // 3) Normaliza texto para TRGM
        $texto = mb_strtolower($textoOriginal, 'UTF-8');
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        $texto = trim($texto);

        SommelierLog::info("🔧 [BUSCADOR] Texto normalizado para TRGM: {$texto}");

        if ($texto === '') {
            SommelierLog::info("⚠️ [BUSCADOR] Texto normalizado vazio.");
            return null;
        }

        // 4) Monta query TRGM
        $query = DB::table('bebidas')
            ->select('id', 'nome_limpo', 'marca', 'tipo', 'precio')
            ->where('stock', '>', 0)
            ->whereRaw('busca_composta % ?', [$texto])
            ->orderByRaw('busca_composta <-> ?', [$texto])
            ->limit(10);

        // LOG DO SQL
        SommelierLog::info("🧩 [BUSCADOR] SQL GERADO:\n" . $query->toSql());
        SommelierLog::info("🔢 [BUSCADOR] BINDINGS:\n" . json_encode($query->getBindings(), JSON_PRETTY_PRINT));

        // Executa
        $lista = $query->get();

        SommelierLog::info("📦 [BUSCADOR] Resultados encontrados: " . $lista->count());
        SommelierLog::info("📄 [BUSCADOR] LISTA DE BEBIDAS:\n" . json_encode($lista, JSON_PRETTY_PRINT));

        if ($lista->isEmpty()) {
            return null;
        }

        // 5) Armazena IDs encontrados
        Session::put('ultimo_resultado_bebidas', $lista->pluck('id')->toArray());

        // 6) Resposta humana
        $emojiEmocao = Emojis::emocao($textoOriginal);

        $textoLista = $lista->take(6)->map(function ($b) use ($emojiEmocao) {
            $emojiTipo = Emojis::tipo($b->tipo);
            $preco = number_format($b->precio, 2, ',', '.');

            return "• {$emojiEmocao} {$emojiTipo} {$b->nome_limpo} — {$preco} dólares";
        })->join("\n");

        $resposta = "Encontrei algumas opções próximas do que você pediu:\n\n{$textoLista}\n\nQuer refinar por categoria, marca ou preço?";

        SommelierLog::info("🤖 [BUSCADOR] Resposta final:\n{$resposta}");

        return $resposta;
    }

    /**
     * ==========================================================
     *  🔥 BUSCA POR INTENÇÕES
     * ==========================================================
     */
    public static function buscarPorIntencoes(array $int, string $textoOriginal): ?string
    {
        SommelierLog::info("🎯 [INTENCOES] Entrada recebida:\n" . json_encode($int, JSON_PRETTY_PRINT));

        // Base
        $query = DB::table('bebidas')
            ->select('id', 'nome_limpo', 'marca', 'tipo', 'precio', 'volume_ml')
            ->where('stock', '>', 0);

        // Categoria
        if (!empty($int['categoria'])) {
            $query->where('tipo', $int['categoria']);
            SommelierLog::info("➡️ [INTENCOES] Categoria aplicada: {$int['categoria']}");
        }

        // Marca
        if (!empty($int['marca'])) {
            $marcaNorm = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $int['marca']), 'UTF-8');
            $query->whereRaw("marca_normalizada % ?", [$marcaNorm]);
            SommelierLog::info("➡️ [INTENCOES] Marca aplicada: {$marcaNorm}");
        }

        // Sensorial
        if (!empty($int['sensorial'])) {
            SommelierLog::info("➡️ [INTENCOES] Sensorial: {$int['sensorial']}");
            switch ($int['sensorial']) {
                case 'doce':
                    $query->whereRaw("nome_limpo ILIKE '%sweet%' OR nome_limpo ILIKE '%moscato%' OR nome_limpo ILIKE '%doce%'");
                    break;
                case 'suave':
                    $query->whereRaw("nome_limpo ILIKE '%suave%' OR nome_limpo ILIKE '%leve%'");
                    break;
                case 'forte':
                    $query->whereRaw("nome_limpo ILIKE '%strong%' OR nome_limpo ILIKE '%encorp%'");
                    break;
                case 'seco':
                    $query->whereRaw("nome_limpo ILIKE '%brut%' OR nome_limpo ILIKE '%sec%'");
                    break;
                case 'frutado':
                    $query->whereRaw("nome_limpo ILIKE '%fruit%' OR nome_limpo ILIKE '%frut%'");
                    break;
            }
        }

        // Preço
        if (!empty($int['precoMin'])) {
            $query->where('precio', '>=', $int['precoMin']);
        }
        if (!empty($int['precoMax'])) {
            $query->where('precio', '<=', $int['precoMax']);
        }

        // Volume
        if (!empty($int['minMl'])) {
            $query->where('volume_ml', '>=', $int['minMl']);
        }
        if (!empty($int['maxMl'])) {
            $query->where('volume_ml', '<=', $int['maxMl']);
        }

        // Ordenação TRGM
        $texto = trim($textoOriginal);
        $texto = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto), 'UTF-8');

        if ($texto !== '') {
            $query->orderByRaw("busca_composta <-> ?", [$texto]);
        }

        // LOG DO SQL
        SommelierLog::info("🧩 [INTENCOES] SQL GERADO:\n" . $query->toSql());
        SommelierLog::info("🔢 [INTENCOES] BINDINGS:\n" . json_encode($query->getBindings(), JSON_PRETTY_PRINT));

        // Executa
        $lista = $query->limit(10)->get();

        SommelierLog::info("📦 [INTENCOES] Resultados encontrados: " . $lista->count());
        SommelierLog::info("📄 [INTENCOES] LISTA DE BEBIDAS:\n" . json_encode($lista, JSON_PRETTY_PRINT));

        if ($lista->isEmpty()) {
            $msg = "Não encontrei nada dentro desses critérios 😕\n\nQuer tentar outra faixa de preço, marca ou categoria?";
            SommelierLog::info("🤖 [INTENCOES] Resposta final:\n{$msg}");
            return $msg;
        }

        Session::put('ultimo_resultado_bebidas', $lista->pluck('id')->toArray());

        // Resposta
        $resposta = "Aqui estão opções dentro do que você descreveu:\n\n";

        foreach ($lista as $b) {
            $emojiTipo = Emojis::tipo($b->tipo);
            $preco     = number_format($b->precio, 2, ',', '.');

            $resposta .= "• {$emojiTipo} {$b->nome_limpo} — {$preco} dólares\n";
        }

        $resposta .= "\nQuer refinar ainda mais?";

        SommelierLog::info("🤖 [INTENCOES] Resposta final:\n{$resposta}");

        return $resposta;
    }
    public static function detectarProduto(string $textoNormalizado): ?array
    {
        // 1) Tenta encontrar pelo nome do produto (melhor acerto)
        $produto = DB::table('bebidas')
            ->select('id', 'nome_limpo', 'marca', 'tipo', 'precio', 'volume_ml', 'pais_origem')
            ->whereRaw("similarity(nome_limpo, ?) > 0.35", [$textoNormalizado])
            ->orderByRaw("similarity(nome_limpo, ?) DESC", [$textoNormalizado])
            ->limit(1)
            ->first();

        if ($produto) {
            return (array)$produto;
        }

        // 2) Fallback: tenta por marca (apenas se muito próxima)
        $produto = DB::table('bebidas')
            ->select('id', 'nome_limpo', 'marca', 'tipo', 'precio', 'volume_ml', 'pais_origem')
            ->whereRaw("similarity(marca_normalizada, ?) > 0.40", [$textoNormalizado])
            ->orderByRaw("similarity(marca_normalizada, ?) DESC", [$textoNormalizado])
            ->limit(1)
            ->first();

        return $produto ? (array)$produto : null;
    }

}
