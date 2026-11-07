@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Execute Small PM</h1>
            <p class="mt-1 text-sm text-slate-500">{{ \App\Models\SmallPmRow::TASK_TYPES[$schedule->task_type] ?? $schedule->task_type }}. Isi action date dan remarks.</p>
        </div>

        <div class="grid gap-4 border-b border-slate-200 bg-slate-50 px-6 py-5 sm:grid-cols-2 md:grid-cols-5">
            @foreach ([
                'Order Number' => $schedule->order_number ?? '-',
                'Row' => $schedule->row->name ?? '-',
                'Cycle' => $schedule->cycle,
                'Plan Date' => $schedule->plan_date->format('d M Y'),
                'Due Date' => $schedule->due_date->format('d M Y'),
            ] as $label => $value)
                <div>
                    <p class="text-xs font-semibold text-slate-500">{{ $label }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="border-b border-slate-200 px-6 py-4">
            <p class="text-xs font-semibold text-slate-500">Mesin di row ini ({{ $machines->count() }})</p>
            @if ($machines->isEmpty())
                <p class="mt-1 text-sm text-slate-500">Row ini belum berisi mesin.</p>
            @else
                <ul class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($machines as $m)
                        <li class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700" title="{{ $m->machine_type }}">{{ $m->machine_number }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($next)
            <p class="mx-6 mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                Jadwal berikutnya sudah dibuat: plan date {{ $next->plan_date->format('d M Y') }} ({{ $next->status }}).
            </p>
        @endif

        <form action="{{ route('small-pm.execute.store', $schedule) }}" method="POST" class="space-y-5 p-6" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="x-action" class="mb-2 block text-sm font-medium text-slate-700">Action Date</label>
                    <input id="x-action" type="date" name="action_date" required value="{{ old('action_date', optional($schedule->action_date)->format('Y-m-d')) }}"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">Status: FINISH ON TIME bila action date tidak melewati due date, selain itu FINISH.</p>
                    @error('action_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="x-status" class="mb-2 block text-sm font-medium text-slate-700">Status Saat Ini</label>
                    <input id="x-status" type="text" value="{{ $schedule->status }}" disabled
                        class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm text-slate-600">
                </div>

                <div class="md:col-span-2">
                    <label for="x-remarks" class="mb-2 block text-sm font-medium text-slate-700">Remarks</label>
                    <textarea id="x-remarks" name="remarks" class="min-h-24 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">{{ old('remarks', $schedule->remarks) }}</textarea>
                    @error('remarks') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button x-bind:disabled="busy" class="min-h-11 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">Simpan Execution</button>
                <a href="{{ route('small-pm.index', ['tab' => $tab]) }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection
