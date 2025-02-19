<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
  /**
   * Define the application"s command schedule.
   *
   * @param Schedule $schedule
   * @return void
   */
  protected function schedule(Schedule $schedule)
  {
    $schedule->command("minute:subscribe")->everyMinute()->withoutOverlapping();
    $schedule->command("minute:queue")->everyMinute()->withoutOverlapping();
    $schedule->command("minute:bill")->everyMinute()->withoutOverlapping();
    $schedule->command("minute:updateCookieDoge")->everyMinute()->withoutOverlapping();
    $schedule->command("minute:updateCookieBot")->everyMinute()->withoutOverlapping();
    $schedule->command("minute:price")->everyMinute();

    $schedule->command("daily:removeBill")->weekly()->withoutOverlapping();
    $schedule->command("daily:removeQueue")->weekly()->withoutOverlapping();
    $schedule->command("daily:removeUser")->daily()->withoutOverlapping();
  }

  /**
   * Register the commands for the application.
   *
   * @return void
   */
  protected function commands()
  {
    $this->load(__DIR__ . "/Commands");

    require base_path("routes/console.php");
  }
}
