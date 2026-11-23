<x-layout>
    <x-slot:title>
        {{ $title ?? 'Profil Staf' }}
    </x-slot:title>

    <x-ui.data-freshness sumber="simpeg.pegawai" :is-empty="$isDataEmpty" :updated-at="\App\Models\Pegawai::min('updated_at')" />

    @php
        $namaLengkap = $staf['nama_pegawai_lengkap'] ?? $staf['nama_pegawai'] ?? $staf['nama'] ?? '-';
        $nipStaf = $staf['nip'] ?? '-';
        $unit = $unitKerja ?: ($staf['unitKerja'] ?? $staf['unit_kerja'] ?? '-');
        $jabatan = $staf['nama_jabatan'] ?? $staf['jabatan'] ?? '-';
        $pangkat = $staf['nama_pangkat'] ?? $staf['pangkat'] ?? '-';
        $statusKerja = $staf['nama_stspegawai'] ?? $staf['statusKerja'] ?? '-';
        $statusPegawai = $staf['nama_stspeg'] ?? $staf['statusPegawai'] ?? '-';
        $levelPegawai = $staf['nama_level_pegawai'] ?? $staf['levelPegawai'] ?? '-';
        $email = $staf['emailPegawai'] ?? $staf['email'] ?? '-';
    @endphp

    <section class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 uppercase">Profil Tenaga Kependidikan</h1>
                <nav class="mt-2 flex items-center gap-2 text-sm text-gray-500">
                    <a href="/" class="hover:text-gray-700">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('pegawai') }}" class="hover:text-gray-700">Pegawai</a>
                    <span>/</span>
                    <a href="{{ route('pegawai.profil-dosen', ['kategori' => 'staf']) }}" class="hover:text-gray-700">Direktori Staf</a>
                    <span>/</span>
                    <span class="text-gray-700 font-medium">{{ $namaLengkap }}</span>
                </nav>
            </div>
            <a href="{{ route('pegawai.profil-dosen', ['kategori' => 'staf']) }}"
               class="inline-flex items-center text-[#4B00FF] hover:text-violet-800 font-semibold text-sm transition-colors self-start">
                <i class="fa-solid fa-arrow-left mr-2"></i> Kembali ke Direktori Staf
            </a>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white shadow-sm p-6 md:p-8 mb-6">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <i class="fa-solid fa-users-gear text-[10px]"></i>
                    <span>{{ $levelPegawai }}</span>
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $statusKerja }}
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                    {{ $statusPegawai }}
                </span>
            </div>

            <h2 class="text-2xl md:text-3xl font-bold text-slate-900 tracking-tight">{{ $namaLengkap }}</h2>

            <div class="mt-4 py-4 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 text-sm text-slate-600">
                <div class="flex p-2 items-center gap-3">
                    <i class="fa-solid fa-id-card text-slate-400 w-5 text-center"></i>
                    <div>
                        <span class="text-xs text-gray-400 block">Nomor Induk Pegawai (NIP)</span>
                        <span class="font-semibold text-slate-800 font-mono text-base">{{ $nipStaf }}</span>
                    </div>
                </div>

                <div class="flex p-2 items-center gap-3">
                    <i class="fa-solid fa-briefcase text-slate-400 w-5 text-center"></i>
                    <div>
                        <span class="text-xs text-gray-400 block">Jabatan</span>
                        <span class="font-medium text-slate-800">{{ $jabatan }}</span>
                    </div>
                </div>

                <div class="flex p-2 items-center gap-3">
                    <i class="fa-solid fa-award text-slate-400 w-5 text-center"></i>
                    <div>
                        <span class="text-xs text-gray-400 block">Pangkat / Golongan</span>
                        <span class="font-medium text-slate-800">{{ $pangkat }}</span>
                    </div>
                </div>

                <div class="flex p-2 items-center gap-3">
                    <i class="fa-solid fa-building text-slate-400 w-5 text-center"></i>
                    <div>
                        <span class="text-xs text-gray-400 block">Unit Kerja Penempatan</span>
                        <span class="font-semibold text-emerald-800">{{ $unit }}</span>
                    </div>
                </div>

                <div class="flex p-2 items-center gap-3">
                    <i class="fa-solid fa-envelope text-slate-400 w-5 text-center"></i>
                    <div>
                        <span class="text-xs text-gray-400 block">Email Dinas</span>
                        @if (!empty($email) && $email !== '-')
                            <a href="mailto:{{ $email }}" class="text-indigo-600 hover:underline font-medium">{{ $email }}</a>
                        @else
                            <span class="text-slate-400">-</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layout>
