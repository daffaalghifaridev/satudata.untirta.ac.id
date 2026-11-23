<?php

namespace App\Services\Integrations;

use App\Services\DTO\ApiResponse;
use Illuminate\Support\Facades\Cache;

class SiakangLulusanService extends SiakangApiClient
{
    protected function serviceName(): string
    {
        return 'siakang.lulusan';
    }

    protected function config(): array
    {
        return [
            'base_url' => config('services.siakang.base_url'),
            'auth_type' => 'token',
            'token' => config('services.siakang.token'),
            'cf_clearance' => config('services.siakang.cf_clearance'),
            'connect_timeout' => 3,
            'timeout' => 8,
        ];
    }

    /**
     * Ambil data ringkasan mahasiswa lulus.
     *
     * SWR dual-key: data (TTL 6 jam) + freshness flag (TTL 20 menit).
     * DB lokal dipakai saat cache miss; API selalu di-refresh di background.
     */
    public function getData(array $params = []): ApiResponse
    {
        $semester = (string) ($params['semester'] ?? '');
        $data = app(\App\Services\PageSummaryReader::class)->mahasiswa($semester, 'lulus');

        return new ApiResponse(success: true, status: 200, message: 'Statistik mahasiswa lokal', data: $data);
    }

    public function getListMahasiswa(array $params = []): ApiResponse
    {
        $key = 'lulusan.list.'.md5(json_encode($params).Cache::get('lulusan.snapshot_version', ''));
        $data = Cache::remember($key, now()->addMinutes(20), fn () => $this->hasilFallbackListLulusan($params));

        return new ApiResponse(success: true, status: 200, message: 'Daftar lulusan lokal', data: $data);
    }

    public function localStatus(): array
    {
        return Cache::remember('lulusan.local_status', now()->addMinutes(20), function () {
            $row = \App\Models\Mahasiswa::where('is_lulus', true)
                ->selectRaw('count(*) as jumlah, min(updated_at) as diperbarui_pada')->first();

            return ['is_empty' => (int) $row->jumlah === 0, 'updated_at' => $row->diperbarui_pada];
        });
    }

    private function hasilFallbackListLulusan(array $params = []): array
    {
        $search = strtolower(trim((string)($params['search'] ?? '')));
        $kodeProdi = strtolower(trim((string)($params['kode_prodi'] ?? '')));
        $angkatan = (string)($params['angkatan'] ?? '');
        $tahunLulus = (string)($params['tahun_lulus'] ?? '');
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 25);

        try {
            if (class_exists(\App\Models\Mahasiswa::class) && \App\Models\Mahasiswa::exists()) {
                $query = \App\Models\Mahasiswa::with('prodi')->where('is_lulus', true);

                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                          ->orWhere('nim', 'like', "%{$search}%");
                    });
                }

                if ($kodeProdi !== '') {
                    $query->whereHas('prodi', function ($q) use ($kodeProdi) {
                        $q->where('kode_prodi', 'like', "%{$kodeProdi}%")
                          ->orWhere('nama_prodi', 'like', "%{$kodeProdi}%");
                    });
                }

                if ($angkatan !== '') {
                    $query->where('angkatan', $angkatan);
                }

                if ($tahunLulus !== '') {
                    // Filter tahun lulus memakai kolom `lulus_pada` (hasil sync). Dulu
                    // di-approximate dari angkatan (tahun - 4 / - 3), sehingga baris yang
                    // tampil bisa bukan lulusan tahun yang diminta.
                    $query->whereBetween('lulus_pada', [$tahunLulus . '-01-01', $tahunLulus . '-12-31']);
                }

                $total = $query->count();
                $items = $query->skip(($page - 1) * $limit)->take($limit)->get();

                $mappedItems = $items->map(function ($mhs) {
                    return [
                        'nim' => $mhs->nim,
                        'nama' => $mhs->nama,
                        'prodi' => [
                            // Nama/kode prodi dari relasi `prodis`; tanpa tebakan.
                            'nama_prodi' => $mhs->prodi->nama_prodi ?? '-',
                            'kode_prodi' => $mhs->prodi->kode_prodi ?? '-',
                        ],
                        'angkatan' => $mhs->angkatan,
                        // Tanggal lulus dari kolom `lulus_pada`; bila belum ada tampil
                        // kosong, tidak dihitung dari angkatan + masa studi.
                        'tanggal_lulus' => $mhs->lulus_pada,
                    ];
                })->toArray();


                return [
                    0 => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $total,
                        'data' => $mappedItems,
                    ]
                ];
            }
        } catch (\Throwable $e) {
            // Log or ignore DB query exception
        }

        return [
            0 => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => 0,
                'data' => [],
            ]
        ];
    }
}
