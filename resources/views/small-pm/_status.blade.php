<span @class([
    'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
    'bg-amber-100 text-amber-700' => $status === 'OPEN',
    'bg-rose-100 text-rose-700' => $status === 'FINISH',
    'bg-emerald-100 text-emerald-700' => $status === 'FINISH ON TIME',
])>{{ $status }}</span>
