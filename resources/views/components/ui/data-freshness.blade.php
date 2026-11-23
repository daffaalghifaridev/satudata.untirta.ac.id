@props(['sumber', 'isEmpty' => false, 'updatedAt' => null, 'semester' => null])

@php
    $konfigurasi = (array) (config('satudata.sources')[$sumber] ?? []);
    $keterangan = (string) ($konfigurasi['keterangan'] ?? ($konfigurasi['label'] ?? $sumber));
    $sistem = (string) ($konfigurasi['sistem'] ?? '');
    $labelTarik = $sistem !== '' ? 'Tarik dari ' . $sistem : 'Tarik data';
    // Semester yang sedang dilihat halaman (bila ada), dipakai penarik statistik SIAKANG.
    $semesterHalaman = $semester ?? (is_string(request()->input('semester')) ? request()->input('semester') : '');
    $prodiHalaman = is_string(request()->input('kode_prodi')) ? request()->input('kode_prodi') : '';
    $tahunHalaman = is_string(request()->input('tahun_lulus')) ? request()->input('tahun_lulus') : (string) now()->year;
    $localStale = $updatedAt !== null
        ? \Illuminate\Support\Carbon::parse($updatedAt)->lt(now()->subMinutes((int) config('satudata.swr.fresh_minutes', 20)))
        : null;
@endphp

{{-- Kesegaran satu sumber data untuk halaman ini: satu baris, satu tombol "Tarik".
     Bentuknya mengikuti kotak penyegaran data aset supaya semua halaman seragam.
     Sumber yang penarikannya butuh identitas dosen hanya menampilkan statusnya,
     karena penarikannya memang dijalankan dari halaman profil dosen. --}}
<div x-data="dataFreshness('{{ $sumber }}', @json((bool) ($isEmpty ?? false)), @json($localStale))" x-init="load()"
     class="mb-2 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3">
    <div class="flex items-center gap-2 text-sm text-slate-600">
        <i class="fa-solid fa-database text-slate-400"></i>
        <span>{{ $keterangan }}</span>
    </div>
    <div class="flex-1"></div>
    <span class="text-xs" :class="kelasStatus" :title="pesanTeknis" x-text="teksStatus">Memeriksa kesegaran data...</span>
    <span x-show="notice && notice !== teksStatus" x-text="notice" class="text-xs text-slate-500"></span>
    <template x-if="bisaDitarik">
        <button type="button" @click="pull($event)"
                :disabled="loading || (data && (data.status || {}).status === 'jalan')"
                class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-cyan-700 disabled:pointer-events-none disabled:opacity-50">
            <i class="fa-solid fa-rotate text-[11px]" :class="loading ? 'fa-spin' : ''"></i>
            <span>{{ $labelTarik }}</span>
        </button>
    </template>
</div>

