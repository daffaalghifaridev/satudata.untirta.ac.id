<footer class="mt-16 border-t border-black/[0.06] bg-[#F5F5F7] text-[#6E6E73]">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-8 md:grid-cols-12">
            <div class="md:col-span-6">
                <a href="/" class="inline-flex items-center gap-3">
                    <img src="{{ asset('images/untirta_logo.png') }}" alt="Logo Untirta" class="h-9 w-9 object-contain">
                    <div class="leading-tight">
                        <span class="block text-sm font-semibold tracking-tight text-[#1D1D1F]">SATUDATA UNTIRTA</span>
                        <span class="block text-xs text-[#6E6E73]">Universitas Sultan Ageng Tirtayasa</span>
                    </div>
                </a>
                <p class="mt-4 max-w-md text-xs leading-relaxed text-[#6E6E73]">
                    Jl. Raya Palka Km 3 Sindangsari, Pabuaran, Kabupaten Serang, Provinsi Banten
                </p>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-[#6E6E73]">
                    <span>Telp: +62 254 3204321</span>
                    <span>Fax: +62 254 281254</span>
                    <span>Email: humas@untirta.ac.id</span>
                </div>
            </div>

            <div class="md:col-span-3">
                <h2 class="text-xs font-semibold text-[#1D1D1F]">Akademik</h2>
                <ul class="mt-3 space-y-2 text-xs">
                    <li><a href="{{ route('akademik') }}" class="hover:text-[#1D1D1F] transition-colors">Ringkasan Akademik</a></li>
                    <li><a href="{{ route('akademik.mahasiswa-lulus') }}" class="hover:text-[#1D1D1F] transition-colors">Mahasiswa Lulus</a></li>
                    <li><a href="{{ route('akademik.perkuliahan') }}" class="hover:text-[#1D1D1F] transition-colors">Monitoring Perkuliahan</a></li>
                </ul>
            </div>

            <div class="md:col-span-3">
                <h2 class="text-xs font-semibold text-[#1D1D1F]">Sumber Daya &amp; Aset</h2>
                <ul class="mt-3 space-y-2 text-xs">
                    <li><a href="{{ route('aset.index') }}" class="hover:text-[#1D1D1F] transition-colors">Inventaris Aset BMN</a></li>
                    <li><a href="{{ route('pegawai') }}" class="hover:text-[#1D1D1F] transition-colors">Statistik SDM</a></li>
                    <li><a href="{{ route('pegawai.profil-dosen', ['kategori' => 'semua']) }}" class="hover:text-[#1D1D1F] transition-colors">Direktori Pegawai &amp; Dosen</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-8 border-t border-black/[0.06] pt-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between text-xs text-[#6E6E73]">
            <p>&copy; {{ date('Y') }} Universitas Sultan Ageng Tirtayasa.</p>
            <p>Portal Data Terpadu Kampus</p>
        </div>
    </div>
</footer>
