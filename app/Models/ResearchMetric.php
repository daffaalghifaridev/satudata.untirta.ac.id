<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchMetric extends Model
{
    protected $table = 'research_metrics';

    protected $fillable = [
        'institution_id',
        'sinta_score_overall',
        'sinta_score_3_years',
        'scopus_documents',
        'scopus_citations',
        'scopus_cited_documents',
        'google_scholar_documents',
        'google_scholar_citations',
        'scopus_h_index',
        'google_scholar_h_index',
        'source',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'institution_id'           => 'integer',
            'sinta_score_overall'      => 'decimal:3',
            'sinta_score_3_years'      => 'decimal:3',
            'scopus_documents'         => 'integer',
            'scopus_citations'         => 'integer',
            'scopus_cited_documents'   => 'integer',
            'google_scholar_documents' => 'integer',
            'google_scholar_citations' => 'integer',
            'scopus_h_index'           => 'integer',
            'google_scholar_h_index'   => 'integer',
            'collected_at'             => 'datetime',
        ];
    }
}
