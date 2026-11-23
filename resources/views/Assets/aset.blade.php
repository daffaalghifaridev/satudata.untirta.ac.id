<x-layout>
    <x-slot:title>
        {{ $title }}
    </x-slot:title>

    @php
        $asetUpdatedAt = \Illuminate\Support\Facades\DB::table('aset_summaries')->where('tingkat', 'semua')->value('source_synced_at');
        $isLevelKampus = ($level ?? 'kampus') === 'kampus';
        $hasNoDatas = empty($datas) || (is_countable($datas) && count($datas) === 0);
        $isDataEmpty = $isLevelKampus
            ? ($hasNoDatas && (empty($summaryStats) || ($summaryStats['total_unit'] ?? 0) === 0))
            : $hasNoDatas;
    @endphp

    {{-- Level kampus sudah punya kotak penyegaran aset sendiri, jadi baris kesegaran
         SIMANTAP ini hanya perlu di level gedung/ruangan yang tidak punya tombol itu. --}}
    @if(!$isLevelKampus)
        <x-ui.data-freshness sumber="simantap.aset" :is-empty="$isDataEmpty" :updated-at="$asetUpdatedAt" />
    @endif

    {{-- Breadcrumb Navigation (For Gedung and Ruangan levels) --}}
    @if(($level ?? 'kampus') !== 'kampus')
        <nav class="mb-5 flex items-center gap-2 text-xs sm:text-sm font-medium text-gray-500" aria-label="Breadcrumb">
            <a href="{{ route('aset.index') }}" class="hover:text-blue-600 transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-boxes-stacked text-xs"></i>
                <span>Aset</span>
            </a>
            <i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
            @if(($level ?? '') === 'gedung')
                <span class="text-gray-900 font-semibold truncate">{{ $title }}</span>
            @elseif(($level ?? '') === 'ruangan')
                <a href="javascript:history.back()" class="hover:text-blue-600 transition-colors">
                    Daftar Gedung
                </a>
                <i class="fa-solid fa-chevron-right text-[10px] text-gray-400"></i>
                <span class="text-gray-900 font-semibold truncate">{{ $title }}</span>
            @endif
        </nav>
    @endif
    
    {{-- Warning Banner if API fails --}}
    @if(!empty($warning))
        <div class="mb-6 px-4 py-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 flex items-center gap-3 shadow-xs">
            <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-triangle-exclamation text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-medium text-sm">{{ $warning }}</p>
            </div>
            <a href="{{ url()->current() }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-amber-200 hover:bg-amber-300 text-amber-900 transition-colors shrink-0">
                Muat Ulang
            </a>
        </div>
    @endif

    {{-- Penyegaran data aset: satu tombol saja, tanpa isian apa pun dari pengguna.
         Penarikan dijalankan host aplikasi memakai Chromium lokal (satu-satunya klien
         yang lolos Cloudflare dari server); kredensial SIMANTAP tetap di .env server. --}}
    @if(($level ?? 'kampus') === 'kampus')
        @php
            // Kalimat dan label tombol dibaca dari config/satudata.php supaya seragam
            // dengan panel kesegaran data di halaman lain.
            $sumberAset = (array) (config('satudata.sources')['simantap.aset'] ?? []);
            $keteranganAset = (string) ($sumberAset['keterangan'] ?? 'Data aset dan total unit dibaca dari database SATUDATA.');
            $labelTarikAset = ($sumberAset['sistem'] ?? '') !== '' ? 'Tarik dari ' . $sumberAset['sistem'] : 'Tarik data aset';
        @endphp
        <div id="sync-aset"
             class="mb-6 rounded-2xl border border-black/[0.06] bg-white px-4 py-3 flex flex-wrap items-center gap-3"
             data-url="{{ route('aset.sync-data') }}"
             data-status-url="{{ route('aset.sync-status') }}"
             data-csrf="{{ csrf_token() }}">
            <div class="flex items-center gap-2 text-xs text-[#6E6E73]">
                <i class="fa-solid fa-database text-[#6E6E73]"></i>
                <span>{{ $keteranganAset }}</span>
            </div>
            <div class="flex-1"></div>
            <span id="sync-aset-status" class="text-xs text-[#6E6E73]"></span>
            <button type="button" id="sync-aset-button"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#0071E3] px-3.5 py-2 text-xs font-medium text-white hover:bg-[#0077ED] transition-colors cursor-pointer disabled:opacity-50 disabled:pointer-events-none">
                <i class="fa-solid fa-rotate text-[11px]"></i>
                <span>{{ $labelTarikAset }}</span>
            </button>
        </div>
    @endif

    @if($isDataEmpty)
        <div class="mb-8 space-y-6">
            @if(($level ?? 'kampus') === 'kampus')
                <x-ui.skeleton-stat-card count="4" label="Memuat ringkasan aset BMN..." />
                <x-ui.skeleton-chart title="Memuat persebaran unit aset per kampus dan status kondisi..." height="min-h-[260px]" />
            @endif
            <x-ui.skeleton-card-grid count="6" label="Memuat data {{ ($level ?? 'kampus') === 'gedung' ? 'gedung' : (($level ?? 'kampus') === 'ruangan' ? 'ruangan' : 'kampus') }}..." />
        </div>
    @else
        {{-- Campus Executive KPI & Breakdown Chart --}}
        @if(($level ?? 'kampus') === 'kampus' && !empty($summaryStats))
            <x-ui.aset-summary-card :stats="$summaryStats" />
        @endif

        {{-- Campus / Gedung / Ruangan Card Grid --}}
        <x-ui.aset-card :datas="$datas" :level="$level ?? 'kampus'"></x-ui.aset-card>
    @endif

    @if(($level ?? 'kampus') === 'kampus')
        <script>
            // Tombol di kotak penyegaran aset: meminta server menyegarkan data BMN dari SIMANTAP
            // (Chromium lokal di host aplikasi) lalu memantau statusnya. Operator tidak
            // mengisi kredensial atau token apa pun.
            (function () {
                const panel = document.getElementById('sync-aset');
                if (!panel) return;

                const url = panel.dataset.url;
                const statusUrl = panel.dataset.statusUrl;
                const csrf = panel.dataset.csrf;
                const tombol = document.getElementById('sync-aset-button');
                const status = document.getElementById('sync-aset-status');

                const writeStatus = (teks, kelas) => { status.textContent = teks || ''; status.className = 'text-xs ' + (kelas || 'text-slate-500'); };
                const lockButton = (mati) => { tombol.disabled = mati; tombol.classList.toggle('opacity-60', mati); };

                async function checkStatus() {
                    const respons = await fetch(statusUrl, { headers: { accept: 'application/json' } });
                    return respons.ok ? respons.json() : { status: 'belum', message: '' };
                }

                // Kesegaran data ditampilkan sejak halaman dibuka, bukan hanya saat tombol ditekan,
                // supaya kotak ini sama informatifnya dengan panel di halaman lain.
                async function tulisStatusAwal() {
                    let hasil;

                    try { hasil = await checkStatus(); } catch (e) { return; }

                    if (hasil.status === 'selesai') {
                        writeStatus(hasil.time ? 'Terakhir diperbarui ' + hasil.time : 'Data sudah diperbarui.', 'text-emerald-600');
                    }
                    if (hasil.status === 'parsial') {
                        writeStatus('Sesi selesai. Sebagian data aset belum ditarik; lanjutkan pembaruan.', 'text-amber-600');
                    }

                    if (hasil.status === 'jalan') {
                        writeStatus('Sedang memperbarui data aset...', 'text-slate-500');
                        lockButton(true);
                        await pantau(900);
                        return;
                    }

                    if (hasil.status === 'gagal') {
                        writeStatus('Pembaruan data aset gagal. Coba lagi nanti.', 'text-rose-600');
                    }

                    if (!hasil.status || hasil.status === 'belum') {
                        writeStatus('Data belum pernah diperbarui', 'text-slate-500');
                    }

                    const isAsetEmpty = @json((bool)$isDataEmpty);
                    const autoPullAsetKey = 'auto_pull_simantap_aset';
                    const sudahAutoPullAset = Number(sessionStorage.getItem(autoPullAsetKey) || 0);
                    const freshMs = @json((int) config('satudata.swr.fresh_minutes', 20) * 60000);
                    const recentAttempt = hasil.time && Date.now() - Date.parse(hasil.time.replace(' ', 'T')) < freshMs;
                    if (isAsetEmpty && !recentAttempt && Date.now() - sudahAutoPullAset >= freshMs) {
                        sessionStorage.setItem(autoPullAsetKey, Date.now().toString());
                        writeStatus('Memperbarui data aset dari SIMANTAP...', 'text-cyan-600');
                        tombol.click();
                    }
                }

                tulisStatusAwal();

                async function pantau(batasDetik) {
                    const mulai = Date.now();

                    while (Date.now() - mulai < batasDetik * 1000) {
                        await new Promise((selesai) => setTimeout(selesai, 3000));

                        let hasil;
                        try { hasil = await checkStatus(); } catch (e) { hasil = { status: 'belum', message: '' }; }

                        if (hasil.status === 'selesai' || hasil.status === 'parsial') {
                            if (hasil.has_data === false) {
                                writeStatus('Tidak ada data baru pada sesi ini.', 'text-slate-500');
                                lockButton(false);
                                return;
                            }

                            writeStatus(hasil.status === 'parsial' ? 'Sesi selesai. Memuat hasil sementara...' : 'Data aset berhasil diperbarui. Memuat ulang...', hasil.status === 'parsial' ? 'text-amber-600' : 'text-emerald-600');
                            window.location.reload();
                            return;
                        }

                        if (hasil.status === 'gagal') {
                            writeStatus('Pembaruan data aset gagal. Coba lagi nanti.', 'text-rose-600');
                            lockButton(false);
                            return;
                        }

                        writeStatus('Sedang memperbarui data aset...', 'text-slate-500');
                    }

                    writeStatus('Masih berjalan. Muat ulang halaman sebentar lagi untuk melihat hasilnya.', 'text-amber-600');
                    lockButton(false);
                }

                tombol.addEventListener('click', async function () {
                    lockButton(true);
                    writeStatus('Meminta penyegaran data aset...', 'text-slate-500');

                    try {
                        const respons = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({}),
                        });

                        const hasil = await respons.json().catch(() => ({}));

                        if (hasil.status === 'diam') {
                            writeStatus('Pembaruan baru saja diminta. Tunggu sebentar lagi.', 'text-slate-500');
                            lockButton(false);
                            return;
                        }

                        if (!respons.ok && respons.status !== 202) {
                            writeStatus('Permintaan pembaruan gagal. Coba lagi nanti.', 'text-rose-600');
                            lockButton(false);
                            return;
                        }

                        writeStatus('Penyegaran dimulai...', 'text-slate-500');
                        await pantau(900);
                    } catch (e) {
                        writeStatus('Gagal menghubungi server. Coba lagi nanti.', 'text-rose-600');
                        lockButton(false);
                    }
                });
            })();
        </script>
    @endif
</x-layout>
