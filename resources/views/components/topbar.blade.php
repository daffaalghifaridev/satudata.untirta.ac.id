@props(['title' => null])

@php
    $navItems = [
        [
            'name' => 'Beranda',
            'url' => url('/'),
            'active' => request()->is('/'),
        ],
        [
            'name' => 'Akademik',
            'url' => route('akademik'),
            'active' => request()->is('akademik') || request()->is('akademik/mahasiswa-lulus*'),
        ],
        [
            'name' => 'Perkuliahan',
            'url' => route('akademik.perkuliahan'),
            'active' => request()->is('akademik/perkuliahan*'),
        ],
        [
            'name' => 'Aset',
            'url' => route('aset.index'),
            'active' => request()->is('aset*'),
        ],
        [
            'name' => 'Pegawai',
            'url' => route('pegawai'),
            'active' => request()->is('pegawai') && !request()->is('pegawai/*'),
        ],
        [
            'name' => 'Direktori',
            'url' => route('pegawai.profil-dosen', ['kategori' => 'semua']),
            'active' => request()->is('pegawai/profil-dosen*') || request()->is('pegawai/profil-staf*'),
        ],
    ];
@endphp

<header x-data="{ mobileDrawerOpen: false }"
    @keydown.escape.window="mobileDrawerOpen = false"
    class="sticky top-0 z-50 w-full select-none bg-white/80 backdrop-blur-md border-b border-black/[0.06]">

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-14 items-center justify-between gap-4">

            {{-- Brand --}}
            <a href="/" class="flex items-center gap-2.5 shrink-0 rounded-lg py-1 transition-opacity hover:opacity-80">
                <img src="{{ asset('images/untirta_logo.png') }}"
                    alt="Logo Untirta"
                    style="width: 28px; height: 28px; object-fit: contain;">
                <div class="leading-tight">
                    <span class="block text-[13px] font-semibold tracking-tight text-[#1D1D1F]">
                        SATUDATA
                    </span>
                    <span class="block text-[10px] font-medium text-[#6E6E73]">UNTIRTA</span>
                </div>
            </a>

            {{-- Desktop Navigation (hanya route nyata) --}}
            <nav aria-label="Navigasi utama" class="hidden md:flex items-center gap-1">
                @foreach ($navItems as $item)
                    <a href="{{ $item['url'] }}"
                        class="px-3 py-1.5 rounded-lg text-[13px] font-medium transition-colors {{ $item['active'] ? 'bg-black/[0.05] text-[#1D1D1F] font-semibold' : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.03]' }}">
                        {{ $item['name'] }}
                    </a>
                @endforeach
            </nav>

            {{-- Right Actions --}}
            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                    class="inline-flex items-center gap-1.5 min-h-[36px] px-3.5 py-1.5 rounded-full bg-[#0071E3] text-white hover:bg-[#0077ED] transition-colors text-xs font-medium cursor-pointer"
                    data-tirta-chat-open
                    aria-label="Buka asisten data TirtaAgent"
                    title="Tanya TirtaAgent">
                    <i class="fa-solid fa-message text-[11px]"></i>
                    <span>TirtaAgent</span>
                </button>

                <button type="button"
                    @click="mobileDrawerOpen = !mobileDrawerOpen"
                    :aria-expanded="mobileDrawerOpen"
                    class="flex md:hidden h-11 w-11 items-center justify-center rounded-xl text-[#1D1D1F] hover:bg-black/[0.05] transition-colors"
                    aria-label="Buka menu navigasi">
                    <i class="fa-solid fa-bars text-base" x-show="!mobileDrawerOpen"></i>
                    <i class="fa-solid fa-xmark text-lg" x-show="mobileDrawerOpen" x-cloak></i>
                </button>
            </div>

        </div>
    </div>

    {{-- Mobile Navigation Drawer --}}
    <div x-show="mobileDrawerOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="md:hidden border-t border-black/[0.06] bg-white px-4 py-3 shadow-lg"
        x-cloak>
        <nav aria-label="Navigasi mobile" class="flex flex-col gap-1">
            @foreach ($navItems as $item)
                <a href="{{ $item['url'] }}"
                    @click="mobileDrawerOpen = false"
                    class="flex items-center min-h-[44px] px-3.5 rounded-xl text-sm font-medium transition-colors {{ $item['active'] ? 'bg-[#0071E3]/10 text-[#0071E3] font-semibold' : 'text-[#1D1D1F] hover:bg-black/[0.04]' }}">
                    {{ $item['name'] }}
                </a>
            @endforeach
        </nav>
    </div>
</header>
