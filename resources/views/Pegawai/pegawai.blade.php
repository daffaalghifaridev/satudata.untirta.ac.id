<x-layout>
    <x-slot:title>
        {{ $title ?? 'Pegawai' }}
    </x-slot:title>

    @php
        $daftarStatistik = $daftarStatistik ?? [];
        $statusPegawai = $statusPegawai ?? [];
        $isDataEmpty = !\Illuminate\Support\Facades\DB::table('pegawai_summaries')->exists();
    @endphp

    {{-- Data pegawai ditarik dari SIMPEG. --}}
    <x-ui.data-freshness sumber="simpeg.pegawai" :is-empty="$isDataEmpty" :updated-at="\App\Models\Pegawai::min('updated_at')" />

    <section class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-[-0.02em] text-[#1D1D1F]">
                    Ringkasan Kepegawaian
                </h1>
                <p class="text-sm text-[#6E6E73] mt-1">
                    Sumber: SIMPEG UNTIRTA
                </p>
            </div>
            <a href="{{ route('pegawai.profil-dosen') }}"
               class="inline-flex items-center gap-2 min-h-[40px] px-4 py-2 rounded-xl text-xs font-medium bg-[#0071E3] text-white hover:bg-[#0077ED] transition-colors self-start sm:self-auto">
                <i class="fa-solid fa-users text-[11px]"></i>
                <span>Buka Direktori Pegawai</span>
            </a>
        </div>

        @if ($isDataEmpty)
            <div class="space-y-6">
                <x-ui.skeleton-stat-card count="4" label="Memuat ringkasan statistik kepegawaian..." />
                <x-ui.skeleton-chart title="Memuat persebaran status kerja pegawai..." height="min-h-[320px]" />
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-6">
                        <x-ui.skeleton-chart title="Memuat status kepegawaian SDM..." height="min-h-[320px]" />
                    </div>
                    <div class="lg:col-span-6">
                        <x-ui.skeleton-chart title="Memuat persebaran level pegawai..." height="min-h-[320px]" />
                    </div>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($daftarStatistik as $data)
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5 flex flex-col justify-between">
                        <div class="flex justify-between items-start gap-3">
                            <h3 class="text-xs font-medium text-[#6E6E73]">
                                {{ $data['title'] }}
                            </h3>
                            <i class="{{ $data['iconClass'] }} text-sm"></i>
                        </div>
                        <div class="mt-3">
                            <p class="text-3xl font-semibold text-[#1D1D1F] tracking-tight tabular-nums">
                                {{ is_numeric($data['value']) ? number_format($data['value'], 0, ',', '.') : ($data['value'] ?? '-') }}
                            </p>
                        </div>
                        <div class="mt-4 border-t border-black/[0.05] pt-3 text-xs text-[#6E6E73]">
                            {{ $data['footerText'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if (!$isDataEmpty)
        <section class="mt-6">
            <div class="bg-white rounded-2xl border border-black/[0.06] p-6 sm:p-7 flex flex-col">
                <div class="mb-6">
                    <h2 class="text-base font-semibold tracking-tight text-[#1D1D1F]">Persebaran Status Kerja Pegawai</h2>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Komposisi pegawai berdasarkan status kerja</p>
                </div>
                <div class="relative w-full flex-1 min-h-[320px]">
                    <x-ui.pegawai-chart :chartData="$chartStatusKerja ?? ['labels' => [], 'data' => [], 'total' => 0]" title="Persebaran Status Kerja" type="bar" :standalone="false" :show-header="false" />
                </div>
            </div>
        </section>

        <section class="mt-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="lg:col-span-6 bg-white rounded-2xl border border-black/[0.06] p-6 sm:p-7 flex flex-col">
                    <div class="mb-6">
                        <h2 class="text-base font-semibold tracking-tight text-[#1D1D1F]">Status Kepegawaian SDM</h2>
                        <p class="text-xs text-[#6E6E73] mt-0.5">Ringkasan status keaktifan seluruh SDM</p>
                    </div>
                    <div class="relative w-full flex-1 min-h-[320px]">
                        <x-ui.pegawai-chart :chartData="$chartStatusPegawai ?? ['labels' => [], 'data' => [], 'total' => 0]" title="Status Kepegawaian" type="doughnut" :standalone="false" :show-header="false" />
                    </div>
                </div>

                <div class="lg:col-span-6 bg-white rounded-2xl border border-black/[0.06] p-6 sm:p-7 flex flex-col">
                    <div class="mb-6">
                        <h2 class="text-base font-semibold tracking-tight text-[#1D1D1F]">Persebaran Level Pegawai</h2>
                        <p class="text-xs text-[#6E6E73] mt-0.5">Komposisi Dosen, Tendik, Dosen DT, dan Dosen LB</p>
                    </div>
                    <div class="relative w-full flex-1 min-h-[320px]">
                        <x-ui.pegawai-chart :chartData="$chartLevelPegawai ?? ['labels' => [], 'data' => [], 'total' => 0]" title="Persebaran Level Pegawai" type="polarArea" :standalone="false" :show-header="false" />
                    </div>
                </div>
            </div>
        </section>
    @endif
</x-layout>
