<?php

namespace App\Services\Integrations;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Satu pintu untuk penarikan ulang data (revalidate) pada pola SWR.
 *
 * Halaman selalu dilayani dari cache/database; kelas ini yang memicu penarikan
 * baru. Penariknya wajib berbasis Chromium lokal (Edge/Chrome di host aplikasi)
 * karena panggilan HTTP langsung dari PHP selalu ditantang Cloudflare
 * (HTTP 403 "Just a moment") sehingga datanya tidak akan pernah ter-update.
 *
 * Setiap percobaan menulis status ke cache `source_status.<sumber>`, sedangkan hasil
 * penarikan yang sebenarnya ditulis penariknya sendiri di kunci `status_key`
 * (mis. `aset_sync`). UI memakai keduanya supaya waktu yang tampil adalah waktu
 * penarikan yang benar-benar berjalan, bukan waktu tombol ditekan.
 */
class SourceRevalidator
{
    /**
     * Pemicu penarikan ulang satu sumber data.
     *
     * @param  array{nip?: string, semester?: string}  $konteks
     * @return array{status: string, message: string}
     */
    public function trigger(string $sumber, array $konteks = []): array
    {
        $key = 'source_launch.'.hash('sha256', $sumber.'|'.($konteks['semester'] ?? '').'|'.($konteks['nip'] ?? '').'|'.($konteks['kode_prodi'] ?? '').'|'.($konteks['tahun'] ?? ''));
        $lock = Cache::lock($key, 10);
        if (!$lock->get()) {
            return ['status' => 'diam', 'message' => 'Pembaruan sedang diminta.'];
        }
        try {
            return $this->triggerLocked($sumber, $konteks);
        } finally {
            $lock->release();
        }
    }

    private function triggerLocked(string $sumber, array $konteks): array
    {
        // Kunci sumber memuat titik (mis. 'simantap.aset'), jadi konfigurasinya diambil
        // langsung dari array-nya: `config('satudata.sources.simantap.aset')` dibaca
        // Laravel sebagai kunci bersarang dan selalu kosong.
        $konfigurasi = (array) (config('satudata.sources')[$sumber] ?? []);

        if (empty($konfigurasi)) {
            return $this->record($sumber, 'tidak_dikenal', 'Sumber data tidak terdaftar di config/satudata.php.');
        }

        $statusKey = $this->scopedKey((string) ($konfigurasi['status_key'] ?? $sumber), $sumber, $konteks);
        $recordSource = $this->scopedKey($sumber, $sumber, $konteks);
        $kunciJeda = 'source_status.' . $sumber . '.jeda.'.hash('sha256', ($konteks['semester'] ?? '').'|'.($konteks['nip'] ?? '').'|'.($konteks['kode_prodi'] ?? '').'|'.($konteks['tahun'] ?? ''));

        // Jeda: satu penarikan per periode, agar penanda segar yang lewat tidak
        // memicu peluncuran bertubi-tubi. Isinya waktu pemicu terakhir, supaya UI bisa
        // menyebut sisa waktunya.
        $waktuJeda = Cache::get($kunciJeda);

        if ($waktuJeda !== null) {
            return ['status' => 'diam', 'message' => $this->pesanJeda($waktuJeda)];
        }

        $launcher = $konfigurasi['launcher'] ?? null;
        $perintah = (array) ($konfigurasi['command'] ?? []);
        $adaPenarik = (!empty($launcher) && class_exists($launcher)) || !empty($perintah['target']);

        if (!$adaPenarik) {
            // Sumber ini belum punya penarik, jadi datanya hanya berubah
            // lewat perintah sinkronisasi manual.
            return $this->record(
                $recordSource,
                'tanpa_penarik',
                'Belum ada penarik untuk ' . ($konfigurasi['label'] ?? $sumber) . '.'
            );
        }

        $proses = app(CommandLauncher::class);

        if (!$proses->isAvailable()) {
            return $this->record(
                $recordSource,
                'tanpa_browser',
                'Host aplikasi tidak memiliki Edge/Chrome, jadi penarikan otomatis tidak tersedia.'
            );
        }

        $metode = (string) ($konfigurasi['method'] ?? 'pages');

        // Sumber per dosen berlauncher butuh identitas dosen; tanpa itu penariknya hanya
        // akan melaporkan kegagalan. Penolakan di sini menyertakan arah halamannya.
        if ($this->butuhNip($konfigurasi) && trim((string) ($konteks['nip'] ?? '').'|'.($konteks['kode_prodi'] ?? '')) === '') {
            return $this->record(
                $recordSource,
                'tanpa_konteks',
                'Penarikan ' . ($konfigurasi['label'] ?? $sumber) . ' dijalankan dari halaman profil dosen.'
            );
        }

        try {
            if (!empty($perintah['target'])) {
                $argumen = array_map(
                    fn ($bagian) => str_replace(
                        ['{semester}', '{nip}', '{key}', '{prodi}', '{angkatan}', '{tahun}'],
                        [$this->semesterDipicu($konteks), (string) ($konteks['nip'] ?? ''), $statusKey, (string) ($konteks['kode_prodi'] ?? ''), (string) (now()->year - 1), (string) ($konteks['tahun'] ?? now()->year)],
                        (string) $bagian
                    ),
                    (array) ($perintah['arguments'] ?? [])
                );

                $berhasil = app(CommandLauncher::class)->sync((string) $perintah['target'], $argumen, $statusKey);
            } else {
                $nipKonteks = (string) ($konteks['nip'] ?? '');
                $semesterKonteks = (string) ($konteks['semester'] ?? '');
                $kunciPeluncur = ($metode === 'per_lecturer' && $nipKonteks !== '')
                    ? 'dosen_sync_' . $nipKonteks . '_' . ($semesterKonteks ?: '20252')
                    : $statusKey;

                $berhasil = $metode === 'per_lecturer'
                    ? app($launcher)->sync($nipKonteks, $semesterKonteks, $kunciPeluncur)
                    : app($launcher)->sync((int) config('aset.tarik.max_pages', 5), $statusKey);
            }
        } catch (\Throwable $e) {
            Log::warning('SourceRevalidator gagal memicu ' . $sumber . ': ' . $e->getMessage());
            $berhasil = false;
        }

        Cache::put($kunciJeda, now()->toDateTimeString(), now()->addMinutes((int) config('satudata.swr.fresh_minutes', 20)));

        return $berhasil
            ? $this->record($recordSource, 'jalan', 'Penarikan ' . ($konfigurasi['label'] ?? $sumber) . ' dimulai.')
            : $this->record($recordSource, 'gagal', 'Gagal menjalankan penarik ' . ($konfigurasi['label'] ?? $sumber) . '.');
    }

