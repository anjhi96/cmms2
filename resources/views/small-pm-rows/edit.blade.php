@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">{{ session('success') }}</div>
    @endif

    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Row</h1>
            <p class="mt-1 text-sm text-slate-500">Ubah data row dan pilih mesin yang masuk row ini.</p>
        </div>

        <form action="{{ route('small-pm-rows.update', $row) }}" method="POST" class="space-y-5 p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            @include('small-pm-rows._fields', ['row' => $row])

            @php($checked = old('machine_ids', $machines->where('small_pm_row_id', $row->id)->pluck('id')->all()))
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Mesin di row ini</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Hanya mesin area {{ $row->area->name ?? '-' }} yang belum masuk row lain. Satu mesin hanya bisa di satu row.
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700" id="machineSelectedCount">
                        {{ count($checked) }} dipilih
                    </span>
                </div>

                <label for="machineSearchInput" class="sr-only">Cari mesin</label>
                <input type="text" id="machineSearchInput" placeholder="Cari nomor atau tipe mesin..."
                    class="mb-3 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">

                <div class="max-h-72 space-y-1 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2" id="machineList">
                    @forelse ($machines as $machine)
                        <label class="machine-row flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-slate-50"
                            data-search="{{ strtolower($machine->machine_number.' '.$machine->machine_type) }}">
                            <input type="checkbox" name="machine_ids[]" value="{{ $machine->id }}"
                                class="machine-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                @checked(in_array($machine->id, $checked))
                                onchange="updateMachineSelectedCount()">
                            <span class="font-medium text-slate-800">{{ $machine->machine_number }}</span>
                            <span class="text-slate-500">{{ $machine->machine_type }}</span>
                        </label>
                    @empty
                        <p class="p-3 text-sm text-slate-500">Tidak ada mesin yang bisa dipilih.</p>
                    @endforelse
                </div>
                @error('machine_ids')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('small-pm-rows.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Batal</a>
                <button class="min-h-11 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('machineSearchInput').addEventListener('input', function (e) {
        const term = e.target.value.trim().toLowerCase();
        document.querySelectorAll('#machineList .machine-row').forEach(function (row) {
            row.style.display = row.dataset.search.includes(term) ? '' : 'none';
        });
    });

    function updateMachineSelectedCount() {
        const count = document.querySelectorAll('.machine-checkbox:checked').length;
        document.getElementById('machineSelectedCount').textContent = count + ' dipilih';
    }
</script>
@endsection
