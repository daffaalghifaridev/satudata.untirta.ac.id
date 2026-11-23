<div>
    @php
        $kelasKontrol = 'w-full min-h-[42px] rounded-xl bg-[#F5F5F7] border border-transparent px-3.5 py-2 text-sm text-[#1D1D1F] placeholder-[#6E6E73] focus:bg-white focus:border-[#0071E3] transition-colors';
        $kelasLabel = 'mb-1.5 block text-xs font-medium text-[#6E6E73]';
    @endphp

    @if (!$result->success)
        <div role="alert" class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Daftar mahasiswa lulus belum bisa dibaca dari SIAKANG.</p>
            <p class="mt-1 text-xs text-amber-800">
                Coba lagi beberapa saat lagi, atau tekan <span class="font-semibold">Tarik dari SIAKANG</span> di panel atas halaman ini.
            </p>
            @if ($result->message)
                <details class="mt-2 text-xs text-amber-800">
                    <summary class="cursor-pointer font-medium">Pesan teknis dari sistem sumber</summary>
                    <p class="mt-1">{{ $result->message }}</p>
                </details>
            @endif
        </div>
    @endif

    {{-- Satu Container Terpadu: Ringkasan Total + Toolbar Filter + Tabel --}}
    <div class="overflow-hidden rounded-2xl border border-black/[0.06] bg-white">
        {{-- Header Total & Reset --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-black/[0.06] px-5 py-4">
            <div class="flex items-baseline gap-3">
                <h2 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">
                    {{ number_format($mahasiswa->total(), 0, ',', '.') }}
                </h2>
                <span class="text-xs text-[#6E6E73]">
                    {{ $adaFilter ? 'lulusan sesuai filter' : 'total lulusan tercatat di SIAKANG' }}
                </span>
            </div>

            <div class="flex items-center gap-3">
                <span wire:loading.delay class="text-xs font-medium text-[#0071E3]">
                    Memuat data...
                </span>
                @if ($adaFilter)
                    <button type="button" wire:click="resetFilter"
                        class="inline-flex items-center gap-1.5 min-h-[36px] rounded-xl bg-[#F5F5F7] px-3.5 py-1.5 text-xs font-medium text-[#1D1D1F] hover:bg-black/[0.08] transition-colors disabled:cursor-wait disabled:opacity-60 cursor-pointer"
                        wire:loading.attr="disabled">
                        <i class="fa-solid fa-rotate-left text-[11px] text-[#6E6E73]"></i>
                        <span>Reset filter</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Toolbar Filter 1 Baris --}}
        <div class="border-b border-black/[0.06] px-5 py-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div>
                    <label for="filter-cari" class="{{ $kelasLabel }}">Cari nama atau NIM</label>
                    <input id="filter-cari" type="text" wire:model.live.debounce.500ms="search" placeholder="Nama atau NIM..." class="{{ $kelasKontrol }}">
                    @error('search')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="filter-prodi" class="{{ $kelasLabel }}">Program studi</label>
                    @if (count($pilihanProdi))
                        <select id="filter-prodi" wire:model.live="kode_prodi" class="{{ $kelasKontrol }}">
                            <option value="">Semua program studi</option>
                            @foreach (collect($pilihanProdi)->groupBy('kelompok') as $kelompok => $daftarProdi)
                                <optgroup label="Jenjang {{ $kelompok }}">
                                    @foreach ($daftarProdi as $prodi)
                                        <option value="{{ $prodi['kode'] }}">{{ $prodi['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    @else
                        <input id="filter-prodi" type="text" wire:model.live.debounce.500ms="kode_prodi" placeholder="Kode prodi, mis. 3332" class="{{ $kelasKontrol }}">
                    @endif
                    @error('kode_prodi')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="filter-angkatan" class="{{ $kelasLabel }}">Angkatan</label>
                    @if (count($pilihanAngkatan))
                        <select id="filter-angkatan" wire:model.live="angkatan" class="{{ $kelasKontrol }}">
                            <option value="">Semua angkatan</option>
                            @foreach ($pilihanAngkatan as $angkatan)
                                <option value="{{ $angkatan }}">{{ $angkatan }}</option>
                            @endforeach
                        </select>
                    @else
                        <input id="filter-angkatan" type="text" wire:model.live.debounce.500ms="angkatan" placeholder="Tahun angkatan, mis. 2021" class="{{ $kelasKontrol }}">
                    @endif
                    @error('angkatan')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="filter-tahun-lulus" class="{{ $kelasLabel }}">Tahun lulus</label>
                    @if (count($pilihanTahunLulus))
                        <select id="filter-tahun-lulus" wire:model.live="tahun_lulus" class="{{ $kelasKontrol }}">
                            <option value="">Semua tahun</option>
                            @foreach ($pilihanTahunLulus as $tahun)
                                <option value="{{ $tahun }}">{{ $tahun }}</option>
                            @endforeach
                        </select>
                    @else
                        <input id="filter-tahun-lulus" type="text" wire:model.live.debounce.500ms="tahun_lulus" placeholder="Tahun lulus, mis. 2025" class="{{ $kelasKontrol }}">
                    @endif
                    @error('tahun_lulus')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div wire:loading.block class="p-4">
            <x-ui.skeleton-table :rows="8" :cols="5" label="Memuat data mahasiswa lulus..." />
        </div>

        <div wire:loading.remove class="overflow-x-auto">
            <table class="min-w-full divide-y divide-black/[0.06] text-sm">
                <thead class="bg-[#F5F5F7]/70">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-[#6E6E73]">NIM</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-[#6E6E73]">Nama</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-[#6E6E73]">Prodi</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-[#6E6E73]">Angkatan</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-[#6E6E73]">Tanggal Lulus</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-black/[0.05]">
                    @forelse ($mahasiswa as $item)
                        <tr class="hover:bg-[#F5F5F7]/60 transition-colors" wire:key="lulusan-{{ data_get($item, 'nim', $loop->index) }}">
                            <td class="px-5 py-3.5 font-medium text-[#1D1D1F] tabular-nums whitespace-nowrap">
                                {{ data_get($item, 'nim') }}
                            </td>
                            <td class="px-5 py-3.5 text-[#1D1D1F]">
                                {{ data_get($item, 'nama') }}
                            </td>
                            <td class="px-5 py-3.5 text-[#6E6E73]">
                                {{ data_get($item, 'prodi.nama_prodi') }}
                            </td>
                            <td class="px-5 py-3.5 text-[#6E6E73] tabular-nums">
                                {{ data_get($item, 'angkatan') }}
                            </td>
                            <td class="px-5 py-3.5 text-[#6E6E73] tabular-nums whitespace-nowrap">
                                {{ data_get($item, 'tanggal_lulus') ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                @if ($adaFilter)
                                    <div class="px-5 py-12 text-center">
                                        <p class="font-semibold text-[#1D1D1F]">Tidak ada mahasiswa lulus yang cocok dengan filter ini.</p>
                                        <p class="mt-1 text-xs text-[#6E6E73]">Longgarkan atau reset filternya untuk melihat data lainnya.</p>
                                        <button type="button" wire:click="resetFilter"
                                            class="mt-4 inline-flex items-center rounded-xl bg-[#0071E3] px-4 py-2 text-xs font-medium text-white hover:bg-[#0077ED] transition-colors cursor-pointer">
                                            Reset filter
                                        </button>
                                    </div>
                                @else
                                    <div class="p-4">
                                        <x-ui.skeleton-table :rows="5" :cols="5" label="Menunggu sinkronisasi data dari SIAKANG..." />
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($mahasiswa->hasPages())
            <div class="border-t border-black/[0.06] bg-[#F5F5F7]/40 px-5 py-4">
                {{ $mahasiswa->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</div>
