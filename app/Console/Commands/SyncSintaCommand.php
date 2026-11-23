<?php

namespace App\Console\Commands;

use App\Services\Integrations\SintaCollector;
use App\Services\Sync\PullStatusReporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sync:sinta {--key= : Kunci cache status yang dipantau panel kesegaran data}')]
#[Description('Sinkronisasi metrik riset, publikasi Scopus, dan Google Scholar dari SINTA')]
class SyncSintaCommand extends Command
{
    public function handle(SintaCollector $collector): int
    {
        $kunci = (string) ($this->option('key') ?: 'sinta_sync');
        $institutionId = (int) config('satudata.sinta.institution_id', 501);

        $this->info("Menarik data metrik riset UNTIRTA (ID: {$institutionId}) dari SINTA...");
        PullStatusReporter::laporkan($kunci, 'jalan', "Menghubungi SINTA Kemdiktisaintek untuk afiliasi {$institutionId}...");

        $metric = $collector->collectInstitutionMetrics($institutionId);

        if (!$metric) {
            $pesanGagal = 'Gagal mengambil atau mem-parsing data dari portal SINTA.';
            PullStatusReporter::laporkan($kunci, 'gagal', $pesanGagal);
            $this->error($pesanGagal);

            return self::FAILURE;
        }

        $summary = sprintf(
            'SINTA Score: %s (3Yr: %s) | Scopus Docs: %s, Citations: %s | GS Docs: %s, Citations: %s',
            number_format($metric->sinta_score_overall ?? 0, 0, ',', '.'),
            number_format($metric->sinta_score_3_years ?? 0, 0, ',', '.'),
            number_format($metric->scopus_documents ?? 0, 0, ',', '.'),
            number_format($metric->scopus_citations ?? 0, 0, ',', '.'),
            number_format($metric->google_scholar_documents ?? 0, 0, ',', '.'),
            number_format($metric->google_scholar_citations ?? 0, 0, ',', '.')
        );

        $this->info("✓ Berhasil memperbarui metrik riset UNTIRTA dari SINTA!");
        $this->line("  {$summary}");

        PullStatusReporter::laporkan(
            $kunci,
            'selesai',
            "Tersinkronisasi dari SINTA. {$summary}",
            1
        );

        return self::SUCCESS;
    }
}
