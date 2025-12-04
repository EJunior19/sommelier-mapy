<?php

namespace App\Services\Sommelier;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class Intencoes
{
    public static function processar(string $textoOriginal): array
    {
        $tLower = mb_strtolower($textoOriginal, 'UTF-8');

        $resultado = [
            'categoria' => null,
            'marca' => null,
            'sensorial' => null,
            'precoMin' => null,
            'precoMax' => null,
            'minMl' => null,
            'maxMl' => null,

            // NOVOS CAMPOS:
            'perguntaEspecifica' => false,
            'produtoDetectado' => null,
        ];

        // ===========================================================
        // 1) DETECTAR PERGUNTA DE PROCEDÊNCIA / FABRICAÇÃO
        // ===========================================================

        $padroes = [
            'procedencia', 'procedência', 'de onde vem',
            'onde é fabricado', 'onde e fabricado', 'onde é feito',
            'onde e feito', 'é argentino', 'é chileno',
            'é brasileiro', 'pais de origem', 'país de origem'
        ];

        $isPergunta = false;
        foreach ($padroes as $p) {
            if (str_contains($tLower, $p)) {
                $isPergunta = true;
                break;
            }
        }

        if ($isPergunta) {

            // Extrair apenas o produto
            $produto = Buscador::detectarProduto(Normalizador::textoLimpo($textoOriginal));

            if ($produto) {
                $resultado['perguntaEspecifica'] = 'procedencia';
                $resultado['produtoDetectado'] = $produto;

                return $resultado; // <-- FINALIZA AQUI
            }
        }

        // ===========================================================
        // 2) SE NÃO FOR PERGUNTA ESPECÍFICA → continuar fluxo normal
        // ===========================================================

        // (seu código atual de faixa de preço, categoria etc)
        return $resultado;
    }

    /* ---------------------------------------------------------
       CATEGORIA — VERSÃO C (MAPA + HEURÍSTICAS + TRGM MELHORADO)
    ----------------------------------------------------------*/
    public static function detectarCategoria(string $texto): ?string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');

        // Normaliza acentos / caracteres
        $normalizado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        $normalizado = preg_replace('/[^a-z0-9 ]/i', ' ', $normalizado);
        $normalizado = preg_replace('/\s+/', ' ', $normalizado);
        $normalizado = trim($normalizado);

        $palavras = $normalizado === '' ? [] : explode(' ', $normalizado);

