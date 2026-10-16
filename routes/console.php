<?php

use Illuminate\Support\Facades\Schedule;

// History taking (FACDBTD006): simpan periode taking yang sudah ditutup.
// Butuh cron `* * * * * php artisan schedule:run` di server.
Schedule::command('facdbt:taking-history')->hourly()->withoutOverlapping();
