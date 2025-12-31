<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\Memory\MemoriaContextualCurta;
use App\Helpers\SommelierLog;

class RegraQuantidadeEvento
{
    /* --------------------------------------------------
     * 🔎 Detecta quantidade de pessoas
     * -------------------------------------------------- */
    public static function match(string $mensagem): ?int
    {
        if (preg_match('/\b(\d{1,3})\s*(pessoas|personas|pessoa|pesoas)\b/i', $mensagem, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /* --------------------------------------------------
     * 🧠 Detecta categoria no TEXTO (prioridade máxima)
     * -------------------------------------------------- */
    protected static function detectarCategoriaTexto(string $mensagem): ?string
    {
        $msg = mb_strtolower($mensagem);

        return match (true) {
            preg_match('/\b(cerveja|cervejas)\b/', $msg)        => 'CERVEZA',
            preg_match('/\b(vinho|vinhos|vino)\b/', $msg)      => 'VINOS',
            preg_match('/\b(espumante|champagne)\b/', $msg)    => 'ESPUMANTES',
            preg_match('/\b(whisky|whiskys|u[ií]sque)\b/', $msg)=> 'WHISKY',
            preg_match('/\b(licor|licores)\b/', $msg)          => 'LICORES',
            default => null,
        };
    }

    /* --------------------------------------------------
     * 🧮 Resposta principal
     * -------------------------------------------------- */
    public static function responder(int $pessoas, string $mensagem = ''): string
    {
        $ctx = MemoriaContextualCurta::recuperar();

        $categoria = self::detectarCategoriaTexto($mensagem)
            ?? ($ctx['categoria'] ?? null);

        SommelierLog::info("🧮 [RegraQuantidadeEvento]", [
            'pessoas' => $pessoas,
            'categoria' => $categoria
        ]);

        return match ($categoria) {
            'VINOS'       => self::vinhos($pessoas),
            'CERVEZA'     => self::cerveja($pessoas),
            'ESPUMANTES'  => self::espumantes($pessoas),
            'WHISKY'      => self::whisky($pessoas),
            'LICORES'     => self::licores($pessoas),
            default       => self::generico($pessoas),
        };
    }

    /* --------------------------------------------------
     * 🍷 VINHO
     * -------------------------------------------------- */
    protected static function vinhos(int $p): string
    {
        $garrafas = ceil($p * 0.8);
        return "Para {$p} pessoas, recomendo cerca de {$garrafas} garrafas de vinho 🍷";
    }

    /* --------------------------------------------------
     * 🍺 CERVEJA
     * -------------------------------------------------- */
    protected static function cerveja(int $p): string
    {
        $litros = ceil($p * 1.5);
        return "Para {$p} pessoas, calcule aproximadamente {$litros} litros de cerveja 🍺";
    }

    /* --------------------------------------------------
     * 🥂 ESPUMANTE
     * -------------------------------------------------- */
    protected static function espumantes(int $p): string
    {
        $garrafas = ceil($p / 6);
        return "Para {$p} pessoas, cerca de {$garrafas} garrafas de espumante 🥂 são ideais";
    }

    /* --------------------------------------------------
     * 🥃 WHISKY
     * -------------------------------------------------- */
    protected static function whisky(int $p): string
    {
        $garrafas = max(1, ceil($p / 10));
        return "Para {$p} pessoas, normalmente {$garrafas} garrafa(s) de whisky 🥃 são suficientes";
    }

    /* --------------------------------------------------
     * 🍸 LICORES (acompanhamento)
     * -------------------------------------------------- */
    protected static function licores(int $p): string
    {
        $garrafas = max(1, ceil($p / 10));
        return "Para {$p} pessoas, 1 a {$garrafas} garrafas de licor 🍸 são suficientes, pois é uma bebida de acompanhamento";
    }

    /* --------------------------------------------------
     * 🤝 FALLBACK HUMANO
     * -------------------------------------------------- */
    protected static function generico(int $p): string
    {
        return "Para {$p} pessoas, posso calcular certinho 🙂 Você prefere vinho, cerveja, whisky, espumante ou licor?";
    }
}
