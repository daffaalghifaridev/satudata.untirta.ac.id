<?php

namespace App\Services\Sync;

use App\Models\Mahasiswa;
use App\Services\Integrations\SiakangMahasiswaService;
use Illuminate\Support\Facades\DB;

class MahasiswaSyncService
{
    public function __construct(
        protected SiakangMahasiswaService $api
    )
    {
    }

    public function sync(array $parameter): array
    {
        $response = $this->api->get('/v2/mahasiswa', $parameter);

        if (!$response->success || empty($response->data)) {
            return [
                'status' => false,
                'message' => $response['message'] ?? 'Data mahasiswa tidak tersedia',
                'total' => 0,
                'received' => 0,
            ];
        }

        $data = $response->data;
        $meta = isset($data[0]['data']) ? $data[0] : $data;
        $items = $meta['data'] ?? $meta;
        if (!is_array($items) || !array_is_list($items)) {
            return ['status' => false, 'message' => 'Format data mahasiswa tidak valid.', 'total' => 0, 'received' => 0];
        }

        return $this->simpan($items);
    }

    public function simpan(array $items): array
    {
        $rows = [];
        $now = now();
        $sourceExpr = DB::connection()->getDriverName() === 'pgsql'
            ? "payload->>'_source_endpoint' as source_endpoint"
            : "json_extract(payload, '$._source_endpoint') as source_endpoint";
        $existing = Mahasiswa::whereIn('nim', array_column($items, 'nim'))->select([
            'nim', 'nama', 'prodi_id', 'angkatan', 'periode_masuk', 'lulus_pada', 'kewarganegaraan',
        ])->selectRaw($sourceExpr)->get()->keyBy('nim');

        foreach ($items as $mhs) {

            $person = $mhs['person'] ?? [];
            $previous = $existing->get($mhs['nim']);
            if (!isset($mhs['_source_endpoint']) && $previous?->source_endpoint !== null) {
                $mhs['_source_endpoint'] = trim((string) $previous->source_endpoint, '"');
            }

            $lulusPada = $mhs['tanggal_lulus'] ?? $mhs['tanggal_ijazah'] ?? $previous?->lulus_pada;
            $isLulus = !empty($lulusPada) || (($mhs['_source_endpoint'] ?? null) === '/v2/mahasiswa/lulusan');

            $rows[] = [
                'nim' => $mhs['nim'],
                'nama' => $mhs['nama'] ?? $person['nama_person'] ?? $previous?->nama ?? '',
                'prodi_id' => $mhs['prodi_id'] ?? $mhs['prodi']['id'] ?? $previous?->prodi_id,
                'jalur_masuk_id' => $mhs['jalur_masuk_id'] ?? '',
                'program_id' => $mhs['program_id'] ?? '',
                'jenjang_id' => $mhs['jenjang_id'] ?? '',
                'dosen_wali_id' => $mhs['dosen_wali_id'] ?? '',
                'angkatan' => $mhs['angkatan'] ?? $previous?->angkatan,
                // Kolom terpisah agar hitungan per semester tidak perlu membongkar JSON.
                'periode_masuk' => $mhs['periode_masuk'] ?? $previous?->periode_masuk,
                'lulus_pada' => $lulusPada,
                'is_lulus' => $isLulus,
                'tanggal_masuk' => $mhs['tanggal_masuk'] ?? '',
                'kewarganegaraan' => $mhs['kewarganegaraan'] ?? $previous?->kewarganegaraan ?? '',

                'agama' => $mhs['agama'] ?? $person['agama'] ?? '',
                'jenis_kelamin_string' => $mhs['jenis_kelamin_string'] ?? $person['jenis_kelamin_string'] ?? $person['jenis_kelamin'] ?? '',
                'tempat_tanggal_lahir' => $mhs['tempat_tanggal_lahir'] ?? $person['tempat_tanggal_lahir'] ?? trim(($person['tempat_lahir'] ?? '') . ', ' . ($person['tanggal_lahir'] ?? ''), ', '),

                'payload' => json_encode($mhs, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::disableQueryLog();

        $chunks = array_chunk($rows, 50);

        DB::transaction(function () use ($chunks) {
            foreach ($chunks as $chunk) {
                Mahasiswa::upsert(
                    $chunk,
                    ['nim'],
                    [
                        'nama',
                        'prodi_id',
                        'jalur_masuk_id',
                        'program_id',
                        'jenjang_id',
                        'dosen_wali_id',
                        'angkatan',
                        'tanggal_masuk',
                        'periode_masuk',
                        'kewarganegaraan',
                        'lulus_pada',
                        'is_lulus',
                        'agama',
                        'jenis_kelamin_string',
                        'tempat_tanggal_lahir',
                        'payload',
                        'updated_at',
                    ]
                );
            }
        });

        return [
            'status' => true,
            'message' => 'Sinkronisasi berhasil.',
            'total' => count($rows),
            'received' => count($rows),
        ];
    }
}
