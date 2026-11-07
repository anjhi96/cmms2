{{-- PIC cell: dropdown for admin/koordinator (saved via fetch, like Greasing), plain name otherwise. --}}
@php($areaId = $s->row?->area_id)
@if ($canManage && $areaId)
    <label for="pic-{{ $s->id }}-{{ $suffix }}" class="sr-only">PIC {{ $s->row?->name }}</label>
    <select id="pic-{{ $s->id }}-{{ $suffix }}" data-url="{{ route('small-pm.assign-pic', $s) }}"
        class="assign-small-pm-pic {{ $suffix === 'm' ? 'mt-1 w-full' : 'w-40' }} rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm font-medium shadow-sm transition hover:border-blue-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        <option value="">Belum ada (siapa saja)</option>
        @foreach ($picsByArea[$areaId] ?? [] as $p)
            <option value="{{ $p->id }}" @selected($s->pic_user_id === (int) $p->id)>{{ \Illuminate\Support\Str::title(strtolower($p->name)) }}</option>
        @endforeach
    </select>
@else
    {{ $s->pic?->name ?? 'Belum ada' }}
@endif
