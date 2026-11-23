<?php

namespace App\Services\Integrations;

use App\Services\DTO\ApiResponse;
use Illuminate\Support\Facades\Cache;

class SimpegPegawaiService extends AbstractApiClient
{
    protected function serviceName(): string
    {
        return 'simpeg.pegawai';
    }

    protected function config(): array
    {
        $rawBase = (string) config('services.simpeg.base_url');
        $baseUrl = preg_replace('#/(pegawai|employee|all-pegawai2)/?$#i', '', rtrim($rawBase, '/'));

        return [
            'base_url' => $baseUrl,
            'auth_type' => 'api_key_header',
            'api_key_header' => config('services.simpeg.key'),
            'api_key_value' => config('services.simpeg.value'),
            // Timeout dialokasikan agar saat cache & database kosong, data 1900+ pegawai tidak terputus di tengah jalan
            'connect_timeout' => 10,
            'timeout' => 60,
        ];
    }

    /**
     * Ambil pemetaan NIP => unitKerja, email, telp, pangkat dari database lokal (Local-First),
     * lalu defer sync API /pegawai di latar belakang.
     */
    public function getPegawaiUnitMap(): array
    {
        return Cache::remember('simpeg_nip_unit_map', now()->addHours(6), function () {
            // 1. LOCAL-FIRST: Cek database pegawais terlebih dahulu
            try {
                $dbRows = \App\Models\Pegawai::whereNotNull('unit_kerja')->get();
                if ($dbRows->count() > 0) {
                    $map = [];
                    foreach ($dbRows as $row) {
                        $nip = trim((string)$row->nip);
                        if (!empty($nip)) {
                            $map[$nip] = [
                                'unitKerja'     => $row->unit_kerja,
                                'unitKerja_id'  => $row->unit_kerja_id,
                                'email'         => $row->email,
                                'noTlp'         => $row->no_tlp,
                                'pangkat'       => $row->pangkat,
                                'pangkat_id'    => $row->pangkat_id,
                                'idSDM'         => $row->id_sdm,
                            ];
                        }
                    }
                    if (!empty($map)) {
                        return $map;
                    }
                }
            } catch (\Throwable $e) {}

            // 2. Fallback jika database belum ada data
            try {
                $response = $this->get('/all-pegawai2');
                if ($response->success && !empty($response->data)) {
                    $items = $response->data;
                    if (isset($items['data']) && is_array($items['data'])) {
                        $items = $items['data'];
                    }
                    if (is_array($items) && !empty($items)) {
                        $map = [];
                        foreach ($items as $p) {
                            $nip = trim((string)($p['nip'] ?? ''));
                            if (!empty($nip)) {
                                $map[$nip] = [
                                    'unitKerja'     => $p['unitKerja'] ?? $p['unit_kerja'] ?? null,
                                    'unitKerja_id'  => $p['unitKerja_id'] ?? $p['unit_kerja_id'] ?? null,
                                    'email'         => $p['emailPegawai'] ?? $p['email'] ?? null,
                                    'noTlp'         => $p['noTlp'] ?? $p['no_tlp'] ?? null,
                                    'pangkat'       => $p['pangkat'] ?? $p['nama_pangkat'] ?? null,
                                    'pangkat_id'    => $p['pangkat_id'] ?? null,
                                    'idSDM'         => $p['idSDM'] ?? $p['id_sdm'] ?? null,
                                ];
                            }
                        }
                        if (!empty($map)) {
                            return $map;
                        }
                    }
                }
            } catch (\Throwable $e) {}

            return [];
        });
    }

    /**
     * Ambil data pegawai dari database lokal terlebih dahulu (Local-First),
     * lalu picu background sync (defer) ke API SIMPEG untuk sinkronisasi pembaruan.
     */
    public function getData(array $params = []): ApiResponse
    {
        $query = \App\Models\Pegawai::query();
        if (!empty($params['nip'])) {
            $query->where('nip', trim((string) $params['nip']));
        }
        $read = fn () => $this->normalizePegawaiList($query->get()->map(fn ($item) => $this->mapDbPegawaiToItem($item))->all());
        if (empty($params['nip'])) {
            $version = \App\Models\Pegawai::selectRaw('COUNT(*) as jumlah, MAX(updated_at) as diperbarui')->first();
            $key = 'simpeg.local.v2.'.hash('sha256', $version->jumlah.'|'.$version->diperbarui.'|'.Cache::get('pegawai.snapshot_version', ''));
            $items = Cache::remember($key, now()->addMinutes(20), $read);
        } else {
            $items = $read();
        }

        return new ApiResponse(success: true, status: 200, message: 'Data pegawai lokal', data: $items);
    }

