<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswas', function (Blueprint $table) {
            if (!Schema::hasColumn('mahasiswas', 'is_lulus')) {
                $table->boolean('is_lulus')->default(false)->index('mahasiswas_is_lulus_index');
            }
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "update mahasiswas set is_lulus = 1 "
                . "where lulus_pada is not null "
                . "or (payload is not null and JSON_UNQUOTE(JSON_EXTRACT(payload, '$._source_endpoint')) = '/v2/mahasiswa/lulusan')"
            );
        } elseif ($driver === 'pgsql') {
            DB::statement(
                "update mahasiswas set is_lulus = true "
                . "where lulus_pada is not null "
                . "or (payload is not null and payload->>'_source_endpoint' = '/v2/mahasiswa/lulusan')"
            );
        } else {
            DB::table('mahasiswas')->whereNotNull('lulus_pada')->update(['is_lulus' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('mahasiswas', function (Blueprint $table) {
            if (Schema::hasColumn('mahasiswas', 'is_lulus')) {
                $table->dropIndex('mahasiswas_is_lulus_index');
                $table->dropColumn('is_lulus');
            }
        });
    }
};
