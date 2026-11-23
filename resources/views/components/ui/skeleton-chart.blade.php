@props([
    'title' => 'Memuat grafik...',
    'height' => 'min-h-[300px]',
])

<div role="status" aria-busy="true" aria-label="{{ $title }}"
     class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8 flex flex-col w-full">
    <span class="sr-only">{{ $title }}</span>
    <div class="mb-6 flex items-start justify-between">
        <div>
            <div class="h-4 w-48 rounded-md skeleton-shimmer"></div>
            <div class="mt-2 h-3 w-64 rounded-md skeleton-shimmer"></div>
        </div>
        <div class="h-6 w-16 rounded-md skeleton-shimmer"></div>
    </div>
    <div class="flex-1 {{ $height }} flex items-end gap-3 pt-6 pb-2 px-2 border-b border-l border-slate-100">
        <div class="h-1/3 flex-1 rounded-t-md skeleton-shimmer"></div>
        <div class="h-2/3 flex-1 rounded-t-md skeleton-shimmer"></div>
        <div class="h-1/2 flex-1 rounded-t-md skeleton-shimmer"></div>
        <div class="h-4/5 flex-1 rounded-t-md skeleton-shimmer"></div>
        <div class="h-3/5 flex-1 rounded-t-md skeleton-shimmer"></div>
        <div class="h-2/5 flex-1 rounded-t-md skeleton-shimmer"></div>
        <div class="h-3/4 flex-1 rounded-t-md skeleton-shimmer"></div>
    </div>
    <div class="mt-3 flex justify-between px-2">
        <div class="h-2.5 w-12 rounded skeleton-shimmer"></div>
        <div class="h-2.5 w-12 rounded skeleton-shimmer"></div>
        <div class="h-2.5 w-12 rounded skeleton-shimmer"></div>
        <div class="h-2.5 w-12 rounded skeleton-shimmer"></div>
        <div class="h-2.5 w-12 rounded skeleton-shimmer"></div>
    </div>
</div>
