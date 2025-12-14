<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Sommelier\Brain\SommelierBrain;
use App\Services\Sommelier\AI\OpenAISommelier;
use App\Helpers\SommelierLog;
use Throwable;

class AsistenteController extends Controller
{
    /**
     * ==========================================================
     * 🎯 ROTA PRINCIPAL DO SOMMELIER
     * ==========================================================
     */
    public function responder(
        Request $request,
        SommelierBrain $sommelier,
        OpenAISommelier $ai
    ) {
        SommelierLog::info("📥 Nova requisição recebida no AsistenteController");

        /**
         * ==========================================================
         * 🎤 1) FLUXO DE ÁUDIO
         * ==========================================================
         */
        if ($request->hasFile('audio')) {

            $file = $request->file('audio');

            SommelierLog::info("🔊 Áudio recebido", [
                'nome'    => $file->getClientOriginalName(),
                'ext'     => $file->getClientOriginalExtension(),
                'mime'    => $file->getMimeType(),
                'tamanho' => $file->getSize(),
            ]);

            $destino = storage_path('app/audios_temp');

            if (!is_dir($destino)) {
                mkdir($destino, 0777, true);
            }

            $filename = uniqid('audio_') . '.' . $file->getClientOriginalExtension();
            $fullPath = $destino . '/' . $filename;

            $file->move($destino, $filename);

            if (!file_exists($fullPath)) {
                SommelierLog::error("❌ Falha ao salvar áudio");
                return response()->json(['erro' => 'Falha ao salvar áudio'], 500);
            }

            // 🎧 Áudio → Texto
            SommelierLog::info("🎧 Iniciando transcrição");

            try {
                $mensagem = $ai->audioParaTexto($fullPath)
                    ?? "Não consegui entender o áudio.";
            } catch (Throwable $e) {
                SommelierLog::error("❌ Erro na transcrição de áudio", [
                    'erro' => $e->getMessage()
                ]);
                $mensagem = "Não consegui entender o áudio.";
            }

            SommelierLog::info("📝 Texto transcrito: {$mensagem}");

            // 🧠 Processar resposta
            try {
                $respostaTexto = $sommelier->responder($mensagem);
            } catch (Throwable $e) {
                SommelierLog::error("❌ Erro no SommelierBrain", [
                    'erro' => $e->getMessage()
                ]);
                $respostaTexto = "Desculpe, ocorreu um erro interno. Pode repetir?";
            }

            // 🔊 Texto → Áudio (BLINDADO)
            try {
                SommelierLog::info("🔊 Gerando áudio da resposta");
                $audioUrl = $ai->gerarAudio($respostaTexto);
            } catch (Throwable $e) {
                SommelierLog::error("❌ Erro ao gerar áudio TTS", [
                    'erro' => $e->getMessage()
                ]);
                $audioUrl = null;
            }

            return response()->json([
                'texto'     => $mensagem,
                'resposta'  => $respostaTexto,
                'audio_url' => $audioUrl,
                'modo'      => 'voz',
            ]);
        }

        /**
         * ==========================================================
         * ⌨️ 2) FLUXO DE TEXTO
         * ==========================================================
         */
        $mensagem = trim($request->input('mensagem', ''));

        SommelierLog::info("💬 Texto recebido: {$mensagem}");

        if ($mensagem === '') {
            return response()->json([
                'resposta' => 'Pode me dizer o que você procura? 🍷',
                'audio_url' => null,
                'modo' => 'texto'
            ]);
        }

        try {
            $respostaTexto = $sommelier->responder($mensagem);
        } catch (Throwable $e) {
            SommelierLog::error("❌ Erro no SommelierBrain (texto)", [
                'erro' => $e->getMessage()
            ]);
            $respostaTexto = "Desculpe, houve um problema. Pode tentar novamente?";
        }

        // 🔊 TTS opcional e seguro
        try {
            SommelierLog::info("🔊 Gerando áudio da resposta (texto)");
            $audioUrl = $ai->gerarAudio($respostaTexto);
        } catch (Throwable $e) {
            SommelierLog::error("❌ Erro ao gerar áudio TTS (texto)", [
                'erro' => $e->getMessage()
            ]);
            $audioUrl = null;
        }

        return response()->json([
            'resposta'  => $respostaTexto,
            'audio_url' => $audioUrl,
            'modo'      => 'texto',
        ]);
    }
}
