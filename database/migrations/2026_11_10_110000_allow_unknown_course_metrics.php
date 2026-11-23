<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perkuliahan_summaries', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_mk')->nullable()->change();
            $table->unsignedInteger('total_sks')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('perkuliahan_summaries')->whereNull('jumlah_mk')->orWhereNull('total_sks')->exists()) {
            throw new RuntimeException('Rollback tidak dapat mengubah metrik yang belum diketahui menjadi angka nol.');
        }
        Schema::table('perkuliahan_summaries', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_mk')->nullable(false)->change();
            $table->unsignedInteger('total_sks')->nullable(false)->change();
        });
    }
};
