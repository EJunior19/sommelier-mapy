<?php

namespace App\Services\Sommelier\AI;

use App\Helpers\SommelierLog;

class OpenAISommelier
{
    protected OpenAIClient $client;

    public function __construct(OpenAIClient $client)
    {
        $this->client = $client;
        SommelierLog::info("🤖 OpenAISommelier inicializado");
    }

    /**
     * ---------------------------------------------
     * 🧠 Texto (resposta do Sommelier)
     * ---------------------------------------------
     */
    public function responderSommelier(string $prompt): ?string
    {
        return $this->client->chat($prompt);
    }

    /**
     * ---------------------------------------------
     * 🔊 Texto → Áudio
     * ---------------------------------------------
     */
    public function gerarAudio(string $texto): ?string
    {
        return $this->client->textToSpeech($texto);
    }

    /**
     * ---------------------------------------------
     * 🎧 Áudio → Texto
     * ---------------------------------------------
     */
    public function audioParaTexto(string $filePath): ?string
    {
        return $this->client->speechToText($filePath);
    }
}
