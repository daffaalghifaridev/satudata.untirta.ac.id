@props([
    'title',
    'value',
    'href' => null,
    'iconClass',
    'badgeText',
    'badgeColor',
    'footerText'
])

@if($href)
    <a href="{{ $href }}" class="block group h-full cursor-pointer">
@else
    <div class="block h-full">
@endif

    <div class="bg-white rounded-2xl border border-black/[0.06] p-5 h-full flex flex-col justify-between transition-colors {{ $href ? 'hover:border-[#0071E3]/40' : '' }}">
        <div class="flex justify-between items-start gap-3">
            <h3 class="text-[11px] font-semibold tracking-wide text-[#6E6E73]">
                {{ $title }}
            </h3>
            <div class="text-[#6E6E73] {{ $href ? 'group-hover:text-[#0071E3]' : '' }} transition-colors">
                <i class="{{ $iconClass }} text-sm"></i>
            </div>
        </div>

        <div class="mt-3">
            <p class="text-3xl font-semibold tracking-tight text-[#1D1D1F] tabular-nums">
                {{-- Nilai null berarti sumbernya belum punya data untuk periode ini. --}}
                {{ $value === null ? '-' : (is_numeric($value) ? number_format($value, 0, ',', '.') : $value) }}
            </p>
        </div>

        <div class="mt-4 pt-3 border-t border-black/[0.05] flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                @if($badgeText)
                    <span class="{{ $badgeColor }} text-white text-[11px] font-medium px-2 py-0.5 rounded-full leading-none shrink-0">{{ $badgeText }}</span>
                @endif
                <span class="text-xs text-[#6E6E73] truncate">{{ $footerText }}</span>
            </div>
            @if($href)
                <i class="fa-solid fa-chevron-right text-[10px] text-[#0071E3] shrink-0"></i>
            @endif
        </div>
    </div>

@if($href)
    </a>
@else
    </div>
@endif
