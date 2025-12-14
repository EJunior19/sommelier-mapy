<?php

namespace App\Services\Sommelier\Presentation;

class Emojis
{
    /**
     * --------------------------------------------------
     * 🍷 Emoji por TIPO de bebida
     * --------------------------------------------------
     */
    public static function tipo(?string $tipo): string
    {
        $t = mb_strtoupper((string) $tipo, 'UTF-8');

        return match (true) {
            str_contains($t, 'VIN')      => '🍷',
            str_contains($t, 'WHI')      => '🥃',
            str_contains($t, 'CERV')     => '🍺',
            str_contains($t, 'ESPUM')    => '🍾',
            str_contains($t, 'CHAMP')    => '🍾',
            str_contains($t, 'LICOR')    => '🍸',
            str_contains($t, 'VODKA')    => '🍸',
            str_contains($t, 'GIN')      => '🍸',
            str_contains($t, 'RUM')      => '🥃',
            str_contains($t, 'TEQUILA')  => '🥃',
            default                      => '🍹',
        };
    }

    /**
     * --------------------------------------------------
     * 👅 Emoji SENSORIAL
     * --------------------------------------------------
     */
    public static function sensorial(?string $sensorial): string
    {
        return match ($sensorial) {
            'doce'   => '🍯',
            'seco'   => '🌵',
            'leve'   => '🌿',
            'forte'  => '🔥',
            default  => '',
        };
    }

    /**
     * --------------------------------------------------
     * 🎉 Emoji por OCASIÃO
     * --------------------------------------------------
     */
    public static function ocasiao(?string $ocasiao): string
    {
        return match ($ocasiao) {
            'presente'   => '🎁',
            'festa'      => '🎉',
            'churrasco'  => '🔥',
            'jantar'     => '🍽️',
            default      => '',
        };
    }

    /**
     * --------------------------------------------------
     * 😊 Emoji por EMOÇÃO do cliente
     * --------------------------------------------------
     * Analisa o texto original
     */
    public static function emocao(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');

        if (preg_match('/\b(oi|olá|hola|bom dia|boa tarde|boa noite)\b/i', $t)) {
            return '😊';
        }

        if (preg_match('/\b(barato|preço|precio|quanto)\b/i', $t)) {
            return '💲';
        }

        if (preg_match('/\b(doce|suave|leve)\b/i', $t)) {
            return '😌';
        }

        if (preg_match('/\b(forte|pesado|encorpado)\b/i', $t)) {
            return '😎';
        }

        if (preg_match('/\b(qual|recomenda|sugere)\b/i', $t)) {
            return '🤔';
        }

        return '👉';
    }

    /**
     * --------------------------------------------------
     * ✨ Combinação inteligente (opcional)
     * --------------------------------------------------
     */
    public static function combo(
        ?string $tipo,
        ?string $sensorial = null,
        ?string $ocasiao = null
    ): string {
        return trim(
            self::tipo($tipo) . ' ' .
            self::sensorial($sensorial) . ' ' .
            self::ocasiao($ocasiao)
        );
    }
}
