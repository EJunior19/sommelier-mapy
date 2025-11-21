<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Registrando comandos Artisan customizados
     */
    protected $commands = [
        \App\Console\Commands\TestOracleSpeed::class,
        \App\Console\Commands\LimparAudiosSommelier::class, // 👈 novo comando
    ];

    /**
     * Definir o schedule (tarefas agendadas)
     */
    protected function schedule(Schedule $schedule)
    {
        // Executa o teste de velocidade do Oracle diariamente às 02:00
        // (caso queira manter)
        // $schedule->command('oracle:test-speed')->dailyAt('02:00');

        // 🧹 Limpar áudios antigos do Sommelier todos os dias às 03:00
        $schedule->command('sommelier:limpar-audios')->dailyAt('03:00');
    }

    /**
     * Registrar os comandos console da aplicação
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
