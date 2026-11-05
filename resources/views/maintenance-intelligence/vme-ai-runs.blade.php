@extends('layouts.app')

@php
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';

    $statusClass = ['ALERT' => 'bg-red-100 text-red-700', 'WORKING' => 'bg-emerald-100 text-emerald-700', 'IDLE' => 'bg-slate-100 text-slate-600'];
    $statusLabel = ['ALERT' => 'Perlu perhatian', 'WORKING' => 'Bekerja', 'IDLE' => 'Idle'];
@endphp

@section('content')
<div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <a href="{{ route('maintenance-intelligence.vme') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 transition">&larr; Virtual Maintenance Environment</a>
        <h1 class="mt-3 text-2xl font-semibold text-slate-800">Kumpulan Hasil AI</h1>
        <p class="mt-1 text-sm text-slate-500">
            Setiap output karakter AI disimpan di sini (baris baru dibuat saat hasilnya berubah). Data ini hanya dibaca dari fitur lain; karakter AI tidak mengubah data FreeDOMS.
        </p>

        <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm text-slate-600">Karakter
                <select name="character" class="mt-1 block rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="">Semua</option>
                    @foreach ($characters as $key => $name)
                        <option value="{{ $key }}" @selected($filters['character'] === $key)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-slate-600">Dari tanggal
                <input type="date" name="from" value="{{ $filters['from'] }}" class="mt-1 block rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
            </label>
            <label class="text-sm text-slate-600">Sampai tanggal
                <input type="date" name="to" value="{{ $filters['to'] }}" class="mt-1 block rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
            </label>
            <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Terapkan</button>
            <a href="{{ route('maintenance-intelligence.vme.ai-runs') }}" class="px-2 py-2 text-sm text-slate-500 hover:text-slate-700">Reset</a>
        </form>
        @if ($errors->any())
            <p class="mt-3 text-sm text-red-700">{{ $errors->first() }}</p>
        @endif
    </div>

    <section class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3">Karakter</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Ringkasan</th>
                    <th class="px-4 py-3">Sumber</th>
                    <th class="px-4 py-3">Referensi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100" data-infinite-list="runs">
                @forelse ($runs as $run)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $run->created_at?->format('d-m-Y H:i:s') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800">{{ $characters[$run->character_key] ?? $run->character_key }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass[$run->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $statusLabel[$run->status] ?? $run->status }}</span></td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $run->summary }}
                            @if (!empty($run->result))
                                <ul class="mt-1 text-xs text-slate-500">
                                    @foreach ($run->result as $line)
                                        <li>{{ $line['label'] ?? '' }}: {{ $line['value'] ?? '' }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $run->source }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $run->source_ref_type ? $run->source_ref_type.' #'.$run->source_ref_id : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Belum ada hasil AI yang tersimpan{{ array_filter($filters) ? ' untuk filter ini' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div><x-infinite-scroll :paginator="$runs" key="runs" /></div>
</div>
@endsection
