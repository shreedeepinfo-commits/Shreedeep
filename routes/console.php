<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('campaigns:dispatch-due')->everyMinute()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
