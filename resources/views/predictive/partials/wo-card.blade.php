@php $overdue = $wo->isOpen() && $wo->due_date->isPast() && ! $wo->due_date->isToday(); @endphp
<article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <a href="{{ route('predictive.show', $wo) }}" class="min-w-0 break-words text-base font-bold text-slate-900">{{ $wo->order_number }}</a>
        @include('predictive.partials.status-badge', ['wo' => $wo])
    </div>
    <p class="mt-1 break-words text-sm text-slate-700">
        <span class="font-semibold">{{ $wo->machine_number }}</span>
        <span class="text-slate-500">· {{ $wo->machine_type ?: 'Tipe belum diisi' }} · {{ $wo->area }}</span>
    </p>
    <p class="mt-2 line-clamp-3 break-words text-sm text-slate-600">{{ $wo->problem_description }}</p>
    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
        <div><dt class="text-slate-500">Tanggal WO</dt><dd class="font-medium text-slate-800">{{ $wo->wo_date->format('d-m-Y') }}</dd></div>
        <div><dt class="text-slate-500">Due date</dt><dd class="font-medium {{ $overdue ? 'text-rose-700' : 'text-slate-800' }}">{{ $wo->due_date->format('d-m-Y') }}{{ $overdue ? ' (terlambat)' : '' }}</dd></div>
        <div class="col-span-2"><dt class="text-slate-500">PIC</dt><dd class="font-medium text-slate-800">{{ $wo->pic?->name ?? 'Belum di-claim' }}</dd></div>
    </dl>
    <div class="mt-3">@include('predictive.partials.wo-buttons', ['wo' => $wo, 'size' => 'card'])</div>
</article>
