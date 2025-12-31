<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // 🧹 Limpieza diaria de audios
        $schedule->command('sommelier:limpar-audios')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->runInBackground();

        // 🚀 Pipeline completo del Sommelier
        $schedule->command('sommelier:sync-all')
            ->dailyAt('04:30')
            ->withoutOverlapping()
            ->runInBackground();
    }
}
