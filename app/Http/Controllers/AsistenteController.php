<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Sommelier\SommelierBrain;
use App\Services\OpenAIService;
use Throwable;

class AsistenteController extends Controller
{
    public function responder(Request $request, SommelierBrain $sommelier, OpenAIService $openai)
    {
        info("📥 Nova requisição recebida no AssistenteController");

        // =============================================================
        // 🎤 1) FLUXO DE ÁUDIO — Cliente enviou áudio
        // =============================================================
        if ($request->hasFile('audio')) {

            $file = $request->file('audio');

            info("🔊 Áudio recebido:", [
                'nome'      => $file->getClientOriginalName(),
                'extensao'  => $file->getClientOriginalExtension(),
                'mime'      => $file->getMimeType(),
                'tamanho'   => $file->getSize(),
            ]);

            // Criar diretório temporário
            $destino = storage_path('app/audios_temp');
            if (!is_dir($destino)) {
                mkdir($destino, 0777, true);
            }

            // Nome único
            $filename = uniqid('audio_') . '.' . $file->getClientOriginalExtension();
            $fullPath = $destino . '/' . $filename;

            $file->move($destino, $filename);

            if (!file_exists($fullPath)) {
                info("❌ ERRO: áudio não salvo!");
                return response()->json([
                    'erro' => 'Falha ao salvar o áudio.',
                ], 500);
            }

            // 1) ÁUDIO → TEXTO
            info("🎧 Iniciando transcrição...");
            $mensagem = $openai->audioParaTexto($fullPath) ?? "Não consegui entender o áudio.";

            info("📝 Texto extraído do áudio: {$mensagem}");

            // 2) PROCESSAR RESPOSTA
            try {
                $respostaTexto = $sommelier->responder($mensagem);
            } catch (Throwable $e) {
                info("❌ Erro no SommelierBrain: {$e->getMessage()}");
                $respostaTexto = "Desculpe, não consegui entender. Pode repetir?";
            }

            // 3) TEXTO → ÁUDIO (TTS)
            info("🔊 Convertendo resposta em áudio...");
            $audioUrl = $openai->gerarAudio($respostaTexto);

            return response()->json([
                'texto'     => $mensagem,
                'resposta'  => $respostaTexto,
                'audio_url' => $audioUrl,
                'modo'      => 'voz',
            ]);
        }

        // =============================================================
        // ⌨️ 2) FLUXO DE TEXTO — Cliente digitou
        // =============================================================
        $mensagem = trim($request->input('mensagem', ''));

        info("💬 Texto recebido: {$mensagem}");

        try {
            $respostaTexto = $sommelier->responder($mensagem);
        } catch (Throwable $e) {
            info("❌ Erro no SommelierBrain (texto): {$e->getMessage()}");
            $respostaTexto = "Desculpe, houve um problema. Pode repetir?";
        }

        // 🔊 Converte a resposta em áudio
        $audioUrl = $openai->gerarAudio($respostaTexto);

        return response()->json([
            'resposta'  => $respostaTexto,
            'audio_url' => $audioUrl,
            'modo'      => 'texto',
        ]);
    }
}
