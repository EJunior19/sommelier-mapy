<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SommelierImportarMarcas extends Command
{
    protected $signature = 'sommelier:importar-marcas 
                            {arquivo=database/updates/marcas_bebidas_2025-10-28.txt : Caminho do arquivo TXT de marcas} 
                            {--forcar : Atualiza mesmo bebidas que já têm marca}';

    protected $description = 'Importa e associa marcas às bebidas existentes com base no nome da bebida.';

    public function handle(): int
    {
        $arquivo = base_path($this->argument('arquivo'));

        if (!file_exists($arquivo)) {
            $this->error("❌ Arquivo não encontrado: {$arquivo}");
            return self::FAILURE;
        }

        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $marcaAtual = null;
        $atualizados = 0;
        $ignorados = 0;

        $this->info("📄 Lendo arquivo de marcas: " . basename($arquivo));

        foreach ($linhas as $linha) {
            $linha = trim($linha);

            // Detecta linha de marca
            if (preg_match('/^Marca:\s*(.+)/i', $linha, $m)) {
                $marcaAtual = trim($m[1]);
                continue;
            }

            // Ignora linhas sem marca ativa
            if (!$marcaAtual || $linha === '') continue;

            // Captura nome da bebida na linha (ignorando códigos/lotes/etc)
            $cols = preg_split('/\s{2,}/', $linha);
            $nomeBebida = trim($cols[1] ?? $cols[0] ?? '');

            if ($nomeBebida === '' || strlen($nomeBebida) < 3) continue;

            // Corrige encoding
            $nomeBebida = iconv('Windows-1252', 'UTF-8//IGNORE', $nomeBebida);
            $marcaLimpa = preg_replace('/\(.+\)/', '', $marcaAtual);
            $marcaLimpa = trim($marcaLimpa);

            // Verifica bebida existente
            $bebida = DB::table('bebidas')
                ->where('nombre', 'ILIKE', "%{$nomeBebida}%")
                ->first();

            if (!$bebida) {
                $ignorados++;
                continue;
            }

            // Atualiza apenas se não tiver marca, ou se forçar
            if (empty($bebida->marca) || $this->option('forcar')) {
                DB::table('bebidas')->where('id', $bebida->id)->update([
                    'marca' => $marcaLimpa,
                    'updated_at' => now(),
                ]);
                $atualizados++;
            } else {
                $ignorados++;
            }
        }

        $this->info("✅ Atualização concluída!");
        $this->line("🟢 {$atualizados} bebidas atualizadas com nova marca.");
        $this->line("🟡 {$ignorados} ignoradas (já tinham marca ou não encontradas).");

        return self::SUCCESS;
    }
}
