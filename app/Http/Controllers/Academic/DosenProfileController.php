<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Services\Integrations\DosenSyncLauncher;
use App\Services\Integrations\SiakangPenjadwalanService;
use App\Services\Integrations\SimpegPegawaiService;
use App\Services\Integrations\SIPPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DosenProfileController extends Controller
{
    private array $portofolioTersedia = [];

    public function __construct(
        public SimpegPegawaiService $pegawaiService,
        public SIPPService $sippService,
        public SiakangPenjadwalanService $penjadwalanService,
        public DosenSyncLauncher $dosenLauncher,
    ) {}

    public function show(Request $request, string $nip): mixed
    {
        $nip = trim($nip);
        $dosenData = $this->getDosenData($nip);
        $lvl = strtolower(trim((string) ($dosenData['nama_level_pegawai'] ?? $dosenData['levelPegawai'] ?? $dosenData['level_pegawai'] ?? '')));
        if (!empty($lvl) && !str_contains($lvl, 'dosen')) {
            return redirect()->route('pegawai.profil-staf.show', ['nip' => $nip]);
        }

        $semester = $this->getActiveSemester($request->input('semester'));
        $semesterNama = $this->getSemesterLabel($semester);

        $portfolio      = $this->loadPortfolioBatch($nip, $semester);
        $publikasiData  = $portfolio['publikasi'];
        $penelitianData = $portfolio['penelitian'];
        $pengabdianData = $portfolio['pengabdian'];
        $academic       = app(\App\Services\PageSummaryReader::class)->dosen($nip, $semester);
        $jadwalData     = $this->getJadwalData($nip, $semester, $academic);

        $profile            = $this->buildProfile($dosenData);
        $publikasi10Tahun   = $this->buildPublikasiTerakhir($publikasiData);
        $penelitianList     = $this->buildPenelitian($penelitianData);
        $pengabdianList     = $this->buildPengabdian($pengabdianData);
        $sintaIndexasi      = $this->buildSintaIndexasi($publikasiData, $profile);
        $jadwalHariIni      = $this->buildJadwalHariIni($jadwalData);
        $statistikMengajar = $academic !== null
            ? array_intersect_key($academic, array_flip(['total_mk', 'total_kelas', 'total_sks']))
            : ['total_mk' => null, 'total_kelas' => null, 'total_sks' => null];

        // Tarik otomatis hanya bila data dosen ini memang masih kosong, tidak sedang
        // dalam jeda, dan belum pernah dicoba (kosong) dalam 24 jam terakhir - supaya
        // kunjungan halaman tidak memicu pemanggilan API berulang-ulang.
        // Identitas dosen bisa berupa NIP 18 digit atau kode lain dari SIMPEG (mis. DLB);
        // API SIPP & SIAKANG menerima nilainya apa adanya, jadi hanya identitas kosong
        // yang tidak bisa ditarik.
        $nipValid = $nip !== '';

        $mengajarKosong = $academic === null;
        $freshUntil = now()->subMinutes((int) config('satudata.swr.fresh_minutes', 20));
        $sippUpdated = $portfolio['updated_at'];
        $academicUpdated = $academic['calculated_at'] ?? null;
        $bimbingan = Cache::get('dosen_bimbingan_'.hash('sha256', $nip.'|'.$semester));
        $dataKosong = in_array(false, $this->portofolioTersedia, true) || !$sippUpdated || !$academicUpdated || $bimbingan === null;
        $syncStatus = Cache::get("dosen_sync_{$nip}_{$semester}");
        $recentAttempt = is_array($syncStatus) && isset($syncStatus['time'])
            && in_array($syncStatus['status'] ?? '', ['jalan', 'selesai'], true)
            && \Illuminate\Support\Carbon::parse($syncStatus['time'])->gte($freshUntil);
        $needsAutoSync = $nipValid && $dataKosong && !$recentAttempt
            && !Cache::has("dosen_sync_cooldown_{$nip}_{$semester}")
            && !Cache::has("dosen_sync_empty_{$nip}_{$semester}");

        $viewData = [
            'title'              => 'Profil Dosen - ' . ($profile['nama'] ?? 'Untirta'),
            'profile'            => $profile,
            'publikasi10Tahun'   => $publikasi10Tahun,
            'penelitianList'     => $penelitianList,
            'pengabdianList'     => $pengabdianList,
            'portofolioTersedia' => $this->portofolioTersedia,
            'sintaIndexasi'      => $sintaIndexasi,
            'jadwalHariIni'      => $jadwalHariIni,
            'statistikMengajar'  => $statistikMengajar,
            'bimbingan'          => $bimbingan ?? [],
            'mataKuliahList'     => $jadwalData,
            'nipValid'           => $nipValid,
            'needsAutoSync'      => $needsAutoSync,
            'isSyncRunning'      => $recentAttempt && ($syncStatus['status'] ?? '') === 'jalan',
            'mengajarKosong'     => $mengajarKosong,
            'nip'                => $nip,
            'semester'           => $semester,
            'semesterNama'       => $semesterNama,
        ];

        return view('academic.dosen-profile', $viewData);
    }

    public function sippMetrics(Request $request, string $nip): \Illuminate\Http\JsonResponse
    {
        $semester = $this->getActiveSemester($request->input('semester'));
        $record = \App\Models\DosenSipp::where('nip', trim($nip))->where('semester', $semester)->first();
        if ($record === null) {
            $record = \App\Models\DosenSipp::where('nip', trim($nip))->orderByDesc('semester')->first();
        }
        $sourceSemester = $record?->semester ?? $semester;
        $metrics = [];
        foreach (['sinta12', 'sinta36', 'jurnal_internasional_q', 'jurnal_internasional_pbb', 'jurnal_nasional_issn', 'pengembangan', 'pengabdian_masyarakat'] as $key) {
            $metrics[$key] = $record?->{$key};
        }
        return response()->json(['success' => true, 'semester' => $sourceSemester, 'requested_semester' => $semester, 'metrics' => $metrics, 'source' => 'database']);
    }

    protected function getActiveSemester(?string $requested = null): string
    {
        if (!empty($requested)) {
            return $requested;
        }

        if (request()->has('semester') && !empty(request('semester'))) {
            return request('semester');
        }

        return Cache::remember('dosen_profile.active_semester', now()->addMinutes(30), function () {
            return (string) (\Illuminate\Support\Facades\DB::table('siakang_semester_stats')->max('semester')
                ?? \Illuminate\Support\Facades\DB::table('dosen_jadwals')->max('semester')
                ?? Cache::get('siakang.active_semester')
                ?? '');
        });
    }

    protected function getSemesterLabel(string $kodeSemester): string
    {
        if (!preg_match('/^\d{4}[123]$/', $kodeSemester)) {
            return '-';
        }

        $tahun = substr($kodeSemester, 0, 4);
        $jenis = substr($kodeSemester, 4, 1);
        $tahunPlus = ((int) $tahun) + 1;

        if ($jenis === '2') {
            return "{$tahun}/{$tahunPlus} Genap";
        }

        return "{$tahun}/{$tahunPlus} Gasal";
    }

    private function getDosenData(string $nip): array
    {
        $pegawai = \App\Models\Pegawai::where('nip', trim($nip))->first();
        return $pegawai ? $this->pegawaiService->normalizePegawaiItem($this->pegawaiService->mapDbPegawaiToItem($pegawai))
            : ['nip' => $nip, 'nama' => '-'];
    }

    public function syncSingleDosenFromApi(string $nip, string $cacheKey): ?array
    {
        try {
            $response = $this->pegawaiService->getData(['nip' => $nip]);
            if ($response->success && !empty($response->data)) {
                $data = $response->data;
                if (is_array($data)) {
                    if (isset($data['data']) && is_array($data['data'])) {
                        $data = $data['data'];
                    }
                    $matched = null;
                    if (isset($data[0]) && is_array($data[0])) {
                        foreach ($data as $item) {
                            if (isset($item['nip']) && trim((string) $item['nip']) === trim($nip)) {
                                $matched = $item;
                                break;
                            }
                        }
                    }
                    if (!$matched && !empty($data)) {
                        $first = reset($data);
                        $matched = is_array($first) ? $first : (array) $data;
                    }

                    if ($matched && !empty($matched['nip'])) {
                        try {
                            \App\Models\Pegawai::updateOrCreate(
                                ['nip' => trim($nip)],
                                [
                                    'kode_data'      => $matched['kd_pegawai'] ?? $matched['kodeData'] ?? null,
                                    'id_sdm'         => $matched['id_sdm'] ?? $matched['idSDM'] ?? null,
                                    'nama'           => $matched['namaPegawai'] ?? $matched['nama_pegawai'] ?? $matched['nama'] ?? '-',
                                    'gelar_depan'    => $matched['gelar_depan'] ?? $matched['gelarDepan'] ?? null,
                                    'gelar_belakang' => $matched['gelar_belakang'] ?? $matched['gelarBelakang'] ?? null,
                                    'email'          => $matched['emailPegawai'] ?? $matched['email'] ?? null,
                                    'no_tlp'         => $matched['noTlp'] ?? $matched['no_tlp'] ?? null,
                                    'unit_kerja'     => $matched['unitKerja'] ?? $matched['unit_kerja'] ?? null,
                                    'unit_kerja_id'  => $matched['unitKerja_id'] ?? $matched['unit_kerja_id'] ?? null,
                                    'jabatan'        => $matched['nama_jabatan'] ?? $matched['jabatan'] ?? null,
                                    'jabatan_id'     => $matched['jabatan_id'] ?? null,
                                    'pangkat'        => $matched['pangkat'] ?? $matched['nama_pangkat'] ?? null,
                                    'pangkat_id'     => $matched['pangkat_id'] ?? null,
                                    'status_kerja'   => $matched['nama_stspegawai'] ?? $matched['statusKerja'] ?? null,
                                    'level_pegawai'  => $matched['nama_level_pegawai'] ?? $matched['levelPegawai'] ?? null,
                                    'payload'        => $matched,
                                ]
                            );
                        } catch (\Throwable $e) {}

                        Cache::put($cacheKey, $matched, now()->addHours(6));
                        return $matched;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Gagal sync data dosen {$nip} dari API SIMPEG: " . $e->getMessage());
        }
        return null;
    }

    /**
     * [Local-First] Ambil penjadwalan dosen (sumber SKS & jumlah MK) dari database SATUDATA,
     * lalu jadwalkan revalidasi background ke API SIAKANG. Bila database belum punya data,
     * baru mengambil langsung dari API.
     */
    private function getJadwalData(string $nip, string $semester, ?array $academic = null): array
    {
        $local = $this->scheduleFromDatabase($nip, $semester);
        if ($local !== []) {
            return $local;
        }
        $academic ??= app(\App\Services\PageSummaryReader::class)->dosen($nip, $semester);
        $items = $academic['payload']['mata_kuliah_diampu']['data'] ?? [];
        return collect($items)->groupBy('kode_mata_kuliah')->map(fn ($rows) => [
            'mata_kuliah' => ['kode' => $rows->first()['kode_mata_kuliah'], 'nama' => $rows->first()['nama_mata_kuliah'], 'sks' => $rows->first()['sks']],
            'jadwal' => $rows->map(fn ($row) => [
                'jadwal_id' => $row['jadwal_id'], 'kode_jadwal' => $row['kode_jadwal'] ?? null,
                'kelas' => [], 'mode' => null,
                'waktu_kuliah' => [['hari' => $row['hari'] ?? null, 'hari_numeric' => null,
                    'jam_mulai' => $row['jam_mulai'] ?? null, 'jam_selesai' => $row['jam_selesai'] ?? null,
                    'ruang' => ['nama_ruang' => $row['ruang'] ?? null, 'kode_ruang' => $row['kode_ruang'] ?? null]]],
            ])->values()->all(),
        ])->values()->all();
    }

    private function scheduleFromDatabase(string $nip, string $semester): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('dosen_jadwals')
            ->where('nip', trim($nip))
            ->where('semester', $semester)
            ->orderBy('mata_kuliah_nama')
            ->orderBy('kode_jadwal')
            ->orderBy('hari_numeric')
            ->orderBy('jam_mulai')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $perMataKuliah = [];

        foreach ($rows as $row) {
            $kunciMk = (string) $row->mata_kuliah_kode . '|' . (string) $row->mata_kuliah_nama;

            if (!isset($perMataKuliah[$kunciMk])) {
                $perMataKuliah[$kunciMk] = [
                    'mata_kuliah' => [
                        'kode' => $row->mata_kuliah_kode,
                        'nama' => $row->mata_kuliah_nama,
                        'sks'  => (int) $row->mata_kuliah_sks,
                    ],
                    'jadwal' => [],
                ];
            }

            $kunciJadwal = (string) ($row->kode_jadwal ?: $row->jadwal_id);

            if (!isset($perMataKuliah[$kunciMk]['jadwal'][$kunciJadwal])) {
                $perMataKuliah[$kunciMk]['jadwal'][$kunciJadwal] = [
                    'kode_jadwal' => $row->kode_jadwal,
                    'kelas' => $row->kelas !== null ? [['nama_kelas' => $row->kelas]] : [],
                    'mode' => $row->mode,
                    'waktu_kuliah' => [],
                ];
            }

            $perMataKuliah[$kunciMk]['jadwal'][$kunciJadwal]['waktu_kuliah'][] = [
                'hari' => $row->hari,
                'hari_numeric' => (int) $row->hari_numeric,
                'jam_mulai' => $row->jam_mulai,
                'jam_selesai' => $row->jam_selesai,
                'ruang' => ['nama_ruang' => $row->nama_ruang],
            ];
        }

        return array_values(array_map(function (array $mk) {
            $mk['jadwal'] = array_values($mk['jadwal']);

            return $mk;
        }, $perMataKuliah));
    }

    /**
     * Simpan hasil tarikan API ke tabel `dosen_jadwals`. Logikanya ada di
     * App\Services\Sync\LecturerScheduleWriter supaya sama dengan skrip CLI.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function storeScheduleInDatabase(string $nip, string $semester, array $items): void
    {
        \App\Services\Sync\LecturerScheduleWriter::store($nip, $semester, $items);
    }

    private function syncPenjadwalanFromApi(string $nip, string $semester, string $cacheKey): array
    {
        try {
            $response = $this->penjadwalanService->getData([
                'semester' => $semester,
                'nip'      => $nip,
            ]);

            if ($response->success && !empty($response->data)) {
                $payload = is_array($response->data) ? $response->data : [];
                $items = $payload['data'] ?? (isset($payload[0]) ? $payload : []);

                if (!empty($items)) {
                    try {
                        $this->storeScheduleInDatabase($nip, $semester, $items);
                    } catch (\Throwable $e) {
                        Log::warning("Gagal simpan penjadwalan dosen {$nip} ke database: " . $e->getMessage());
                    }

                    Cache::put($cacheKey, $items, now()->addHours(6));
                    return $items;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal ambil jadwal dosen {$nip} dari API: " . $e->getMessage());
        }

        return [];
    }

    /**
     * [Local-First SWR]: Ambil portofolio Tri Dharma langsung dari database lokal (dosen_sipps),
     * lalu jadwalkan revalidasi background API SIPP via defer().
     */
    private function loadPortfolioBatch(string $nip, string $semester): array
    {
        $types = ['publikasi', 'penelitian', 'pengabdian'];
        $result = ['publikasi' => [], 'penelitian' => [], 'pengabdian' => [], 'updated_at' => null];
        foreach ($types as $type) {
            $this->portofolioTersedia[$type] = false;
        }

        $records = \App\Models\DosenSipp::where('nip', trim($nip))
            ->orderByRaw('CASE WHEN semester = ? THEN 0 ELSE 1 END', [$semester])
            ->orderByDesc('semester')
            ->get(['semester', 'publikasi', 'penelitian', 'pengabdian', 'updated_at']);

        if ($records->isEmpty()) {
            return $result;
        }

        $result['updated_at'] = $records->max('updated_at');

        foreach ($types as $type) {
            foreach ($records as $record) {
                $data = $record->{$type};
                if ($data === null) {
                    continue;
                }
                $items = is_array($data) ? ($data['data'] ?? $data) : null;
                if (is_array($items)) {
                    $this->portofolioTersedia[$type] = true;
                }
                if (is_array($items) && $items !== []) {
                    $result[$type] = $items;
                    break;
                }
            }
        }

        return $result;
    }

    private function getPortfolioData(string $type, string $nip, string $semester): array
    {
        $this->portofolioTersedia[$type] = false;
        $records = \App\Models\DosenSipp::where('nip', trim($nip))->whereNotNull($type)
            ->orderByRaw('CASE WHEN semester = ? THEN 0 ELSE 1 END', [$semester])
            ->orderByDesc('semester')->get([$type]);
        foreach ($records as $record) {
            $data = $record->{$type};
            $items = is_array($data) ? ($data['data'] ?? $data) : null;
            if (is_array($items)) {
                $this->portofolioTersedia[$type] = true;
            }
            if (is_array($items) && $items !== []) {
                return $items;
            }
        }
        return [];
    }

    public function syncPortfolioFromApi(string $type, string $nip, string $semester, string $cacheKey): array
    {
        try {
            $params = ['nip' => $nip, 'page' => 1, 'per_page' => 25];
            $response = match ($type) {
                'publikasi'  => $this->sippService->getPublikasi($params),
                'penelitian' => $this->sippService->getPenelitian($params),
                'pengabdian' => $this->sippService->getPengabdian($params),
                default      => null,
            };

            if ($response && $response->success && !empty($response->data)) {
                $raw = is_array($response->data) ? $response->data : [];
                $items = isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : $raw;
                if (!empty($items)) {
                    try {
                        \App\Models\DosenSipp::updateOrCreate(
                            ['nip' => trim($nip), 'semester' => $semester],
                            [$type => $items]
                        );
                    } catch (\Throwable $e) {}

                    Cache::put($cacheKey, $items, now()->addHours(6));
                    return $items;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal sync {$type} dosen {$nip} dari API: " . $e->getMessage());
        }
        return [];
    }

    private function getPublikasiData(string $nip, string $semester): array
    {
        return $this->getPortfolioData('publikasi', $nip, $semester);
    }

    private function getPenelitianData(string $nip, string $semester): array
    {
        return $this->getPortfolioData('penelitian', $nip, $semester);
    }

    private function getPengabdianData(string $nip, string $semester): array
    {
        return $this->getPortfolioData('pengabdian', $nip, $semester);
    }

    private function buildProfile(array $dosenData): array
    {
        $namaLengkap = $dosenData['nama_pegawai_lengkap'] ?? null;
        if (empty($namaLengkap)) {
            $nama = $dosenData['namaPegawai'] ?? $dosenData['nama_pegawai'] ?? $dosenData['nama'] ?? '-';
            $gelarDepan = $dosenData['gelarDepan'] ?? $dosenData['gelar_depan'] ?? '';
            $gelarBelakang = $dosenData['gelarBelakang'] ?? $dosenData['gelar_belakang'] ?? '';
            $namaLengkap = trim($gelarDepan . ' ' . $nama . ' ' . $gelarBelakang);
        }

        return [
            'nip' => $dosenData['nip'] ?? '-',
            'nidn' => $dosenData['nidn'] ?? ($dosenData['payload']['nidn'] ?? null),
            'nik' => $dosenData['nik'] ?? null,
            'kd_pegawai' => $dosenData['kd_pegawai'] ?? null,
            'nama' => $namaLengkap ?: '-',
            'jabatan' => $dosenData['nama_jabatan'] ?? $dosenData['jabatan'] ?? '-',
            'unit_kerja' => $dosenData['unitKerja'] ?? $dosenData['unit_kerja'] ?? '-',
            'pangkat' => $dosenData['pangkat'] ?? $dosenData['nama_pangkat'] ?? '-',
            'email' => $dosenData['emailPegawai'] ?? $dosenData['email'] ?? '-',
            'noTlp' => $dosenData['noTlp'] ?? $dosenData['no_tlp'] ?? '-',
            'statusKerja' => $dosenData['nama_stspegawai'] ?? $dosenData['statusKerja'] ?? '-',
            'statusPegawai' => $dosenData['nama_stspeg'] ?? $dosenData['statusPegawai'] ?? '-',
            'levelPegawai' => $dosenData['nama_level_pegawai'] ?? $dosenData['levelPegawai'] ?? '-',
        ];
    }

    private function buildJadwalHariIni(array $mkList): array
    {
        if (!is_array($mkList) || empty($mkList)) {
            return [];
        }

        $hariIniName = strtolower(now()->locale('id')->isoFormat('dddd'));
        $hariIniNum  = now()->dayOfWeekIso; // 1=Mon .. 7=Sun

        $jadwalHari = [];
        foreach ($mkList as $mk) {
            $namaMK = $mk['mata_kuliah']['nama'] ?? ($mk['nama_mata_kuliah'] ?? '-');
            $kodeMK = $mk['mata_kuliah']['kode'] ?? ($mk['kode_mata_kuliah'] ?? '-');
            $sks    = $mk['mata_kuliah']['sks'] ?? ($mk['sks'] ?? 0);

            foreach ($mk['jadwal'] ?? [] as $jadwal) {
                // Format kelas
                $kelasList = is_array($jadwal['kelas'] ?? null) 
                    ? collect($jadwal['kelas'])->pluck('nama_kelas')->filter()->implode(', ')
                    : ($jadwal['nama_kelas'] ?? $jadwal['kelas'] ?? '-');

                foreach ($jadwal['waktu_kuliah'] ?? [] as $waktu) {
                    $hariName = strtolower($waktu['hari'] ?? '');
                    $hariNum  = (int) ($waktu['hari_numeric'] ?? 0);

                    if ($hariName === $hariIniName || ($hariNum > 0 && $hariNum === $hariIniNum)) {
                        $ruang = $waktu['ruang']['nama_ruang'] ?? ($waktu['nama_ruang'] ?? $waktu['ruang'] ?? '-');

                        $jadwalHari[] = [
                            'nama_mk' => $namaMK,
                            'kode_mk' => $kodeMK,
                            'sks'     => $sks,
                            'kelas'   => $kelasList ?: '-',
                            'jam'     => ($waktu['jam_mulai'] ?? '-') . ' - ' . ($waktu['jam_selesai'] ?? '-'),
                            'ruang'   => $ruang,
                            'mode'    => $jadwal['mode'] ?? '-',
                        ];
                    }
                }
            }
        }
        return $jadwalHari;
    }

    private function buildStatistikMengajar(array $mkList): array
    {
        $totalSKS = 0;
        $totalMK  = 0;
        $totalKelas = 0;

        foreach ($mkList as $mk) {
            if (!is_array($mk)) continue;
            $sks = (int) ($mk['mata_kuliah']['sks'] ?? $mk['sks'] ?? 0);
            $totalSKS += $sks;
            $totalMK++;
            $totalKelas += count($mk['jadwal'] ?? []);
        }

        return [
            'total_sks'   => $totalSKS,
            'total_mk'    => $totalMK,
            'total_kelas' => $totalKelas,
        ];
    }

    private function extractYear(array $item): string
    {
        $detail = $item['detail'] ?? [];

        foreach (['tahun_pelaksanaan', 'tahun_kegiatan', 'tahun_usulan', 'tahun', 'year'] as $field) {
            $val = trim((string) ($detail[$field] ?? $item[$field] ?? ''));
            if (preg_match('/^\d{4}$/', $val)) {
                return $val;
            }
        }

        foreach (['tanggal_berlaku', 'tanggal_sk_penugasan'] as $dateField) {
            $val = trim((string) ($item[$dateField] ?? $detail[$dateField] ?? ''));
            if (preg_match('/^(\d{4})-\d{2}-\d{2}/', $val, $matches)) {
                return $matches[1];
            }
        }

        return '-';
    }

    private function extractDoiAndTautan(array $item): array
    {
        $detail = $item['detail'] ?? [];
        $rawDoi = trim((string)($detail['doi'] ?? $item['doi'] ?? ''));
        $rawTautan = trim((string)($detail['tautan'] ?? $detail['url'] ?? $item['tautan'] ?? $item['url'] ?? ''));

        $doiUrl = null;
        if (!empty($rawDoi)) {
            if (str_starts_with($rawDoi, 'http://') || str_starts_with($rawDoi, 'https://')) {
                $doiUrl = $rawDoi;
            } else {
                $cleaned = trim(preg_replace('/^(doi:?\s*)/i', '', $rawDoi));
                if (preg_match('/^10\.\d{4,9}\/[-._;()\/:A-Z0-9]+/i', $cleaned)) {
                    $doiUrl = 'https://doi.org/' . $cleaned;
                }
            }
        }

        $tautanUrl = null;
        if (!empty($rawTautan) && (str_starts_with($rawTautan, 'http://') || str_starts_with($rawTautan, 'https://'))) {
            $tautanUrl = $rawTautan;
        }

        return [
            'doi'     => !empty($rawDoi) ? $rawDoi : null,
            'doi_url' => $doiUrl ?: ($tautanUrl && str_contains($tautanUrl, 'doi.org') ? $tautanUrl : null),
            'tautan'  => $tautanUrl,
        ];
    }

    private function buildPublikasi(array $publikasiData): array
    {
        return $this->buildPublikasiTerakhir($publikasiData);
    }

    private function buildPublikasiTerakhir(array $publikasiData): array
    {
        $items = $publikasiData['data'] ?? $publikasiData;
        if (!is_array($items)) return [];

        $result = [];
        foreach ($items as $item) {
            $penulisList = collect($item['anggota'] ?? [])->pluck('nama')->filter()->implode(', ');
            $detail = $item['detail'] ?? [];
            $links  = $this->extractDoiAndTautan($item);

            $result[] = [
                'judul'             => $item['judul_portofolio'] ?? $item['judul'] ?? $item['title'] ?? '-',
                'penulis'           => !empty($penulisList) ? $penulisList : ($item['penulis'] ?? $item['authors'] ?? '-'),
                'journal'           => $detail['nama_jurnal'] ?? $detail['penerbit'] ?? $item['nama_jurnal'] ?? $item['journal'] ?? $item['sources'] ?? '-',
                'penerbit'          => $detail['penerbit'] ?? $item['penerbit'] ?? '-',
                'tahun'             => $this->extractYear($item),
                'tipe'              => $detail['jenis_publikasi'] ?? $item['jenis_portofolio'] ?? $item['tipe'] ?? '-',
                'status_verifikasi' => $item['status_verifikasi_label'] ?? $item['status_verifikasi'] ?? '-',
                'doi'               => $links['doi'],
                'doi_url'           => $links['doi_url'],
                'tautan'            => $links['tautan'],
            ];
        }
        return $result;
    }

    private function buildPenelitian(array $penelitianData): array
    {
        $items = $penelitianData['data'] ?? $penelitianData;
        if (!is_array($items)) return [];

        $result = [];
        foreach ($items as $item) {
            $detail = $item['detail'] ?? [];
            $links  = $this->extractDoiAndTautan($item);

            $result[] = [
                'judul'             => $item['judul_portofolio'] ?? $item['judul'] ?? $item['title'] ?? '-',
                'tahun'             => $this->extractYear($item),
                'tipe'              => $detail['jenis_publikasi'] ?? $item['jenis_portofolio'] ?? $item['detail']['kategori_kegiatan'] ?? $item['tipe'] ?? $item['type'] ?? '-',
                'status_verifikasi' => $item['status_verifikasi_label'] ?? $item['status_verifikasi'] ?? '-',
                'lokasi'            => $detail['nama_jurnal'] ?? $detail['penerbit'] ?? $detail['lokasi'] ?? '-',
                'doi'               => $links['doi'],
                'doi_url'           => $links['doi_url'],
                'tautan'            => $links['tautan'],
            ];
        }
        return $result;
    }

    private function buildPengabdian(array $pengabdianData): array
    {
        $items = $pengabdianData['data'] ?? $pengabdianData;
        if (!is_array($items)) return [];

        $result = [];
        foreach ($items as $item) {
            $detail = $item['detail'] ?? [];
            $links  = $this->extractDoiAndTautan($item);

            $result[] = [
                'judul'             => $item['judul_portofolio'] ?? $item['judul'] ?? $item['title'] ?? '-',
                'tahun'             => $this->extractYear($item),
                'tipe'              => $detail['jenis_publikasi'] ?? $item['jenis_portofolio'] ?? $item['detail']['kategori_kegiatan'] ?? $item['tipe'] ?? $item['type'] ?? '-',
                'status_verifikasi' => $item['status_verifikasi_label'] ?? $item['status_verifikasi'] ?? '-',
                'lokasi'            => $detail['nama_jurnal'] ?? $detail['penerbit'] ?? $detail['lokasi'] ?? '-',
                'doi'               => $links['doi'],
                'doi_url'           => $links['doi_url'],
                'tautan'            => $links['tautan'],
            ];
        }
        return $result;
    }

    private function buildSintaIndexasi(array $publikasiData, array $profile = []): array
    {
        $nama = (string) ($profile['nama'] ?? '');
        $nidn = !empty($profile['nidn']) ? (string) $profile['nidn'] : null;

        if ($nama !== '' && $nama !== '-') {
            try {
                return Cache::get('sinta_author_' . md5(trim($nama) . '_' . ($nidn ?? '')), []);
            } catch (\Throwable $e) {
                Log::warning("Gagal ambil metrik SINTA untuk dosen {$nama}: " . $e->getMessage());
            }
        }

        return [
            'sinta_score' => '-',
            'sinta_score_3yr' => '-',
            'scopus' => ['dokumen' => '-', 'sitasi' => '-', 'h_index' => '-', 'i10_index' => '-', 'g_index' => '-'],
            'google_scholar' => ['dokumen' => '-', 'sitasi' => '-', 'h_index' => '-', 'i10_index' => '-', 'g_index' => '-'],
        ];
    }

    /**
     * Mulai penarikan data dosen (portofolio SIPP + penjadwalan SIAKANG) dari tombol di
     * halaman profil. Prosesnya berjalan terpisah; halaman memantau lewat syncStatus().
     * Token API tetap di .env server, jadi pengguna tidak diminta mengisi apa pun.
     */
    public function syncData(Request $request, string $nip): JsonResponse
    {
        $key = 'dosen_launch_'.hash('sha256', trim($nip).'|'.$this->getActiveSemester($request->input('semester')));
        $lock = Cache::lock($key, 10);
        if (!$lock->get()) {
            return response()->json(['success' => true, 'status' => 'diam', 'message' => 'Pembaruan sedang diminta.']);
        }
        try {
            return $this->startSyncData($request, $nip);
        } finally {
            $lock->release();
        }
    }

    private function startSyncData(Request $request, string $nip): JsonResponse
    {
        $nip = trim($nip);
        $semester = $this->getActiveSemester($request->input('semester'));

        // Identitas dosen tidak selalu 18 digit (mis. kode DLB dari SIMPEG); API SIPP dan
        // SIAKANG menerima nilainya apa adanya, jadi yang dicegah hanya identitas kosong.
        if ($nip === '') {
            return response()->json([
                'success' => false,
                'status' => 'gagal',
                'message' => 'Identitas dosen tidak tersedia.',
            ], 422);
        }

        $key = 'dosen_sync_' . $nip . '_' . $semester;
        $cooldownKey = 'dosen_sync_cooldown_' . $nip . '_' . $semester;
        $status = Cache::get($key);

        // Status "jalan" yang terlalu lama dianggap basi (mis. proses penarik mati),
        // supaya tombol tetap bisa dipakai lagi.
        $masihBerjalan = is_array($status)
            && ($status['status'] ?? '') === 'jalan'
            && isset($status['time'])
            && \Illuminate\Support\Carbon::parse($status['time'])->addMinutes(5)->greaterThanOrEqualTo(now());

        if ($masihBerjalan) {
            return response()->json([
                'success' => true,
                'status' => 'jalan',
                'message' => 'Penarikan data sedang berjalan.',
            ]);
        }

        if (!$this->dosenLauncher->isAvailable()) {
            Cache::put($key, [
                'status' => 'gagal',
                'message' => 'Host aplikasi tidak memiliki Edge/Chrome.',
                'time' => now()->toDateTimeString(),
            ], now()->addMinutes(15));

            return response()->json([
                'success' => false,
                'status' => 'gagal',
                'message' => 'Penarikan otomatis tidak tersedia di server ini (butuh Edge/Chrome di host aplikasi).',
            ], 503);
        }

        // Jeda 3 menit per dosen: mencegah peluncuran berulang akibat halaman dimuat ulang.
        // Bila dosen ini sudah diketahui belum punya data (24 jam terakhir), tidak diulang lagi.
        if (Cache::has($cooldownKey) || Cache::has('dosen_sync_empty_' . $nip . '_' . $semester)) {
            return response()->json([
                'success' => true,
                'status' => 'diam',
                'message' => 'Pembaruan baru saja diminta. Tunggu sebentar lagi.',
            ]);
        }

        Cache::put($cooldownKey, true, now()->addMinutes((int) config('satudata.swr.fresh_minutes', 20)));

        Cache::put($key, [
            'status' => 'jalan',
            'message' => 'Menarik portofolio SIPP dan penjadwalan SIAKANG...',
            'time' => now()->toDateTimeString(),
        ], now()->addMinutes(15));

        if (!$this->dosenLauncher->sync($nip, $semester, $key)) {
            Cache::put($key, [
                'status' => 'gagal',
                'message' => 'Gagal menjalankan penarik.',
                'time' => now()->toDateTimeString(),
            ], now()->addMinutes(15));

            return response()->json([
                'success' => false,
                'status' => 'gagal',
                'message' => 'Gagal menjalankan penarik data.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'status' => 'jalan',
            'message' => 'Penarikan data dimulai.',
        ], 202);
    }

    /**
     * Status penarikan data dosen, dipantau oleh tombol di halaman profil.
     */
    public function syncStatus(Request $request, string $nip): JsonResponse
    {
        $nip = trim($nip);
        $semester = $this->getActiveSemester($request->input('semester'));
        $kunciDosen = 'dosen_sync_' . $nip . '_' . $semester;
        $status = Cache::get($kunciDosen);

        // Fallback: periksa juga kunci agregat dosen_sync jika kunci spesifik belum selesai
        if (!is_array($status) || ($status['status'] ?? '') === 'jalan') {
            $agregat = Cache::get('dosen_sync');
            if (is_array($agregat) && ($agregat['status'] ?? '') === 'selesai' && str_contains($agregat['message'] ?? '', $nip)) {
                $status = $agregat;
                Cache::put($kunciDosen, $agregat, now()->addMinutes(15));
            }
        }

        if (is_array($status) && ($status['status'] ?? '') === 'jalan' && isset($status['time'])
            && \Illuminate\Support\Carbon::parse($status['time'])->addMinutes(15)->isPast()) {
            $status['status'] = 'basi';
            Cache::put($kunciDosen, $status, now()->addMinutes(15));
        }

        return response()->json([
            'success' => true,
            'status' => is_array($status) ? ($status['status'] ?? 'belum') : 'belum',
            'has_data' => is_array($status) ? ($status['has_data'] ?? null) : null,
            'message' => is_array($status) ? ($status['message'] ?? '') : '',
        ]);
    }
}
