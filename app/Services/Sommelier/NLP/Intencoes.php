<?php

namespace App\Services\Sommelier\NLP;

use App\Services\Sommelier\Domain\CategoriaMap;
use App\Services\Sommelier\Search\Buscador;
use App\Services\Sommelier\Support\Normalizador;
use App\Services\Sommelier\Memory\MemoriaContextualCurta;
use App\Helpers\SommelierLog;

class Intencoes
{
    // ===============================
    // 🎯 FILTROS PRINCIPAIS
    // ===============================
    public ?string $categoria = null;
    public ?string $marca     = null;
    public ?string $sensorial = null;
    public ?string $ocasiao   = null;

    // ===============================
    // 💲 PREÇO
    // ===============================
    public ?float $precoMin = null;
    public ?float $precoMax = null;

    // ===============================
    // 🧴 VOLUME
    // ===============================
    public ?int $minMl = null;
    public ?int $maxMl = null;

    // ===============================
    // ❓ PERGUNTAS ESPECÍFICAS
    // ===============================
    public ?string $perguntaEspecifica = null; // procedencia | abstrata
    public ?array  $produtoDetectado   = null;

    /**
     * --------------------------------------------------
     * 🧠 PROCESSAR TEXTO
     * --------------------------------------------------
     */
    public static function processar(string $texto): self
    {
        $i = new self();

        $textoOriginal = (string) $texto;
        $t = Normalizador::textoLimpo(
            mb_strtolower($textoOriginal, 'UTF-8')
        );

        if ($t === '') {
            return $i;
        }

        $t = self::normalizarSTT($t);

        // ===============================
        // ❓ PERGUNTA ABSTRATA (INTERROMPE)
        // ===============================
        if (self::ehPerguntaAbstrata($t)) {
            $i->perguntaEspecifica = 'abstrata';
            $i->categoria = CategoriaMap::detectar($t);
            return $i;
        }

        // ===============================
        // ❓ PROCEDÊNCIA
        // ===============================
        if (preg_match(
            '/\b(procedenc|procedência|origem|origen|de onde (vem|é)|pais de origem|país de origem)\b/i',
            $t
        )) {
            $i->perguntaEspecifica = 'procedencia';
        }

        // ===============================
        // 🍷 CATEGORIA
        // ===============================
        $i->categoria = CategoriaMap::detectar($t);

        // ===============================
        // 👅 SENSORIAL
        // ===============================
        if (preg_match('/\b(doce|dulce|adocicado|meloso)\b/i', $t)) {
            $i->sensorial = 'doce';
        } elseif (preg_match('/\b(forte|fuerte|encorpado|intenso)\b/i', $t)) {
            $i->sensorial = 'forte';
        } elseif (preg_match('/\b(leve|ligero|suave|light)\b/i', $t)) {
            $i->sensorial = 'leve';
        } elseif (preg_match('/\b(seco|dry|brut)\b/i', $t)) {
            $i->sensorial = 'seco';
            $i->categoria ??= 'ESPUMANTES';
        }

        // ===============================
        // 🎉 OCASIÃO
        // ===============================
        if (preg_match('/\b(presente|regalo|presentear)\b/i', $t)) {
            $i->ocasiao = 'presente';
        } elseif (preg_match('/\b(festa|cumple|anivers[aá]rio)\b/i', $t)) {
            $i->ocasiao = 'festa';
        } elseif (preg_match('/\b(churrasco|asado)\b/i', $t)) {
            $i->ocasiao = 'churrasco';
        } elseif (preg_match('/\b(jantar|cena)\b/i', $t)) {
            $i->ocasiao = 'jantar';
        }

        // ===============================
        // 💲 PREÇO
        // ===============================
        [$i->precoMin, $i->precoMax] = self::extrairFaixaPreco($t);

        // ===============================
        // 🧴 VOLUME
        // ===============================
        [$i->minMl, $i->maxMl] = self::extrairFaixaVolumeMl($t);

        // ==================================================
        // ♻️ HERANÇA DE CONTEXTO (PREÇO SEM CATEGORIA)
        // ==================================================
        if (
            ($i->precoMin !== null || $i->precoMax !== null)
            && $i->categoria === null
        ) {
            $contexto = MemoriaContextualCurta::recuperar();

            if (!empty($contexto['categoria'])) {
                $i->categoria = $contexto['categoria'];

                SommelierLog::info(
                    "♻️ [NLP] Categoria herdada do contexto",
                    ['categoria' => $i->categoria]
                );
            }
        }

        // ==================================================
        // 🧠 PRODUTO DIRETO (SÓ SE PERGUNTA EXPLÍCITA)
        // ==================================================
        if ($i->perguntaEspecifica === 'procedencia') {
            $produto = Buscador::buscarProdutoPorTexto($textoOriginal);
            if ($produto) {
                $i->produtoDetectado = [
                    'id'          => $produto['id'],
                    'nome_limpo'  => $produto['nome_limpo'],
                    'pais_origem' => $produto['pais_origem'] ?? null,
                ];
            }
        }

        return $i;
    }

