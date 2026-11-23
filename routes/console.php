<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Penarikan data terjadwal (SWR: database/cache dulu, API untuk revalidate)
|--------------------------------------------------------------------------
|
| Penarik berbasis Chromium lokal (Edge/Chrome di host aplikasi) karena
| panggilan HTTP langsung dari PHP selalu ditantang Cloudflare. Dengan jadwal
| ini, data tetap segar walau tidak ada yang membuka halaman.
|
| Aktifkan sekali di host: php artisan schedule:work (atau cron tiap menit
| menjalankan: php artisan schedule:run).
|
*/

$syncEvents = [];

$syncEvents['aset'] = Schedule::command('sync:aset --max-page=0')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->description('Tarik data aset BMN dari SIMANTAP');

// Penjadwalan SIAKANG per NIP dijalankan lewat skrip Chromium.
$syncEvents['penjadwalan'] = Schedule::exec(PHP_BINARY . ' ' . base_path('scripts/sync-penjadwalan.php') . ' --limit=25 --delay=4000')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->description('Tarik penjadwalan dosen dari SIAKANG');

$syncEvents['pegawai'] = Schedule::command('sync:pegawai')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->description('Tarik data pegawai dari SIMPEG');

$syncEvents['sinta'] = Schedule::command('sync:sinta')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->description('Tarik metrik riset UNTIRTA dari SINTA');


$syncEvents['mahasiswa'] = Schedule::command('sync:summaries mahasiswa')->dailyAt('00:30')->withoutOverlapping(360);
$syncEvents['lulusan'] = Schedule::command('sync:summaries lulusan')->dailyAt('01:00')->withoutOverlapping(360);
$syncEvents['mahasiswa-detail'] = Schedule::command('sync:summaries mahasiswa-detail')->dailyAt('00:00')->withoutOverlapping(360);
$syncEvents['perkuliahan'] = Schedule::command('sync:summaries perkuliahan')->dailyAt('04:30')->withoutOverlapping(360);
$syncEvents['dosen'] = Schedule::command('sync:summaries dosen')->dailyAt('05:00')->withoutOverlapping(360);
$syncEvents['jalur'] = Schedule::command('sync:summaries jalur --angkatan='.(now()->year - 1))->dailyAt('06:00')->withoutOverlapping(360);

foreach ($syncEvents as $source => $event) {
    $key = 'scheduled_sync.last_success.'.$source;
    $event->when(function () use ($key) {
        $lastSuccess = \Illuminate\Support\Facades\Cache::get($key);
        return !$lastSuccess || now()->greaterThanOrEqualTo(
            \Illuminate\Support\Carbon::parse($lastSuccess)->addDays((int) config('satudata.sync_interval_days'))
        );
    })->onSuccess(fn () => \Illuminate\Support\Facades\Cache::forever($key, now()->toDateTimeString()));
}
