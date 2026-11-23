@props([
    'count' => 4,
    'cols' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    'label' => 'Memuat data statistik...',
])

<div role="status" aria-busy="true" aria-label="{{ $label }}" class="w-full">
    <span class="sr-only">{{ $label }}</span>
    <div class="grid {{ $cols }} gap-5">
        @for ($i = 0; $i < $count; $i++)
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div class="h-3 w-28 rounded-md skeleton-shimmer"></div>
                    <div class="h-9 w-9 rounded-xl skeleton-shimmer"></div>
                </div>
                <div class="mt-4">
                    <div class="h-8 w-20 rounded-md skeleton-shimmer"></div>
                </div>
                <div class="mt-4 border-t border-gray-50 pt-2.5">
                    <div class="h-2.5 w-36 rounded-md skeleton-shimmer"></div>
                </div>
            </div>
        @endfor
    </div>
</div>
