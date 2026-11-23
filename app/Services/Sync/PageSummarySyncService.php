<?php

namespace App\Services\Sync;

use App\Models\Pegawai;
use App\Services\Integrations\SiakangSummaryApiClient;
use App\Services\Integrations\SimpegPegawaiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PageSummarySyncService
{
    public function __construct(private SiakangSummaryApiClient $api) {}

    public function lulusan(?string $tahun = null): int
    {
        $year = $tahun ?: (string) now()->year;
        return $this->mahasiswaDetail('/v2/mahasiswa/lulusan', ['tahun_lulus' => $year]);
    }

    public function mahasiswaDetail(string $endpoint = '/v2/mahasiswa', array $params = []): int
    {
        $count = 0;
        $seen = [];
        $stream = fopen('php://temp/maxmemory:1048576', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Penyimpanan sementara sinkronisasi tidak tersedia.');
        }
        try {
            foreach ($this->api->rows($endpoint, $params, 500) as $item) {
                $nim = trim((string) ($item['nim'] ?? ''));
                if ($nim === '') {
                    throw new RuntimeException('Identitas mahasiswa kosong.');
                }
                if (isset($seen[$nim])) {
                    throw new RuntimeException('Identitas mahasiswa berulang antar-record sumber.');
                }
                $seen[$nim] = true;
                if ($endpoint === '/v2/mahasiswa/lulusan') {
                    $item['_source_endpoint'] = $endpoint;
                }
                $line = json_encode($item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
                if (fwrite($stream, $line) !== strlen($line)) {
                    throw new RuntimeException('Data sementara sinkronisasi tidak lengkap.');
                }
            }
            rewind($stream);
            DB::transaction(function () use ($stream, &$count) {
                $batch = [];
                while (($line = fgets($stream)) !== false) {
                    $batch[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (count($batch) === 100) {
                        $count += app(MahasiswaSyncService::class)->simpan($batch)['received'];
                        $batch = [];
                    }
                }
                if (!feof($stream)) {
                    throw new RuntimeException('Pembacaan data sementara gagal.');
                }
                if ($batch !== []) {
                    $count += app(MahasiswaSyncService::class)->simpan($batch)['received'];
                }
            });
        } finally {
            fclose($stream);
        }
        foreach (['prodi', 'angkatan', 'tahun_lulus'] as $filter) {
            Cache::forget('filter_lulusan.'.$filter);
        }
        Cache::forget('lulusan.local_status');
        Cache::put('lulusan.snapshot_version', (string) microtime(true), now()->addDay());
        Cache::put('mahasiswa.snapshot_version', (string) microtime(true), now()->addDay());

        return $count;
    }

    public function mahasiswa(string $semester, string $jenis): int
    {
        if (!in_array($jenis, ['aktif', 'lulus'], true)) {
            throw new RuntimeException('Jenis statistik mahasiswa tidak valid.');
        }
        $endpoint = ($jenis === 'aktif' && config('services.siakang.mahasiswa_aktif_url'))
            ? config('services.siakang.mahasiswa_aktif_url')
            : '/v2/mahasiswa-'.$jenis;
        $params = ['semester' => $semester];
        if ($jenis === 'aktif') {
            $params['jenis'] = 'prodi';
        }
        $data = $this->api->data($endpoint, $params);
        $key = 'jumlah_mahasiswa_'.$jenis;
        $totalKey = 'total_mahasiswa_'.$jenis;
        $kodeSemester = (string) (data_get($data, 'filter.semester') ?? data_get($data, 'semester.kode_semester') ?? '');
        if ($kodeSemester !== $semester
            || !isset($data[$totalKey], $data['detail_per_prodi']) || !is_array($data['detail_per_prodi'])
            || !empty(data_get($data, 'filter.jenjang')) || !empty(data_get($data, 'filter.angkatan'))
            || !empty($data['jenjang_filter']) || !empty($data['angkatan'])) {
            throw new RuntimeException('Cakupan statistik mahasiswa tidak sesuai.');
        }
        $rows = [];
        foreach ($data['detail_per_prodi'] as $item) {
            $jumlah = $item[$key] ?? $item['total_mahasiswa_'.$jenis] ?? null;
            if (empty($item['prodi_id']) || $jumlah === null) {
                throw new RuntimeException('Identitas atau jumlah statistik prodi tidak tersedia.');
            }
            $rows[] = [
                'semester' => $semester, 'jenis' => $jenis,
                'prodi_id' => $item['prodi_id'], 'kode_prodi' => $item['kode_prodi'] ?? null,
                'nama_prodi' => $item['nama_prodi'] ?? null, 'jenjang' => $item['jenjang'] ?? null,
                'fakultas' => $item['nama_fakultas'] ?? $item['fakultas'] ?? null, 'jumlah' => (int) $jumlah,
                'laki_laki' => $item['total_laki_laki'] ?? $item['jumlah_laki_laki'] ?? null,
                'perempuan' => $item['total_perempuan'] ?? $item['jumlah_perempuan'] ?? null,
                'diambil_pada' => now(), 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        if (array_sum(array_column($rows, 'jumlah')) !== (int) $data[$totalKey]
            || count(array_unique(array_column($rows, 'prodi_id'))) !== count($rows)) {
            throw new RuntimeException('Total atau identitas statistik prodi tidak konsisten.');
        }
        if ($rows === []) {
            throw new RuntimeException('Sumber belum memberikan baris statistik prodi.');
        }
        $this->replace('siakang_semester_stats', ['semester' => $semester, 'jenis' => $jenis], $rows);
        Cache::forget('akademik_auto_detected_semester');
        Cache::forget('dosen_profile.active_semester');
        PullStatusReporter::laporkan('siakang_'.$jenis.'_sync', 'selesai', 'Statistik semester diperbarui.', count($rows));

        return count($rows);
    }

    public function perkuliahan(string $semester, ?string $prodi = null): int
    {
        $params = ['kode_semester' => $semester];
        if ($prodi !== null) {
            $params['kode_prodi'] = $prodi;
        }
        $items = [];
        $fields = array_flip(['id_jadwal', 'semester', 'kode_semester', 'tahun', 'kode_prodi', 'nama_prodi', 'kode_mk', 'matakuliah', 'sks', 'kelas']);
        foreach ($this->api->rows('/v2/mata_kuliah/with-kelas', $params, 500) as $item) {
            $items[] = array_intersect_key($item, $fields);
        }
        $seen = [];
        foreach ($items as $item) {
            if ((string) ($item['kode_semester'] ?? '') !== $semester || empty($item['kode_prodi'])
                || ($prodi !== null && (string) $item['kode_prodi'] !== $prodi)
                || empty($item['id_jadwal'])) {
                throw new RuntimeException('Semester atau identitas jadwal tidak sesuai.');
            }
            if (isset($seen[$item['id_jadwal']])) {
                throw new RuntimeException('Sumber mengembalikan ID jadwal berulang.');
            }
            $seen[$item['id_jadwal']] = true;
        }
        $items = app(CourseDetailEnricher::class)->enrich($items, $semester, $prodi !== null);
        $previous = DB::table('perkuliahan_summaries')->where('semester', $semester)->get(['kode_prodi', 'details_synced_at'])->keyBy('kode_prodi');
        $metadata = DB::table('siakang_semester_stats')->where('semester', $semester)
            ->where('jenis', 'aktif')->get()->keyBy('kode_prodi');
        $groups = collect($items)->groupBy('kode_prodi');
        foreach ($metadata as $code => $meta) {
            if ($prodi !== null && (string) $code !== $prodi) {
                continue;
            }
            if (!$groups->has($code)) {
                $groups->put($code, collect());
            }
        }
        $rows = [];
        foreach ($groups as $code => $group) {
            $detailsAt = $previous->get($code)?->details_synced_at;
            if ($prodi === null && $detailsAt !== null) {
                $oldJson = DB::table('perkuliahan_summaries')->where('semester', $semester)->where('kode_prodi', $code)->value('jadwals');
                $old = collect(json_decode($oldJson, true, 512, JSON_THROW_ON_ERROR))->keyBy('id_jadwal');
                if ($old->count() !== $group->count() || $group->contains(fn ($item) => !$old->has($item['id_jadwal']))) {
                    $detailsAt = null;
                }
                $group = $group->map(function ($item) use ($old, &$detailsAt) {
                    $saved = $old->get($item['id_jadwal']);
                    if (($saved['pengampu'] ?? []) !== ($item['pengampu'] ?? [])) {
                        $detailsAt = null;
                    } elseif (isset($saved['waktu_kuliah'])) {
                        $item['waktu_kuliah'] = $saved['waktu_kuliah'];
                    }
                    return $item;
                });
                unset($old, $oldJson);
            }
            $classes = $group->flatMap(fn ($row) => $row['kelas'] ?? []);
            $rows[] = [
                'semester' => $semester, 'kode_prodi' => (string) $code,
                'nama_prodi' => $group->first()['nama_prodi'] ?? $metadata->get($code)?->nama_prodi,
                'fakultas' => $metadata->get($code)?->fakultas,
                'jumlah_mk' => $group->contains(fn ($row) => empty($row['kode_mk']))
                    ? null : $group->pluck('kode_mk')->unique()->count(),
                'jumlah_jadwal' => $group->count(),
                'jumlah_kelas' => $group->contains(fn ($row) => !array_key_exists('kelas', $row))
                    || $classes->contains(fn ($row) => empty($row['id_kelas']))
                    ? null : $classes->pluck('id_kelas')->unique()->count(),
                'total_sks' => $group->contains(fn ($row) => !isset($row['sks'])) ? null : $group->sum('sks'),
                'sks_teori' => $group->contains(fn ($row) => !isset($row['sks_teori'])) ? null : $group->sum('sks_teori'),
                'sks_praktik' => $group->contains(fn ($row) => !isset($row['sks_praktik'])) ? null : $group->sum('sks_praktik'),
                'details_synced_at' => $prodi !== null ? now() : $detailsAt,
                'jadwals' => json_encode($group->values()->all(), JSON_THROW_ON_ERROR),
                'source_synced_at' => now(), 'calculated_at' => now(),
            ];
        }
        if ($rows === []) {
            throw new RuntimeException('Referensi prodi belum tersedia untuk snapshot kosong.');
        }
        $scope = ['semester' => $semester];
        if ($prodi !== null) {
            $scope['kode_prodi'] = $prodi;
        }
        $this->replace('perkuliahan_summaries', $scope, $rows);
        Cache::forget("monitoring_perkuliahan_{$semester}");

        return count($rows);
    }

    public function jalur(string $angkatan, string $fakultas, string $jenjang): int
    {
        $items = $this->api->pages('/v2/jalur-masuk/total-mahasiswa', [
            'angkatan' => $angkatan, 'kodeFakultas' => $fakultas, 'jenjangId' => $jenjang,
        ]);
        $scope = ['angkatan' => $angkatan, 'kode_fakultas' => $fakultas, 'jenjang' => $jenjang];
        $rows = [];
        foreach ($items as $item) {
            if ((string) ($item['kode_fakultas'] ?? '') !== $fakultas || empty($item['id'])
                || !isset($item['jumlah_mahasiswa'])) {
                throw new RuntimeException('Fakultas atau identitas jalur tidak sesuai.');
            }
            $rows[] = $scope + [
                'jalur_id' => $item['id'], 'nama_fakultas' => $item['nama_fakultas'] ?? null,
                'nama_jalur' => $item['nama_jalur_masuk'] ?? null,
                'slug_jalur' => $item['slug_jalur_masuk'] ?? null, 'jumlah' => $item['jumlah_mahasiswa'],
                'source_synced_at' => now(), 'calculated_at' => now(),
            ];
        }
        DB::transaction(function () use ($scope, $rows, $angkatan) {
            DB::table('mahasiswa_jalur_summaries')->where('angkatan', $angkatan)->update(['cakupan_lengkap' => false]);
            $this->replace('mahasiswa_jalur_summaries', $scope, $rows);
        });

        return count($rows);
    }

    public function dosen(string $nip, string $semester): int
    {
        $data = $this->api->data('/v2/dashboard-akademik/dosen', ['nip' => $nip]);
        $items = data_get($data, 'mata_kuliah_diampu.data');
        if ((string) data_get($data, 'semester.kode_semester') !== $semester
            || (string) data_get($data, 'dosen.nip') !== $nip || !is_array($items)
            || count($items) !== data_get($data, 'mata_kuliah_diampu.total')) {
            throw new RuntimeException('Identitas, semester, atau jumlah jadwal dashboard dosen tidak sesuai.');
        }
        foreach ($items as $item) {
            if (empty($item['jadwal_id']) || empty($item['kode_mata_kuliah'])) {
                throw new RuntimeException('Identitas jadwal dashboard dosen tidak lengkap.');
            }
        }
        if (collect($items)->pluck('jadwal_id')->unique()->count() !== count($items)) {
            throw new RuntimeException('ID jadwal dashboard dosen berulang.');
        }
        DB::table('dosen_akademik_summaries')->updateOrInsert(['nip' => $nip, 'semester' => $semester], [
            'jumlah_mk' => collect($items)->pluck('kode_mata_kuliah')->unique()->count(),
            'jumlah_jadwal' => count($items), 'total_sks' => data_get($data, 'beban_sks_semester.total_sks'),
            'payload' => json_encode($data, JSON_THROW_ON_ERROR),
            'source_synced_at' => now(), 'calculated_at' => now(),
        ]);
        Cache::forget("dosen_profile_{$nip}_{$semester}");
        Cache::forget("dosen_profile_summary_{$nip}_{$semester}");

        return 1;
    }

    public function bimbingan(string $nip, string $semester): int
    {
        $params = ['semester' => $semester, 'nip' => $nip];
        $academic = $this->api->data('/v2/bimbingan-akademik', $params);
        $thesis = $this->api->data('/v2/bimbingan-tugas-akhir', $params);
        $academic = $academic['beban_bimbing_akademik'] ?? null;
        $fields = array_flip(['beban_bimbing_ta_utama', 'beban_bimbing_ta_pendamping',
            'beban_bimbing_tesis_utama', 'beban_bimbing_tesis_pendamping',
            'beban_bimbing_disertasi_promotor', 'beban_bimbing_disertasi_co_promotor',
            'beban_bimbing_disertasi_anggota_promotor']);
        $thesis = array_intersect_key($thesis, $fields);
        foreach (array_merge([$academic], array_values($thesis)) as $value) {
            if ($value !== null && (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0)) {
                throw new RuntimeException('Nilai beban bimbingan tidak valid.');
            }
        }
        if ($academic === null && $thesis === []) {
            throw new RuntimeException('Data beban bimbingan tidak tersedia.');
        }
        Cache::forever('dosen_bimbingan_'.hash('sha256', $nip.'|'.$semester), [
            'akademik' => $academic, 'tugas_akhir' => $thesis,
        ]);

        return 1;
    }

    public function dosenDariJadwal(string $semester): int
    {
        $count = 0;
        $nips = DB::table('dosen_jadwals')->where('semester', $semester)->distinct()->pluck('nip');
        foreach ($nips as $nip) {
            $scope = ['nip' => $nip, 'semester' => $semester];
            if (DB::table('dosen_akademik_summaries')->where($scope)->exists()) {
                continue;
            }
            $rows = DB::table('dosen_jadwals')->where($scope)->get();
            if ($rows->contains(fn ($row) => !$row->jadwal_id || !$row->mata_kuliah_kode || $row->sks === null)) {
                continue;
            }
            $groups = $rows->groupBy('jadwal_id');
            if ($groups->contains(fn ($group) => $group->pluck('sks')->unique()->count() !== 1
                || $group->pluck('mata_kuliah_kode')->unique()->count() !== 1)) {
                continue;
            }
            $created = DB::table('dosen_akademik_summaries')->insertOrIgnore($scope + [
                'jumlah_mk' => $rows->pluck('mata_kuliah_kode')->unique()->count(),
                'jumlah_jadwal' => $groups->count(),
                'total_sks' => $groups->sum(fn ($group) => $group->first()->sks),
                'payload' => json_encode(['source' => 'dosen_jadwals'], JSON_THROW_ON_ERROR),
                'source_synced_at' => $rows->min('updated_at'), 'calculated_at' => now(),
            ]);
            $count += $created;
            if ($created) {
                Cache::forget("dosen_profile_summary_{$nip}_{$semester}");
            }
        }
        return $count;
    }

    public function pegawai(): int
    {
        $groups = [];
        $api = app(SimpegPegawaiService::class);
        foreach (Pegawai::cursor() as $pegawai) {
            $item = $api->normalizePegawaiItem($api->mapDbPegawaiToItem($pegawai));
            $scope = [
                'unit_kerja_id' => $pegawai->unit_kerja_id, 'unit_kerja' => $pegawai->unit_kerja,
                'status_kerja' => $pegawai->status_kerja,
                'status_pegawai' => $item['statusPegawai'] !== '' ? $item['statusPegawai'] : null,
                'level_pegawai' => $pegawai->level_pegawai, 'jabatan' => $pegawai->jabatan,
            ];
            $key = hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR));
            $groups[$key] ??= $scope + ['scope_key' => $key, 'jumlah' => 0, 'guru_besar' => 0, 'source_synced_at' => null, 'calculated_at' => now()];
            $groups[$key]['jumlah']++;
            $professor = preg_match('/Profesor|Guru Besar/i', (string) $pegawai->jabatan)
                || stripos((string) $pegawai->gelar_depan, 'Prof') !== false
                || stripos((string) $pegawai->nama, 'Prof.') !== false;
            $groups[$key]['guru_besar'] += $professor ? 1 : 0;
            $updated = $pegawai->updated_at?->toDateTimeString();
            if ($groups[$key]['source_synced_at'] === null || $updated < $groups[$key]['source_synced_at']) {
                $groups[$key]['source_synced_at'] = $updated;
            }
        }
        $this->replace('pegawai_summaries', [], array_values($groups));

        return count($groups);
    }

    public function aset(): int
    {
        $rows = [];
        foreach (['semua' => null, 'kampus' => 'id_kampus', 'gedung' => 'id_gedung', 'ruangan' => 'id_ruangan'] as $level => $column) {
            $query = DB::table('asets')->selectRaw('COUNT(*) as jumlah, SUM(nilai_perolehan) as nilai_perolehan, MIN(updated_at) as source_synced_at');
            foreach (['baik', 'rusak_ringan', 'rusak_berat'] as $condition) {
                $query->selectRaw("SUM(CASE WHEN kondisi = ? THEN 1 ELSE 0 END) as kondisi_{$condition}", [config("aset.kondisi.{$condition}.kode")]);
            }
            if ($column) {
                $query->addSelect($column.' as lokasi_id')->whereNotNull($column)->where($column, '!=', '')->groupBy($column);
            }
            foreach ($query->get() as $item) {
                if (!$column && (int) $item->jumlah === 0) {
                    continue;
                }
                $rows[] = [
                    'tingkat' => $level, 'lokasi_id' => (string) ($item->lokasi_id ?? ''),
                    'jumlah' => $item->jumlah, 'nilai_perolehan' => $item->nilai_perolehan,
                    'kondisi_baik' => $item->kondisi_baik, 'kondisi_rusak_ringan' => $item->kondisi_rusak_ringan,
                    'kondisi_rusak_berat' => $item->kondisi_rusak_berat,
                    'source_synced_at' => $item->source_synced_at, 'calculated_at' => now(),
                ];
            }
        }
        $this->replace('aset_summaries', [], $rows);
        foreach (['aset_big_card_stats', 'aset_status_summary', 'simantap_kampus_summary_v4', 'simantap_kampus_dari_data_v1', 'simantap_distinct_locations_v3'] as $key) {
            Cache::forget($key);
        }

        return count($rows);
    }

    private function replace(string $table, array $scope, array $rows): void
    {
        DB::transaction(function () use ($table, $scope, $rows) {
            DB::table($table)->where($scope)->delete();
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        });
    }
}
