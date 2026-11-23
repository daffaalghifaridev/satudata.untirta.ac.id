<?php

namespace App\Services\Integrations;

use App\Services\DTO\ApiResponse;
use Illuminate\Support\Facades\Cache;

class SiakangMahasiswaAktifService extends SiakangApiClient
{
    protected function serviceName(): string
    {
        return 'siakang.mahasiswa_aktif';
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
     * Ambil data mahasiswa aktif.
     *
     * SWR dual-key: data (TTL 6 jam) + freshness flag (TTL 20 menit).
     * DB lokal dipakai saat cache miss; API selalu di-refresh di background.
     */
    public function getData(array $params = []): ApiResponse
    {
        $semester = (string) ($params['semester'] ?? '');
        $data = app(\App\Services\PageSummaryReader::class)->mahasiswa($semester, 'aktif');

        return new ApiResponse(success: true, status: 200, message: 'Statistik mahasiswa lokal', data: $data);
    }

}
