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
        // $schedule->command('inspire')->hourly();
        $schedule->command('auth:clear-resets')->everyFifteenMinutes();

        $schedule->command('send:wishes')->daily();
        // $schedule->command('send:wishes')->everyMinute();

        // Remider Task

         $schedule->command('reminders:send')->everyMinute();
        // Recursion Task

        $schedule->command('recurring-tasks:generate')->daily()->at('00:00')->withoutOverlapping();

        // reset Database
        $schedule->command('demo:reset')->everyTwoHours();

        // Send Scheduled Emails
        $schedule->command('emails:send-scheduled')->everyMinute();
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
