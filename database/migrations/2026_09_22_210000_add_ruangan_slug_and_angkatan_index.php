<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua indeks untuk sisa halaman dingin yang masih memindai banyak baris:
     * - mahasiswas(angkatan, jalur_masuk_id): grafik peminat pada halaman Akademik
     * - asets.ruangan_slug: pencarian BMN per ruangan (LIKE '%...%' tidak dapat
     *   memakai indeks). Kolomnya dihitung otomatis dari lokasi_lengkap sehingga
     *   sinkronisasi tidak perlu diubah.
     */
    public function up(): void
    {
        if (!$this->hasIndex('mahasiswas', 'mahasiswas_angkatan_jalur_index')) {
            Schema::table('mahasiswas', function (Blueprint $table) {
                $table->index(['angkatan', 'jalur_masuk_id'], 'mahasiswas_angkatan_jalur_index');
            });
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            if (!Schema::hasColumn('asets', 'ruangan_slug')) {
                DB::statement(
                    "ALTER TABLE asets ADD COLUMN ruangan_slug VARCHAR(255) "
                    . "GENERATED ALWAYS AS (CONCAT('RUANG-', UPPER(REGEXP_REPLACE(SUBSTRING_INDEX(lokasi_lengkap, ' - ', -1), '[^a-zA-Z0-9]+', '-')))) STORED"
                );
            }
        } elseif ($driver === 'pgsql') {
            if (!Schema::hasColumn('asets', 'ruangan_slug')) {
                DB::statement(
                    "ALTER TABLE asets ADD COLUMN ruangan_slug VARCHAR(255) "
                    . "GENERATED ALWAYS AS (CASE WHEN lokasi_lengkap IS NULL THEN NULL ELSE 'RUANG-' || UPPER(REGEXP_REPLACE(SPLIT_PART(lokasi_lengkap, ' - ', -1), '[^a-zA-Z0-9]+', '-', 'g')) END) STORED"
                );
            }
        } elseif (!Schema::hasColumn('asets', 'ruangan_slug')) {
            // Basis data uji (SQLite) tidak punya REGEXP_REPLACE: kolom biasa saja.
            Schema::table('asets', function (Blueprint $table) {
                $table->string('ruangan_slug')->nullable();
            });
        }

        if (!$this->hasIndex('asets', 'asets_ruangan_slug_index')) {
            Schema::table('asets', function (Blueprint $table) {
                $table->index('ruangan_slug', 'asets_ruangan_slug_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('asets', function (Blueprint $table) {
            $table->dropIndex('asets_ruangan_slug_index');
            $table->dropColumn('ruangan_slug');
        });

        Schema::table('mahasiswas', function (Blueprint $table) {
            $table->dropIndex('mahasiswas_angkatan_jalur_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getConnection()->getSchemaBuilder()->getIndexes($table);
            foreach ($indexes as $index) {
                if (($index['name'] ?? '') === $indexName) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
        }

        return false;
    }
};
