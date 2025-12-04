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
        // ---------------------------------------------
        // ❗ FILTRO DE PERGUNTAS CONCEITUAIS
        // Apenas explicações → NÃO usa banco/TRGM
        // ---------------------------------------------
        $textoLower = mb_strtolower($mensagem, 'UTF-8');

        $conceituais = [
            // Conceitos diretos
            'o que e', 'o que é',
            'como funciona',
            'como se faz',
            'para que serve',
            'qual a diferenca', 'qual a diferença',
            'diferenca entre', 'diferença entre',
            'defina', 'definição',
            'explique', 'explica',

            // Perguntas de uso e comportamento
            'posso tomar sozinho',
            'pode tomar sozinho',
            'fica bom sozinho',
            'é bom sozinho',
            'combina com',
            'vai bem com',
            'devo servir',
            'como servir',
            'como tomar',
            'como beber',
            'misturar com',
            'posso misturar',
            'mistura com',
            'acompanha',
            'harmoniza',
            'combinação',
            'combina com',

            // Perguntas sobre intensidade
            'é forte',
            'é leve',
            'é doce',
            'é seco',

            // Perguntas gerais de recomendação não ligadas ao banco
            'para relaxar',
            'para jantar',
            'para almoço',
            'pra almocar',
            'pra jantar',
        ];

        // se for pergunta conceitual → resposta direto pela IA
        foreach ($conceituais as $padrao) {
            if (str_contains($textoLower, $padrao)) {

                info("🧠 Pergunta conceitual detectada → enviando direto para IA");

                // IA gera texto direto (sem banco)
                $respostaTexto = $openai->gerarTexto(
                    "Responda como Sommelier Mapy: profissional, educado e simples.\nPergunta do cliente: {$mensagem}\nExplique de forma breve, clara e amigável."
                );

                // gera áudio normalmente
                $audioUrl = $openai->gerarAudio($respostaTexto);

                return response()->json([
                    'resposta'  => $respostaTexto,
                    'audio_url' => $audioUrl,
                    'modo'      => 'texto',
                ]);
            }
        }

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
