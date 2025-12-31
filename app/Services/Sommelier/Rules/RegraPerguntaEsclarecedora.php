<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\NLP\Intencoes;

class RegraPerguntaEsclarecedora
{
    /**
     * --------------------------------------------------
     * ❓ Decide se precisa perguntar algo ao cliente
     * --------------------------------------------------
     * Retorna:
     * - string → pergunta humana
     * - null   → já dá pra recomendar
     */
    public static function perguntar(Intencoes $int): ?string
    {
        /**
         * ==================================================
         * 1️⃣ NÃO PERGUNTA SE É PERGUNTA CONCEITUAL
         * ==================================================
         */
        if ($int->perguntaEspecifica === 'abstrata') {
            return null;
        }

        /**
         * ==================================================
         * 2️⃣ FALTA CATEGORIA (BASE DE TUDO)
         * ==================================================
         */
        if (!$int->categoria) {

            // já tem ocasião → pergunta direcionada
            if ($int->ocasiao) {
                return self::perguntaCategoriaPorOcasiao($int->ocasiao);
            }

            return "Para te indicar melhor 😊 você prefere vinho, cerveja ou destilado?";
        }

        /**
         * ==================================================
         * 3️⃣ OCASIÃO DEFINIDA, MAS FALTA DETALHE
         * ==================================================
         */
        if ($int->ocasiao) {

            // jantar / refeição
            if (in_array($int->ocasiao, ['jantar', 'acompanhar_refeicao'])) {
                if (!$int->sensorial) {
                    return "Vai ser uma refeição mais leve ou algo mais encorpado?";
                }
            }

            // churrasco
            if ($int->ocasiao === 'churrasco') {
                if (!$int->sensorial) {
                    return "No churrasco você prefere algo mais forte ou mais fácil de beber?";
                }
            }

            // presente
            if ($int->ocasiao === 'presente') {
                if (!$int->precoMin && !$int->precoMax) {
                    return "É para algo mais simples ou um presente mais especial?";
                }
            }
        }

        /**
         * ==================================================
         * 4️⃣ CATEGORIA DEFINIDA, MAS MUITO GENÉRICA
         * ==================================================
         */
        if ($int->categoria && !$int->sensorial && !$int->ocasiao) {

            switch ($int->categoria) {
                case 'VINOS':
                    return "Prefere um vinho mais leve ou mais encorpado?";
                case 'CERVEZA':
                    return "Você gosta mais de cervejas leves ou mais intensas?";
                case 'WHISKY':
                    return "Prefere algo mais suave ou mais marcante?";
            }
        }

        /**
         * ==================================================
         * 5️⃣ FALTA FAIXA DE PREÇO (REFINO FINAL)
         * ==================================================
         */
        if (
            !$int->precoMin &&
            !$int->precoMax &&
            $int->categoria
        ) {
            return "Quer algo mais em conta ou uma opção mais especial?";
        }

        /**
         * ==================================================
         * 6️⃣ JÁ TEM TUDO NECESSÁRIO
         * ==================================================
         */
        return null;
    }

    /**
     * --------------------------------------------------
     * 🎯 Pergunta de categoria baseada na ocasião
     * --------------------------------------------------
     */
    protected static function perguntaCategoriaPorOcasiao(string $ocasiao): string
    {
        return match ($ocasiao) {
            'jantar', 'acompanhar_refeicao' =>
                "Para esse jantar 😊 você prefere vinho, cerveja ou espumante?",

            'churrasco' =>
                "Para o churrasco 🔥 prefere cerveja, vinho ou algo mais forte?",

            'presente' =>
                "É para presentear 🎁 prefere vinho, espumante ou destilado?",

            default =>
                "Que tipo de bebida você prefere?",
        };
    }
}
