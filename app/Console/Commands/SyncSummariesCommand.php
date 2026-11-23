<?php

namespace App\Console\Commands;

use App\Models\Pegawai;
use App\Services\Integrations\SiakangSummaryApiClient;
use App\Services\Sync\PageSummarySyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncSummariesCommand extends Command
{
    protected $signature = 'sync:summaries {domain : mahasiswa|mahasiswa-detail|lulusan|perkuliahan|dosen|jalur|pegawai|aset}
        {--semester=} {--prodi=} {--nip=} {--angkatan=} {--fakultas=} {--jenjang=} {--tahun=} {--key=}';

    protected $description = 'Perbarui agregasi halaman setelah seluruh data sumber berhasil dibaca';

    public function handle(PageSummarySyncService $sync, SiakangSummaryApiClient $api): int
    {
        $domain = (string) $this->argument('domain');
        $defaultKey = ['mahasiswa-detail' => 'siakang_mahasiswa_detail_sync', 'lulusan' => 'siakang_lulusan_detail_sync', 'jalur' => 'siakang_jalur_sync'][$domain] ?? 'summary_'.$domain;
        $statusKey = (string) ($this->option('key') ?: $defaultKey);
        $scope = $domain === 'perkuliahan' ? '|'.(string) $this->option('semester').'|'.(string) $this->option('prodi') : '';
        $lock = Cache::lock('sync:summaries:'.$domain.$scope, 21600);
        if (!$lock->get()) {
            $this->error('Sinkronisasi domain ini masih berjalan.');
            return self::FAILURE;
        }
        try {
            \App\Services\Sync\PullStatusReporter::laporkan($statusKey, 'jalan', 'Menarik sumber agregasi.');
            if (in_array($domain, ['pegawai', 'aset'], true)) {
                $count = $sync->{$domain}();
            } elseif ($domain === 'lulusan') {
                $count = $sync->lulusan($this->option('tahun') ? (string) $this->option('tahun') : null);
            } elseif ($domain === 'mahasiswa-detail') {
                $count = $sync->mahasiswaDetail();
            } elseif ($domain === 'jalur') {
                $year = (string) ($this->option('angkatan') ?: now()->year);
                if (!preg_match('/^\d{4}$/', $year)) {
                    throw new \RuntimeException('Angkatan harus empat digit.');
                }
                $faculties = $this->option('fakultas') ? [(string) $this->option('fakultas')]
                    : array_column($api->pages('/v2/fakultas', []), 'kode_fakultas');
                $levels = $this->option('jenjang') ? [(string) $this->option('jenjang')]
                    : array_values(array_unique(array_filter(array_column($api->pages('/v2/prodi', []), 'jenjang_id'))));
                if ($faculties === [] || $levels === []) {
                    throw new \RuntimeException('Referensi fakultas atau jenjang tidak tersedia.');
                }
                $count = 0;
                \Illuminate\Support\Facades\DB::transaction(function () use ($sync, $year, $faculties, $levels, &$count) {
                    if (!$this->option('fakultas') && !$this->option('jenjang')) {
                        \Illuminate\Support\Facades\DB::table('mahasiswa_jalur_summaries')->where('angkatan', $year)->delete();
                    }
                    foreach ($faculties as $faculty) {
                        foreach ($levels as $level) {
                            $count += $sync->jalur($year, (string) $faculty, (string) $level);
                        }
                    }
                    if (!$this->option('fakultas') && !$this->option('jenjang')) {
                        \Illuminate\Support\Facades\DB::table('mahasiswa_jalur_summaries')->where('angkatan', $year)
                            ->update(['cakupan_lengkap' => true]);
                    }
                });
            } elseif (in_array($domain, ['mahasiswa', 'perkuliahan', 'dosen'], true)) {
                $semester = (string) $this->option('semester');
                if ($semester === '') {
                    $semesters = $api->pages('/v2/semester', ['is_aktif' => 1]);
                    $semester = (string) ($semesters[0]['kode_semester'] ?? '');
                }
                if (!preg_match('/^\d{4}[123]$/', $semester)) {
                    throw new \RuntimeException('Semester sumber tidak valid.');
                }
                $count = 0;
                if ($domain === 'mahasiswa') {
                    foreach (['aktif', 'lulus'] as $jenis) {
                        $count += $sync->mahasiswa($semester, $jenis);
                    }
                } elseif ($domain === 'perkuliahan') {
                    $prodi = trim((string) $this->option('prodi'));
                    $count = $sync->perkuliahan($semester, $prodi !== '' ? $prodi : null);
                } else {
                    if (!$this->option('nip')) {
                        $sync->dosenDariJadwal($semester);
                    }
                    $nips = $this->option('nip') ? [(string) $this->option('nip')]
                        : Pegawai::where('level_pegawai', 'like', '%Dosen%')->pluck('nip')->all();
                    $failed = 0;
                    foreach ($nips as $nip) {
                        try {
                            $count += $sync->dosen((string) $nip, $semester);
                        } catch (\RuntimeException $e) {
                            if (preg_match('/HTTP (401|403|429)/', $e->getMessage())) {
                                throw $e;
                            }
                            $failed++;
                        }
                    }
                    if ($failed > 0) {
                        throw new \RuntimeException("{$count} ringkasan dosen diperbarui; {$failed} belum mendapat hasil sumber yang sesuai.");
                    }
                }
            } else {
                throw new \RuntimeException('Domain agregasi tidak dikenal.');
            }
            $this->info("{$count} baris agregasi diperbarui.");
            \App\Services\Sync\PullStatusReporter::laporkan($statusKey, 'selesai', "{$count} baris agregasi diperbarui.", $count);
            return self::SUCCESS;
        } catch (Throwable $e) {
            \App\Services\Sync\PullStatusReporter::laporkan($statusKey, 'gagal', $e->getMessage());
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
