<?php

namespace App\Services\Sommelier\Rules;

use App\Services\Sommelier\NLP\Intencoes;
use App\Helpers\SommelierLog;

class RegraEventoMacro
{
    public static function aplicar(string $mensagem, Intencoes $int): void
    {
        $msg = mb_strtolower($mensagem, 'UTF-8');

        // ==================================================
        // 🎉 MAPA DE EVENTOS (ANO TODO)
        // ==================================================
        $eventos = [
            'reveillon'        => ['réveillon', 'reveillon', 'ano novo', 'virada do ano'],
            'natal'            => ['natal', 'ceia de natal'],
            'aniversario'      => ['aniversário', 'aniversario', 'niver'],
            'casamento'        => ['casamento', 'boda', 'bodas'],
            'formatura'        => ['formatura', 'colação', 'graduacao', 'graduação'],
            'confraternizacao' => ['confraternização', 'confraternizacao', 'empresa', 'fim de ano da empresa'],
            'churrasco'        => ['churrasco', 'assado', 'parrilla'],
            'jantar'           => ['jantar', 'janta', 'ceia'],
            'almoco'           => ['almoço', 'almoco'],
            'evento'           => ['evento', 'festa', 'comemoração', 'celebração'],
        ];

        $eventoDetectado = null;

        foreach ($eventos as $tipo => $palavras) {
            foreach ($palavras as $p) {
                if (str_contains($msg, $p)) {
                    $eventoDetectado = $tipo;
                    break 2;
                }
            }
        }

        if (!$eventoDetectado) {
            return;
        }

        // ==================================================
        // 🔥 RESET CONTROLADO DE CONTEXTO
        // ==================================================
        // Evento sempre tem prioridade sobre categoria herdada
        $int->categoria = null;

        // Marca ocasião macro
        $int->ocasiao = $eventoDetectado;

        // ==================================================
        // 🧠 AJUSTES INTELIGENTES POR TIPO DE EVENTO
        // ==================================================

        // Eventos grandes → normalmente múltiplas bebidas
        if (in_array($eventoDetectado, [
            'reveillon',
            'confraternizacao',
            'evento',
            'casamento',
            'formatura'
        ])) {
            $int->perfilEvento = 'grande';
        }

        // Eventos sociais médios
        if (in_array($eventoDetectado, [
            'aniversario',
            'churrasco'
        ])) {
            $int->perfilEvento = 'medio';
        }

        // Eventos mais elegantes
        if (in_array($eventoDetectado, [
            'jantar',
            'casamento',
            'natal'
        ])) {
            $int->perfilEvento = 'elegante';
        }

        // ==================================================
        // 📅 HORÁRIO (opcional, ajuda muito)
        // ==================================================
        if (str_contains($msg, 'noite') || str_contains($msg, 'jantar')) {
            $int->horario = 'noite';
        } elseif (str_contains($msg, 'almoço') || str_contains($msg, 'almoco')) {
            $int->horario = 'dia';
        }

        // ==================================================
        // 📋 LOG
        // ==================================================
        SommelierLog::info("🎉 [RegraEventoMacro] Evento macro detectado", [
            'evento'       => $eventoDetectado,
            'perfilEvento' => $int->perfilEvento ?? null,
            'horario'      => $int->horario ?? null,
        ]);
    }
}
