<?php

namespace App\Services\Sommelier\AI;

use Illuminate\Support\Facades\Http;
use App\Helpers\SommelierLog;

class OpenAIClient
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
    }

    /**
     * ---------------------------------------------
     * 🧠 Texto (Chat Completion)
     * ---------------------------------------------
     */
    public function chat(string $prompt): ?string
    {
        SommelierLog::info("🤖 [OpenAIClient] Chat request");

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é o Sommelier Mapy.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.4,
            ]);

        if (!$response->successful()) {
            SommelierLog::error("❌ OpenAI chat erro", $response->json());
            return null;
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * ---------------------------------------------
     * 🔊 Texto → Áudio (TTS)
     * ---------------------------------------------
     */
    public function textToSpeech(string $texto): ?string
    {
        SommelierLog::info("🔊 [OpenAIClient] TTS iniciado");

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/audio/speech", [
                'model' => 'gpt-4o-mini-tts',
                'voice' => 'alloy',
                'input' => $texto,
            ]);

        if (!$response->successful()) {
            SommelierLog::error("❌ OpenAI TTS erro", $response->json());
            return null;
        }

        $file = 'sommelier_' . uniqid() . '.mp3';
        $path = storage_path("app/public/{$file}");

        file_put_contents($path, $response->body());

        return asset("storage/{$file}");
    }

    /**
     * ---------------------------------------------
     * 🎧 Áudio → Texto (Whisper)
     * ---------------------------------------------
     */
    public function speechToText(string $filePath): ?string
    {
        SommelierLog::info("🎧 [OpenAIClient] STT iniciado");

        $response = Http::withToken($this->apiKey)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post("{$this->baseUrl}/audio/transcriptions", [
                'model' => 'whisper-1',
            ]);

        if (!$response->successful()) {
            SommelierLog::error("❌ OpenAI STT erro", $response->json());
            return null;
        }

        return $response->json('text');
    }
}
