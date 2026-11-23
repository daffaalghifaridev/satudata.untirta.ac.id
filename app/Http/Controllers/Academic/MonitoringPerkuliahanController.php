<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Services\DTO\ApiResponse;
use App\Services\Integrations\SiakangMahasiswaAktifService;
use App\Services\Integrations\SiakangMataKuliahService;
use App\Services\Integrations\SiakangPenjadwalanService;
use App\Services\Integrations\SimpegPegawaiService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MonitoringPerkuliahanController extends Controller
{
    public function __construct(
        public SiakangPenjadwalanService $penjadwalanService,
        public SimpegPegawaiService $pegawaiService,
        public SiakangMataKuliahService $mataKuliahService,
        public SiakangMahasiswaAktifService $mahasiswaAktifService,
    ) {}

    /**
     * Mapping Nama Fakultas ke String Fakultas di API /v2/mahasiswa-aktif (detail_per_prodi)
     */
    protected function getFacultyNameMapping(): array
    {
        return config('satudata.monitoring_faculties');
    }

    /**
     * Mapping prodi per fakultas/unit untuk API /v2/mata_kuliah/tingkat-prodi.
     *
     * Dipakai menghitung jumlah MK per fakultas = total MK seluruh prodinya. Endpoint
     * tingkat-fakultas tidak bisa menggantikannya: isinya hanya MK tingkat fakultas
     * (mis. FAPERTA = 6, sedangkan seluruh MK prodinya ratusan).
     */
    protected function getUnitProdiMapping(): array
    {
        return config('satudata.monitoring_prodis');
    }

    /**
     * Deteksi Kode Semester Terkini secara otomatis jika tidak ada input parameter.
     */
    protected function getKodeSemesterTerkini(?string $requestedSemester = null): string
    {
        if (!empty($requestedSemester)) {
            return $requestedSemester;
        }

        $now = now();
        $year = $now->year;
        $month = $now->month;

        if ($month < 8) {
            return ($year - 1) . '2';
        }

        return $year . '1';
    }

    /**
     * Format label nama semester (misal 20261 -> 2026/2027 Gasal).
     */
    protected function getNamaSemesterLabel(string $kodeSemester): string
    {
        $tahun = substr($kodeSemester, 0, 4);
        $jenisDigit = substr($kodeSemester, 4, 1);
        $tahunPlus = ((int) $tahun) + 1;

        if ($jenisDigit === '2') {
            return "{$tahun}/{$tahunPlus} Genap";
        }

        return "{$tahun}/{$tahunPlus} Gasal";
    }

    public function index(Request $request): View
    {
        $semester = $this->getKodeSemesterTerkini($request->input('semester'));
        $rows = \Illuminate\Support\Facades\DB::table('perkuliahan_summaries')->where('semester', $semester)->get(['kode_prodi', 'fakultas', 'jumlah_mk', 'jumlah_jadwal', 'total_sks', 'sks_teori', 'sks_praktik', 'details_synced_at', 'source_synced_at']);
        $monitoringData = [];
        foreach ($this->getFacultyNameMapping() as $code => $name) {
            $codes = $this->getUnitProdiMapping()[$code] ?? [];
            $group = $rows->filter(fn ($row) => $row->fakultas ? $row->fakultas === $name : in_array($row->kode_prodi, $codes, true));
            if ($code === 'MKU' && $group->isEmpty()) {
                continue;
            }
            $monitoringData[] = [
                'no' => count($monitoringData) + 1, 'unit_kode' => $code, 'unit_nama' => $name,
                'jumlah_mk' => $group->isEmpty() || $group->contains(fn ($row) => $row->jumlah_mk === null) ? null : $group->sum('jumlah_mk'), 'jumlah_jadwal' => $group->isEmpty() ? null : $group->sum('jumlah_jadwal'),
                'total_sks' => $group->isEmpty() || $group->contains(fn ($row) => $row->total_sks === null) ? null : $group->sum('total_sks'), 'sks_teori' => $group->isEmpty() || $group->contains(fn ($row) => $row->sks_teori === null) ? null : $group->sum('sks_teori'),
                'sks_praktik' => $group->isEmpty() || $group->contains(fn ($row) => $row->sks_praktik === null) ? null : $group->sum('sks_praktik'), 'rerata_pertemuan' => null,
            ];
        }

        return view('academic.monitoring-perkuliahan', [
            'title' => 'Monitoring Perkuliahan', 'semester' => $semester,
            'semesterInfo' => ['kode_semester' => $semester, 'nama_semester' => $this->getNamaSemesterLabel($semester)],
            'monitoringData' => $monitoringData, 'filterNip' => $request->input('nip'),
            'totalJadwal' => $rows->isEmpty() ? null : $rows->sum('jumlah_jadwal'),
            'totalMK' => $rows->isEmpty() || $rows->contains(fn ($row) => $row->jumlah_mk === null) ? null : $rows->sum('jumlah_mk'),
            'totalSKS' => $rows->isEmpty() || $rows->contains(fn ($row) => $row->total_sks === null) ? null : $rows->sum('total_sks'),
            'diperbarui_pada' => $rows->min('source_synced_at'),
        ]);
    }

    public function detail(Request $request, string $unitKode): View
    {
        $semester = $this->getKodeSemesterTerkini($request->input('semester'));
        $name = $this->getFacultyNameMapping()[$unitKode] ?? ($unitKode === 'MKU' ? 'Mata Kuliah Umum' : null);
        abort_if($name === null, 404);
        $unit = ['kode' => $unitKode, 'nama' => $name];
        $selectedKodeProdi = $request->input('kode_prodi');
        $filterNip = $request->input('nip');
        $unitCodes = $this->getUnitProdiMapping()[$unitKode] ?? [];
        $rows = \Illuminate\Support\Facades\DB::table('perkuliahan_summaries')
            ->where('semester', $semester)
            ->where(function ($q) use ($name, $unitCodes) {
                $q->where('fakultas', $name);
                if ($unitCodes !== []) {
                    $q->orWhere(fn ($sub) => $sub->whereNull('fakultas')->whereIn('kode_prodi', $unitCodes));
                }
            })
            ->get(['kode_prodi', 'nama_prodi', 'fakultas', 'jumlah_mk', 'jumlah_jadwal', 'total_sks', 'sks_teori', 'sks_praktik', 'details_synced_at', 'source_synced_at']);
        $base = [
            'semester' => $semester, 'unit' => $unit, 'diperbarui_pada' => $rows->min('source_synced_at'),
            'semesterInfo' => ['kode_semester' => $semester, 'nama_semester' => $this->getNamaSemesterLabel($semester)],
            'search' => trim((string) $request->input('search', '')),
            'perPage' => max(1, min(100, (int) $request->input('per_page', 10))),
        ];
        if (!$selectedKodeProdi && $unitKode !== 'MKU') {
            $items = $rows->map(fn ($row) => [
                'kode_prodi' => $row->kode_prodi, 'nama_prodi' => $row->nama_prodi ?? '-', 'jenjang' => null,
                'jumlah_mk' => $row->jumlah_mk, 'jumlah_jadwal' => $row->jumlah_jadwal,
                'total_sks' => $row->total_sks, 'sks_teori' => $row->sks_teori, 'sks_praktik' => $row->sks_praktik, 'rerata_pertemuan' => null,
                'aksi_url' => route('akademik.perkuliahan.detail', ['unitKode' => $unitKode, 'semester' => $semester, 'kode_prodi' => $row->kode_prodi]),
            ])->values()->all();
            return view('academic.monitoring-perkuliahan-detail', $base + [
                'title' => 'Monitoring Perkuliahan - '.$name, 'viewType' => 'prodi_list',
                'prodiRows' => $this->summaryPaginator($items, $request), 'totalProdi' => count($items),
                'totalMK' => $rows->contains(fn ($row) => $row->jumlah_mk === null) ? null : $rows->sum('jumlah_mk'), 'totalSKS' => $rows->contains(fn ($row) => $row->total_sks === null) ? null : $rows->sum('total_sks'),
            ]);
        }
        $selected = $rows->firstWhere('kode_prodi', $selectedKodeProdi);
        $base['diperbarui_pada'] = $selected?->details_synced_at;
        $snapshot = $selected ? \Illuminate\Support\Facades\DB::table('perkuliahan_summaries')->where('semester', $semester)->where('kode_prodi', $selectedKodeProdi)->value('jadwals') : null;
        $items = $snapshot ? json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR) : [];
        $jadwalIds = array_values(array_filter(array_column($items, 'id_jadwal')));
        $schedules = $jadwalIds === []
            ? collect()
            : \Illuminate\Support\Facades\DB::table('dosen_jadwals')
                ->where('semester', $semester)
                ->whereIn('jadwal_id', $jadwalIds)
                ->get()
                ->groupBy('jadwal_id');
        $dosenVersion = Cache::get('pegawai.snapshot_version', '');
        $dosen = collect(Cache::remember('monitoring.dosen_lookup.v2.' . $dosenVersion, now()->addMinutes(20), function () {
            return \Illuminate\Support\Facades\DB::table('pegawais')
                ->select('nip', 'nama', 'gelar_depan', 'gelar_belakang')
                ->where(function ($q) {
                    $q->where('level_pegawai', 'like', '%Dosen%')
                        ->orWhere('jabatan', 'like', '%Dosen%')
                        ->orWhere('jabatan', 'like', '%Guru Besar%')
                        ->orWhere('jabatan', 'like', '%Profesor%')
                        ->orWhere('jabatan', 'like', '%Lektor%')
                        ->orWhere('jabatan', 'like', '%Asisten Ahli%')
                        ->orWhere('jabatan', 'like', '%Tenaga Pengajar%');
                })
                ->get()
                ->mapWithKeys(function ($row) {
                    $nip = trim((string) $row->nip);
                    $lengkap = trim(($row->gelar_depan ? $row->gelar_depan . ' ' : '') . $row->nama . ($row->gelar_belakang ? ', ' . $row->gelar_belakang : ''));
                    return [
                        $nip => [
                            'nip' => $nip,
                            'nama' => $row->nama,
                            'nama_pegawai_lengkap' => $lengkap !== '' ? $lengkap : $row->nama,
                        ],
                    ];
                })
                ->all();
        }));
        $details = [];
        foreach ($items as $item) {
            $teachers = $schedules->get($item['id_jadwal'], collect());
            $assigned = collect($item['pengampu'] ?? []);
            $nips = $assigned->pluck('nip')->merge($teachers->pluck('nip'))->filter()->unique();
            if ($filterNip && !$nips->contains($filterNip)) {
                continue;
            }
            $names = $assigned->map(fn ($teacher) => $dosen->get($teacher['nip'] ?? '')['nama_pegawai_lengkap'] ?? ($teacher['nama'] ?? null))
                ->merge($nips->map(fn ($nip) => $dosen->get($nip)['nama_pegawai_lengkap'] ?? null))->filter()->unique();
            $times = isset($item['waktu_kuliah']) ? collect($item['waktu_kuliah']) : $teachers->map(fn ($row) => [
                'hari' => $row->hari, 'jam_mulai' => $row->jam_mulai, 'jam_selesai' => $row->jam_selesai,
                'ruang' => ['nama_ruang' => $row->nama_ruang],
            ]);
            $details[] = [
                'kode_mk' => $item['kode_mk'] ?? '-', 'nama_mk' => $item['matakuliah'] ?? '-', 'sks' => $item['sks'] ?? null,
                'sks_teori' => $item['sks_teori'] ?? null, 'sks_praktik' => $item['sks_praktik'] ?? null,
                'kurikulum' => $item['kurikulum'] ?? null, 'kode_jadwal' => $item['kode_jadwal'] ?? '-',
                'kelas' => collect($item['kelas'] ?? [])->pluck('nama_kelas')->filter()->implode(', ') ?: '-',
                'nip_dosen' => $nips->implode(', ') ?: '-',
                'nama_dosen' => $names->implode(', ') ?: '-',
                'jam_kuliah' => $times->filter(fn ($time) => !empty($time['hari']) || !empty($time['jam_mulai']) || !empty($time['jam_selesai']))
                    ->map(fn ($time) => trim(($time['hari'] ?? '').' '.($time['jam_mulai'] ?? '-').' - '.($time['jam_selesai'] ?? '-')))->unique()->implode(', ') ?: '-',
                'ruang' => $times->map(fn ($time) => data_get($time, 'ruang.nama_ruang'))->filter()->unique()->implode(', ') ?: '-',
            ];
        }
        return view('academic.monitoring-perkuliahan-detail', $base + [
            'title' => 'Monitoring Perkuliahan - Detail Jadwal', 'viewType' => 'mk_list',
            'selectedKodeProdi' => $selectedKodeProdi, 'selectedProdiName' => $selected->nama_prodi ?? '-',
            'jadwalRows' => $this->summaryPaginator($details, $request), 'filterNip' => $filterNip,
            'totalJadwal' => count($details), 'totalSKS' => collect($details)->contains(fn ($row) => $row['sks'] === null) ? null : array_sum(array_column($details, 'sks')),
            'allDosenList' => $dosen->values()->all(),
        ]);
    }

    private function summaryPaginator(array $items, Request $request): LengthAwarePaginator
    {
        $search = mb_strtolower(trim((string) $request->input('search', '')));
        if ($search !== '') {
            $items = array_values(array_filter($items, fn ($row) => str_contains(mb_strtolower(implode(' ', array_filter($row, 'is_scalar'))), $search)));
        }
        $perPage = max(1, min(100, (int) $request->input('per_page', 10)));
        $page = max(1, (int) $request->input('page', 1));

        return new LengthAwarePaginator(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]);
    }

}
