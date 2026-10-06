<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work database', [
    '--stop-when-empty' => true,
    '--max-time' => 50,
    '--tries' => 3,
])
    ->everyMinute()
    ->withoutOverlapping(2)
    ->onOneServer();

Schedule::command('queue:prune-failed', ['--hours' => 168])->weekly();

Schedule::command('vtu:reconcile-pending', ['--limit' => 50])->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();
Schedule::command('vtu:recover-bulk', ['--limit' => 50, '--stale-minutes' => 10])->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();

Schedule::command('vtu:recover-stale-initiations', ['--limit' => 50, '--stale-minutes' => 10])->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();

Schedule::command('cac:reconcile-pending', ['--limit' => 50, '--min-age' => 1])->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();

Schedule::command('payments:expire-pending', ['--limit' => 100])->everyMinute()->withoutOverlapping(2)->onOneServer();

Schedule::command('savings:process-maturity', ['--limit' => 100])->everyMinute()->withoutOverlapping(2)->onOneServer();

Schedule::command('loans:process-overdue', ['--limit'=>100])->everyMinute()->withoutOverlapping(2)->onOneServer();

Schedule::command('investments:process-maturity', ['--limit'=>100])->everyMinute()->withoutOverlapping(2)->onOneServer();
Schedule::command('sim-hosting:expire', ['--limit'=>100])->everyMinute()->withoutOverlapping(2)->onOneServer();