    /**
     * Status terakhir satu sumber data (untuk indikator "terakhir diperbarui").
     *
     * @return array{status: string, message: string, waktu: ?string}
     */
    public function status(string $sumber, array $konteks = []): array
    {
        $status = Cache::get('source_status.' . $this->scopedKey($sumber, $sumber, $konteks));

        return [
            'status' => is_array($status) ? (string) ($status['status'] ?? 'belum') : 'belum',
            'message' => is_array($status) ? (string) ($status['message'] ?? '') : '',
            'time' => is_array($status) ? ($status['time'] ?? null) : null,
        ];
    }

    /**
     * Semua sumber beserta statusnya, untuk ditampilkan di UI.
     *
     * @return array<string, array{label: string, has_puller: bool, needs_context: bool, status: array, pull: array}>
     */
    public function allStatuses(array $konteks = []): array
    {
        $hasil = [];

        foreach ((array) config('satudata.sources', []) as $kunci => $konfigurasi) {
            $baseStatusKey = (string) ($konfigurasi['status_key'] ?? $kunci);
            $pemicu = $this->status($kunci, $konteks);
            $penarikan = $this->lastPull($this->scopedKey($baseStatusKey, $kunci, $konteks));
            if ($penarikan['status'] === 'belum' && $pemicu['status'] !== 'jalan') {
                $penarikan = $this->lastPull($baseStatusKey);
            }

            $hasil[$kunci] = [
                'label' => (string) ($konfigurasi['label'] ?? $kunci),
                'has_puller' => !empty($konfigurasi['launcher']) || !empty($konfigurasi['command']['target']),
                'needs_context' => $this->butuhNip($konfigurasi),
                'status' => $this->gabungkanStatus($pemicu, $penarikan),
                'pull' => $penarikan,
                'stale' => $penarikan['status'] !== 'selesai' || !$penarikan['time']
                    || Carbon::parse($penarikan['time'])->lt(now()->subMinutes((int) config('satudata.swr.fresh_minutes', 20))),
            ];
        }

        return $hasil;
    }

    /**
     * Sumber yang penariknya hanya bisa berjalan dengan identitas dosen
     * (mis. penjadwalan SIAKANG), sehingga tidak bisa dipicu dari halaman mana pun.
     *
     * @param  array<string, mixed>  $konfigurasi
     */
    private function butuhNip(array $konfigurasi): bool
    {
        return !empty($konfigurasi['launcher'])
            && (string) ($konfigurasi['method'] ?? 'pages') === 'per_lecturer';
    }