@once
    @push('scripts')
        <script>
            function dataFreshness(sumber, autoPullIfEmpty = false, localStale = null) {
                return {
                    kunci: sumber,
                    autoPullIfEmpty: autoPullIfEmpty,
                    localStale: localStale,
                    hasAutoPulled: false,
                    wasRunning: false,
                    data: null,
                    galat: '',
                    loading: false,
                    notice: '',
                    noticeTimer: null,
                    pantau: null,
                    pantauSampai: 0,

                    get bisaDitarik() {
                        return this.galat === '' && !!this.data && this.data.has_puller && !this.data.needs_context;
                    },

                    get kelasStatus() {
                        if (this.galat !== '' || this.data === null) return 'text-rose-600';

                        const status = (this.data.status || {}).status;
                        const pull = (this.data.pull || {}).status;

                        if (status === 'jalan') return 'text-cyan-600';
                        // Penarik yang tidak melaporkan hasil adalah ketidakpastian, bukan kegagalan.
                        if (status === 'basi' || status === 'parsial' || pull === 'parsial') return 'text-amber-600';
                        if (['gagal', 'tidak_dikenal', 'tanpa_browser', 'tanpa_konteks'].includes(status) || pull === 'gagal') {
                            return 'text-rose-600';
                        }
                        if (status === 'selesai' || pull === 'selesai') return 'text-emerald-600';

                        return 'text-slate-500';
                    },

                    get teksStatus() {
                        if (this.galat !== '') return 'Status data tidak bisa diperiksa.';
                        if (this.data === null) return 'Sumber data tidak dikenali.';

                        const status = this.data.status || {};
                        const pull = this.data.pull || {};

                        if (status.status === 'jalan') {
                            return this.kunci.startsWith('halaman.') ? (status.message || 'Sedang memperbarui data...') : 'Sedang memperbarui data...';
                        }

                        if (status.status === 'parsial' || pull.status === 'parsial') {
                            return 'Sesi selesai. Sebagian data belum ditarik; lanjutkan pembaruan.';
                        }
                        if (status.status === 'gagal' || pull.status === 'gagal') {
                            return this.kunci.startsWith('halaman.') ? (status.message || pull.message || 'Pembaruan belum berhasil.') : 'Pembaruan data gagal. Coba lagi nanti.';
                        }

                        if (pull.status === 'selesai' && pull.time) {
                            return 'Terakhir diperbarui ' + pull.time;
                        }

                        if (status.status === 'selesai' && status.time) {
                            return 'Terakhir diperbarui ' + status.time;
                        }

                        if (this.data.needs_context || status.status === 'tanpa_konteks') {
                            return 'Diperbarui per dosen dari halaman profil dosen';
                        }


                        if (status.status === 'tanpa_browser') return 'Pembaruan otomatis tidak tersedia di server ini';
                        if (status.status === 'tanpa_penarik') return 'Belum tersedia pembaruan otomatis';
                        if (status.status === 'tidak_dikenal') return 'Sumber data tidak dikenali';
                        if (status.status === 'basi') return 'Pembaruan data belum dilaporkan';

                        return 'Data belum pernah diperbarui';
                    },

                    // Rincian teknis (mis. pesan penarik) hanya muncul saat kursor diarahkan,
                    // supaya baris status tetap ringkas untuk pengguna umum.
                    get pesanTeknis() {
                        const status = (this.data || {}).status || {};
                        const pull = (this.data || {}).pull || {};

                        return pull.message || status.message || '';
                    },

                    async load() {
                        this.loading = true;

                        try {
                            const response = await fetch('{{ route('sumber-data.status') }}?semester=' + encodeURIComponent(@json($semesterHalaman)) + '&kode_prodi=' + encodeURIComponent(@json($prodiHalaman)), { headers: { accept: 'application/json' } });
                            const payload = await response.json();
                            this.data = (payload.sources || {})[this.kunci] || null;
                            this.galat = '';
                        } catch (error) {
                            this.galat = 'Status data tidak bisa diperiksa.';
                        }

                        this.loading = false;

                        const ttl = @json((int) config('satudata.swr.fresh_minutes', 20) * 60000);
                        const sessionKey = 'auto_pull_' + this.kunci + '_' + window.location.pathname + '_' + @json($semesterHalaman) + '_' + @json($prodiHalaman);
                        let lastAttempt = 0;
                        try { lastAttempt = Number(sessionStorage.getItem(sessionKey) || 0); } catch (error) {}
                        const running = !!this.data && (this.data.status || {}).status === 'jalan';
                        const needsRefresh = this.autoPullIfEmpty;
                        if (needsRefresh && !running && !this.hasAutoPulled && this.bisaDitarik && Date.now() - lastAttempt >= ttl) {
                            this.hasAutoPulled = true;
                            try { sessionStorage.setItem(sessionKey, Date.now().toString()); } catch (error) {}
                            await this.triggerPull();
                            return;
                        }

                        this.pantauBilaBerjalan();
                    },

                    async triggerPull() {
                        this.hasAutoPulled = true;
                        try {
                            const response = await fetch('{{ route('sumber-data.pemicu') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    accept: 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                },
                                body: JSON.stringify({ source: this.kunci, semester: @json($semesterHalaman), kode_prodi: @json($prodiHalaman), tahun: @json($tahunHalaman) }),
                            });
                            const payload = await response.json();
                            this.tampilkanNotice(payload.message || (response.ok
                                ? 'Permintaan penarikan dikirim.'
                                : 'Permintaan penarikan ditolak (HTTP ' + response.status + ').'));
                        } catch (error) {
                            this.tampilkanNotice('Gagal menghubungi server: ' + error.message);
                        }
                        await this.load();
                    },

                    async pull(event) {
                        const button = event ? event.currentTarget : null;
                        if (button) button.disabled = true;

                        await this.triggerPull();

                        if (button) button.disabled = !!this.data && (this.data.status || {}).status === 'jalan';
                    },

                    tampilkanNotice(pesan) {
                        if (pesan === this.teksStatus) {
                            this.notice = '';
                            return;
                        }
                        this.notice = pesan;
                        clearTimeout(this.noticeTimer);
                        this.noticeTimer = setTimeout(() => { this.notice = ''; }, 10000);
                    },

                    // Selama penarikan masih berjalan, status dipantau berkala (batas 5 menit)
                    // sampai penariknya melapor, supaya baris tidak tertinggal di "sedang menarik".
                    pantauBilaBerjalan() {
                        const berjalan = !!this.data && (this.data.status || {}).status === 'jalan';

                        if (berjalan) {
                            this.wasRunning = true;
                        }

                        if (!berjalan) {
                            if (this.wasRunning) {
                                this.wasRunning = false;
                                const st = (this.data || {}).status || {};
                                const pl = (this.data || {}).pull || {};
                                if (st.status === 'selesai' || ((!st.status || st.status === 'belum') && pl.status === 'selesai')) {
                                    this.tampilkanNotice('Data berhasil diperbarui dari API. Memuat ulang halaman...');
                                    setTimeout(() => { window.location.reload(); }, 1200);
                                    return;
                                }
                            }
                            this.pantauSampai = 0;

                            return;
                        }

                        if (this.pantau !== null) return;

                        if (this.pantauSampai === 0) {
                            this.pantauSampai = Date.now() + 5 * 60 * 1000;
                        }

                        this.pantau = setTimeout(async () => {
                            this.pantau = null;

                            if (Date.now() > this.pantauSampai) {
                                this.pantauSampai = 0;

                                return;
                            }

                            await this.load();
                        }, 10000);
                    },
                };
            }
        </script>
    @endpush
@endonce
