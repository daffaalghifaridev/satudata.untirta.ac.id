<x-layout>
    <x-slot:title>
        {{ $title ?? 'Dashboard' }}
    </x-slot:title>

    <section class="space-y-8">
        <div class="max-w-2xl">
            <p class="text-xs font-medium text-[#6E6E73]">Portal Data Terpadu</p>
            <h1 class="mt-1.5 text-3xl sm:text-4xl font-semibold tracking-[-0.025em] text-[#1D1D1F]">
                SATUDATA UNTIRTA
            </h1>
            <p class="mt-2 text-sm sm:text-base leading-relaxed text-[#6E6E73]">
                Ringkasan data akademik, inventaris aset, dan kepegawaian Universitas Sultan Ageng Tirtayasa dalam satu pintu.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <x-ui.dashboard-card
                title="Akademik & Mahasiswa"
                description="Statistik mahasiswa aktif, lulusan, jalur masuk, dan monitoring perkuliahan per fakultas."
                badge="Akademik"
                href="/akademik"
                icon-bg="bg-[#0071E3]/10"
                icon-color="text-[#0071E3]">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 3 2 8l10 5 10-5-10-5Zm-6 8.3V15c0 2.2 2.7 4 6 4s6-1.8 6-4v-3.7l-6 3-6-3Z" />
                    </svg>
                </x-slot:icon>
            </x-ui.dashboard-card>

            <x-ui.dashboard-card
                title="Inventaris Aset BMN"
                description="Sebaran sarana, prasarana, nilai perolehan, dan kondisi barang per kampus, gedung, dan ruangan."
                badge="Aset"
                href="/aset"
                icon-bg="bg-[#0071E3]/10"
                icon-color="text-[#0071E3]">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9 3a2 2 0 0 0-2 2v2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-5V9h-2v2h-4V9H8v2H5V9h2v1h2V5h6v2h2V5a2 2 0 0 0-2-2H9Z" />
                    </svg>
                </x-slot:icon>
            </x-ui.dashboard-card>

            <x-ui.dashboard-card
                title="SDM & Kepegawaian"
                description="Komposisi dosen dan tenaga kependidikan berdasarkan status kerja, jabatan, dan unit kerja."
                badge="Pegawai"
                href="/pegawai"
                icon-bg="bg-[#0071E3]/10"
                icon-color="text-[#0071E3]">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H8Zm4 4a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Zm-4 10c.4-2.2 2-3.5 4-3.5s3.6 1.3 4 3.5H8Z" />
                    </svg>
                </x-slot:icon>
            </x-ui.dashboard-card>
        </div>
    </section>
</x-layout>
