{{-- Row actions, same set as Greasing: START (PIC), Execute, Edit / Delete (admin, koordinator). $mobile switches to full-width buttons. --}}
@php
    $user = auth()->user();
    $btn = $mobile ? 'flex-1 rounded-lg px-3 py-2 text-center text-xs font-medium text-white transition' : 'rounded-lg px-3 py-1.5 text-sm font-medium text-white transition';
@endphp
@if ($user->isPic() && ! $s->isDone())
    @if ($s->start_time)
        <span class="{{ $mobile ? 'flex-1 py-2 text-xs' : 'inline-flex flex-col items-center py-1 text-sm' }} rounded-lg bg-slate-200 px-3 text-center font-semibold text-slate-700">
            STARTED <span class="{{ $mobile ? '' : 'text-[10px]' }} font-normal text-slate-500">{{ $s->start_time->format('d M Y H:i') }}</span>
        </span>
    @else
        <button type="button" class="small-pm-start-btn {{ $btn }} bg-blue-600 hover:bg-blue-700"
            data-url="{{ route('small-pm.start', $s) }}" data-row="{{ $s->row?->name }}">START</button>
    @endif
@endif
@unless ($user->isSupervisor())
    <a href="{{ route('small-pm.execute', ['schedule' => $s->id, 'page' => request('page')]) }}" class="{{ $btn }} bg-emerald-600 hover:bg-emerald-700">
        {{ $s->isDone() ? 'Lihat' : 'Execute' }}
    </a>
@endunless
@if ($canManage)
    <a href="{{ route('small-pm.edit', ['schedule' => $s->id, 'page' => request('page')]) }}" class="{{ $btn }} bg-amber-500 hover:bg-amber-600">Edit</a>
    <form method="POST" action="{{ route('small-pm.destroy', $s) }}" class="{{ $mobile ? 'flex-1' : 'inline' }}">
        @csrf
        @method('DELETE')
        <button class="{{ $btn }} {{ $mobile ? 'w-full' : '' }} bg-rose-600 hover:bg-rose-700" onclick="return confirm('Hapus jadwal Small PM?')">Delete</button>
    </form>
@endif
