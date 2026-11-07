<div x-show="importOpen" x-cloak x-transition.opacity.duration.150ms
    @keydown.escape.window="importOpen = false"
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="predictive-import-title">
    <div @click.outside="importOpen = false" class="max-h-dvh w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6">
        <form method="POST" action="{{ route('predictive.import') }}" enctype="multipart/form-data" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <input type="hidden" name="category" value="{{ $category }}">
            <h3 id="predictive-import-title" class="text-lg font-semibold text-slate-900">Import WO {{ $categoryLabel }}</h3>
            <p class="mt-1 text-sm text-slate-500">
                File .xlsx, .xls, atau .csv dengan kolom
                <span class="font-medium text-slate-700">Order Number, Machine Number, Date, Problem Description</span>. Due Date dihitung otomatis: Date + 14 hari.
                Area dan Machine Type diambil dari Machine Master.
                Order Number yang sudah ada dilewati, WO existing tidak diubah.
            </p>
            <a href="{{ route('import-templates.download', 'predictive-work-order') }}" class="mt-2 inline-block text-sm font-semibold text-sky-700 hover:underline">Unduh template (Excel)</a>

            @error('file')
                <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</div>
            @enderror

            <div class="mt-4">
                <label for="pi-file" class="mb-1.5 block text-sm font-medium text-slate-700">File</label>
                <input id="pi-file" name="file" type="file" required accept=".xlsx,.xls,.csv"
                    class="block w-full rounded-lg border border-slate-300 text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-sm file:font-semibold file:text-slate-700">
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" @click="importOpen = false" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</button>
                <button type="submit" x-bind:disabled="busy" class="min-h-11 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                    <span x-show="!busy">Import</span><span x-show="busy" x-cloak>Memproses…</span>
                </button>
            </div>
        </form>
    </div>
</div>
