<?php

use Illuminate\Support\Facades\Schedule;

// History taking (FACDBTD005): simpan periode taking yang sudah ditutup.
// Halaman History Taking juga menyimpan periode tertutup saat dibuka, jadi scheduler ini opsional
// (butuh cron `* * * * * php artisan schedule:run` di server).
Schedule::command('facdbt:taking-history')->hourly()->withoutOverlapping();