    /**
     * Ambil data dosen (Local-First dari database lokal pegawais, lalu defer sync API).
     */
    public function getDataDosen(string $endpoint = '', array $params = []): ApiResponse
    {
        $items = $this->hasilFallbackDataDosen();
        if (!empty($params['nip'])) {
            $items = array_values(array_filter($items, fn ($item) => $item['nip'] === trim((string) $params['nip'])));
        }

        return new ApiResponse(success: true, status: 200, message: 'Data dosen lokal', data: $items);
    }

    /**
     * Revalidasi data pegawai dari API SIMPEG dan update ke database MySQL pegawais
     */
    public function syncPegawaiFromApi(string $cacheKey, array $params = []): array
    {
        @set_time_limit(120); // Alokasi waktu cukup untuk fetch dan batch upsert 1900+ pegawai
        $response = $this->get('/all-pegawai2', $params);
        if (!$response->success || empty($response->data)) {
            return [];
        }

        $items = $response->data;
        if (isset($items['data']) && is_array($items['data'])) {
            $items = $items['data'];
        }

        if (!is_array($items) || empty($items)) {
            return [];
        }

        $items = $this->normalizePegawaiList($items);
        $unitMap = $this->getPegawaiUnitMap();
        if (!empty($unitMap)) {
            foreach ($items as &$item) {
                $nip = trim((string)($item['nip'] ?? ''));
                if ($nip && isset($unitMap[$nip])) {
                    $u = $unitMap[$nip];
                    if (empty($item['unitKerja']) && !empty($u['unitKerja'])) {
                        $item['unitKerja'] = $u['unitKerja'];
                        $item['unit_kerja'] = $u['unitKerja'];
                    }
                    if (empty($item['unitKerja_id']) && !empty($u['unitKerja_id'])) {
                        $item['unitKerja_id'] = $u['unitKerja_id'];
                        $item['unit_kerja_id'] = $u['unitKerja_id'];
                    }
                    if (empty($item['email']) && !empty($u['email'])) {
                        $item['email'] = $u['email'];
                        $item['emailPegawai'] = $u['email'];
                    }
                    if (empty($item['noTlp']) && !empty($u['noTlp'])) {
                        $item['noTlp'] = $u['noTlp'];
                        $item['no_tlp'] = $u['noTlp'];
                    }
                    if (empty($item['pangkat']) && !empty($u['pangkat'])) {
                        $item['pangkat'] = $u['pangkat'];
                        $item['nama_pangkat'] = $u['pangkat'];
                    }
                    if (empty($item['idSDM']) && !empty($u['idSDM'])) {
                        $item['idSDM'] = $u['idSDM'];
                        $item['id_sdm'] = $u['idSDM'];
                    }
                }
            }
            unset($item);
        }

        $normalizedData = $this->normalizePegawaiList($items);
        if (empty($normalizedData)) {
            return [];
        }

        // Sinkronisasi pembaruan ke database MySQL pegawais
        try {
            $batch = [];
            $now = now();
            foreach ($normalizedData as $item) {
                $nip = trim((string)($item['nip'] ?? ''));
                if (!$nip) continue;

                $batch[] = [
                    'nip'            => $nip,
                    'kode_data'      => $item['kd_pegawai'] ?? $item['kodeData'] ?? null,
                    'id_sdm'         => $item['id_sdm'] ?? $item['idSDM'] ?? null,
                    'nama'           => $item['namaPegawai'] ?? $item['nama_pegawai'] ?? $item['nama'] ?? '-',
                    'gelar_depan'    => $item['gelar_depan'] ?? $item['gelarDepan'] ?? null,
                    'gelar_belakang' => $item['gelar_belakang'] ?? $item['gelarBelakang'] ?? null,
                    'email'          => $item['emailPegawai'] ?? $item['email'] ?? null,
                    'no_tlp'         => $item['noTlp'] ?? $item['no_tlp'] ?? null,
                    'unit_kerja'     => $item['unitKerja'] ?? $item['unit_kerja'] ?? null,
                    'unit_kerja_id'  => $item['unitKerja_id'] ?? $item['unit_kerja_id'] ?? null,
                    'jabatan'        => $item['nama_jabatan'] ?? $item['jabatan'] ?? null,
                    'jabatan_id'     => $item['jabatan_id'] ?? null,
                    'pangkat'        => $item['pangkat'] ?? $item['nama_pangkat'] ?? null,
                    'pangkat_id'     => $item['pangkat_id'] ?? null,
                    'status_kerja'   => $item['nama_stspegawai'] ?? $item['statusKerja'] ?? null,
                    'level_pegawai'  => $item['nama_level_pegawai'] ?? $item['levelPegawai'] ?? null,
                    'payload'        => json_encode($item),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }

            if (!empty($batch)) {
                foreach (array_chunk($batch, 150) as $chunk) {
                    \App\Models\Pegawai::upsert(
                        $chunk,
                        ['nip'],
                        ['kode_data', 'id_sdm', 'nama', 'gelar_depan', 'gelar_belakang', 'email', 'no_tlp', 'unit_kerja', 'unit_kerja_id', 'jabatan', 'jabatan_id', 'pangkat', 'pangkat_id', 'status_kerja', 'level_pegawai', 'payload', 'updated_at']
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal sinkronisasi data pegawai ke database: " . $e->getMessage());
        }

        try {
            Cache::put($cacheKey, $normalizedData, now()->addHours(6));
        } catch (\Throwable $e) {
            try {
                Cache::store('file')->put($cacheKey, $normalizedData, now()->addHours(6));
            } catch (\Throwable $f) {}
        }

        return $normalizedData;
    }

    /**
     * Revalidasi data dosen dari API SIMPEG
     */
    public function syncDosenFromApi(string $cacheKey, array $params = []): array
    {
        $employeeCacheKey = 'simpeg_employee_all_' . md5(json_encode($params));
        $items = $this->syncPegawaiFromApi($employeeCacheKey, $params);

        if (!empty($items)) {
            $dosenList = collect($items)
                ->filter(function ($item) {
                    $level = trim((string)($item['nama_level_pegawai'] ?? $item['levelPegawai'] ?? ''));
                    $levelId = trim((string)($item['level_pegawai'] ?? ''));
                    $jabatan = trim((string)($item['nama_jabatan'] ?? $item['jabatan'] ?? ''));

                    return $levelId === '3'
                        || strcasecmp($level, 'Dosen') === 0 
                        || stripos($level, 'Dosen') !== false 
                        || stripos($jabatan, 'Guru Besar') !== false
                        || stripos($jabatan, 'Lektor') !== false
                        || stripos($jabatan, 'Asisten Ahli') !== false
                        || stripos($jabatan, 'Tenaga Pengajar') !== false;
                })
                ->values()
                ->toArray();

            if (!empty($dosenList)) {
                try {
                    Cache::put($cacheKey, $dosenList, now()->addHours(6));
                } catch (\Throwable $e) {
                    try {
                        Cache::store('file')->put($cacheKey, $dosenList, now()->addHours(6));
                    } catch (\Throwable $f) {}
                }
                return $dosenList;
            }
        }

        return [];
    }

    /**
     * Data dosen dinamis dari record database `pegawais` (jika ada).
     * Tanpa array statis / hardcoded.
     */
    private function hasilFallbackDataDosen(): array
    {
        try {
            if (\App\Models\Pegawai::count() === 0) {
                return [];
            }

            $rows = \App\Models\Pegawai::where(function ($q) {
                $q->where('level_pegawai', 'like', '%Dosen%')
                  ->orWhere('jabatan', 'like', '%Dosen%')
                  ->orWhere('jabatan', 'like', '%Guru Besar%')
                  ->orWhere('jabatan', 'like', '%Profesor%')
                  ->orWhere('jabatan', 'like', '%Lektor%')
                  ->orWhere('jabatan', 'like', '%Asisten Ahli%')
                  ->orWhere('jabatan', 'like', '%Tenaga Pengajar%');
            })->get();

            $list = $rows->map(fn($item) => $this->mapDbPegawaiToItem($item))->toArray();
            return $this->normalizePegawaiList($list);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Map model database Pegawai ke array standar untuk cache dan normalisasi.
     */
    public function mapDbPegawaiToItem(\App\Models\Pegawai $item): array
    {
        $payload = $this->normalizePegawaiItem((array) $item->payload);

        return array_replace($payload, [
            'kodeData'           => $item->kode_data,
            'kd_pegawai'         => $item->kode_data,
            'nip'                => $item->nip,
            'idSDM'              => $item->id_sdm,
            'id_sdm'             => $item->id_sdm,
            'namaPegawai'        => $item->nama,
            'nama'               => $item->nama,
            'nama_pegawai_lengkap'=> trim(($item->gelar_depan ? $item->gelar_depan . ' ' : '') . $item->nama . ($item->gelar_belakang ? ', ' . $item->gelar_belakang : '')),
            'gelarDepan'         => $item->gelar_depan,
            'gelar_depan'        => $item->gelar_depan,
            'gelarBelakang'      => $item->gelar_belakang,
            'gelar_belakang'     => $item->gelar_belakang,
            'emailPegawai'       => $item->email,
            'email'              => $item->email,
            'noTlp'              => $item->no_tlp,
            'no_tlp'             => $item->no_tlp,
            'unitKerja'          => $item->unit_kerja,
            'unit_kerja'         => $item->unit_kerja,
            'unitKerja_id'       => $item->unit_kerja_id,
            'unit_kerja_id'      => $item->unit_kerja_id,
            'jabatan'            => $item->jabatan,
            'nama_jabatan'       => $item->jabatan,
            'jabatan_id'         => $item->jabatan_id,
            'pangkat'            => $item->pangkat,
            'nama_pangkat'       => $item->pangkat,
            'pangkat_id'         => $item->pangkat_id,
            'statusKerja'        => $item->status_kerja,
            'nama_stspegawai'    => $item->status_kerja,
            'levelPegawai'       => $item->level_pegawai,
            'nama_level_pegawai' => $item->level_pegawai,
        ]);
    }

    /**
     * Normalisasi list data pegawai agar kompatibel dengan camelCase dan snake_case
     */
    public function normalizePegawaiList(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        return array_values(array_map([$this, 'normalizePegawaiItem'], $data));
    }

    /**
     * Normalisasi satu record pegawai
     */
    public function normalizePegawaiItem(mixed $item): array
    {
        if (!is_array($item)) {
            return (array) $item;
        }

        $nama = $item['nama'] ?? $item['nama_pegawai'] ?? $item['namaPegawai'] ?? '';
        $gelarDepan = $item['gelar_depan'] ?? $item['gelarDepan'] ?? '';
        $gelarBelakang = $item['gelar_belakang'] ?? $item['gelarBelakang'] ?? '';

        $namaLengkap = $item['nama_pegawai_lengkap'] ?? '';
        if (empty($namaLengkap)) {
            $namaLengkap = trim($gelarDepan . ' ' . $nama . ($gelarBelakang ? ', ' . $gelarBelakang : ''));
        }
        if (empty($namaLengkap)) {
            $namaLengkap = $nama;
        }

        $statusKerja = $item['nama_stspegawai'] ?? $item['statusKerja'] ?? $item['status_kerja'] ?? $item['kategoriPegawai'] ?? '';
        $statusPegawai = $item['nama_stspeg'] ?? $item['statusPegawai'] ?? $item['status_pegawai'] ?? '';
        $levelPegawai = $item['nama_level_pegawai'] ?? $item['levelPegawai'] ?? $item['level_pegawai'] ?? $item['jenisPegawai'] ?? '';
        $jabatan = $item['nama_jabatan'] ?? $item['jabatan'] ?? $item['jabatanFungsional'] ?? '';
        $unitKerja = $item['unit_kerja'] ?? $item['unitKerja'] ?? $item['namaUnitkerja'] ?? '';
        $email = $item['email'] ?? $item['emailPegawai'] ?? '';
        $noTlp = $item['no_tlp'] ?? $item['noTlp'] ?? $item['noTelp'] ?? '';
        $pangkat = $item['pangkat'] ?? $item['nama_pangkat'] ?? '';

        $item['nama'] = $nama;
        $item['namaPegawai'] = $nama;
        $item['namaLengkap'] = $namaLengkap;
        $item['nama_pegawai'] = $nama;
        $item['nama_pegawai_lengkap'] = $namaLengkap;
        $item['gelarDepan'] = $gelarDepan;
        $item['gelar_depan'] = $gelarDepan;
        $item['gelarBelakang'] = $gelarBelakang;
        $item['gelar_belakang'] = $gelarBelakang;
        $item['statusKerja'] = $statusKerja;
        $item['status_kerja'] = $statusKerja;
        $item['nama_stspegawai'] = $statusKerja;
        $item['statusPegawai'] = $statusPegawai;
        $item['status_pegawai'] = $statusPegawai;
        $item['nama_stspeg'] = $statusPegawai;
        $item['levelPegawai'] = $levelPegawai;
        $item['level_pegawai'] = $item['level_pegawai'] ?? $levelPegawai;
        $item['nama_level_pegawai'] = $levelPegawai;
        $item['jabatan'] = $jabatan;
        $item['nama_jabatan'] = $jabatan;
        $item['unitKerja'] = $unitKerja;
        $item['unit_kerja'] = $unitKerja;
        $item['unit_kerja_id'] = $item['unit_kerja_id'] ?? $item['unitKerja_id'] ?? $item['kdUnitKerja'] ?? null;
        $item['unitKerja_id'] = $item['unit_kerja_id'];
        $item['jabatan_id'] = $item['jabatan_id'] ?? $item['kdJabatanfungsional'] ?? null;
        $item['email'] = $email;
        $item['emailPegawai'] = $email;
        $item['noTlp'] = $noTlp;
        $item['no_tlp'] = $noTlp;
        $item['pangkat'] = $pangkat;
        $item['nip'] = trim((string)($item['nip'] ?? ''));
        $item['nik'] = trim((string)($item['nik'] ?? ''));
        $item['kd_pegawai'] = trim((string)($item['kd_pegawai'] ?? $item['kodeData'] ?? $item['kdPegawai'] ?? ''));
        $item['kodeData'] = $item['kd_pegawai'];

        return $item;
    }

}
