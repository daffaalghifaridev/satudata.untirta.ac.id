@props([
    'rows' => 5,
    'cols' => 5,
    'label' => 'Memuat data tabel...',
])

<div role="status" aria-busy="true" aria-label="{{ $label }}"
     class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm w-full">
    <span class="sr-only">{{ $label }}</span>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    @for ($c = 0; $c < $cols; $c++)
                        <th class="px-5 py-3.5 text-left">
                            <div class="h-3.5 {{ $c === 0 ? 'w-20' : ($c === 1 ? 'w-36' : 'w-24') }} rounded skeleton-shimmer"></div>
                        </th>
                    @endfor
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @for ($r = 0; $r < $rows; $r++)
                    <tr>
                        @for ($c = 0; $c < $cols; $c++)
                            <td class="px-5 py-4">
                                <div class="h-3.5 {{ $c === 0 ? 'w-16' : ($c === 1 ? 'w-44' : ($c === 2 ? 'w-28' : 'w-20')) }} rounded skeleton-shimmer"></div>
                            </td>
                        @endfor
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
</div>
