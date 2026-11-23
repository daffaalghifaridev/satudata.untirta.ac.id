<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Penarikan data satu dosen (portofolio SIPP + penjadwalan SIAKANG) memakai Chromium lokal.
 *
 * Dipakai tombol "Tarik data" di halaman profil: halaman hanya mencatat status lalu
 * memantau, sedangkan proses penarikan yang memakan puluhan detik berjalan terpisah
 * lewat perintah ini. Token API tetap di .env server; pengguna tidak diminta apa pun.
 */
class SyncDosenCommand extends Command
{
    protected $signature = 'sync:dosen
                            {nip : NIP dosen (18 digit)}
                            {--semester= : Kode semester}
                            {--key= : Kunci cache status yang dipantau halaman profil}';

    private bool $scriptFailed = false;

    protected $description = 'Tarik portofolio SIPP dan penjadwalan SIAKANG satu dosen memakai Chromium lokal, lalu simpan ke database';

    public function handle(): int
    {
        $nip = trim((string) $this->argument('nip'));
        $semester = (string) $this->option('semester');
        $key = (string) ($this->option('key') ?: '');
        $statusKey = $key !== '' ? $key : 'dosen_sync_' . $nip . '_' . $semester;
        if ($semester === '') {
            try {
                $semesters = app(\App\Services\Integrations\SiakangSummaryApiClient::class)->pages('/v2/semester', ['is_aktif' => 1]);
                $semester = (string) ($semesters[0]['kode_semester'] ?? '');
                if (!preg_match('/^\d{4}[123]$/', $semester)) {
                    throw new \RuntimeException('Semester aktif tidak tersedia dari sumber.');
                }
                Cache::put('siakang.active_semester', $semester, now()->addHours(6));
            } catch (\Throwable $e) {
                $this->finish($statusKey, 'gagal', 'Semester aktif belum dapat diambil dari SIAKANG.');
                return self::FAILURE;
            }
        }

        // Identitas dosen tidak selalu 18 digit (mis. kode DLB dari SIMPEG); API SIPP dan
        // SIAKANG menerima nilainya apa adanya. Yang dicegah hanya identitas kosong.
        if ($nip === '') {
            $this->finish($statusKey, 'gagal', 'Identitas dosen tidak tersedia.');

            return self::FAILURE;
        }

        $jobs = [
            [
                'label' => 'portofolio SIPP',
                'http' => fn () => $this->syncPortfoliosHttp($nip, $semester),
                'args' => [
                    'scripts/sync-portofolio.php',
                    '--nip=' . $nip,
                    '--type=publikasi,penelitian,pengabdian',
                    '--delay=0',
                    '--force',
                ],
            ],
            [
                'label' => 'indikator SIPP',
                'args' => ['artisan', 'sync:sipp', '--semester=' . $semester, '--nip=' . $nip],
            ],
            [
                'label' => 'penjadwalan SIAKANG',
                'http' => fn () => $this->syncScheduleHttp($nip, $semester),
                'args' => [
                    'scripts/sync-penjadwalan.php',
                    '--nip=' . $nip,
                    '--semester=' . $semester,
                    '--delay=0',
                    '--force',
                ],
            ],
        ];

        $identityUpdated = \App\Models\Pegawai::where('nip', $nip)->value('updated_at');
        if (!$identityUpdated || \Illuminate\Support\Carbon::parse($identityUpdated)->lt(now()->subMinutes((int) config('satudata.swr.fresh_minutes', 20)))) {
            array_unshift($jobs, ['label' => 'identitas SIMPEG', 'args' => ['artisan', 'sync:pegawai']]);
        }

        $summaries = [];
        $failed = false;
        $hasData = false;

        foreach ($jobs as $job) {
            $this->line('→ Menarik ' . $job['label'] . '...');

            if (isset($job['http'])) {
                try {
                    $count = $job['http']();
                    $hasData = true;
                    $summaries[] = $job['label'].': '.$count.' item sumber diproses.';
                    continue;
                } catch (\Throwable $e) {
                    if (!str_contains($e->getMessage(), 'HTTP 403')) {
                        $failed = true;
                        $summaries[] = $job['label'].': '.$e->getMessage();
                        continue;
                    }
                }
            }

            $this->scriptFailed = false;
            $output = $this->runScript($job['args']);
            $failed = $failed || $this->scriptFailed;
            $summary = $this->summarize($output);

            // Tampilkan keluaran mentah penarik agar penyebab kegagalan mudah ditelusuri.
            $this->line(trim($output));

            $summaries[] = $job['label'] . ': ' . $summary;
            $this->line('  ' . $summary);

            if (preg_match('/(\d+) penugasan ditulis ke|(\d+) mata kuliah disimpan/', $output, $matches) === 1 && (int) ($matches[1] ?? $matches[2]) > 0) {
                $hasData = true;
            }

            if (preg_match('/tidak ditemukan|belum diisi|gagal menjalankan|Gagal \(|QueryException/i', $output) === 1) {
                $failed = true;
            }

            if (preg_match('/HTTP 401|Unauthenticated/i', $output) === 1) {
                $failed = true;
                $summaries[] = 'Token API kedaluwarsa: perbarui di .env lewat "php scripts/save-cookie.php sipp --token" (dari clipboard)';
            }
        }

        try {
            app(\App\Services\Sync\PageSummarySyncService::class)->dosen($nip, $semester);
            $hasData = true;
        } catch (\Throwable $e) {
            $failed = true;
            $summaries[] = 'Ringkasan akademik belum diperbarui: '.$e->getMessage();
        }

        try {
            app(\App\Services\Sync\PageSummarySyncService::class)->bimbingan($nip, $semester);
            $hasData = true;
        } catch (\Throwable $e) {
            $failed = true;
            $summaries[] = 'Beban bimbingan belum diperbarui: '.$e->getMessage();
        }

        // Tandai bahwa dosen ini memang belum punya data, supaya halaman profil tidak
        // berulang kali memicu penarikan otomatis untuk dosen yang sama.
        if (!$failed && !$hasData) {
            Cache::put('dosen_sync_empty_' . $nip . '_' . $semester, true, now()->addHours(24));
        }

        $this->finish($statusKey, $failed ? 'gagal' : 'selesai', implode(' | ', $summaries), $hasData);

        $kunciDosen = 'dosen_sync_' . $nip . '_' . $semester;
        if ($statusKey !== $kunciDosen) {
            $this->finish($kunciDosen, $failed ? 'gagal' : 'selesai', implode(' | ', $summaries), $hasData);
        }
        if ($statusKey !== 'dosen_sync') {
            $this->finish('dosen_sync', $failed ? 'gagal' : 'selesai', implode(' | ', $summaries), $hasData);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Jalankan salah satu skrip penarik dan ambil seluruh keluarannya.
     *
     * @param  array<int, string>  $args
     */
    private function syncPortfoliosHttp(string $nip, string $semester): int
    {
        $updates = [$semester => []];
        $count = 0;
        foreach (['publikasi', 'penelitian', 'pengabdian'] as $type) {
            $groups = [$semester => []];
            $seen = [];
            $expected = null;
            $lastPage = null;
            for ($page = 1; ; $page++) {
                $response = app(\App\Services\Integrations\SIPPService::class)->get('/api/'.$type, ['nip' => $nip, 'page' => $page, 'per_page' => 100]);
                if (!$response->success) {
                    throw new \RuntimeException('Portofolio '.$type.' gagal (HTTP '.$response->status.').');
                }
                $body = $response->rawBody;
                $pagination = $body['pagination'] ?? [];
                if (!is_array($response->data) || !array_is_list($response->data)
                    || (string) ($body['nip'] ?? '') !== $nip || !isset($body['total'], $pagination['last_page'])
                    || (int) ($pagination['current_page'] ?? 0) !== $page) {
                    throw new \RuntimeException('Cakupan atau pagination portofolio tidak valid.');
                }
                $expected ??= (int) $body['total'];
                $lastPage ??= (int) $pagination['last_page'];
                if ($expected !== (int) $body['total'] || $lastPage !== (int) $pagination['last_page']
                    || $lastPage < 1 || $lastPage > 2000 || ($page < $lastPage && $response->data === [])) {
                    throw new \RuntimeException('Pagination portofolio berubah atau tidak lengkap.');
                }
                foreach ($response->data as $item) {
                    $id = $item['id_portofolio'] ?? $item['id'] ?? null;
                    $period = (string) ($item['kode_semester'] ?? '');
                    if (!preg_match('/^\d{4}[123]$/', $period)) {
                        if (!empty($item['tanggal_berlaku']) && preg_match('/^(\d{4})-(\d{2})/', (string) $item['tanggal_berlaku'], $m)) {
                            $period = (int) $m[2] < 8 ? ((int) $m[1] - 1).'2' : $m[1].'1';
                        } else {
                            $period = $semester;
                        }
                    }
                    if (!$id || isset($seen[$id])) {
                        throw new \RuntimeException('Identitas portofolio tidak valid.');
                    }
                    $seen[$id] = true;
                    $groups[$period][] = $item;
                }
                if ($page === $lastPage) {
                    break;
                }
            }
            if (count($seen) !== $expected) {
                throw new \RuntimeException('Total portofolio tidak sesuai sumber.');
            }
            foreach ($groups as $period => $items) {
                $updates[$period][$type] = $items;
            }
            $count += count($seen);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($updates, $nip) {
            foreach ($updates as $period => $values) {
                \App\Models\DosenSipp::updateOrCreate(['nip' => $nip, 'semester' => (string) $period], $values);
            }
        });
        return $count;
    }

    private function syncScheduleHttp(string $nip, string $semester): int
    {
        $data = app(\App\Services\Integrations\SiakangSummaryApiClient::class)->data('/rencana-studi/penjadwalan', ['nip' => $nip, 'semester' => $semester]);
        if ((string) data_get($data, 'dosen.nip') !== $nip || (string) data_get($data, 'semester.kode_semester') !== $semester
            || !is_array($data['data'] ?? null)) {
            throw new \RuntimeException('Identitas atau semester penjadwalan tidak sesuai.');
        }
        return \Illuminate\Support\Facades\DB::transaction(fn () => \App\Services\Sync\LecturerScheduleWriter::store($nip, $semester, $data['data']));
    }

    private function runScript(array $args): string
    {
        $process = @proc_open(
            array_merge([PHP_BINARY], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path()
        );

        if (!is_resource($process)) {
            return 'gagal menjalankan ' . $args[0];
        }

        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $this->scriptFailed = proc_close($process) !== 0;

        return (string) $output;
    }

    /**
     * Ambil baris ringkasan penting dari keluaran skrip penarik.
     */
    private function summarize(string $output): string
    {
        $line = array_values(array_filter(
            preg_split('/\r?\n/', $output) ?: [],
            static fn ($content) => trim($content) !== '' && !str_starts_with(trim($content), '[batch')
        ));

        $penting = [];

        foreach ($line as $content) {
            $content = trim($content);

            if (preg_match('/penugasan ditulis|mata kuliah disimpan|Baris penjadwalan|Tidak ada|tidak ada|tidak ditemukan|belum diisi|Gagal|gagal menjalankan/i', $content) === 1) {
                $penting[] = $content;
            }
        }

        if (empty($penting)) {
            $penting = array_slice($line, -2);
        }

        return mb_substr(implode('; ', array_slice($penting, 0, 3)), 0, 250);
    }

    /**
     * Tulis status penarikan agar halaman profil bisa menampilkannya.
     */
    private function finish(string $statusKey, string $status, string $message, ?bool $hasData = null): void
    {
        Cache::put($statusKey, [
            'status' => $status,
            'message' => $message,
            'has_data' => $hasData,
            'time' => now()->toDateTimeString(),
        ], now()->addMinutes(15));

        $this->info(($status === 'selesai' ? 'Selesai: ' : 'Gagal: ') . $message);
    }
}
