@props([
    'count' => 6,
    'cols' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
    'label' => 'Memuat daftar kartu...',
])

<div role="status" aria-busy="true" aria-label="{{ $label }}" class="w-full">
    <span class="sr-only">{{ $label }}</span>
    <div class="grid {{ $cols }} gap-5">
        @for ($i = 0; $i < $count; $i++)
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="h-12 w-12 shrink-0 rounded-full skeleton-shimmer"></div>
                    <div class="flex-1 min-w-0">
                        <div class="h-4 w-3/4 rounded skeleton-shimmer"></div>
                        <div class="mt-2 h-3 w-1/2 rounded skeleton-shimmer"></div>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-50 flex items-center justify-between">
                    <div class="h-3 w-24 rounded skeleton-shimmer"></div>
                    <div class="h-6 w-16 rounded-lg skeleton-shimmer"></div>
                </div>
            </div>
        @endfor
    </div>
</div>
