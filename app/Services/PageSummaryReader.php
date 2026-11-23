<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PageSummaryReader
{
    public function mahasiswa(string $semester, string $jenis): array
    {
        $rows = DB::table('siakang_semester_stats')->where('semester', $semester)->where('jenis', $jenis)->get();

        return $this->formatMahasiswaRows($rows, $jenis);
    }

    /**
     * Ambil beberapa kombinasi semester & jenis statistik mahasiswa dalam satu query.
     *
     * @param  array<int, string>  $semesters
     * @param  array<int, string>  $jenisList
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function mahasiswaBatch(array $semesters, array $jenisList): array
    {
        $semesters = array_values(array_unique($semesters));
        $jenisList = array_values(array_unique($jenisList));

        $grouped = DB::table('siakang_semester_stats')
            ->whereIn('semester', $semesters)
            ->whereIn('jenis', $jenisList)
            ->get()
            ->groupBy(fn ($row) => $row->semester . '|' . $row->jenis);

        $result = [];
        foreach ($semesters as $semester) {
            foreach ($jenisList as $jenis) {
                $rows = $grouped->get($semester . '|' . $jenis, collect());
                $result[$semester][$jenis] = $this->formatMahasiswaRows($rows, $jenis);
            }
        }

        return $result;
    }

    private function formatMahasiswaRows($rows, string $jenis): array
    {
        $countKey = 'jumlah_mahasiswa_'.$jenis;
        $sumNullable = fn ($items, $column) => $items->contains(fn ($item) => $item->{$column} === null)
            ? null : (int) $items->sum($column);
        $prodi = $rows->map(fn ($row) => [
            'prodi_id' => $row->prodi_id, 'kode_prodi' => $row->kode_prodi,
            'nama_prodi' => $row->nama_prodi, 'jenjang' => $row->jenjang,
            'fakultas' => $row->fakultas, $countKey => (int) $row->jumlah,
            'jumlah_laki_laki' => $row->laki_laki === null ? null : (int) $row->laki_laki,
            'jumlah_perempuan' => $row->perempuan === null ? null : (int) $row->perempuan,
        ])->all();
        $faculties = $rows->groupBy('fakultas')->map(fn ($items, $name) => [
            'nama_fakultas' => $name !== '' ? $name : null,
            $countKey => (int) $items->sum('jumlah'),
            'jumlah_laki_laki' => $sumNullable($items, 'laki_laki'),
            'jumlah_perempuan' => $sumNullable($items, 'perempuan'),
        ])->values()->all();

        return [
            'total_mahasiswa_'.$jenis => $rows->isEmpty() ? null : (int) $rows->sum('jumlah'),
            'total_laki_laki' => $rows->isEmpty() ? null : $sumNullable($rows, 'laki_laki'),
            'total_perempuan' => $rows->isEmpty() ? null : $sumNullable($rows, 'perempuan'),
            'detail_per_prodi' => $prodi, 'detail_per_fakultas' => $faculties,
            'sources' => 'database', 'diambil_pada' => $rows->min('diambil_pada'),
        ];
    }

    public function dosen(string $nip, string $semester): ?array
    {
        $row = DB::table('dosen_akademik_summaries')->where('nip', $nip)->where('semester', $semester)->first();
        if (!$row) {
            return null;
        }
        return [
            'total_mk' => (int) $row->jumlah_mk, 'total_kelas' => (int) $row->jumlah_jadwal,
            'total_sks' => $row->total_sks === null ? null : (int) $row->total_sks,
            'payload' => json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR),
            'calculated_at' => $row->calculated_at,
        ];
    }

    public function aset(): ?array
    {
        $rows = DB::table('aset_summaries')->whereIn('tingkat', ['semua', 'kampus'])->get();
        $total = $rows->firstWhere('tingkat', 'semua');
        if (!$total) {
            return null;
        }
        $campuses = $rows->where('tingkat', 'kampus')->map(fn ($row) => [
            'id_kampus' => $row->lokasi_id, 'total_unit' => (int) $row->jumlah,
            'kondisi_baik' => (int) $row->kondisi_baik,
            'kondisi_rusak_ringan' => (int) $row->kondisi_rusak_ringan,
            'kondisi_rusak_berat' => (int) $row->kondisi_rusak_berat,
            'pct_baik' => $row->jumlah > 0 ? round($row->kondisi_baik / $row->jumlah * 100, 1) : null,
        ])->values()->all();

        return [
            'total_unit' => (int) $total->jumlah, 'nilai_perolehan' => $total->nilai_perolehan,
            'kondisi_baik' => (int) $total->kondisi_baik,
            'kondisi_rusak_ringan' => (int) $total->kondisi_rusak_ringan,
            'kondisi_rusak_berat' => (int) $total->kondisi_rusak_berat,
            'total_kampus' => count($campuses), 'kampus_breakdown' => $campuses,
            'total_tanpa_kampus' => (int) $total->jumlah - array_sum(array_column($campuses, 'total_unit')),
        ];
    }
}
