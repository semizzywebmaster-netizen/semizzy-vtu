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
