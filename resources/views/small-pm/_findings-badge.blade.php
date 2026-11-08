{{-- Dead blower filter count for one schedule (Ganti Filter Panel). --}}
@if ($s->findings_count > 0)
    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $s->findings_count }} mesin</span>
    @if ($s->open_findings_count > 0)
        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ $s->open_findings_count }} open</span>
    @endif
@elseif ($s->action_date)
    <span class="text-xs text-slate-500">Semua normal</span>
@else
    <span class="text-xs text-slate-500">-</span>
@endif
