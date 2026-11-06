@props(['name'])

{{-- Square-cap line icons drawn for this page: schedule, clipboard, QR tag, hex nut (sparepart). --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
    stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" {{ $attributes->merge(['class' => 'h-6 w-6']) }}>
    @switch($name)
        @case('calendar')
            <path d="M3 5h18v16H3zM3 10h18M8 3v4M16 3v4M8 15l2.5 2.5L16 13" />
        @break

        @case('clipboard')
            <path d="M5 4h14v17H5zM9 2h6v4H9zM8.5 11H16M8.5 15H13" />
        @break

        @case('qr')
            <path d="M4 7V4h3M20 7V4h-3M4 17v3h3M20 17v3h-3M8 8h2v2H8zM14 8h2v2h-2zM8 14h2v2H8zM14 14h2v2h-2z" />
        @break

        @case('nut')
            <path d="M12 3l7.8 4.5v9L12 21l-7.8-4.5v-9zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z" />
        @break
    @endswitch
</svg>
