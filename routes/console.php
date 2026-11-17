<?php

use Illuminate\Support\Facades\Schedule;

// Runs from the worker container locally and from cron (schedule:run) on Hostinger.
Schedule::command('flights:mark-departed')->dailyAt('00:05')->withoutOverlapping();

// Prune old activity-log rows (older than config('activitylog.delete_records_older_than_days')).
Schedule::command('activitylog:clean --force')->weekly();

// Shared hosting has no long-running queue worker: drain the queue (emails) every minute.
// Locally the `worker` container runs queue:work instead.
if (app()->environment('production')) {
    Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
        ->everyMinute()
        ->withoutOverlapping(5);
}
