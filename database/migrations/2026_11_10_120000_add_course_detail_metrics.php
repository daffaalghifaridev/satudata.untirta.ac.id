<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perkuliahan_summaries', function (Blueprint $table) {
            $table->unsignedInteger('sks_teori')->nullable();
            $table->unsignedInteger('sks_praktik')->nullable();
            $table->dateTime('details_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('perkuliahan_summaries', fn (Blueprint $table) => $table->dropColumn(['sks_teori', 'sks_praktik', 'details_synced_at']));
    }
};
