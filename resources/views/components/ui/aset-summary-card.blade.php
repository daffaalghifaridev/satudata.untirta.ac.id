@props([
    'stats' => null,
])

@if(!empty($stats))
@php
    $totalUnit = $stats['total_unit'] ?? 0;
    $nilaiPerolehan = (float)($stats['nilai_perolehan'] ?? 0);
    $kondisiBaik = $stats['kondisi_baik'] ?? 0;
    $kondisiRusakBerat = $stats['kondisi_rusak_berat'] ?? 0;
    $kondisiRusakRingan = $stats['kondisi_rusak_ringan'] ?? 0;
    $totalRusak = $kondisiRusakRingan + $kondisiRusakBerat;
    $totalKampus = $stats['total_kampus'] ?? 0;
    $totalTanpaKampus = (int) ($stats['total_tanpa_kampus'] ?? 0);
    $kampusBreakdown = $stats['kampus_breakdown'] ?? [];

    $pctBaik = $totalUnit > 0 ? round(($kondisiBaik / $totalUnit) * 100, 1) : 0;
    $pctRusak = $totalUnit > 0 ? round(($totalRusak / $totalUnit) * 100, 1) : 0;

    // Format nilai perolehan
    if ($nilaiPerolehan >= 1000000000000) {
        $nilaiFormatted = 'Rp ' . number_format($nilaiPerolehan / 1000000000000, 2, ',', '.') . ' T';
    } elseif ($nilaiPerolehan >= 1000000000) {
        $nilaiFormatted = 'Rp ' . number_format($nilaiPerolehan / 1000000000, 2, ',', '.') . ' M';
    } else {
        $nilaiFormatted = 'Rp ' . number_format($nilaiPerolehan, 0, ',', '.');
    }
    $nilaiFull = 'Rp ' . number_format($nilaiPerolehan, 0, ',', '.');
@endphp

<div class="mb-8 space-y-6">
    {{-- 4 Executive KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Card 1: Total Unit BMN --}}
        <div class="bg-white rounded-2xl border border-black/[0.06] p-5 flex flex-col justify-between">
            <div>
                <span class="text-xs font-medium text-[#6E6E73]">Total Unit Aset</span>
                <h3 class="text-2xl sm:text-3xl font-semibold text-[#1D1D1F] mt-2 tracking-tight tabular-nums">
                    {{ number_format($totalUnit, 0, ',', '.') }}
                </h3>
            </div>
            <div class="mt-4 pt-3 border-t border-black/[0.05] flex items-center justify-between text-xs text-[#6E6E73]">
                <span class="font-medium text-[#0071E3]">{{ $totalKampus }} Kampus</span>
                @if($totalTanpaKampus > 0)
                    <span class="font-medium text-amber-700"
                          title="Aset pada data sumber belum memiliki id kampus maupun lokasi, sehingga belum masuk rincian kampus">
                        {{ number_format($totalTanpaKampus, 0, ',', '.') }} belum berlokasi
                    </span>
                @else
                    <span>Inventaris Terdata</span>
                @endif
            </div>
        </div>

        {{-- Card 2: Total Nilai Perolehan --}}
        <div class="bg-white rounded-2xl border border-black/[0.06] p-5 flex flex-col justify-between" title="{{ $nilaiFull }}">
            <div>
                <span class="text-xs font-medium text-[#6E6E73]">Nilai Perolehan</span>
                <h3 class="text-2xl sm:text-3xl font-semibold text-[#1D1D1F] mt-2 tracking-tight tabular-nums">
                    {{ $nilaiFormatted }}
                </h3>
            </div>
            <div class="mt-4 pt-3 border-t border-black/[0.05] flex items-center justify-between text-xs text-[#6E6E73]">
                <span class="font-medium text-[#1D1D1F]">Akumulasi BMN</span>
                <span>Nilai Buku Sistem</span>
            </div>
        </div>

        {{-- Card 3: Kondisi Baik --}}
        <div class="bg-white rounded-2xl border border-black/[0.06] p-5 flex flex-col justify-between">
            <div>
                <span class="text-xs font-medium text-[#6E6E73]">Kondisi Baik</span>
                <h3 class="text-2xl sm:text-3xl font-semibold text-emerald-700 mt-2 tracking-tight tabular-nums">
                    {{ number_format($kondisiBaik, 0, ',', '.') }}
                </h3>
            </div>
            <div class="mt-4 pt-3 border-t border-black/[0.05] flex items-center justify-between text-xs text-[#6E6E73]">
                <span class="font-semibold text-emerald-700 tabular-nums">{{ $pctBaik }}%</span>
                <span>Siap Operasional</span>
            </div>
        </div>

        {{-- Card 4: Perlu Perhatian / Pemeliharaan --}}
        <div class="bg-white rounded-2xl border border-black/[0.06] p-5 flex flex-col justify-between">
            <div>
                <span class="text-xs font-medium text-[#6E6E73]">Perlu Pemeliharaan</span>
                <h3 class="text-2xl sm:text-3xl font-semibold {{ $totalRusak > 0 ? 'text-amber-700' : 'text-[#1D1D1F]' }} mt-2 tracking-tight tabular-nums">
                    {{ number_format($totalRusak, 0, ',', '.') }}
                </h3>
            </div>
            <div class="mt-4 pt-3 border-t border-black/[0.05] flex items-center justify-between text-xs text-[#6E6E73] tabular-nums">
                <span>{{ number_format($kondisiRusakRingan, 0, ',', '.') }} Ringan &bull; {{ number_format($kondisiRusakBerat, 0, ',', '.') }} Berat</span>
                <span>{{ $pctRusak }}%</span>
            </div>
        </div>

    </div>

    {{-- Main Chart Card --}}
    <div class="bg-white rounded-2xl border border-black/[0.06] p-6 sm:p-7">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-5 border-b border-black/[0.06]">
            <div>
                <h2 class="text-base sm:text-lg font-semibold text-[#1D1D1F] tracking-tight">
                    Persebaran &amp; Kondisi Inventaris Berdasarkan Kampus
                </h2>
                <p class="text-xs text-[#6E6E73] mt-0.5">
                    Komparasi unit kondisi baik, rusak ringan, dan rusak berat di seluruh lokasi kampus UNTIRTA
                </p>
            </div>
            <span class="text-xs font-medium text-[#6E6E73] self-start sm:self-auto">
                Sumber: SIMANTAP
            </span>
        </div>

        <!-- Big Chart Container -->
        <div class="relative w-full my-6" style="height: 380px;">
            <canvas data-aset-combo-chart data-payload='@json($kampusBreakdown)'></canvas>
        </div>

        <!-- Custom Legend Below Chart -->
        <div class="gap-4 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-xs sm:text-sm font-semibold text-gray-600">
            <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full bg-[#00e676] inline-block shadow-sm"></span>
                <span>Kondisi Baik</span>
            </div>
            @if($kondisiRusakRingan > 0)
            <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full bg-[#b9f6ca] inline-block shadow-sm"></span>
                <span>Rusak Ringan</span>
            </div>
            @endif
            <div class="flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full bg-[#ff5252] inline-block shadow-sm"></span>
                <span>Rusak Berat</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-7 h-0.5 bg-[#ffb74d] relative inline-flex items-center justify-center">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#ffb74d] border-2 border-white absolute shadow-xs"></span>
                </span>
                <span>Total Unit Aset</span>
            </div>
        </div>

    </div>
</div>
@endif
