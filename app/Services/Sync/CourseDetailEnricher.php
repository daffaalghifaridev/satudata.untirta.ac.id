<?php

namespace App\Services\Sync;

use App\Services\Integrations\SiakangSummaryApiClient;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CourseDetailEnricher
{
    public function __construct(private SiakangSummaryApiClient $api) {}

    public function enrich(array $items, string $semester, bool $includeTimes = false): array
    {
        if ($items === []) {
            return [];
        }
        $scheduleIds = array_column($items, 'id_jadwal');
        sort($scheduleIds, SORT_STRING);
        $key = 'siakang.course-index.v3.'.$semester;
        $index = Cache::get($key);
        if ($index === null) {
            $index = Cache::lock($key.'.build', 600)->block(300,
                fn () => Cache::remember($key, now()->addMinutes(20), fn () => $this->index($semester)));
        }
        if (collect($scheduleIds)->contains(fn ($id) => empty($index['jadwal'][$id]['mata_kuliah_id']))) {
            $fallback = Cache::remember($key.'.jadwal', now()->addMinutes(20), fn () => $this->scheduleIndex($semester));
            foreach ($scheduleIds as $id) {
                if (empty($index['jadwal'][$id]['mata_kuliah_id']) && isset($fallback[$id])) {
                    $index['jadwal'][$id] = $fallback[$id] + ['pengampu' => $index['jadwal'][$id]['pengampu'] ?? []];
                }
            }
        }
        foreach ($scheduleIds as $id) {
            $courseId = $index['jadwal'][$id]['mata_kuliah_id'] ?? null;
            if ($courseId !== null && !isset($index['courses'][$courseId])) {
                $index['courses'][$courseId] = Cache::remember($key.'.mk.'.hash('sha256', $courseId), now()->addMinutes(20), function () use ($courseId) {
                    $data = $this->api->data('/v2/mata_kuliah/'.rawurlencode($courseId), []);
                    $course = array_is_list($data) ? ($data[0] ?? []) : $data;
                    if ((string) ($course['id'] ?? '') !== (string) $courseId) {
                        throw new RuntimeException('Identitas master mata kuliah tidak sesuai.');
                    }
                    return $this->course($course);
                });
            }
        }
        foreach ($items as &$item) {
            $schedule = $index['jadwal'][$item['id_jadwal']] ?? null;
            $course = $index['courses'][$schedule['mata_kuliah_id'] ?? ''] ?? null;
            if ($course && ($item['kode_mk'] ?? null) === $course['kode']) {
                $item['sks_teori'] = $course['sks_teori'];
                $item['sks_praktik'] = $course['sks_praktik'];
                $item['kurikulum'] = $course['kurikulum'];
            }
            if ($schedule) {
                $item['kode_jadwal'] = $schedule['kode_jadwal'];
                $item['pengampu'] = array_values($schedule['pengampu']);
            }
        }
        unset($item);

        if ($includeTimes) {
            $nips = collect($items)->flatMap(fn ($row) => $row['pengampu'] ?? [])->pluck('nip')->filter()->unique();
            $times = [];
            foreach ($nips as $nip) {
                $data = Cache::remember('siakang.lecturer-times.'.$semester.'.'.hash('sha256', (string) $nip), now()->addMinutes(20), function () use ($semester, $nip) {
                    $data = $this->api->data('/rencana-studi/penjadwalan', ['semester' => $semester, 'nip' => $nip]);
                    if ((string) data_get($data, 'semester.kode_semester') !== $semester
                        || (string) data_get($data, 'dosen.nip') !== (string) $nip
                        || !isset($data['data']) || !is_array($data['data'])) {
                        throw new RuntimeException('Semester atau format waktu kuliah tidak sesuai.');
                    }
                    return $data;
                });
                foreach ($data['data'] as $course) {
                    foreach ($course['jadwal'] ?? [] as $schedule) {
                        if (empty($schedule['jadwal_id'])) {
                            continue;
                        }
                        foreach ($schedule['waktu_kuliah'] ?? [] as $time) {
                            $key = json_encode($time, JSON_THROW_ON_ERROR);
                            $times[$schedule['jadwal_id']][$key] = $time;
                        }
                    }
                }
            }
            foreach ($items as &$item) {
                $item['waktu_kuliah'] = array_values($times[$item['id_jadwal']] ?? []);
            }
            unset($item);
        }

        return $items;
    }

    private function index(string $semester): array
    {
        $data = $this->api->data('/v2/semester/'.$semester, []);
        $ref = array_is_list($data) ? ($data[0] ?? []) : $data;
        if (empty($ref['id']) || (string) ($ref['kode_semester'] ?? '') !== $semester) {
            throw new RuntimeException('Identitas semester tidak tersedia.');
        }
        $schedules = [];
        $courses = [];
        $prefix = substr($semester, 2);
        foreach ($this->api->rows('/v2/jadwal_pengampu', ['search' => $prefix], 500) as $row) {
            if (data_get($row, 'jadwal.semester_id') !== $ref['id'] || empty($row['jadwal_id'])) {
                continue;
            }
            $id = $row['jadwal_id'];
            if (data_get($row, 'jadwal.id') !== null && (string) data_get($row, 'jadwal.id') !== (string) $id) {
                throw new RuntimeException('Relasi jadwal pengampu tidak sesuai.');
            }
            $schedules[$id] ??= [
                'mata_kuliah_id' => data_get($row, 'jadwal.mata_kuliah_id'),
                'kode_jadwal' => data_get($row, 'jadwal.kode_jadwal'), 'pengampu' => [],
            ];
            $course = data_get($row, 'jadwal.mata_kuliah');
            if (is_array($course) && !empty($course['id'])
                && (string) $course['id'] === (string) $schedules[$id]['mata_kuliah_id']) {
                $courses[$course['id']] = $this->course($course);
            }
            $nip = trim((string) data_get($row, 'dosen.pegawai.nip'));
            if (in_array($nip, ['', '-', 'N/A'], true)) {
                $nip = trim((string) data_get($row, 'dosen.pegawai.nip_baru'));
            }
            if (in_array($nip, ['-', 'N/A'], true)) {
                $nip = '';
            }
            $key = $nip !== '' ? $nip : (string) ($row['dosen_id'] ?? '');
            if ($key !== '') {
                $schedules[$id]['pengampu'][$key] = [
                    'nip' => $nip !== '' ? $nip : null,
                    'nama' => data_get($row, 'dosen.pegawai.person.nama_person'),
                ];
            }
        }
        return ['jadwal' => $schedules, 'courses' => $courses];
    }

    private function scheduleIndex(string $semester): array
    {
        $data = $this->api->data('/v2/semester/'.$semester, []);
        $ref = array_is_list($data) ? ($data[0] ?? []) : $data;
        $schedules = [];
        if (empty($ref['id']) || (string) ($ref['kode_semester'] ?? '') !== $semester) {
            throw new RuntimeException('Identitas semester tidak tersedia.');
        }
        foreach ($this->api->rows('/v2/jadwal', ['search' => substr($semester, 2)], 500) as $row) {
            if (($row['semester_id'] ?? null) === ($ref['id'] ?? null) && !empty($row['id'])) {
                $schedules[$row['id']] = ['mata_kuliah_id' => $row['mata_kuliah_id'], 'kode_jadwal' => $row['kode_jadwal'] ?? null];
            }
        }
        return $schedules;
    }

    private function course(array $row): array
    {
        $parts = ['sks_praktik', 'sks_praktik_lapangan', 'sks_simulasi', 'sks_praktikum'];
        $practice = collect($parts)->contains(fn ($key) => !isset($row[$key]))
            ? null : array_sum(array_intersect_key($row, array_flip($parts)));

        return [
            'kode' => $row['kode_mata_kuliah'] ?? null,
            'sks_teori' => $row['sks_teori'] ?? null, 'sks_praktik' => $practice,
            'kurikulum' => data_get($row, 'kurikulum.nama_kurikulum') ?? ($row['nama_kurikulum'] ?? null),
        ];
    }
}
