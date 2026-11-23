<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siakang_semester_stats', function (Blueprint $table) {
            $table->integer('laki_laki')->nullable()->default(null)->change();
            $table->integer('perempuan')->nullable()->default(null)->change();
        });
        Schema::create('mahasiswa_jalur_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('angkatan', 4);
            $table->string('kode_fakultas', 20);
            $table->string('jenjang', 32);
            $table->string('jalur_id', 100);
            $table->string('nama_fakultas')->nullable();
            $table->string('nama_jalur')->nullable();
            $table->string('slug_jalur')->nullable();
            $table->unsignedBigInteger('jumlah');
            $table->boolean('cakupan_lengkap')->default(false);
            $table->dateTime('source_synced_at');
            $table->dateTime('calculated_at');
            $table->unique(['angkatan', 'kode_fakultas', 'jenjang', 'jalur_id'], 'jalur_summary_scope');
        });
        Schema::create('perkuliahan_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('semester', 10);
            $table->string('kode_prodi');
            $table->string('nama_prodi')->nullable();
            $table->string('fakultas')->nullable();
            $table->unsignedInteger('jumlah_mk');
            $table->unsignedInteger('jumlah_jadwal');
            $table->unsignedInteger('jumlah_kelas')->nullable();
            $table->unsignedInteger('total_sks');
            $table->json('jadwals');
            $table->dateTime('source_synced_at');
            $table->dateTime('calculated_at');
            $table->unique(['semester', 'kode_prodi']);
        });
        Schema::create('dosen_akademik_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('nip');
            $table->string('semester', 10);
            $table->unsignedInteger('jumlah_mk');
            $table->unsignedInteger('jumlah_jadwal');
            $table->unsignedInteger('total_sks')->nullable();
            $table->json('payload');
            $table->dateTime('source_synced_at');
            $table->dateTime('calculated_at');
            $table->unique(['nip', 'semester']);
        });
        Schema::create('pegawai_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key', 64)->unique();
            $table->string('unit_kerja_id')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('status_kerja')->nullable();
            $table->string('status_pegawai')->nullable();
            $table->string('level_pegawai')->nullable();
            $table->string('jabatan')->nullable();
            $table->unsignedInteger('jumlah');
            $table->unsignedInteger('guru_besar');
            $table->dateTime('source_synced_at')->nullable();
            $table->dateTime('calculated_at');
        });
        Schema::create('aset_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('tingkat', 10);
            $table->string('lokasi_id');
            $table->unsignedBigInteger('jumlah');
            $table->decimal('nilai_perolehan', 22, 2)->nullable();
            $table->unsignedBigInteger('kondisi_baik');
            $table->unsignedBigInteger('kondisi_rusak_ringan');
            $table->unsignedBigInteger('kondisi_rusak_berat');
            $table->dateTime('source_synced_at')->nullable();
            $table->dateTime('calculated_at');
            $table->unique(['tingkat', 'lokasi_id']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('siakang_semester_stats')
            ->whereNull('laki_laki')->orWhereNull('perempuan')->exists()) {
            throw new RuntimeException('Rollback tidak dapat mengubah gender yang belum diketahui menjadi angka nol.');
        }
        Schema::table('siakang_semester_stats', function (Blueprint $table) {
            $table->integer('laki_laki')->nullable(false)->default(0)->change();
            $table->integer('perempuan')->nullable(false)->default(0)->change();
        });
        foreach (['aset_summaries', 'pegawai_summaries', 'dosen_akademik_summaries', 'perkuliahan_summaries', 'mahasiswa_jalur_summaries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
