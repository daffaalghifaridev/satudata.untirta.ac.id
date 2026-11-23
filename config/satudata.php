<?php

use App\Services\Integrations\AssetSyncLauncher;
use App\Services\Integrations\DosenSyncLauncher;

return [

    'sync_interval_days' => max(1, (int) (env('SATUDATA_SYNC_INTERVAL_DAYS') ?: 7)),
    'api_request_interval_ms' => 300,

    /*
    |--------------------------------------------------------------------------
    | Kontrak SWR (stale-while-revalidate)
    |--------------------------------------------------------------------------
    |
    | Dipakai semua sumber data: halaman selalu dilayani dari cache/database
    | lebih dulu, penarikan ulang dijalankan di latar. Penanda segar mencegah
    | penarikan berulang-ulang pada satu periode.
    |
    */

    'swr' => [
        'ttl_minutes' => 360,      // umur data di cache
        'fresh_minutes' => 20,     // data dianggap segar selama ini (anti-badai revalidate)
        'stale_minutes' => 60,      // lewat ini, data dianggap perlu ditarik ulang
    ],

    /*
    |--------------------------------------------------------------------------
    | Sumber data & penariknya
    |--------------------------------------------------------------------------
    |
    | Setiap sumber data punya penarik berbasis Chromium lokal (Edge/Chrome di
    | host aplikasi) karena panggilan HTTP langsung dari PHP selalu ditantang
    | Cloudflare (HTTP 403 "Just a moment"). Sumber tanpa penarik ditandai
    | launcher => null supaya UI menyebutkan bahwa penariknya belum ada.
    |
    */

    'sources' => [
        'halaman.akademik' => [
            'label' => 'Data halaman akademik',
            'keterangan' => 'Data tersimpan ditampilkan selama pembaruan berjalan.',
            'launcher' => null,
            'command' => [
                'target' => 'sync:page',
                'arguments' => ['akademik', '--semester={semester}', '--key={key}'],
            ],
            'status_key' => 'page_akademik_sync',
            'method' => 'pages',
        ],
        'siakang.perkuliahan' => [
            'label' => 'Perkuliahan (SIAKANG)',
            'sistem' => 'SIAKANG',
            'keterangan' => 'Ringkasan perkuliahan dibaca dari database hasil sinkronisasi SIAKANG.',
            'launcher' => null,
            'command' => [
                'target' => 'sync:summaries',
                'arguments' => ['perkuliahan', '--semester={semester}', '--prodi={prodi}', '--key={key}'],
            ],
            'status_key' => 'summary_perkuliahan',
            'method' => 'pages',
        ],
        'simantap.aset' => [
            'label' => 'Aset BMN (SIMANTAP)',
            'sistem' => 'SIMANTAP',
            'keterangan' => 'Data aset dan total unit dibaca dari database SATUDATA hasil sinkronisasi SIMANTAP.',
            'launcher' => AssetSyncLauncher::class,
            'status_key' => 'aset_sync',
            'method' => 'pages',
        ],
        'siakang.penjadwalan' => [
            'label' => 'Penjadwalan dosen (SIAKANG)',
            'sistem' => 'SIAKANG',
            'keterangan' => 'Jadwal kuliah dibaca dari database SATUDATA hasil sinkronisasi SIAKANG per dosen.',
            'launcher' => DosenSyncLauncher::class,
            'status_key' => 'dosen_sync',
            'method' => 'per_lecturer',
        ],
        'sipp.portofolio' => [
            'label' => 'Portofolio dosen (SIPP)',
            'sistem' => 'SIPP',
            'keterangan' => 'Portofolio dosen dibaca dari database SATUDATA hasil sinkronisasi SIPP.',
            'launcher' => null,
            'command' => [
                'target' => 'scripts/sync-portofolio.php',
                'arguments' => ['--limit=25', '--key={key}'],
            ],
            'status_key' => 'sipp_sync',
            'method' => 'per_lecturer',
        ],
        'siakang.mahasiswa_aktif' => [
            'label' => 'Mahasiswa aktif (SIAKANG)',
            'sistem' => 'SIAKANG',
            'keterangan' => 'Statistik mahasiswa dibaca dari database SATUDATA hasil sinkronisasi SIAKANG.',
            'launcher' => null,
            'command' => [
                'target' => 'sync:summaries',
                'arguments' => ['mahasiswa', '--semester={semester}', '--key={key}'],
            ],
            'status_key' => 'siakang_aktif_sync',
            'method' => 'pages',
        ],
        'siakang.mahasiswa' => [
            'label' => 'Biodata mahasiswa (SIAKANG)',
            'sistem' => 'SIAKANG',
            'keterangan' => 'Jumlah mahasiswa baru dihitung dari periode masuk pada data mahasiswa.',
            'launcher' => null,
            'command' => [
                'target' => 'sync:summaries',
                'arguments' => ['mahasiswa-detail', '--key={key}'],
            ],
            'status_key' => 'siakang_mahasiswa_detail_sync',
            'method' => 'pages',
        ],
        'siakang.jalur' => [
            'label' => 'Jalur masuk mahasiswa (SIAKANG)',
            'sistem' => 'SIAKANG',
            'keterangan' => 'Jumlah mahasiswa per jalur masuk dibaca dari agregasi SIAKANG.',
            'launcher' => null,
            'command' => [
                'target' => 'sync:summaries',
                'arguments' => ['jalur', '--angkatan={angkatan}', '--key={key}'],
            ],
            'status_key' => 'siakang_jalur_sync',
            'method' => 'pages',
        ],
        'siakang.lulusan' => [
            'label' => 'Mahasiswa lulus (SIAKANG)',
            'sistem' => 'SIAKANG',
            'keterangan' => 'Daftar mahasiswa lulus dibaca dari database SATUDATA hasil sinkronisasi SIAKANG.',
            'launcher' => null,
            'command' => [
                'target' => 'sync:summaries',
                'arguments' => ['lulusan', '--tahun={tahun}', '--key={key}'],
            ],
            'status_key' => 'siakang_lulusan_detail_sync',
            'method' => 'pages',
        ],
        'simpeg.pegawai' => [
            'label' => 'Pegawai (SIMPEG)',
            'sistem' => 'SIMPEG',
            'keterangan' => 'Data pegawai dibaca dari database SATUDATA hasil sinkronisasi SIMPEG.',
            'launcher' => null,
            'command' => [
                'target' => 'sync:pegawai',
                'arguments' => ['--key={key}'],
            ],
            'status_key' => 'pegawai_sync',
            'method' => 'pages',
        ],
        'sinta.metrics' => [
            'label' => 'Metrik Riset (SINTA)',
            'sistem' => 'SINTA',
            'keterangan' => 'Metrik riset, publikasi Scopus, dan Google Scholar dari SINTA Kemdiktisaintek.',
            'launcher' => null,
            'command' => [
                'target' => 'sync:sinta',
                'arguments' => [],
            ],
            'status_key' => 'sinta_sync',
            'method' => 'pages',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi SINTA Kemdiktisaintek
    |--------------------------------------------------------------------------
    |
    | Konfigurasi URL dan ID afiliasi UNTIRTA (501) di portal SINTA untuk
    | penarikan data metrik riset, publikasi Scopus, dan Google Scholar.
    |
    */

    'sinta' => [
        'institution_id'   => 501,
        'institution_name' => 'Universitas Sultan Ageng Tirtayasa',
        'profile_url'      => 'https://sinta.kemdiktisaintek.go.id/affiliations/profile/{id}',
        'scopus_url'       => 'https://sinta.kemdiktisaintek.go.id/affiliations/scopusanalysis/{id}',
        'authors_url'      => 'https://sinta.kemdiktisaintek.go.id/affiliations/authors/{id}',
    ],

    'monitoring_faculties' => [
            'MKU' => 'Mata Kuliah Umum',
            'FH' => 'Fakultas Hukum',
            'FKIP' => 'Fakultas Keguruan dan Ilmu Pendidikan',
            'FT' => 'Fakultas Teknik',
            'FAPERTA' => 'Fakultas Pertanian',
            'FEB' => 'Fakultas Ekonomi dan Bisnis',
            'FISIP' => 'Fakultas Ilmu Sosial dan Ilmu Politik',
            'FKIK' => 'Fakultas Kedokteran dan Ilmu Kesehatan',
            'PASCA' => 'Pascasarjana',
        ],

    'monitoring_prodis' => [
            'FH' => ['1111', '7773'],
            'FT' => ['3331', '3332', '3333', '3334', '3335', '3336', '3337', '3338', '3339', '7780', '7787', '7788', '7790'],
            'FEB' => ['5551', '5552', '5553', '5554', '5501', '5502', '5503', '5504', '7774', '7776', '7783', '7786'],
            'FISIP' => ['6661', '6662', '6670', '7775', '7781'],
            'FAPERTA' => ['4441', '4442', '4443', '4444', '4445', '4446', '7779', '7785'],
            'FKIP' => ['2221', '2222', '2223', '2224', '2225', '2227', '2228', '2237', '2280', '2281', '2282', '2283', '2284', '2285', '2286', '2287', '2288', '2289', '2290', '7771', '7772', '7777', '7778', '7782', '7784'],
            'FKIK' => ['8881', '8882', '8883', '8884', '8801', '8831', '8832', '8870', '8871', '8872', '8873', '8874', '8875'],
            'PASCA' => ['7789'],
        ],

    'jalur_groups' => [
        'Seleksi Nasional' => ['snbp', 'snbt', 'snmptn', 'sbmptn'],
        'Seleksi Mandiri' => ['sm', 'ujian-mandiri', 'smmptn-barat', 'seleksi-mandiri-berdasarkan-test', 'smptn', 'umb'],
    ],
];
