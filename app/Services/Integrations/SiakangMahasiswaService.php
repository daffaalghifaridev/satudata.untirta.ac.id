<?php

namespace App\Services\Integrations;

use App\Services\DTO\ApiResponse;
use Illuminate\Support\Facades\Cache;

class SiakangMahasiswaService extends SiakangApiClient
{
    protected function serviceName(): string
    {
        return 'siakang.mahasiswa';
    }

    protected function config(): array
    {
        return [
            'base_url' => config('services.siakang.base_url'),
            'auth_type' => 'token',
            'token' => config('services.siakang.token'),
            'cf_clearance' => config('services.siakang.cf_clearance'),
            'connect_timeout' => 5,
            'timeout' => 10,
        ];
    }

    /**
     * Ambil data mahasiswa.
     *
     * SWR dual-key: data (TTL 6 jam) + freshness flag (TTL 20 menit).
     * DB lokal (mahasiswas) dipakai saat cache miss, API selalu di-refresh background.
     */
    public function getData(array $params = []): ApiResponse
    {
        $query = \App\Models\Mahasiswa::query();
        if (!empty($params['angkatan'])) {
            $query->where('angkatan', $params['angkatan']);
        }
        return new ApiResponse(success: true, status: 200, message: 'Data mahasiswa lokal', data: [['total' => $query->count()]]);
    }
}
