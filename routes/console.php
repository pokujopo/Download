<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ai:automations')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('posts:dispatch-scheduled')
    ->everyMinute();

Schedule::command('smart-autocut:schedule-rotation')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('smart-autocut:dispatch-stories')
    ->everyMinute()
    ->withoutOverlapping();
