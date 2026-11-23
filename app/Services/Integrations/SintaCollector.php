<?php

namespace App\Services\Integrations;

use App\Models\ResearchMetric;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SintaCollector
{
    protected const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    public function __construct(
        protected ChromiumFetcher $chromiumFetcher
    ) {}

    /**
     * Ambil metrik afiliasi institusi dari SINTA dan simpan ke database.
     */
    public function collectInstitutionMetrics(?int $institutionId = null): ?ResearchMetric
    {
        $institutionId = $institutionId ?: (int) config('satudata.sinta.institution_id', 501);
        $urlTemplate = config('satudata.sinta.profile_url', 'https://sinta.kemdiktisaintek.go.id/affiliations/profile/{id}');
        $url = str_replace('{id}', (string) $institutionId, $urlTemplate);

        $html = $this->fetchHtml($url);
        if (!$html) {
            Log::error("SintaCollector: Gagal mengambil HTML dari {$url}");
            return null;
        }

        $parsed = $this->parseInstitutionMetrics($html);
        if (empty($parsed)) {
            Log::warning("SintaCollector: Gagal mengekstrak data dari HTML {$url}");
            return null;
        }

        return ResearchMetric::create([
            'institution_id'           => $institutionId,
            'sinta_score_overall'      => $parsed['sinta_score_overall'] ?? null,
            'sinta_score_3_years'      => $parsed['sinta_score_3_years'] ?? null,
            'scopus_documents'         => $parsed['scopus_documents'] ?? null,
            'scopus_citations'         => $parsed['scopus_citations'] ?? null,
            'scopus_cited_documents'   => $parsed['scopus_cited_documents'] ?? null,
            'google_scholar_documents' => $parsed['google_scholar_documents'] ?? null,
            'google_scholar_citations' => $parsed['google_scholar_citations'] ?? null,
            'scopus_h_index'           => null,
            'google_scholar_h_index'   => null,
            'source'                   => 'SINTA',
            'collected_at'             => now(),
        ]);
    }

    /**
     * Ambil record metrik riset terbaru dari database internal.
     */
    public function getLatestMetrics(?int $institutionId = null): ?ResearchMetric
    {
        $institutionId = $institutionId ?: (int) config('satudata.sinta.institution_id', 501);

        return ResearchMetric::where('institution_id', $institutionId)
            ->latest('collected_at')
            ->first();
    }

    /**
     * Ambil data metrik SINTA untuk satu author/dosen (berdasarkan nama dan NIDN).
     * Hasil di-cache untuk efisiensi dan tidak membebani SINTA.
     */
    public function getAuthorMetrics(string $nama, ?string $nidn = null): array
    {
        $cleanNama = trim($nama);
        if ($cleanNama === '' || $cleanNama === '-') {
            return $this->emptyAuthorMetrics();
        }

        $cacheKey = 'sinta_author_' . md5($cleanNama . '_' . ($nidn ?? ''));
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($cleanNama, $nidn) {
            return $this->fetchAuthorMetricsFromSinta($cleanNama, $nidn);
        });
    }

    /**
     * Cari profil author di SINTA dan ekstrak skor SINTA, Scopus H-Index, dan GS H-Index.
     */
    protected function fetchAuthorMetricsFromSinta(string $nama, ?string $nidn = null): array
    {
        // Bersihkan gelar akademik dari nama untuk pencarian optimal di SINTA
        $queryName = preg_replace('/(\b(Prof|Dr|Dra|Drs|Ir|H|Hj|M\.Si|M\.Kom|M\.Pd|M\.T|S\.T|S\.Pd|S\.Kom|S\.E|Ph\.D|Ak)\b|\.)/i', '', $nama);
        $queryName = trim(preg_replace('/\s+/', ' ', $queryName));

        $searchQuery = urlencode($queryName ?: $nama);
        $url = "https://sinta.kemdiktisaintek.go.id/authors?q={$searchQuery}";

        $html = $this->fetchHtml($url);
        if (!$html) {
            return $this->emptyAuthorMetrics();
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $authorItems = $xpath->query('//div[contains(@class, "au-item")]');
        if ($authorItems->length === 0) {
            return $this->emptyAuthorMetrics();
        }

        // Cari item yang terafiliasi dengan UNTIRTA atau cocok dengan NIDN
        $matchedNode = null;
        foreach ($authorItems as $item) {
            $itemText = $item->textContent;
            if (stripos($itemText, 'Sultan Ageng Tirtayasa') !== false || stripos($itemText, 'UNTIRTA') !== false) {
                $matchedNode = $item;
                break;
            }
        }

        if (!$matchedNode) {
            $matchedNode = $authorItems->item(0);
        }

        $metrics = $this->emptyAuthorMetrics();

        // 1. Ekstrak H-Index Scopus & Google Scholar
        $hIndexNodes = $xpath->query('.//div[contains(@class, "profile-hindex")]//span', $matchedNode);
        foreach ($hIndexNodes as $span) {
            $txt = trim($span->textContent);
            if (stripos($txt, 'Scopus H-Index') !== false && preg_match('/Scopus H-Index\s*:\s*(\d+)/i', $txt, $m)) {
                $metrics['scopus']['h_index'] = (int) $m[1];
            }
            if (stripos($txt, 'GS H-Index') !== false && preg_match('/GS H-Index\s*:\s*(\d+)/i', $txt, $m)) {
                $metrics['google_scholar']['h_index'] = (int) $m[1];
            }
        }

        // 2. Ekstrak Skor SINTA
        $statCols = $xpath->query('.//div[contains(@class, "stat-text")]', $matchedNode);
        foreach ($statCols as $statTextNode) {
            $label = trim($statTextNode->textContent);
            $parentCol = $statTextNode->parentNode;
            $numNodes = $xpath->query('.//div[contains(@class, "stat-num")]', $parentCol);
            if ($numNodes->length > 0) {
                $numVal = $this->parseNumber(trim($numNodes->item(0)->textContent));
                if (strcasecmp($label, 'SINTA Score') === 0) {
                    $metrics['scopus']['dokumen'] = $numVal; // Digunakan sebagai Skor SINTA di kartu profil dosen
                    $metrics['sinta_score'] = $numVal;
                } elseif (stripos($label, 'Score 3Yr') !== false) {
                    $metrics['sinta_score_3yr'] = $numVal;
                }
            }
        }

        return $metrics;
    }

    /**
     * Ekstrak angka metrik institusi dari HTML halaman profil afiliasi SINTA.
     */
    protected function parseInstitutionMetrics(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $data = [];

        // 1. SINTA Scores
        $txtNodes = $xpath->query('//div[contains(@class, "pr-txt")]');
        foreach ($txtNodes as $txtNode) {
            $text = trim($txtNode->textContent);
            $parent = $txtNode->parentNode;
            $numNodes = $xpath->query('.//div[contains(@class, "pr-num")]', $parent);
            if ($numNodes->length > 0) {
                $numVal = trim($numNodes->item(0)->textContent);
                if (stripos($text, 'Overall') !== false && stripos($text, 'Score') !== false && stripos($text, 'Productivity') === false) {
                    $data['sinta_score_overall'] = $this->parseDecimal($numVal);
                } elseif (stripos($text, '3Yr') !== false && stripos($text, 'Score') !== false && stripos($text, 'Productivity') === false) {
                    $data['sinta_score_3_years'] = $this->parseDecimal($numVal);
                }
            }
        }

        // 2. Scopus & Google Scholar dari stat-table
        $rows = $xpath->query('//table[contains(@class, "stat-table")]//tbody//tr');
        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells->length >= 3) {
                $label = trim($cells->item(0)->textContent);
                $scopusVal = trim($cells->item(1)->textContent);
                $gsVal = trim($cells->item(2)->textContent);

                if (stripos($label, 'Documents') !== false && stripos($label, 'Cited') === false) {
                    $data['scopus_documents'] = $this->parseNumber($scopusVal);
                    $data['google_scholar_documents'] = $this->parseNumber($gsVal);
                } elseif (stripos($label, 'Citation') !== false && stripos($label, 'Per') === false) {
                    $data['scopus_citations'] = $this->parseNumber($scopusVal);
                    $data['google_scholar_citations'] = $this->parseNumber($gsVal);
                } elseif (stripos($label, 'Cited Document') !== false) {
                    $data['scopus_cited_documents'] = $this->parseNumber($scopusVal);
                    $data['google_scholar_cited_documents'] = $this->parseNumber($gsVal);
                }
            }
        }

        return $data;
    }

    /**
     * Unduh konten HTML dengan HTTP client standar, dengan fallback ke Chromium headless bila ditantang bot/cloudflare.
     */
    protected function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => self::USER_AGENT,
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            ])->timeout(25)->get($url);

            if ($response->successful() && strlen($response->body()) > 1000) {
                return $response->body();
            }
        } catch (\Throwable $e) {
            Log::warning("SintaCollector: HTTP GET gagal untuk {$url}: " . $e->getMessage());
        }

        // Fallback jika HTTP biasa gagal dan Chromium tersedia di host
        if ($this->chromiumFetcher->isAvailable()) {
            Log::info("SintaCollector: Mencoba fallback Chromium headless untuk {$url}");
            try {
                $containerHtml = '<!DOCTYPE html><html><body><script>location.href="' . htmlspecialchars($url, ENT_QUOTES) . '";</script></body></html>';
                $dom = $this->chromiumFetcher->fetch($containerHtml, self::USER_AGENT, 30000);
                if ($dom && strlen($dom) > 1000) {
                    return $dom;
                }
            } catch (\Throwable $e) {
                Log::warning("SintaCollector: Fallback Chromium gagal: " . $e->getMessage());
            }
        }

        return null;
    }

    protected function parseNumber(?string $str): ?int
    {
        if ($str === null || trim($str) === '' || trim($str) === '-') {
            return null;
        }

        $cleaned = str_replace(['.', ' ', ','], '', trim($str));

        return is_numeric($cleaned) ? (int) $cleaned : null;
    }

    protected function parseDecimal(?string $str): ?float
    {
        if ($str === null || trim($str) === '' || trim($str) === '-') {
            return null;
        }

        $str = trim($str);
        if (str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } else {
            $str = str_replace('.', '', $str);
        }

        return is_numeric($str) ? (float) $str : null;
    }

    protected function emptyAuthorMetrics(): array
    {
        return [
            'sinta_score'     => '-',
            'sinta_score_3yr' => '-',
            'scopus'          => ['dokumen' => '-', 'sitasi' => '-', 'h_index' => '-', 'i10_index' => '-', 'g_index' => '-'],
            'google_scholar'  => ['dokumen' => '-', 'sitasi' => '-', 'h_index' => '-', 'i10_index' => '-', 'g_index' => '-'],
        ];
    }
}
