<?php
putenv("SSL_CERT_FILE=C:\\php\\extras\\ssl\\cacert.pem");
putenv("CURL_CA_BUNDLE=C:\\php\\extras\\ssl\\cacert.pem");

$ctx = stream_context_create([
    "ssl" => [
        "verify_peer" => true,
        "verify_peer_name" => true,
        "allow_self_signed" => false,
        "cafile" => "C:\\php\\extras\\ssl\\cacert.pem",
    ]
]);

echo "\n🔍 Testando conexão HTTPS...\n\n";

$result = @file_get_contents("https://api.openai.com", false, $ctx);

if ($result === false) {
    echo "❌ Falhou\n";
    $error = error_get_last();
    print_r($error);
} else {
    echo "✅ SSL funcionou! Resposta:\n";
    echo $result;
}
