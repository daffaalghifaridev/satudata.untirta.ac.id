<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('research_metrics', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('institution_id')->index();

            $table->decimal('sinta_score_overall', 15, 3)->nullable();
            $table->decimal('sinta_score_3_years', 15, 3)->nullable();

            $table->integer('scopus_documents')->nullable();
            $table->integer('scopus_citations')->nullable();
            $table->integer('scopus_cited_documents')->nullable();

            $table->integer('google_scholar_documents')->nullable();
            $table->integer('google_scholar_citations')->nullable();

            $table->integer('scopus_h_index')->nullable();
            $table->integer('google_scholar_h_index')->nullable();

            $table->string('source', 50)->default('SINTA');
            $table->timestamp('collected_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research_metrics');
    }
};
