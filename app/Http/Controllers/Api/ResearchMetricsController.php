<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResearchMetric;
use Illuminate\Http\JsonResponse;

class ResearchMetricsController extends Controller
{
    public function index(): JsonResponse
    {
        $institutionId = (int) config('satudata.sinta.institution_id', 501);
        $institutionName = (string) config('satudata.sinta.institution_name', 'Universitas Sultan Ageng Tirtayasa');

        $metric = ResearchMetric::where('institution_id', $institutionId)
            ->latest('collected_at')
            ->first();

        if (!$metric) {
            return response()->json([
                'institution' => $institutionName,
                'sinta' => [
                    'score_overall' => null,
                    'score_3_years' => null,
                ],
                'scopus' => [
                    'documents' => null,
                    'citations' => null,
                    'cited_documents' => null,
                    'h_index' => null,
                ],
                'google_scholar' => [
                    'documents' => null,
                    'citations' => null,
                    'h_index' => null,
                ],
                'source' => 'SINTA',
                'collected_at' => null,
            ]);
        }

        return response()->json([
            'institution' => $institutionName,
            'sinta' => [
                'score_overall' => $metric->sinta_score_overall !== null ? (float) $metric->sinta_score_overall : null,
                'score_3_years' => $metric->sinta_score_3_years !== null ? (float) $metric->sinta_score_3_years : null,
            ],
            'scopus' => [
                'documents' => $metric->scopus_documents,
                'citations' => $metric->scopus_citations,
                'cited_documents' => $metric->scopus_cited_documents,
                'h_index' => $metric->scopus_h_index,
            ],
            'google_scholar' => [
                'documents' => $metric->google_scholar_documents,
                'citations' => $metric->google_scholar_citations,
                'h_index' => $metric->google_scholar_h_index,
            ],
            'source' => $metric->source,
            'collected_at' => $metric->collected_at?->toIso8601String(),
        ]);
    }
}
