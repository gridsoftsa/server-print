<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // ✅ POLLING RÁPIDO: Cada segundo para máxima velocidad
        $schedule->command('db:check-table')
            ->everySecond()
            ->withoutOverlapping() // Evitar ejecuciones simultáneas
            ->runInBackground() // Ejecutar en background
            ->appendOutputTo(storage_path('logs/scheduler.log')); // Log de scheduler
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
