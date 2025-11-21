<?php

namespace App\Services\Sommelier;

class Emojis
{
    /**
     * Emoji baseado no tipo da bebida (somente 1 emoji)
     */
    public static function tipo(string $tipo): string
    {
        $t = strtoupper($tipo);

        return match (true) {
            str_contains($t, 'VIN')       => '🍷',
            str_contains($t, 'WHI')       => '🥃',
            str_contains($t, 'CERV')      => '🍺',
            str_contains($t, 'GIN')       => '🍸',
            str_contains($t, 'VODKA')     => '🥂',
            str_contains($t, 'LICOR')     => '🍮',
            str_contains($t, 'ESPUM')     => '🍾',
            str_contains($t, 'CHAMP')     => '🥂',
            str_contains($t, 'CACHA')     => '🧉',
            str_contains($t, 'RON')       => '🍹',
            str_contains($t, 'TEQUILA')   => '🌵',
            str_contains($t, 'ENERG')     => '⚡',
            str_contains($t, 'AGUA')      => '💧',
            default                       => '🍸',
        };
    }

    /**
     * Emoji baseado na emoção / intenção do cliente (somente 1 emoji)
     */
    public static function emocao(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');

        // Sensações
        if (str_contains($t, 'forte'))       return '🔥';
        if (str_contains($t, 'doce'))        return '🍯';
        if (str_contains($t, 'suave'))       return '🌙';
        if (str_contains($t, 'leve'))        return '😌';

        // Qualidade / premium
        if (str_contains($t, 'premium'))     return '💎';
        if (str_contains($t, 'especial'))    return '✨';

        // Ocasiões
        if (str_contains($t, 'churrasco'))   return '🥩';
        if (str_contains($t, 'festa'))       return '🎉';
        if (str_contains($t, 'presente'))    return '🎁';
        if (str_contains($t, 'romant'))      return '❤️';
        if (str_contains($t, 'amizade'))     return '🤝';
        if (str_contains($t, 'relaxar'))     return '😌';

        // Emoção por preço
        if (preg_match('/(acima|mais de|maior que)\s+(\d+)/', $t, $m)) {
            $valor = (int)$m[2];

            if ($valor >= 150) return '💎';
            if ($valor >= 80)  return '💰';
            if ($valor >= 40)  return '👌';
        }

        // Dúvida
        if (str_contains($t, 'qual') || str_contains($t, 'melhor') || str_contains($t, 'não sei')) {
            return '🤔';
        }

        // Urgência
        if (str_contains($t, 'rápido') || str_contains($t, 'urgente')) {
            return '⚡';
        }

        // Padrão
        return '🙂';
    }
}