    /**
     * --------------------------------------------------
     * 🎯 Filtro básico (compatibilidade antiga)
     * --------------------------------------------------
     */
    public function temFiltro(): bool
    {
        return (bool) (
            $this->categoria ||
            $this->sensorial ||
            $this->ocasiao ||
            $this->precoMin !== null ||
            $this->precoMax !== null ||
            $this->minMl !== null ||
            $this->maxMl !== null
        );
    }

    /**
     * --------------------------------------------------
     * 🎯 Filtro SUFICIENTE para RECOMENDAR
     * --------------------------------------------------
     * Evita chutes e força interação humana
     */
    public function temFiltroSuficiente(): bool
    {
        // precisa ter categoria
        if (!$this->categoria) {
            return false;
        }

        // categoria + qualquer refinamento
        if (
            $this->sensorial ||
            $this->ocasiao ||
            $this->precoMin !== null ||
            $this->precoMax !== null ||
            $this->minMl !== null ||
            $this->maxMl !== null
        ) {
            return true;
        }

        return false;
    }

    // ==================================================
    // 🔧 HELPERS
    // ==================================================

    protected static function ehPerguntaAbstrata(string $t): bool
    {
        $gatilhosFortes = [
            '/\b(qual|cu[aá]l)\s+o\s+melhor\b/i',
            '/\b(quem|qu[ií]en)\s+(criou|inventou)\b/i',
            '/\b(hist[oó]ria|origem\s+do)\b/i',
            '/\b(explica|explique|me\s+conta)\b/i',
            '/\b(processo\s+de\s+fabric)/i',
        ];

        foreach ($gatilhosFortes as $rx) {
            if (preg_match($rx, $t)) {
                return true;
            }
        }

        if (
            str_contains($t, 'como') &&
            (
                str_contains($t, 'feito') ||
                str_contains($t, 'fabric') ||
                str_contains($t, 'produz') ||
                str_contains($t, 'funcion') ||
                str_contains($t, 'process')
            )
        ) {
            return true;
        }

        return false;
    }

    protected static function normalizarSTT(string $t): string
    {
        $map = [
            'mais de' => 'acima de',
            'a mais de' => 'acima de',
            'por menos de' => 'menos de',
            'menos do que' => 'menos de',
            'us$' => 'dólares',
            'u$s' => 'dólares',
        ];

        $t = str_replace(array_keys($map), array_values($map), $t);
        $t = preg_replace('/[^\p{L}\p{N}\s\.,\$]/u', ' ', $t);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    protected static function extrairFaixaPreco(string $t): array
    {
        $min = $max = null;

        if (preg_match('/entre\s+(\d+(?:[.,]\d+)?)\s*(e|a)\s*(\d+(?:[.,]\d+)?)/i', $t, $m)) {
            return [self::toFloat($m[1]), self::toFloat($m[3])];
        }

        if (preg_match('/(até|menos de)\s*(\d+(?:[.,]\d+)?)/i', $t, $m)) {
            $max = self::toFloat($m[2]);
        }

        if (preg_match('/(acima de|mais de)\s*(\d+(?:[.,]\d+)?)/i', $t, $m)) {
            $min = self::toFloat($m[2]);
        }

        return [$min, $max];
    }

    protected static function extrairFaixaVolumeMl(string $t): array
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*l/i', $t, $m)) {
            return [(int) (self::toFloat($m[1]) * 1000), null];
        }

        if (preg_match('/(\d+)\s*ml/i', $t, $m)) {
            return [(int) $m[1], null];
        }

        return [null, null];
    }

    protected static function toFloat(string $n): float
    {
        if (str_contains($n, ',') && str_contains($n, '.')) {
            $n = str_replace('.', '', $n);
            $n = str_replace(',', '.', $n);
        } elseif (str_contains($n, ',')) {
            $n = str_replace(',', '.', $n);
        }

        return (float) $n;
    }
}
