<x-layout>
    <x-slot:title>
        {{ $title ?? 'Akademik' }}
    </x-slot:title>

    @php
        $daftarStatistik = $datas ?? [];
        $isDataEmpty = !\Illuminate\Support\Facades\DB::table('siakang_semester_stats')->where('semester', $kodeSemesterTampil)->exists();
    @endphp

    {{-- Statistik mahasiswa halaman ini ditarik dari SIAKANG. --}}
    <x-ui.data-freshness sumber="halaman.akademik" :is-empty="$isDataEmpty || $totalDosen === null || empty($chartPeminat)" :semester="$kodeSemesterTampil" />

    <section class="relative space-y-6"
        x-data="{
            open: false,
            isSwitching: false,
            targetSemester: ''
        }"
        x-init="window.addEventListener('pageshow', () => { isSwitching = false; })">

        {{-- Komponen Loading Overlay Umum --}}
        <x-ui.loading-overlay show="isSwitching" />

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-[-0.02em] text-[#1D1D1F]">
                    Ringkasan Akademik
                </h1>
                <p class="mt-1 text-sm text-[#6E6E73]">
                    Statistik mahasiswa aktif, lulusan, jalur masuk, dan persebaran dosen.
                </p>
            </div>

            @php
                $labelSemesterAktif = $daftarSemester[$kodeSemesterTampil] ?? $kodeSemesterTampil;
            @endphp
            <div class="relative inline-block self-start sm:self-auto">
                <button @click="if (!isSwitching) open = !open" @keydown.escape="open = false"
                    :disabled="isSwitching"
                    class="inline-flex items-center gap-2 min-h-[40px] rounded-xl bg-[#0071E3] px-4 py-2 text-xs font-medium text-white hover:bg-[#0077ED] transition-colors cursor-pointer disabled:opacity-75 disabled:cursor-wait">
                    <template x-if="!isSwitching">
                        <span class="inline-flex items-center gap-2">
                            <i class="fa-solid fa-calendar-days text-[11px] text-white/80"></i>
                            <span>{{ $labelSemesterAktif }}</span>
                            <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="{ 'rotate-180': open }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </span>
                    </template>
                    <template x-if="isSwitching">
                        <span class="inline-flex items-center gap-2">
                            <i class="fa-solid fa-circle-notch fa-spin text-xs text-white"></i>
                            <span>Memuat data...</span>
                        </span>
                    </template>
                </button>
                <div x-show="open" @click.outside="open = false" x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 z-50 mt-2 w-72 origin-top-right rounded-2xl bg-white border border-black/[0.08] shadow-lg max-h-80 overflow-y-auto">
                    <div class="p-1.5">
                        @foreach ($daftarSemester as $kode => $label)
                            <a href="{{ route('akademik', ['semester' => $kode]) }}"
                                @click="if ('{{ $kode }}' !== '{{ $kodeSemesterTampil }}') { isSwitching = true; targetSemester = '{{ addslashes($label) }}'; open = false; }"
                                class="flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-colors
                                    {{ $kode === $kodeSemesterTampil
                                        ? 'bg-[#0071E3]/10 text-[#0071E3] font-semibold'
                                        : 'text-[#1D1D1F] hover:bg-[#F5F5F7]' }}">
                                <span>{{ $label }}</span>
                                @if ($kode === $kodeSemesterTampil)
                                    <svg class="h-3.5 w-3.5 text-[#0071E3]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @if (request()->has('semester') && isset($daftarSemester[request('semester')]))
            <div x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-200 opacity-0 scale-95"
                class="mb-5 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3.5 text-emerald-900 shadow-sm backdrop-blur">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-sm">
                        <i class="fa-solid fa-circle-check text-base"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-emerald-950">
                            Berhasil mengganti semester ke {{ $labelSemesterAktif }}
                        </p>
                        <p class="text-xs text-emerald-700 mt-0.5">
                            Data ringkasan dan statistik di bawah ini telah diperbarui sesuai periode {{ $labelSemesterAktif }}.
                        </p>
                    </div>
                </div>
                <button @click="show = false" class="rounded-lg p-1.5 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-800 transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
        @endif


        @if ($isDataEmpty)
            <div class="space-y-6">
                <x-ui.skeleton-stat-card count="4" label="Memuat ringkasan data mahasiswa..." />
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-9">
                        <x-ui.skeleton-chart title="Memuat persebaran mahasiswa berdasarkan fakultas..." height="min-h-[300px]" />
                    </div>
                    <div class="lg:col-span-3">
                        <x-ui.skeleton-chart title="Memuat komposisi jenjang..." height="min-h-[300px]" />
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-6">
                        <x-ui.skeleton-chart title="Memuat tren mahasiswa baru 5 tahun terakhir..." height="min-h-[300px]" />
                    </div>
                    <div class="lg:col-span-6">
                        <x-ui.skeleton-chart title="Memuat persebaran mahasiswa baru berdasarkan jalur masuk..." height="min-h-[300px]" />
                    </div>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($daftarStatistik as $data)
                    <x-ui.akademik-card :title="$data['title']" :value="$data['value']" :href="$data['href']" :icon-class="$data['iconClass']"
                        :badge-text="$data['badgeText']" :badge-color="$data['badgeColor']" :footer-text="$data['footerText']" />
                @endforeach
            </div>
        @endif
    </section>

    @if (!$isDataEmpty)
    <section class="mt-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-9 bg-white rounded-2xl border border-black/[0.06] p-6 sm:p-7 flex flex-col"
                data-fakultas-root data-payload="{{ json_encode($fakultas) }}">
                <div class="mb-6">
                    <h2 class="text-base font-semibold tracking-tight text-[#1D1D1F]">Persebaran Mahasiswa Berdasarkan Fakultas</h2>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Sumber: UPA Teknologi Informasi dan Komunikasi</p>
                </div>
                <div class="relative w-full flex-1 min-h-[300px]">
                    <canvas data-fakultas-chart></canvas>
                </div>
            </div>
            <div
                class="lg:col-span-3 bg-white rounded-2xl border border-black/[0.06] p-6 sm:p-7 flex flex-col gap-6">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">
                    Sorotan Program Sarjana (S1)
                </h3>
                <div>
                    <h4 class="text-xs font-medium text-[#6E6E73] mb-1">Mahasiswa Terbanyak</h4>
                    @if(!empty($jurusanTerbanyak))
                        <p class="text-3xl font-semibold text-[#1D1D1F] tracking-tight tabular-nums">
                            {{ number_format($jurusanTerbanyak['jumlah_mahasiswa_aktif'] ?? 0, 0, ',', '.') }}
                        </p>
                        <p class="text-xs mt-1.5 text-[#6E6E73] font-medium truncate" title="{{ $jurusanTerbanyak['nama_prodi'] ?? '-' }}">
                            {{ $jurusanTerbanyak['nama_prodi'] ?? '-' }}
                        </p>
                    @else
                        <p class="text-3xl font-semibold text-[#6E6E73] tracking-tight">-</p>
                        <p class="text-xs mt-1.5 text-[#6E6E73]">Belum ada data semester ini</p>
                    @endif
                </div>
                <div class="border-t border-black/[0.06]"></div>
                <div>
                    <h4 class="text-xs font-medium text-[#6E6E73] mb-1">Mahasiswa Paling Sedikit</h4>
                    @if(!empty($jurusanSedikit))
                        <p class="text-3xl font-semibold text-[#1D1D1F] tracking-tight tabular-nums">
                            {{ number_format($jurusanSedikit['jumlah_mahasiswa_aktif'] ?? 0, 0, ',', '.') }}
                        </p>
                        <p class="text-xs mt-1.5 text-[#6E6E73] font-medium truncate" title="{{ $jurusanSedikit['nama_prodi'] ?? '-' }}">
                            {{ $jurusanSedikit['nama_prodi'] ?? '-' }}
                        </p>
                    @else
                        <p class="text-3xl font-semibold text-[#6E6E73] tracking-tight">-</p>
                        <p class="text-xs mt-1.5 text-[#6E6E73]">Belum ada data semester ini</p>
                    @endif
                </div>
                <div class="border-t border-black/[0.06]"></div>
                <div>
                    <h4 class="text-xs font-medium text-[#6E6E73] mb-1">Lulusan Terbanyak</h4>
                    @if(!empty($jurusanLulusTerbanyak))
                        <p class="text-3xl font-semibold text-[#1D1D1F] tracking-tight tabular-nums">
                            {{ number_format($jurusanLulusTerbanyak['jumlah_mahasiswa_lulus'] ?? 0, 0, ',', '.') }}
                        </p>
                        <p class="text-xs mt-1.5 text-[#6E6E73] font-medium truncate" title="{{ $jurusanLulusTerbanyak['nama_prodi'] ?? '-' }}">
                            {{ $jurusanLulusTerbanyak['nama_prodi'] ?? '-' }}
                        </p>
                    @else
                        <p class="text-3xl font-semibold text-[#6E6E73] tracking-tight">-</p>
                        <p class="text-xs mt-1.5 text-[#6E6E73]">Belum ada data semester ini</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="mt-6">
        <div class="bg-white rounded-2xl border border-black/[0.06] p-6 sm:p-7" data-peminat-root
            data-payload="{{ json_encode($chartPeminat) }}">

            <div class="mb-6">
                <h2 class="text-base font-semibold tracking-tight text-[#1D1D1F]">Jumlah Mahasiswa per Jalur Masuk</h2>
                <p class="text-xs text-[#6E6E73] mt-0.5">Sumber: UPA Teknologi Informasi dan Komunikasi</p>
            </div>

            <div class="relative w-full h-[380px]">
                <canvas data-peminat-chart></canvas>
            </div>

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-[#6E6E73]">
                <div class="rounded-xl bg-[#F5F5F7] px-3.5 py-2.5">
                    <span class="font-semibold text-[#1D1D1F]">Seleksi Nasional:</span> SNBP, SNBT, SNMPTN, dan SBMPTN.
                </div>
                <div class="rounded-xl bg-[#F5F5F7] px-3.5 py-2.5">
                    <span class="font-semibold text-[#1D1D1F]">Seleksi Mandiri:</span> SMPTN, SMBT, SMMPTN-BARAT, Ujian Mandiri, dan UMB.
                </div>
                <div class="rounded-xl bg-[#F5F5F7] px-3.5 py-2.5">
                    <span class="font-semibold text-[#1D1D1F]">Lainnya:</span> Jalur penerimaan di luar kategori nasional dan mandiri.
                </div>
            </div>
        </div>
    </section>

    <section class="mt-8 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold tracking-tight text-[#1D1D1F]">
                Dosen
            </h2>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white border border-black/[0.06] px-3.5 py-1.5 text-xs font-semibold text-[#1D1D1F] tabular-nums">
                {{ $totalDosen === null ? '-' : number_format($totalDosen, 0, ',', '.') }} Dosen
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-7 bg-white rounded-2xl border border-black/[0.06] p-6 sm:p-7"
                data-dosen-fakultas-root data-payload="{{ json_encode($dosenByFakultas ?? []) }}">
                <div class="mb-6">
                    <h3 class="text-base font-semibold tracking-tight text-[#1D1D1F]">Persebaran Dosen Berdasarkan Fakultas</h3>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Sumber: SIMPEG</p>
                </div>
                <div class="relative w-full h-[340px]">
                    <canvas data-dosen-fakultas-chart></canvas>
                </div>
            </div>

            <div class="lg:col-span-5 bg-white rounded-2xl border border-black/[0.06] p-6 sm:p-7"
                data-dosen-status-root data-payload="{{ json_encode($dosenByStatus ?? []) }}">
                <div class="mb-6">
                    <h3 class="text-base font-semibold tracking-tight text-[#1D1D1F]">Dosen Berdasarkan Status Kepegawaian</h3>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Sumber: SIMPEG</p>
                </div>
                <div class="relative w-full h-[340px]">
                    <canvas data-dosen-status-chart></canvas>
                </div>
            </div>
        </div>
    </section>
    @endif
</x-layout>