        // ---------------------------------------------------------
        // 🧠 1) DICIONÁRIO PRINCIPAL (MAPEIA → tipos REAIS do banco)
        // ---------------------------------------------------------
        $mapa = Cache::remember('sommelier_mapa_categorias_vC', 86400, function () {
            return [

                // ------------------- VINOS -------------------
                'vinho'         => 'VINOS',
                'vinhos'        => 'VINOS',
                'vino'          => 'VINOS',
                'vinos'         => 'VINOS',
                'vinitos'       => 'VINOS',
                'tinto'         => 'VINOS',
                'tintos'        => 'VINOS',
                'rose'          => 'VINOS',
                'rosé'          => 'VINOS',
                'rosado'        => 'VINOS',
                'branco'        => 'VINOS',
                'blanco'        => 'VINOS',
                'demi sec'      => 'VINOS',
                'demisec'       => 'VINOS',
                'cabernet'      => 'VINOS',
                'malbec'        => 'VINOS',
                'sauvignon'     => 'VINOS',
                'merlot'        => 'VINOS',
                'carmenere'     => 'VINOS',
                'chardonnay'    => 'VINOS',
                'moscato'       => 'VINOS',
                'moscatel'      => 'VINOS',

                // ------------------- WHISKY -------------------
                'whisky'        => 'WHISKY',
                'whisqui'       => 'WHISKY',
                'wisky'         => 'WHISKY',
                'whiskys'       => 'WHISKY',
                'whiskeys'      => 'WHISKY',
                'bourbon'       => 'WHISKY',
                'scotch'        => 'WHISKY',
                'single malt'   => 'WHISKY',

                // ------------------- CERVEZA -------------------
                'cerveja'       => 'CERVEZA',
                'cervejas'      => 'CERVEZA',
                'cervejinhas'   => 'CERVEZA',
                'breja'         => 'CERVEZA',
                'beer'          => 'CERVEZA',
                'ipa'           => 'CERVEZA',
                'pilsen'        => 'CERVEZA',
                'lager'         => 'CERVEZA',
                'long neck'     => 'CERVEZA',
                'latinha'       => 'CERVEZA',

                // ------------------- GIN -------------------
                'gin'           => 'GIN',
                'gim'           => 'GIN',
                'ging'          => 'GIN',
                'gin tonica'    => 'GIN',
                'gin tônica'    => 'GIN',
                'gintonic'      => 'GIN',
                'london dry'    => 'GIN',

                // ------------------- VODKA -------------------
                'vodka'         => 'VODKA',
                'vocka'         => 'VODKA',
                'vódica'        => 'VODKA',
                'smirnoff'      => 'VODKA',
                'sminorff'      => 'VODKA',
                'ice'           => 'VODKA',

                // ------------------- LICOR(ES) -------------------
                'licor'         => 'LICORES',
                'licores'       => 'LICORES',
                'amarula'       => 'LICORES',
                'baileys'       => 'LICORES',
                'soju'          => 'LICORES',
                'chum churum'   => 'LICORES',
                'chumchurum'    => 'LICORES',

                // ------------------- ESPUMANTES / CHAMPAGNE ------
                'espumante'         => 'ESPUMANTES',
                'espumantes'        => 'ESPUMANTES',
                'espumanta'         => 'ESPUMANTES',
                'prosecco'          => 'ESPUMANTES',
                'brut'              => 'ESPUMANTES',
                'extra brut'        => 'ESPUMANTES',
                'champagne'         => 'CHAMPAGNE',
                'champanhe'         => 'CHAMPAGNE',

                // ------------------- CACHAÇA -------------------
                // (mapeia para o tipo exatamente como está no banco: "CACHAÃA")
                'cachaca'       => 'CACHAÇA',
                'cachaça'       => 'CACHAÇA',
                'pinga'         => 'CACHAÇA',
                'caninha'       => 'CACHAÇA',
                'caninha de alambique' => 'CACHAÇA',
                'alambique'     => 'CACHAÇA',
                'alambiques'    => 'CACHAÇA',
                'engenho'      => 'CACHAÇA',


                // ------------------- RON / RUM -------------------
                'rum'           => 'RON',
                'ron'           => 'RON',

                // ------------------- TEQUILA -------------------
                'tequila'       => 'TEQUILA',
                'mezcal'        => 'TEQUILA',

                // ------------------- ENERGÉTICOS -------------------
                // Tua base tem ENERGETICO e ENERGIZANTE
                'energetico'    => 'ENERGETICO',
                'energético'    => 'ENERGETICO',
                'energy drink'  => 'ENERGETICO',
                'monster'       => 'ENERGETICO',
                'red bull'      => 'ENERGETICO',
                'redbull'       => 'ENERGETICO',
                'fusion'        => 'ENERGETICO',
                'energizante'   => 'ENERGIZANTE',

                // ------------------- AGUAS / ÁGUA SABORIZADA ------
                'agua'              => 'AGUA',
                'água'              => 'AGUA',
                'agua mineral'      => 'AGUA',
                'água mineral'      => 'AGUA',
                'mineral'           => 'AGUA',
                'agua saborizada'   => 'AGUA SABORISADA.',
                'água saborizada'   => 'AGUA SABORISADA.',

                // ------------------- COCTEL / DRINKS -------------
                'coquetel'      => 'COCTEL',
                'coquetéis'     => 'COCTEL',
                'coctel'        => 'COCTEL',
                'drink'         => 'COCTEL',
                'drinks'        => 'COCTEL',
                'cocktail'      => 'COCTEL',

                // ------------------- VAPE / VAPEADOR --------------
                'vape'              => 'VAPEADOR',
                'vaper'             => 'VAPEADOR',
                'vapeador'          => 'VAPEADOR',
                'cigarro eletronico'=> 'VAPEADOR',
                'cigarro eletrônico'=> 'VAPEADOR',
                'pod'               => 'VAPEADOR',

                'liquido vape'      => 'LIQUIDO DE VAPEADOR',
                'liquido de vape'   => 'LIQUIDO DE VAPEADOR',
                'juice'             => 'LIQUIDO DE VAPEADOR',
                'e-liquid'          => 'LIQUIDO DE VAPEADOR',

                'filtro vape'           => 'FILTRO PARA VAIPER',
                'filtro para vape'      => 'FILTRO PARA VAIPER',
                'acessorio vape'        => 'ACCESORIO DE VAPEADOR',
                'acessórios vape'       => 'ACCESORIO DE VAPEADOR',
                'accesorio de vapeador' => 'ACCESORIO DE VAPEADOR',

                // ------------------- TABACOS / HABANOS ------------
                'charuto'       => 'HABANOS',
                'charutos'      => 'HABANOS',
                'habanos'       => 'HABANOS',
                'cigarro'       => 'TABACOS',
                'cigarros'      => 'TABACOS',
                'tabaco'        => 'TABACOS',
                'tabacos'       => 'TABACOS',

                // ------------------- COISAS EXTRAS ----------------
                'cognac'        => 'COGNAG',
                'conhaque'      => 'COGNAG',
                'fernét'        => 'FERNET',
                'fernet'        => 'FERNET',
                'vermute'       => 'VERMOUT',
                'vermouth'      => 'VERMOUT',
            ];
        });

