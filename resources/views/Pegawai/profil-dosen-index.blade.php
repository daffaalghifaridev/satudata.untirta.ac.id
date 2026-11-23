<x-layout>
    <x-slot:title>
        {{ $title ?? 'Direktori Pegawai' }}
    </x-slot:title>

    @php
        $isDataEmpty = ($countSemua ?? 0) === 0;
        $hasActiveFilters = !empty($search) || !empty($selectedFakultas) || !empty($selectedJabatan) || !empty($selectedStatusKerja);
    @endphp

    {{-- Daftar pegawai halaman ini bersumber dari SIMPEG. --}}
    <x-ui.data-freshness sumber="simpeg.pegawai" :is-empty="$isDataEmpty" :updated-at="\App\Models\Pegawai::min('updated_at')" />

    <section class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <nav aria-label="Breadcrumb" class="mb-2 flex items-center gap-2 text-xs font-medium text-[#6E6E73]">
                    <a href="/" class="hover:text-[#1D1D1F] transition-colors">Beranda</a>
                    <span>/</span>
                    <a href="{{ route('pegawai') }}" class="hover:text-[#1D1D1F] transition-colors">Pegawai</a>
                    <span>/</span>
                    <span class="text-[#1D1D1F]">
                        {{ ($selectedKategori ?? '') === 'dosen' ? 'Direktori Dosen' : (($selectedKategori ?? '') === 'staf' ? 'Direktori Staf' : 'Direktori Pegawai') }}
                    </span>
                </nav>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-[-0.02em] text-[#1D1D1F]">
                    {{ ($selectedKategori ?? '') === 'dosen' ? 'Direktori Dosen' : (($selectedKategori ?? '') === 'staf' ? 'Direktori Tenaga Kependidikan' : 'Direktori Pegawai') }}
                </h1>
                <p class="mt-1 text-sm text-[#6E6E73]">
                    {{ ($selectedKategori ?? '') === 'dosen' ? 'Daftar tenaga pendidik terintegrasi SIMPEG dan SIPP UNTIRTA.' : (($selectedKategori ?? '') === 'staf' ? 'Daftar tenaga kependidikan (staf) terintegrasi SIMPEG UNTIRTA.' : 'Seluruh sumber daya manusia dosen dan tenaga kependidikan UNTIRTA.') }}
                </p>
            </div>

            <a href="{{ route('pegawai') }}"
               class="inline-flex items-center justify-center gap-2 min-h-[40px] px-4 py-2 rounded-xl text-xs font-medium bg-white border border-black/[0.08] text-[#1D1D1F] hover:bg-black/[0.03] transition-colors self-start sm:self-auto">
                <i class="fa-solid fa-chart-pie text-[#0071E3]"></i>
                <span>Statistik SDM</span>
            </a>
        </div>

        {{-- Apple Segmented Control: Kategori --}}
        <div class="inline-flex flex-wrap items-center gap-1 p-1 rounded-xl bg-black/[0.05]">
            <a href="{{ route('pegawai.profil-dosen', array_merge(request()->except(['kategori', 'page']), ['kategori' => 'semua'])) }}"
               class="inline-flex items-center gap-2 min-h-[36px] px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ ($selectedKategori ?? 'semua') === 'semua' ? 'bg-white text-[#1D1D1F] font-semibold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]' }}">
                <span>Semua</span>
                <span class="tabular-nums text-[11px] {{ ($selectedKategori ?? 'semua') === 'semua' ? 'text-[#0071E3]' : 'text-[#6E6E73]' }}">
                    {{ number_format($countSemua ?? 0, 0, ',', '.') }}
                </span>
            </a>

            <a href="{{ route('pegawai.profil-dosen', array_merge(request()->except(['kategori', 'page']), ['kategori' => 'dosen'])) }}"
               class="inline-flex items-center gap-2 min-h-[36px] px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ ($selectedKategori ?? '') === 'dosen' ? 'bg-white text-[#1D1D1F] font-semibold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]' }}">
                <span>Dosen</span>
                <span class="tabular-nums text-[11px] {{ ($selectedKategori ?? '') === 'dosen' ? 'text-[#0071E3]' : 'text-[#6E6E73]' }}">
                    {{ number_format($countDosen ?? 0, 0, ',', '.') }}
                </span>
            </a>

            <a href="{{ route('pegawai.profil-dosen', array_merge(request()->except(['kategori', 'page']), ['kategori' => 'staf'])) }}"
               class="inline-flex items-center gap-2 min-h-[36px] px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all {{ ($selectedKategori ?? '') === 'staf' ? 'bg-white text-[#1D1D1F] font-semibold shadow-xs' : 'text-[#6E6E73] hover:text-[#1D1D1F]' }}">
                <span>Tendik / Staf</span>
                <span class="tabular-nums text-[11px] {{ ($selectedKategori ?? '') === 'staf' ? 'text-[#0071E3]' : 'text-[#6E6E73]' }}">
                    {{ number_format($countStaf ?? 0, 0, ',', '.') }}
                </span>
            </a>
        </div>

        @if ($isDataEmpty)
            <div class="space-y-6">
                <x-ui.skeleton-stat-card count="4" label="Memuat ringkasan data pegawai..." />
                <x-ui.skeleton-table :rows="8" :cols="6" label="Memuat direktori pegawai dari SIMPEG..." />
            </div>
        @else
            {{-- Ringkasan Metrik Kompak --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @if (($selectedKategori ?? 'semua') === 'semua')
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5">
                        <p class="text-xs font-medium text-[#6E6E73]">Total Pegawai</p>
                        <p class="mt-1.5 text-2xl sm:text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">{{ number_format($countSemua ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5">
                        <p class="text-xs font-medium text-[#6E6E73]">Total Dosen</p>
                        <p class="mt-1.5 text-2xl sm:text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">{{ number_format($countDosen ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5">
                        <p class="text-xs font-medium text-[#6E6E73]">Staf / Tendik</p>
                        <p class="mt-1.5 text-2xl sm:text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">{{ number_format($countStaf ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5">
                        <p class="text-xs font-medium text-[#6E6E73]">Unit Kerja</p>
                        <p class="mt-1.5 text-2xl sm:text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">{{ count($facultyOptions ?? []) }}</p>
                    </div>
                @elseif (($selectedKategori ?? '') === 'staf')
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5 col-span-1 lg:col-span-2">
                        <p class="text-xs font-medium text-[#6E6E73]">Total Staf Tendik</p>
                        <p class="mt-1.5 text-2xl sm:text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">{{ number_format($countStaf ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5 col-span-1 lg:col-span-2">
                        <p class="text-xs font-medium text-[#6E6E73]">Unit Kerja</p>
                        <p class="mt-1.5 text-2xl sm:text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">{{ count($facultyOptions ?? []) }} Unit</p>
                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5">
                        <p class="text-xs font-medium text-[#6E6E73]">Total Dosen</p>
                        <p class="mt-1.5 text-2xl sm:text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">{{ number_format($totalDosen, 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5">
                        <p class="text-xs font-medium text-[#6E6E73]">Guru Besar</p>
                        <p class="mt-1.5 text-2xl sm:text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">{{ number_format($totalGuruBesar, 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5">
                        <p class="text-xs font-medium text-[#6E6E73]">Lektor Kepala</p>
                        <p class="mt-1.5 text-2xl sm:text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">{{ number_format($totalLektorKepala, 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-white rounded-2xl border border-black/[0.06] p-5">
                        <p class="text-xs font-medium text-[#6E6E73]">Lektor</p>
                        <p class="mt-1.5 text-2xl sm:text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">{{ number_format($totalLektor, 0, ',', '.') }}</p>
                    </div>
                @endif
            </div>

            {{-- Satu Toolbar Filter Terpadu + Tabel --}}
            <div class="bg-white rounded-2xl border border-black/[0.06] overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-black/[0.06]">
                    <form method="GET" action="{{ route('pegawai.profil-dosen') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                        <input type="hidden" name="kategori" value="{{ $selectedKategori ?? 'semua' }}">

                        {{-- Search --}}
                        <div class="lg:col-span-4 relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-[#6E6E73]">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text"
                                   name="search"
                                   value="{{ $search ?? '' }}"
                                   placeholder="Cari nama, NIP, jabatan, unit..."
                                   class="w-full min-h-[42px] pl-9 pr-3 py-2 rounded-xl bg-[#F5F5F7] border border-transparent text-xs text-[#1D1D1F] placeholder-[#6E6E73] focus:bg-white focus:border-[#0071E3] transition-colors">
                        </div>

                        {{-- Fakultas / Unit Kerja (Dropdown terpadu, bukan kartu pill) --}}
                        <div class="lg:col-span-3">
                            <select name="fakultas"
                                    aria-label="Filter Fakultas atau Unit Kerja"
                                    class="w-full min-h-[42px] px-3 py-2 rounded-xl bg-[#F5F5F7] border border-transparent text-xs text-[#1D1D1F] focus:bg-white focus:border-[#0071E3] transition-colors">
                                <option value="">Semua Fakultas / Unit ({{ count($facultyOptions ?? []) }})</option>
                                @foreach ($facultyOptions ?? [] as $fOpt)
                                    <option value="{{ $fOpt }}" {{ ($selectedFakultas ?? '') === $fOpt ? 'selected' : '' }}>
                                        {{ $fOpt }} ({{ $facultyCounts[$fOpt] ?? 0 }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Jabatan --}}
                        <div class="lg:col-span-2">
                            <select name="jabatan"
                                    aria-label="Filter Jabatan"
                                    class="w-full min-h-[42px] px-3 py-2 rounded-xl bg-[#F5F5F7] border border-transparent text-xs text-[#1D1D1F] focus:bg-white focus:border-[#0071E3] transition-colors">
                                <option value="">Semua Jabatan</option>
                                @foreach ($jabatanOptions ?? [] as $jOpt)
                                    <option value="{{ $jOpt }}" {{ ($selectedJabatan ?? '') === $jOpt ? 'selected' : '' }}>
                                        {{ $jOpt }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Status Kerja --}}
                        <div class="lg:col-span-2">
                            <select name="status_kerja"
                                    aria-label="Filter Status Kerja"
                                    class="w-full min-h-[42px] px-3 py-2 rounded-xl bg-[#F5F5F7] border border-transparent text-xs text-[#1D1D1F] focus:bg-white focus:border-[#0071E3] transition-colors">
                                <option value="">Semua Status</option>
                                @foreach ($statusKerjaOptions as $opt)
                                    <option value="{{ $opt }}" {{ ($selectedStatusKerja ?? '') === $opt ? 'selected' : '' }}>
                                        {{ $opt }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Submit & Contextual Reset --}}
                        <div class="lg:col-span-1 flex items-center gap-2">
                            <button type="submit"
                                    class="flex-1 min-h-[42px] px-3.5 py-2 rounded-xl bg-[#0071E3] text-white text-xs font-medium hover:bg-[#0077ED] transition-colors cursor-pointer">
                                Terapkan
                            </button>
                            @if ($hasActiveFilters)
                                <a href="{{ route('pegawai.profil-dosen', ['kategori' => $selectedKategori ?? 'semua']) }}"
                                   class="inline-flex items-center justify-center min-h-[42px] min-w-[42px] rounded-xl bg-[#F5F5F7] text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.08] transition-colors"
                                   title="Reset semua filter"
                                   aria-label="Reset semua filter">
                                    <i class="fa-solid fa-rotate-left text-xs"></i>
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="mt-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-[#6E6E73]">
                        <div>
                            Menampilkan <strong class="font-semibold text-[#1D1D1F] tabular-nums">{{ $dosens->firstItem() ?? 0 }} - {{ $dosens->lastItem() ?? 0 }}</strong>
                            dari <strong class="font-semibold text-[#1D1D1F] tabular-nums">{{ number_format($totalFiltered, 0, ',', '.') }}</strong>
                            {{ ($selectedKategori ?? '') === 'dosen' ? 'dosen' : (($selectedKategori ?? '') === 'staf' ? 'staf tendik' : 'pegawai') }}
                            @if (!empty($selectedFakultas))
                                di <span class="font-medium text-[#1D1D1F]">{{ $selectedFakultas }}</span>
                            @endif
                        </div>
                        <div class="tabular-nums">
                            Halaman {{ $dosens->currentPage() }} dari {{ $dosens->lastPage() }}
                        </div>
                    </div>
                </div>

                {{-- Tabel Pegawai --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#F5F5F7]/70 border-b border-black/[0.06] text-[#6E6E73] font-semibold">
                            <tr>
                                <th class="px-5 py-3.5 w-12 text-center">No</th>
                                <th class="px-5 py-3.5">Nama &amp; Gelar</th>
                                <th class="px-5 py-3.5">NIP</th>
                                <th class="px-5 py-3.5">Peran</th>
                                <th class="px-5 py-3.5">Unit Kerja</th>
                                <th class="px-5 py-3.5">Jabatan</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5 text-right">Profil</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black/[0.05] text-[#1D1D1F]">
                            @forelse ($dosens as $idx => $d)
                                @php
                                    $namaPegawai = $d['nama_pegawai_lengkap'] ?? $d['nama_pegawai'] ?? $d['nama'] ?? '-';
                                    $nipPegawai = $d['nip'] ?? '';
                                    $fakultas = $d['unitKerja'] ?? $d['unit_kerja'] ?? '-';
                                    $lvlPegawai = strtolower(trim((string) ($d['nama_level_pegawai'] ?? $d['levelPegawai'] ?? $d['level_pegawai'] ?? '')));
                                    $isDosenRow = str_contains($lvlPegawai, 'dosen');
                                    $jabatan = $d['nama_jabatan'] ?? $d['jabatan'] ?? ($isDosenRow ? 'Dosen Pengajar' : 'Staf');
                                    $profilUrl = !empty($nipPegawai)
                                        ? ($isDosenRow ? route('pegawai.profil-dosen.show', ['nip' => $nipPegawai]) : route('pegawai.profil-staf.show', ['nip' => $nipPegawai]))
                                        : null;
                                @endphp
                                <tr class="hover:bg-[#F5F5F7]/60 transition-colors">
                                    <td class="px-5 py-3.5 text-center text-[#6E6E73] tabular-nums">
                                        {{ $dosens->firstItem() + $idx }}
                                    </td>

                                    <td class="px-5 py-3.5">
                                        @if ($profilUrl)
                                            <a href="{{ $profilUrl }}"
                                               class="text-[13px] font-medium text-[#1D1D1F] hover:text-[#0071E3] transition-colors">
                                                {{ $namaPegawai }}
                                            </a>
                                        @else
                                            <span class="text-[13px] font-medium text-[#1D1D1F]">{{ $namaPegawai }}</span>
                                        @endif
                                    </td>

                                    <td class="px-5 py-3.5 text-[#6E6E73] tabular-nums whitespace-nowrap">
                                        {{ $nipPegawai ?: '-' }}
                                    </td>

                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium {{ $isDosenRow ? 'bg-[#0071E3]/10 text-[#0071E3]' : 'bg-black/[0.05] text-[#6E6E73]' }}">
                                            {{ $isDosenRow ? 'Dosen' : 'Tendik' }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-3.5 text-[#1D1D1F]">
                                        {{ $fakultas }}
                                    </td>

                                    <td class="px-5 py-3.5 text-[#6E6E73]">
                                        {{ $jabatan }}
                                    </td>

                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-[#F5F5F7] text-[#1D1D1F]">
                                                {{ $d['nama_stspegawai'] ?: '-' }}
                                            </span>
                                            @if (!empty($d['nama_stspeg']))
                                                <span class="text-[11px] text-[#6E6E73]">
                                                    {{ $d['nama_stspeg'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        @if ($profilUrl)
                                            <a href="{{ $profilUrl }}"
                                               class="inline-flex items-center gap-1 text-xs font-medium text-[#0071E3] hover:underline">
                                                <span>Lihat</span>
                                                <i class="fa-solid fa-chevron-right text-[9px]"></i>
                                            </a>
                                        @else
                                            <span class="text-[#6E6E73]">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-14 text-center">
                                        <p class="text-sm font-semibold text-[#1D1D1F]">Tidak ada data pegawai yang cocok</p>
                                        <p class="mt-1 text-xs text-[#6E6E73]">
                                            Coba ubah kata kunci pencarian atau reset filter yang aktif.
                                        </p>
                                        <a href="{{ route('pegawai.profil-dosen', ['kategori' => $selectedKategori ?? 'semua']) }}"
                                           class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl bg-[#0071E3] text-white text-xs font-medium hover:bg-[#0077ED] transition-colors">
                                            Reset Filter
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($dosens->hasPages())
                    <div class="px-5 py-4 border-t border-black/[0.06] bg-[#F5F5F7]/40">
                        {{ $dosens->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        @endif
    </section>
</x-layout>
