<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('campaigns:dispatch-due')->everyMinute()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('automations:run')->everyMinute()->withoutOverlapping();
Schedule::command('resellers:renew')->hourly()->withoutOverlapping();
