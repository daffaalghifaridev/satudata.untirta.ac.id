@props([
    'datas' => [],
    'level' => 'kampus'
])

@php
    $items = collect($datas)->values();
    $levelLabel = match($level) {
        'kampus' => 'Kampus',
        'gedung' => 'Gedung',
        'ruangan' => 'Ruangan',
        default => 'Aset'
    };
    $backLabel = match($level) {
        'gedung' => 'Kembali ke Daftar Kampus',
        'ruangan' => 'Kembali ke Daftar Gedung',
        default => 'Kembali'
    };
    $backUrl = match($level) {
        'gedung' => route('aset.index'),
        default => 'javascript:history.back()'
    };
@endphp

<div x-data="{
    search: '',
    items: @js($items),
    get filteredItems() {
        if (!this.search.trim()) return this.items;
        const query = this.search.toLowerCase().trim();
        return this.items.filter(item => {
            const title = (item.title || '').toLowerCase();
            const count = (item.count || '').toLowerCase();
            return title.includes(query) || count.includes(query);
        });
    },
    formatCount(countStr) {
        if (!countStr) return { num: '-', unit: 'Unit' };
        const parts = String(countStr).trim().split(' ');
        if (parts.length > 1 && !isNaN(parts[0].replace(/\./g, ''))) {
            return { num: parts[0], unit: parts.slice(1).join(' ') };
        }
        return { num: countStr, unit: '' };
    },
    getCardUrl(id) {
        const base = '{{ url('/aset') }}';
        @if($level === 'kampus')
            return base + '/kampus/' + encodeURIComponent(id) + '/gedung';
        @elseif($level === 'gedung')
            return base + '/gedung/' + encodeURIComponent(id) + '/ruangan?kampus_id=' + encodeURIComponent(@js(request()->route('kampusId') ?? request('kampus_id', '')));
        @elseif($level === 'ruangan')
            const lokasi = this.items.find(item => String(item.id) === String(id))?.filter_lokasi;
            return base + '/ruangan/' + encodeURIComponent(id) + '/bmn' + (lokasi ? '?lokasi=' + encodeURIComponent(lokasi) : '');
        @else
            return base;
        @endif
    }
}" class="w-full">

    {{-- Header Section: Navigation & Search Toolbar --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        
        {{-- Left: Breadcrumb / Back Link & Title --}}
        <div>
            @if($level !== 'kampus')
                <div class="mb-2">
                    <a href="{{ $backUrl }}"
                       class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-800 transition-colors group">
                        <i class="fa-solid fa-arrow-left text-xs group-hover:-translate-x-0.5 transition-transform"></i>
                        <span>{{ $backLabel }}</span>
                    </a>
                </div>
            @endif
            <div class="flex items-center gap-3">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">
                    @if($level === 'kampus')
                        Daftar Lokasi Kampus
                    @elseif($level === 'gedung')
                        Daftar Gedung
                    @elseif($level === 'ruangan')
                        Daftar Ruangan
                    @else
                        Daftar Inventaris
                    @endif
                </h2>
            </div>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                @if($level === 'kampus')
                    Pilih salah satu kampus untuk meninjau rincian gedung dan persebaran inventaris BMN.
                @elseif($level === 'gedung')
                    Pilih gedung untuk melihat ruangan operasional dan daftar aset yang tersimpan.
                @elseif($level === 'ruangan')
                    Pilih ruangan untuk melihat rincian setiap unit Barang Milik Negara (BMN).
                @endif
            </p>
        </div>
    </div>
    <div class="mb-6 w-full sm:w-72 relative self-start sm:self-auto">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-gray-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input type="text"
                       x-model="search"
                       placeholder="Cari {{ strtolower($levelLabel) }}..."
                       class="w-full pl-10 pr-8 py-2 bg-white border border-gray-200 rounded-xl text-sm placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all shadow-2xs">
                <button type="button"
                        x-show="search.length > 0"
                        @click="search = ''"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 cursor-pointer"
                        x-cloak>
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>
        </div>

    {{-- Cards Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" x-show="filteredItems.length > 0">
        <template x-for="card in filteredItems" :key="card.id">
            <div class="group relative bg-white rounded-2xl border border-black/[0.06] p-6 hover:border-[#0071E3]/40 transition-colors flex flex-col justify-between overflow-hidden">

                {{-- Header Content --}}
                <div>
                    {{-- Category Tag & Icon --}}
                    <div class="flex items-center justify-between gap-3 mb-3">
                        {{-- Title --}}
                        <h3 class="text-base sm:text-lg font-semibold text-[#1D1D1F] group-hover:text-[#0071E3] transition-colors line-clamp-2 leading-snug"
                            x-text="card.title">
                        </h3>
                        {{-- Icon Badge --}}
                        <div class="shrink-0 w-10 h-10 rounded-xl bg-[#F5F5F7] text-[#0071E3] flex items-center justify-center text-base group-hover:bg-[#0071E3] group-hover:text-white transition-colors">
                            <template x-if="card.icon === 'building'">
                                <i class="fa-solid fa-building"></i>
                            </template>
                            <template x-if="card.icon === 'map'">
                                <i class="fa-solid fa-building-columns"></i>
                            </template>
                            <template x-if="card.icon === 'door'">
                                <i class="fa-solid fa-door-closed"></i>
                            </template>
                            <template x-if="card.icon === 'computer' || card.icon === 'laptop'">
                                <i class="fa-solid fa-laptop"></i>
                            </template>
                            <template x-if="card.icon === 'chair' || card.icon === 'meja'">
                                <i class="fa-solid fa-chair"></i>
                            </template>
                            <template x-if="card.icon === 'car'">
                                <i class="fa-solid fa-car"></i>
                            </template>
                            <template x-if="!['building', 'map', 'door', 'computer', 'laptop', 'chair', 'meja', 'car'].includes(card.icon)">
                                <i class="fa-solid fa-box-archive"></i>
                            </template>
                        </div>
                    </div>



                    {{-- Metric KPI Display: tampil bila total aset tersedia (field API atau agregasi tabel `asets`) --}}
                    <template x-if="card.count">
                    <div class="mt-6 p-4 rounded-xl bg-slate-50/80 border border-slate-100 group-hover:bg-blue-50/30 group-hover:border-blue-100 transition-colors duration-200">
                        <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400 block mb-1">
                            @if($level === 'kampus')
                                Total Unit Aset
                            @elseif($level === 'gedung')
                                Total Unit BMN Terdata
                            @else
                                Inventaris Ruangan
                            @endif
                        </span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-extrabold font-mono text-slate-900 tracking-tight"
                                  x-text="formatCount(card.count).num"></span>
                            <span class="text-xs font-semibold text-slate-500"
                                  x-text="formatCount(card.count).unit"></span>
                        </div>
                    </div>
                    </template>
                </div>

                {{-- Card Footer --}}
                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs text-slate-400 font-medium inline-flex items-center gap-1.5">
                    </span>

                    <a :href="getCardUrl(card.id)"
                       class="inline-flex items-center gap-2 text-xs font-semibold text-blue-600 hover:text-blue-800 transition-colors group/btn cursor-pointer py-1 px-1">
                        <span>Lihat Detail</span>
                        <span class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 group-hover/btn:bg-blue-600 group-hover/btn:text-white flex items-center justify-center transition-all duration-200 shadow-2xs group-hover/btn:translate-x-0.5">
                            <i class="fa-solid fa-arrow-right text-[9px]"></i>
                        </span>
                    </a>
                </div>

            </div>
        </template>
    </div>

    {{-- Empty State (No matching search or no data) --}}
    <div x-show="filteredItems.length === 0" class="bg-white rounded-2xl border border-gray-200/80 p-12 text-center shadow-xs" x-cloak>
        <div class="w-16 h-16 rounded-2xl bg-gray-50 text-gray-400 mx-auto flex items-center justify-center text-2xl mb-4 border border-gray-100">
            <i class="fa-solid fa-magnifying-glass"></i>
        </div>
        <h3 class="text-base font-bold text-gray-900 mb-1">
            Tidak Ada Data Ditemukan
        </h3>
        <p class="text-sm text-gray-500 max-w-md mx-auto mb-4" x-show="search.length > 0">
            Tidak ada {{ strtolower($levelLabel) }} yang cocok dengan kata kunci "<span class="font-semibold text-gray-700" x-text="search"></span>".
        </p>
        <p class="text-sm text-gray-500 max-w-md mx-auto mb-4" x-show="search.length === 0">
            Belum ada data {{ strtolower($levelLabel) }} yang tercatat di sistem saat ini.
        </p>
        <button type="button"
                x-show="search.length > 0"
                @click="search = ''"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 transition-colors cursor-pointer">
            <i class="fa-solid fa-rotate-left text-[10px]"></i> Reset Pencarian
        </button>
    </div>

</div>
