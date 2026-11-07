@php $result = session($resultKey ?? 'predictive_import_result'); @endphp
<section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Hasil import">
    @if ($result['fatal'])
        <p class="text-sm font-medium text-rose-700" role="alert">{{ $result['fatal'] }}</p>
    @else
        <h2 class="text-sm font-semibold text-slate-900">Hasil import</h2>
        <dl class="mt-2 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-emerald-50 px-2 py-2"><dt class="text-xs text-emerald-800">Success</dt><dd class="text-xl font-bold text-emerald-800">{{ $result['success'] }}</dd></div>
            <div class="rounded-xl bg-rose-50 px-2 py-2"><dt class="text-xs text-rose-800">Failed</dt><dd class="text-xl font-bold text-rose-800">{{ $result['failed'] }}</dd></div>
            <div class="rounded-xl bg-amber-50 px-2 py-2"><dt class="text-xs text-amber-800">Skipped</dt><dd class="text-xl font-bold text-amber-800">{{ $result['skipped'] }}</dd></div>
        </dl>
        @if ($result['details'])
            <details class="group mt-3">
                <summary class="min-h-11 cursor-pointer list-none py-2 text-sm font-semibold text-sky-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400">
                    <span class="group-open:hidden">Lihat penyebab per baris ({{ count($result['details']) + $result['hidden'] }})</span>
                    <span class="hidden group-open:inline">Sembunyikan detail</span>
                </summary>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <tr><th class="px-3 py-2">Baris</th><th class="px-3 py-2">{{ $labelHeader ?? 'Order Number' }}</th><th class="px-3 py-2">Hasil</th><th class="px-3 py-2">Penyebab</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($result['details'] as $d)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $d['row'] }}</td>
                                    <td class="px-3 py-2 font-medium text-slate-800">{{ ($d['label'] ?? $d['order_number'] ?? '') ?: '-' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $d['result'] === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($d['result']) }}</span>
                                    </td>
                                    <td class="min-w-56 px-3 py-2 text-slate-600">{{ $d['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($result['hidden'] > 0)
                    <p class="mt-2 text-xs text-slate-500">{{ $result['hidden'] }} baris lain tidak ditampilkan (batas {{ ($maxDetail ?? \App\Imports\PredictiveWorkOrderImport::MAX_DETAIL_ROWS) }} baris).</p>
                @endif
            </details>
        @endif
    @endif
</section>
