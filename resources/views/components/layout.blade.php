<!DOCTYPE html>
<html lang="id" class="h-full bg-[#F5F5F7]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <title>
        {{ $title ?? ucwords(str_replace('-', ' ', request()->segment(count(request()->segments())) ?? 'dashboard')) }}
        | SATUDATA UNTIRTA</title>

    <script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
    {{-- Livewire dimuat eksplisit (sekaligus membawa Alpine dan direktifnya) supaya tidak
         bergantung pada penyuntikan otomatis dan urutannya pasti sebelum app.js. --}}
    @livewireStyles
    @livewireScripts
    <script>
        if (localStorage.getItem('sidebar_collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>
    <style>
        @keyframes skeleton-shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .skeleton-shimmer {
            position: relative;
            overflow: hidden;
            background-color: #f1f5f9;
        }
        .skeleton-shimmer::after {
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            transform: translateX(-100%);
            background-image: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.65) 40%,
                rgba(255, 255, 255, 0.85) 60%,
                rgba(255, 255, 255, 0) 100%
            );
            animation: skeleton-shimmer 1.4s infinite cubic-bezier(0.4, 0, 0.2, 1);
            content: '';
        }
    </style>
</head>

<body class="h-full bg-[#F5F5F7] text-[#1D1D1F] antialiased">
    {{-- Indikator transisi navigasi halus antar-halaman --}}
    <div id="page-progress-bar"
         class="fixed top-0 left-0 right-0 z-50 h-0.5 bg-[#0071E3] transition-all duration-300 pointer-events-none opacity-0 -translate-y-full"
         style="width: 0%;"></div>

    <div class="min-h-full flex flex-col">
        <x-topbar :title="$title ?? ''"></x-topbar>

        <main class="flex-1">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>
        <x-footer></x-footer>
    </div>

    <x-tirta-agent-chat></x-tirta-agent-chat>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bar = document.getElementById('page-progress-bar');
            if (!bar) return;

            window.addEventListener('pageshow', () => {
                bar.style.width = '0%';
                bar.classList.add('opacity-0', '-translate-y-full');
            });

            document.querySelectorAll('a[href]:not([target="_blank"]):not([href^="#"]):not([href^="javascript:"])').forEach(link => {
                link.addEventListener('click', (e) => {
                    try {
                        const url = new URL(link.href, window.location.origin);
                        if (url.origin === window.location.origin && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
                            bar.classList.remove('opacity-0', '-translate-y-full');
                            bar.style.width = '35%';
                            setTimeout(() => { bar.style.width = '75%'; }, 180);
                        }
                    } catch (err) {}
                });
            });
        });
    </script>

    @stack('scripts')
</body>

</html>