    /**
     * Hasil penarikan terakhir yang ditulis penariknya lewat kunci `status_key`
     * (mis. `aset_sync` diisi SyncAsetCommand). Inilah bukti penarikan yang benar-benar
     * berjalan, bukan sekadar pemicu yang dikirim halaman.
     *
     * @return array{status: string, message: string, time: ?string, count: ?int}
     */
    private function scopedKey(string $key, string $source, array $context): string
    {
        if (in_array($source, ['halaman.akademik', 'siakang.perkuliahan', 'siakang.mahasiswa_aktif'], true) && !empty($context['semester'])) {
            return $key.'.'.hash('sha256', $context['semester'].'|'.($context['kode_prodi'] ?? ''));
        }
        if ($source === 'siakang.lulusan' && !empty($context['tahun'])) {
            return $key.'.'.hash('sha256', (string) $context['tahun']);
        }
        return $key;
    }

    private function lastPull(string $statusKey): array
    {
        $status = Cache::get($statusKey);

        return [
            'status' => is_array($status) ? (string) ($status['status'] ?? 'belum') : 'belum',
            'message' => is_array($status) ? (string) ($status['message'] ?? '') : '',
            'time' => is_array($status) ? ($status['time'] ?? null) : null,
            'count' => is_array($status) ? ($status['count'] ?? null) : null,
        ];
    }

    /**
     * Satukan catatan pemicu dengan catatan penarik supaya status 'jalan' tidak menggantung.
     *
     * @param  array{status: string, message: string, time: ?string}  $pemicu
     * @param  array{status: string, message: string, time: ?string, count: ?int}  $penarikan
     * @return array{status: string, message: string, time: ?string}
     */
    private function gabungkanStatus(array $pemicu, array $penarikan): array
    {
        if ($pemicu['status'] !== 'jalan') {
            return $pemicu;
        }

        $waktuPemicu = $pemicu['time'] !== null ? Carbon::parse($pemicu['time']) : null;
        $waktuPenarikan = $penarikan['time'] !== null ? Carbon::parse($penarikan['time']) : null;

        // Penarik sudah melaporkan hasil sesudah pemicu: penarikan tidak berjalan lagi.
        if ($waktuPenarikan !== null && ($waktuPemicu === null || $waktuPenarikan->greaterThanOrEqualTo($waktuPemicu))) {
            if ($penarikan['status'] === 'jalan' && $waktuPenarikan->copy()->addMinutes(15)->lessThanOrEqualTo(now())) {
                return ['status' => 'basi', 'message' => 'Pembaruan belum melaporkan hasil terbaru.', 'time' => $penarikan['time']];
            }
            return [
                'status' => $penarikan['status'],
                'message' => $penarikan['message'],
                'time' => $penarikan['time'],
            ];
        }

        // Pemicu yang lewat 15 menit tanpa kabar dianggap basi (proses penarik mati),
        // sama seperti perlakuan tombol di halaman aset. Perbandingan ditulis eksplisit
        // karena diffInMinutes() sejak Carbon 3 mengembalikan nilai bertanda.
        if ($waktuPemicu !== null && $waktuPemicu->addMinutes(15)->lessThanOrEqualTo(now())) {
            return [
                'status' => 'basi',
                'message' => 'Penarik belum melaporkan hasil (dipicu ' . $pemicu['time'] . ').',
                'time' => $pemicu['time'],
            ];
        }

        return $pemicu;
    }

    /**
     * Semester yang dipakai penarik: semester halaman bila ada, kalau tidak semester berjalan
     * - aturan yang sama dengan halaman akademik/perkuliahan yang menampilkan datanya.
     *
     * @param  array{nip?: string, semester?: string}  $konteks
     */
    private function semesterDipicu(array $konteks): string
    {
        $semester = trim((string) ($konteks['semester'] ?? ''));

        if ($semester !== '') {
            return $semester;
        }

        return now()->month < 8 ? (now()->year - 1) . '2' : now()->year . '1';
    }

    /**
     * Pesan saat permintaan datang di dalam masa jeda.
     *
     * Klik yang jatuh di masa jeda tidak menarik data apa pun: yang terjadi hanyalah
     * pemicu sebelumnya masih berjalan, jadi pesannya menyebut sisa waktunya.
     */
    private function pesanJeda(mixed $waktuJeda): string
    {
        $menit = (int) config('satudata.swr.fresh_minutes', 20);

        if (!is_string($waktuJeda) || $waktuJeda === '') {
            return 'Penarikan baru saja dipicu; tunggu ' . $menit . ' menit lagi.';
        }

        $batas = Carbon::parse($waktuJeda)->addMinutes($menit);
        $sisaDetik = max(0, $batas->getTimestamp() - now()->getTimestamp());

        return 'Penarikan baru saja dipicu; tunggu ' . max(1, (int) ceil($sisaDetik / 60)) . ' menit lagi.';
    }

    private function record(string $sumber, string $status, string $pesan): array
    {
        Cache::put('source_status.' . $sumber, [
            'status' => $status,
            'message' => $pesan,
            'time' => now()->toDateTimeString(),
        ], now()->addDays(7));

        return ['status' => $status, 'message' => $pesan];
    }
}
