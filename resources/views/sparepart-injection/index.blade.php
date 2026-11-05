@extends('layouts.app')

@section('content')
    @include('spareparts._tabs')

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-800">Sparepart Injection</h1>
        <p class="text-sm text-slate-500">
            Import warehouse issues (Month, SAP Number, Qty, Order Number) into the Sparepart Usage of the matching PM Schedule.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if ($errors->has('file'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('file') }}</div>
    @endif

    @if ($canImport)
        <form action="{{ route('sparepart-injection.store') }}" method="POST" enctype="multipart/form-data"
            class="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center">
            @csrf
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border file:border-slate-300 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 sm:w-auto">
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">Upload &amp; Import</button>
            <p class="text-xs text-slate-500 sm:ml-2">
                Columns: Month (month name, e.g. Oktober / October), SAP Number, Qty, Order Number. Qty of rows with the same
                Order Number + SAP Number is summed; spareparts already recorded on the PM are skipped.
            </p>
        </form>
    @else
        <p class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Read-only view.</p>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">Import history</div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead>
                    <tr>
                        @foreach (['Date', 'File', 'By', 'Rows', 'Processed', 'Usage added', 'Skipped', 'Failed', ''] as $h)
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" data-infinite-list="imports">
                    @forelse ($imports as $import)
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-3 py-3 text-sm">{{ $import->created_at->format('d M Y H:i') }}</td>
                            <td class="px-3 py-3 text-sm text-slate-700">{{ $import->filename }}</td>
                            <td class="px-3 py-3 text-sm text-slate-700">{{ $import->user?->name ?? '-' }}</td>
                            <td class="px-3 py-3 text-sm">{{ $import->total_rows }}</td>
                            <td class="px-3 py-3 text-sm font-semibold text-emerald-700">{{ $import->processed_rows }}</td>
                            <td class="px-3 py-3 text-sm">{{ $import->usage_added }}</td>
                            <td class="px-3 py-3 text-sm font-semibold text-amber-700">{{ $import->skipped_rows }}</td>
                            <td class="px-3 py-3 text-sm font-semibold text-rose-700">{{ $import->failed_rows }}</td>
                            <td class="px-3 py-3"><a href="{{ route('sparepart-injection.show', $import) }}" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">No imports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4"><x-infinite-scroll :paginator="$imports" key="imports" /></div>
@endsection
