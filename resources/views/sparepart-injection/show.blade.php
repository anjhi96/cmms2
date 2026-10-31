@extends('layouts.app')

@section('content')
    @include('spareparts._tabs')

    <div class="mb-4 flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Import result</h1>
            <p class="text-sm text-slate-500">{{ $import->filename }} · {{ $import->created_at->format('d M Y H:i') }} · {{ $import->user?->name ?? '-' }}</p>
        </div>
        <a href="{{ route('sparepart-injection.index') }}" class="text-sm font-medium text-blue-600 hover:underline">&larr; Back to history</a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-5">
        @foreach ([
            ['Rows read', $import->total_rows, 'text-slate-800'],
            ['New transactions processed', $import->processed_rows, 'text-emerald-700'],
            ['Usage added', $import->usage_added, 'text-blue-700'],
            ['Skipped (duplicate / usage exists)', $import->skipped_rows, 'text-amber-700'],
            ['Failed / invalid', $import->failed_rows, 'text-rose-700'],
        ] as [$label, $value, $color])
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-medium text-slate-500">{{ $label }}</div>
                <div class="text-2xl font-semibold {{ $color }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="mb-3 flex flex-wrap gap-2 text-sm">
        @foreach ([null => 'All', 'PROCESSED' => 'Processed', 'SKIPPED' => 'Skipped', 'FAILED' => 'Failed'] as $key => $label)
            <a href="{{ route('sparepart-injection.show', array_filter(['import' => $import, 'status' => $key])) }}"
                class="rounded-full border px-3 py-1 {{ $status === $key ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead>
                    <tr>
                        @foreach (['#', 'Month', 'Order Number', 'SAP Number', 'Qty', 'Status', 'Reason'] as $h)
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-3 text-sm text-slate-500">{{ $row->row_number }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm">{{ $row->monthLabel() }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm font-semibold text-slate-800">{{ $row->order_number }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm">{{ $row->sap_number }}</td>
                            <td class="px-3 py-3 text-sm">{{ $row->qty }}</td>
                            <td class="px-3 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium
                                    {{ ['PROCESSED' => 'bg-emerald-100 text-emerald-700', 'SKIPPED' => 'bg-amber-100 text-amber-700', 'FAILED' => 'bg-rose-100 text-rose-700'][$row->status] }}">{{ $row->status }}</span>
                            </td>
                            <td class="px-3 py-3 text-sm text-slate-600">{{ $row->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No rows.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