        // -------------------------------------------
        // 🧠 2) BIGRAMAS / TRIGRAMAS (melhor precisão)
        // -------------------------------------------
        for ($i = 0; $i < count($palavras) - 1; $i++) {
            $bi = trim($palavras[$i] . ' ' . $palavras[$i + 1]);
            if (isset($mapa[$bi])) {
                return $mapa[$bi];
            }
        }

        for ($i = 0; $i < count($palavras) - 2; $i++) {
            $tri = trim($palavras[$i] . ' ' . $palavras[$i + 1] . ' ' . $palavras[$i + 2]);
            if (isset($mapa[$tri])) {
                return $mapa[$tri];
            }
        }

        // -------------------------------------------
        // 🧠 3) PALAVRAS ISOLADAS
        // -------------------------------------------
        foreach ($palavras as $p) {
            if (isset($mapa[$p])) {
                return $mapa[$p];
            }
        }

        // -------------------------------------------
        // 🧠 4) TRGM MELHORADO — palavra a palavra
        // -------------------------------------------
        $melhorTipo  = null;
        $melhorScore = 0.0;

        foreach ($palavras as $p) {
            if (strlen($p) < 3) {
                continue;
            }

            $res = DB::table('bebidas')
                ->select('tipo')
                ->selectRaw("similarity(tipo, ?) AS score", [$p])
                ->whereRaw("similarity(tipo, ?) > 0.35", [$p])
                ->orderByDesc('score')
                ->limit(1)
                ->first();

            if ($res && isset($res->score) && $res->score > $melhorScore) {
                $melhorScore = (float)$res->score;
                $melhorTipo  = $res->tipo;
            }
        }

        if ($melhorTipo) {
            return $melhorTipo;
        }

