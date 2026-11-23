<?php

namespace App\Console\Commands;

use App\Services\Sync\PageSummarySyncService;
use App\Services\Sync\PegawaiSyncService;
use App\Services\Sync\PullStatusReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncPageCommand extends Command
{
    protected $signature = 'sync:page {page} {--semester=} {--key=}';

    protected $description = 'Perbarui sumber halaman melalui satu proses latar';

    public function handle(PageSummarySyncService $summaries, PegawaiSyncService $pegawai): int
    {
        $semester = (string) $this->option('semester');
        $key = (string) ($this->option('key') ?: 'page_akademik_sync');
        if ($this->argument('page') !== 'akademik' || !preg_match('/^\d{4}[123]$/', $semester)) {
            PullStatusReporter::laporkan($key, 'gagal', 'Halaman atau semester tidak valid.');
            return self::FAILURE;
        }
        $lock = Cache::lock('sync:page:akademik:'.$semester, 1800);
        if (!$lock->get()) {
            return self::SUCCESS;
        }
        $errors = [];
        $count = 0;
        try {
            PullStatusReporter::laporkan($key, 'jalan', 'Memperbarui ringkasan mahasiswa.');
            try {
                foreach (['aktif', 'lulus'] as $jenis) {
                    $count += $summaries->mahasiswa($semester, $jenis);
                }
            } catch (\Throwable $e) {
                $errors[] = str_contains($e->getMessage(), 'HTTP 403')
                    ? 'SIAKANG menolak akses (403). Data tersimpan tetap ditampilkan.'
                    : 'Ringkasan SIAKANG belum dapat diperbarui. Data tersimpan tetap ditampilkan.';
            }
            $jalurUpdated = DB::table('mahasiswa_jalur_summaries')->where('angkatan', now()->year - 1)
                ->where('cakupan_lengkap', true)->min('source_synced_at');
            if ($errors === [] && (!$jalurUpdated || \Illuminate\Support\Carbon::parse($jalurUpdated)->lt(now()->subDay()))) {
                PullStatusReporter::laporkan($key, 'jalan', 'Memperbarui jalur masuk mahasiswa.');
                if ($this->callSilent('sync:summaries', ['domain' => 'jalur', '--angkatan' => (string) (now()->year - 1), '--key' => $key.'_jalur']) !== self::SUCCESS) {
                    $errors[] = 'Jalur masuk belum dapat diperbarui. Data tersimpan tetap ditampilkan.';
                }
            }
            $freshUntil = now()->subMinutes((int) config('satudata.swr.fresh_minutes', 20));
            $updated = DB::table('pegawai_summaries')->min('source_synced_at');
            if (!$updated || \Illuminate\Support\Carbon::parse($updated)->lt($freshUntil)) {
                PullStatusReporter::laporkan($key, 'jalan', 'Memperbarui data pegawai.');
                try {
                    $result = $pegawai->sync();
                    if (!$result['status']) {
                        throw new \RuntimeException('SIMPEG belum dapat diperbarui.');
                    }
                    $count += (int) $result['received'];
                } catch (\Throwable $e) {
                    $errors[] = 'Data pegawai belum dapat diperbarui.';
                }
            }
            PullStatusReporter::laporkan($key, $errors === [] ? 'selesai' : 'gagal',
                $errors === [] ? 'Data halaman diperbarui.' : implode(' ', $errors), $count);
            return $errors === [] ? self::SUCCESS : self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
