<?php

namespace App\Console;

use App\Console\Commands\CleanSvgTemp;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        CleanSvgTemp::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Example: clean temp files daily at 2am
        $schedule->command('svg:clean-temp')->dailyAt('02:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // By default Laravel will load commands in app/Console/Commands
        $this->load(__DIR__.'/Commands');
    }
}