        return null;
    }

    /* ---------------------------------------------------------
       MARCA — PRIMEIRO TRGM → DEPOIS CACHE (EVITA FULL SCAN)
    ----------------------------------------------------------*/
    public static function detectarMarca(string $textoSemAcento): ?string
    {
        // TRGM ultra rápido (usa índice idx_marca_normalizada_trgm)
        $possivel = DB::table('bebidas')
            ->whereNotNull('marca_normalizada')
            ->whereRaw("similarity(marca_normalizada, ?) > 0.35", [$textoSemAcento])
            ->orderByRaw("similarity(marca_normalizada, ?) DESC", [$textoSemAcento])
            ->limit(1)
            ->value('marca');

        if ($possivel) {
            return $possivel;
        }

        // fallback: lista cacheada REDUZIDA (somente marcas >= 4 chars)
        $todas = Cache::remember('sommelier_marcas_cache_v3', 43200, function () {
            return DB::table('bebidas')
                ->select('marca')
                ->distinct()
                ->whereNotNull('marca')
                ->where('marca', '<>', '')
                ->orderBy('marca')
                ->pluck('marca')
                ->map(fn ($m) => [
                    'orig' => trim($m),
                    'norm' => trim(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($m)))
                ])
                ->filter(fn ($m) => strlen($m['norm']) >= 4)
                ->values()
                ->toArray();
        });

        foreach ($todas as $m) {
            if (str_contains($textoSemAcento, $m['norm'])) {
                return $m['orig'];
            }
        }

        return null;
    }

    /* ---------------------------------------------------------
       PERFIL SENSORIAL — EXPANDIDO (VERSÃO D)
    ----------------------------------------------------------*/
    public static function detectarPerfilSensorial(string $texto): ?string
    {
        $t = mb_strtolower($texto, 'UTF-8');

        // 🍯 DOCE
        if (
            str_contains($t, 'doce') ||
            str_contains($t, 'docinho') ||
            str_contains($t, 'adocicado') ||
            str_contains($t, 'adocicada') ||
            str_contains($t, 'mel') ||
            str_contains($t, 'melado') ||
            str_contains($t, 'suavemente doce')
        ) {
            return 'doce';
        }

        // 🌸 SUAVE / LEVE
        if (
            str_contains($t, 'suave') ||
            str_contains($t, 'suavinho') ||
            str_contains($t, 'suavão') ||
            str_contains($t, 'leve') ||
            str_contains($t, 'tranquilo') ||
            str_contains($t, 'facil de beber') ||
            str_contains($t, 'fácil de beber') ||
            str_contains($t, 'delicado') ||
            str_contains($t, 'mais leve')
        ) {
            return 'suave';
        }

        // 🔥 FORTE / ENCORSADO / INTENSO
        if (
            str_contains($t, 'forte') ||
            str_contains($t, 'bem forte') ||
            str_contains($t, 'encorpado') ||
            str_contains($t, 'encorpada') ||
            str_contains($t, 'pesado') ||
            str_contains($t, 'bem pesado') ||
            str_contains($t, 'intenso') ||
            str_contains($t, 'intensa') ||
            str_contains($t, 'marcante') ||
            str_contains($t, 'vigoroso') ||
            str_contains($t, 'mais forte') ||
            str_contains($t, 'impactante')
        ) {
            return 'forte';
        }

        // 🥂 SECO
        if (
            str_contains($t, 'seco') ||
            str_contains($t, 'secos') ||
            str_contains($t, 'demi sec') ||
            str_contains($t, 'demisec') ||
            str_contains($t, 'brut') ||
            str_contains($t, 'extra brut')
        ) {
            return 'seco';
        }

        // 🍃 AMARGO / IPA
        if (
            str_contains($t, 'amargo') ||
            str_contains($t, 'amarga') ||
            str_contains($t, 'amarguinho') ||
            str_contains($t, 'ipa') ||
            str_contains($t, 'lupulo') ||
            str_contains($t, 'lúpulo')
        ) {
            return 'amargo';
        }

        // 🍓 FRUTADO / AROMÁTICO
        if (
            str_contains($t, 'frutado') ||
            str_contains($t, 'frutada') ||
            str_contains($t, 'frutas') ||
            str_contains($t, 'aromatico') ||
            str_contains($t, 'aromático') ||
            str_contains($t, 'aroma') ||
            str_contains($t, 'floral') ||
            str_contains($t, 'citrico') ||
            str_contains($t, 'cítrico') ||
            str_contains($t, 'citricos')
        ) {
            return 'frutado';
        }

        return null;
    }

    /* ---------------------------------------------------------
       FAIXA DE PREÇO — MAIS ROBUSTA + NÚMEROS POR EXTENSO
       (agora entende "a cima de 10" e "menor a 20")
    ----------------------------------------------------------*/
    public static function detectarFaixaPreco(string $textoLower): array
    {
        $num = fn ($n) => (float)str_replace([',', '.'], ['.', '.'], $n);

        $min = null;
        $max = null;

        // PADRÃO MAIS COMUM: "20 a 60", "20 até 60", "20-60", "20 – 60"
        if (preg_match('/\b(\d+(?:[\.,]\d+)?)\s*(?:a|ate|até|\-|–|—|~|to)\s*(\d+(?:[\.,]\d+)?)/iu', $textoLower, $m)) {
            $min = $num($m[1]);
            $max = $num($m[2]);

            // se vier invertido: 60 a 20 → corrige
            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }

            return [
                'precoMin' => $min,
                'precoMax' => $max,
            ];
        }


        // ENTRE X E Y (com número)
        if (preg_match('/entre\s+(\d+(?:[\.,]\d+)?)\s*(e|a|até|ate|hasta)\s*(\d+(?:[\.,]\d+)?)/iu', $textoLower, $m)) {
            $min = $num($m[1]);
            $max = $num($m[3]);
        }

        // A PARTIR DE / DESDE X
        if (preg_match('/(a partir de|desde)\s+(\d+(?:[\.,]\d+)?)/iu', $textoLower, $m)) {
            $min = $num($m[2]);
        }

        // ACIMA DE / A CIMA DE / MAIOR QUE X
        if (preg_match('/(acima de|a cima de|a cima|maior que|superior a|mais de)\s+(\d+(?:[\.,]\d+)?)/iu', $textoLower, $m)) {
            $min = $num($m[2]);
        }

        // ATÉ X / MENOR QUE X / MENOR A X
        if (preg_match('/(até|ate|hasta|no maximo|no máximo|menor que|menor de|menor a)\s+(\d+(?:[\.,]\d+)?)/iu', $textoLower, $m)) {
            $max = $num($m[2]);
        }

        // “barato / baratinho / em conta” → teto padrão se ainda não definido
        if (
            (str_contains($textoLower, 'barato') ||
             str_contains($textoLower, 'baratinho') ||
             str_contains($textoLower, 'em conta') ||
             str_contains($textoLower, 'mais em conta')) && $max === null
        ) {
            $max = 10.0;
        }

        // “caro / mais caro / top / reserva / premium / especial” → piso padrão
        if (
            (str_contains($textoLower, 'caro') ||
             str_contains($textoLower, 'mais caro') ||
             str_contains($textoLower, 'top') ||
             str_contains($textoLower, 'reserva') ||
             str_contains($textoLower, 'gran reserva') ||
             str_contains($textoLower, 'premium') ||
             str_contains($textoLower, 'super premium') ||
             str_contains($textoLower, 'especial')) && $min === null
        ) {
            $min = 25.0;
        }

        // Se ainda não encontrou número em dígitos, tenta por extenso
        if ($min === null && $max === null) {
            $valorExtenso = self::extrairNumeroPorExtenso($textoLower);

            if ($valorExtenso !== null) {
                // contexto “a partir de / mais de / acima” → mínimo
                if (preg_match('/(a partir de|desde|acima de|a cima de|a cima|maior que|superior a|mais de)/iu', $textoLower)) {
                    $min = $valorExtenso;
                }
                // contexto “até / no máximo / menor que / menor a” → máximo
                elseif (preg_match('/(até|ate|hasta|no maximo|no máximo|menor que|menor de|menor a)/iu', $textoLower)) {
                    $max = $valorExtenso;
                } else {
                    // sem contexto → trata como valor mínimo
                    $min = $valorExtenso;
                }
            }
        }

        return [
            'precoMin' => $min,
            'precoMax' => $max,
        ];
    }

    /* ---------------------------------------------------------
       VOLUME — ML + LITROS + HEURÍSTICAS COMUNS
    ----------------------------------------------------------*/
    public static function detectarVolume(string $texto): array
    {
        $t = mb_strtolower($texto, 'UTF-8');

        // 1) litros explícitos → ml (corrigido — entende "de 20 litros", "20 litros", etc.)
        if (preg_match('/(?:de|com)?\s*(\d+(?:[\.,]\d+)?)\s*(litro|litros|lt|lts|l)\b/iu', $t, $m)) {
            $valor = (float)str_replace(',', '.', $m[1]);
            return [
                'minMl' => (int)round($valor * 1000),
                'maxMl' => null,
            ];
        }
        // 2) Faixa entre X e Y ml
        if (preg_match('/entre\s+(\d+)\s*(?:ml)?\s*(e|a)\s*(\d+)\s*ml/iu', $t, $m)) {
            return [
                'minMl' => (int)$m[1],
                'maxMl' => (int)$m[3],
            ];
        }

        // 3) acima / maior que / mais de X ml
        if (preg_match('/(acima de|a cima de|a cima|maior que|mais de)\s+(\d+)\s*ml/iu', $t, $m)) {
            return [
                'minMl' => (int)$m[2],
                'maxMl' => null,
            ];
        }

        // 4) até / menor que X ml
        if (preg_match('/(ate|até|menor que|menor de|menor a|no maximo|no máximo)\s+(\d+)\s*ml/iu', $t, $m)) {
            return [
                'minMl' => null,
                'maxMl' => (int)$m[2],
            ];
        }

        // 5) valor único em ml
        if (preg_match('/(\d+)\s*ml/iu', $t, $m)) {
            return [
                'minMl' => (int)$m[1],
                'maxMl' => null,
            ];
        }

        // 6) HEURÍSTICAS
        if (str_contains($t, 'long neck')) {
            return [
                'minMl' => 330,
                'maxMl' => 355,
            ];
        }

        if (
            str_contains($t, 'latinha') ||
            str_contains($t, 'lata') ||
            str_contains($t, 'em lata') ||
            str_contains($t, 'en lata')
        ) {
            return [
                'minMl' => 300,
                'maxMl' => 400,
            ];
        }

        if (
            str_contains($t, 'mini garrafa') ||
            str_contains($t, 'mini-garrafa') ||
            str_contains($t, 'garrafinha') ||
            str_contains($t, 'garrafa pequena')
        ) {
            return [
                'minMl' => 180,
                'maxMl' => 375,
            ];
        }

        if (
            str_contains($t, 'garrafa grande') ||
            str_contains($t, 'garrafao') ||
            str_contains($t, 'garrafão') ||
            str_contains($t, 'litrao') ||
            str_contains($t, 'litrão')
        ) {
            return [
                'minMl' => 1000,
                'maxMl' => null,
            ];
        }

        return [
            'minMl' => null,
            'maxMl' => null,
        ];
    }

    /* ---------------------------------------------------------
       AJUDANTE: NÚMEROS POR EXTENSO EM PT/ES → FLOAT
       (trinta, cuarenta, cinquenta, veinte, etc.)
    ----------------------------------------------------------*/
    protected static function extrairNumeroPorExtenso(string $textoLower): ?float
    {
        // Ordem importa: palavras mais específicas primeiro
        $mapa = [
            // português
            'cem'       => 100,
            'noventa'   => 90,
            'oitenta'   => 80,
            'setenta'   => 70,
            'sessenta'  => 60,
            'cinquenta' => 50,
            'quarenta'  => 40,
            'trinta'    => 30,
            'vinte'     => 20,
            'quinze'    => 15,
            'dez'       => 10,

            // espanhol
            'cien'      => 100,
            'ochenta'   => 80,
            'sesenta'   => 60,
            'cincuenta' => 50,
            'cuarenta'  => 40,
            'treinta'   => 30,
            'veinte'    => 20,
            'diez'      => 10,
        ];

        foreach ($mapa as $palavra => $valor) {
            if (str_contains($textoLower, $palavra)) {
                return (float)$valor;
            }
        }

        return null;
    }
}
