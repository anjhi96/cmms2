{{-- Add WO. Area and Machine Type are shown read-only; the server re-reads them from the Machine Master. --}}
<div x-show="addOpen" x-cloak x-transition.opacity.duration.150ms
    @keydown.escape.window="addOpen = false"
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="predictive-add-title">
    <div @click.outside="addOpen = false" class="max-h-dvh w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6">
        <form method="POST" action="{{ route('predictive.store') }}" x-data="machinePicker(@js(old('machine_number', '')), @js(old('wo_date', now()->format('Y-m-d'))), @js(old('due_date')))" @submit="submitted()">
            @csrf
            <input type="hidden" name="_form" value="add">
            <input type="hidden" name="category" value="{{ $category }}">
            <h3 id="predictive-add-title" class="text-lg font-semibold text-slate-900">Tambah WO {{ $categoryLabel }}</h3>

            @if ($errors->any() && old('_form') === 'add')
                <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                    <ul class="list-disc space-y-0.5 pl-4">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-4 space-y-4">
                <div>
                    <label for="pw-order" class="mb-1.5 block text-sm font-medium text-slate-700">Order Number</label>
                    <input id="pw-order" name="order_number" type="text" required maxlength="100" value="{{ old('order_number') }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                </div>

                <div class="relative">
                    <label for="pw-machine" class="mb-1.5 block text-sm font-medium text-slate-700">Machine Number</label>
                    <input id="pw-machine" name="machine_number" type="text" required maxlength="255" autocomplete="off"
                        x-model="q" @input="schedule()" @keydown.escape="results = []"
                        placeholder="Ketik untuk mencari mesin"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    <ul x-show="results.length" x-cloak class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                        <template x-for="m in results" :key="m.machine_number">
                            <li>
                                <button type="button" @click="pick(m)" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-sky-50">
                                    <span class="font-medium text-slate-800" x-text="m.machine_number"></span>
                                    <span class="truncate text-xs text-slate-500" x-text="(m.machine_type || '-') + ' · ' + m.area"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p x-show="loading" x-cloak class="mt-1 text-xs text-slate-500">Mencari mesin…</p>
                    <p x-show="failed" x-cloak class="mt-1 text-xs text-rose-600">Pencarian mesin gagal. Coba lagi.</p>
                    <p x-show="searched && !loading && !failed && !results.length && !picked" x-cloak class="mt-1 text-xs text-amber-700">Mesin tidak ditemukan di Machine Master.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Area</span>
                        <div class="min-h-[42px] rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700" x-text="picked ? picked.area : 'Otomatis'" :class="picked ? '' : 'text-slate-400'"></div>
                    </div>
                    <div>
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Machine Type</span>
                        <div class="min-h-[42px] rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700" x-text="picked ? (picked.machine_type || '-') : 'Otomatis'" :class="picked ? '' : 'text-slate-400'"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="pw-date" class="mb-1.5 block text-sm font-medium text-slate-700">Date</label>
                        <input id="pw-date" name="wo_date" type="date" required x-model="woDate" @change="syncDue()"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    </div>
                    <div>
                        <label for="pw-due" class="mb-1.5 block text-sm font-medium text-slate-700">Due Date</label>
                        <input id="pw-due" name="due_date" type="date" required x-model="due" @input="dueTouched = true"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                        <p class="mt-1 text-xs text-slate-500">Otomatis tanggal WO + 14 hari, bisa diubah.</p>
                    </div>
                </div>

                <div>
                    <label for="pw-problem" class="mb-1.5 block text-sm font-medium text-slate-700">Problem Description</label>
                    <textarea id="pw-problem" name="problem_description" rows="3" required maxlength="2000"
                        class="w-full resize-y rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">{{ old('problem_description') }}</textarea>
                </div>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" @click="addOpen = false" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</button>
                <button type="submit" x-bind:disabled="busy" class="min-h-11 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">Simpan WO</button>
            </div>
        </form>
    </div>
</div>
