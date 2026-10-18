<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();
Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
