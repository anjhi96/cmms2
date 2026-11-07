@php $overdue = $wo->isOpen() && $wo->due_date->isPast() && ! $wo->due_date->isToday(); @endphp
<tr class="align-top hover:bg-slate-50">
    <td class="whitespace-nowrap px-3 py-3 font-semibold text-slate-900">
        <a href="{{ route('predictive.show', $wo) }}" class="hover:text-sky-700 hover:underline">{{ $wo->order_number }}</a>
    </td>
    <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $wo->area }}</td>
    <td class="px-3 py-3">
        <span class="block whitespace-nowrap font-medium text-slate-800">{{ $wo->machine_number }}</span>
        <span class="block text-xs text-slate-500">{{ $wo->machine_type ?: '-' }}</span>
    </td>
    <td class="whitespace-nowrap px-3 py-3 text-slate-600">
        {{ $wo->wo_date->format('d-m-Y') }}
        <span class="block text-xs {{ $overdue ? 'font-semibold text-rose-700' : 'text-slate-500' }}">Due {{ $wo->due_date->format('d-m-Y') }}{{ $overdue ? ', terlambat' : '' }}</span>
    </td>
    <td class="w-56 min-w-44 px-3 py-3 text-slate-600"><span class="line-clamp-2">{{ $wo->problem_description }}</span></td>
    <td class="px-3 py-3 text-slate-600">{{ $wo->pic?->name ?? '-' }}</td>
    <td class="px-3 py-3">@include('predictive.partials.status-badge', ['wo' => $wo])</td>
    <td class="min-w-36 px-3 py-3">@include('predictive.partials.wo-buttons', ['wo' => $wo, 'size' => 'row'])</td>
</tr>
