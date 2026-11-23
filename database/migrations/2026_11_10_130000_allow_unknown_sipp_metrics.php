<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $metrics = ['sinta12', 'sinta36', 'jurnal_internasional_q', 'jurnal_internasional_pbb', 'jurnal_nasional_issn', 'pengembangan', 'pengabdian_masyarakat', 'buku_referensi'];

    public function up(): void
    {
        Schema::table('dosen_sipps', function (Blueprint $table) {
            foreach ($this->metrics as $metric) {
                $table->integer($metric)->nullable()->default(null)->change();
            }
        });
    }

    public function down(): void
    {
        foreach ($this->metrics as $metric) {
            if (DB::table('dosen_sipps')->whereNull($metric)->exists()) {
                throw new RuntimeException('Indikator SIPP yang belum tersedia tidak boleh diubah menjadi nol.');
            }
        }
        Schema::table('dosen_sipps', function (Blueprint $table) {
            foreach ($this->metrics as $metric) {
                $table->integer($metric)->nullable(false)->default(0)->change();
            }
        });
    }
};
