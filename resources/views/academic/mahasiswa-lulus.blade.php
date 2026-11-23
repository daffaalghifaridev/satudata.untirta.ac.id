<x-layout>
    <x-slot:title>
        {{ $title }}
    </x-slot:title>

    {{-- Daftar lulusan ditarik dari SIAKANG. --}}
    <x-ui.data-freshness sumber="siakang.lulusan" :is-empty="$localStatus['is_empty']" :updated-at="$localStatus['updated_at']" />

    <section class="space-y-6">
        <div>
            <a href="{{ route('akademik') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-[#0071E3] hover:underline">
                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                <span>Kembali ke Akademik</span>
            </a>

            <h1 class="mt-3 text-2xl sm:text-3xl font-semibold tracking-[-0.02em] text-[#1D1D1F]">
                Mahasiswa Lulus
            </h1>

            <p class="mt-1 text-sm text-[#6E6E73]">
                Saring data mahasiswa lulus berdasarkan tahun lulus, angkatan, program studi, atau nama dan NIM.
            </p>
        </div>

        <livewire:mahasiswa-lulus-table />
    </section>
</x-layout>
