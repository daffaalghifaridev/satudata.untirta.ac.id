@props([
    'title' => 'Judul card',
    'description' => 'Deskripsi card',
    'badge' => 'Kategori',
    'href' => '#',
    'iconBg' => 'bg-[#0071E3]/10',
    'iconColor' => 'text-[#0071E3]',
])

<a href="{{ $href }}"
    {{ $attributes->merge([
        'class' =>
            'group flex flex-col justify-between rounded-2xl border border-black/[0.06] bg-white p-6 transition-colors hover:border-[#0071E3]/40',
    ]) }}>
    <div>
        <div class="flex items-center justify-between gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $iconBg }} {{ $iconColor }}">
                @isset($icon)
                    {{ $icon }}
                @else
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                @endisset
            </div>
            <span class="text-xs font-medium text-[#6E6E73]">{{ $badge }}</span>
        </div>

        <h2 class="mt-5 text-lg font-semibold tracking-tight text-[#1D1D1F] group-hover:text-[#0071E3] transition-colors">
            {{ $title }}
        </h2>
        <p class="mt-2 text-sm leading-relaxed text-[#6E6E73]">
            {{ $description }}
        </p>
    </div>

    <div class="mt-6 flex items-center gap-1.5 text-xs font-medium text-[#0071E3]">
        <span>Buka modul</span>
        <i class="fa-solid fa-chevron-right text-[9px] transition-transform group-hover:translate-x-0.5"></i>
    </div>
</a>
