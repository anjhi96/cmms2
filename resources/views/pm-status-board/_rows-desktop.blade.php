@foreach ($schedules as $schedule)
    <tr class="hover:bg-slate-50 transition">
        <td class="px-4 py-3">
            <div class="flex flex-wrap items-center gap-1.5"
                data-pm-status-cell
                data-pm-status-id="{{ $schedule['id'] }}"
                data-pm-status-original="{{ $schedule['status'] }}">
                <span data-pm-status-badge
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                    {{ $schedule['status'] === 'CLOSED' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800' }}">
                    {{ $schedule['status'] }}
                </span>
                @if ($canOverrideStatus)
                    <span data-pm-status-override-tag
                        class="hidden text-[10px] font-medium text-slate-400"
                        title="Tampilan status ini diubah manual, data mesin asli tidak berubah.">
                        (manual)
                    </span>
                    <button type="button"
                        data-pm-status-toggle
                        class="rounded-md border border-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        title="Override tampilan status (tidak mengubah data mesin asli)">
                        Ubah
                    </button>
                @endif
            </div>
        </td>
        <td class="px-4 py-3 text-slate-700">{{ $schedule['month_label'] }}</td>
        <td class="px-4 py-3 font-medium text-slate-800">{{ $schedule['machine_number'] }}</td>
        <td class="px-4 py-3 text-slate-700">{{ $schedule['plan_date'] }}</td>
        <td class="px-4 py-3 text-center">
            <span
                class="inline-block rounded-full px-3 py-1 text-sm font-medium
                {{ $schedule['is_gap_day_out_of_range'] ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-800' }}">
                {{ $schedule['gap_day'] > 0 ? '+' : '' }}{{ $schedule['gap_day'] }}
            </span>
        </td>
        <td class="px-4 py-3 text-slate-700">
            {{ $schedule['actual_date'] ?? '—' }}
        </td>
    </tr>
@endforeach
