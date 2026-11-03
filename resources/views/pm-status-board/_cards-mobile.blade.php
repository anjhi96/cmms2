@foreach ($schedules as $schedule)
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <div class="font-semibold text-slate-800">{{ $schedule['machine_number'] }}</div>
                <div class="mt-1 flex flex-wrap items-center gap-1.5"
                    data-pm-status-cell
                    data-pm-status-id="{{ $schedule['id'] }}"
                    data-pm-status-original="{{ $schedule['status'] }}">
                    <span data-pm-status-badge
                        class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold
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
            </div>
        </div>

        <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
            <div>
                <div class="text-slate-400">Bulan</div>
                <div class="font-medium text-slate-700">{{ $schedule['month_label'] }}</div>
            </div>
            <div>
                <div class="text-slate-400">Plan Date</div>
                <div class="font-medium text-slate-700">{{ $schedule['plan_date'] }}</div>
            </div>
            <div>
                <div class="text-slate-400">Gap Day</div>
                <div
                    class="font-medium
                    {{ $schedule['is_gap_day_out_of_range'] ? 'text-red-600' : 'text-slate-700' }}">
                    {{ $schedule['gap_day'] > 0 ? '+' : '' }}{{ $schedule['gap_day'] }}
                </div>
            </div>
            <div>
                <div class="text-slate-400">Actual PM Date</div>
                <div class="font-medium text-slate-700">{{ $schedule['actual_date'] ?? '—' }}</div>
            </div>
        </div>
    </div>
@endforeach
