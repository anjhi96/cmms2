{{--
    Edit WO. The page root owns `edit` (null or the WO payload built in
    wo-buttons). Machine Number can only change while the WO is unclaimed and
    has no history; Area and Machine Type then follow the new machine.
--}}
<div x-show="edit" x-cloak x-transition.opacity.duration.150ms
    @keydown.escape.window="edit = null"
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="predictive-edit-title">
    <div @click.outside="edit = null" class="max-h-dvh w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6">
        <template x-if="edit">
            <form method="POST" :action="edit.url" x-data="machinePicker(edit.machine_number, edit.wo_date, edit.due_date)" @submit="submitted()">
                @csrf
                @method('PUT')
                <input type="hidden" name="_edit_wo" :value="edit.id">
                <h3 id="predictive-edit-title" class="text-lg font-semibold text-slate-900">Edit WO <span x-text="edit.order"></span></h3>

                @if ($errors->any() && old('_edit_wo'))
                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                        <ul class="list-disc space-y-0.5 pl-4">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="pe-order" class="mb-1.5 block text-sm font-medium text-slate-700">Order Number</label>
                        <input id="pe-order" name="order_number" type="text" required maxlength="100" :value="edit.order_input"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    </div>

                    <div class="relative">
                        <label for="pe-machine" class="mb-1.5 block text-sm font-medium text-slate-700">Machine Number</label>
                        <template x-if="edit.locked">
                            <div>
                                <input id="pe-machine" type="text" :value="edit.machine_number" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-500">
                                <input type="hidden" name="machine_number" :value="edit.machine_number">
                                <p class="mt-1 text-xs text-slate-500">Terkunci karena WO sudah di-claim atau punya riwayat Action.</p>
                            </div>
                        </template>
                        <template x-if="!edit.locked">
                            <div>
                                <input id="pe-machine" name="machine_number" type="text" required maxlength="255" autocomplete="off"
                                    x-model="q" @input="schedule()" @keydown.escape="results = []"
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
                                <p class="mt-1 text-xs text-slate-500">Area dan Machine Type mengikuti Machine Master.</p>
                            </div>
                        </template>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="pe-date" class="mb-1.5 block text-sm font-medium text-slate-700">Date</label>
                            <input id="pe-date" name="wo_date" type="date" required x-model="woDate" @change="syncDue()"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                        </div>
                        <div>
                            <label for="pe-due" class="mb-1.5 block text-sm font-medium text-slate-700">Due Date</label>
                            <input id="pe-due" name="due_date" type="date" required x-model="due" @input="dueTouched = true"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                        </div>
                    </div>

                    <div>
                        <label for="pe-problem" class="mb-1.5 block text-sm font-medium text-slate-700">Problem Description</label>
                        <textarea id="pe-problem" name="problem_description" rows="3" required maxlength="2000" x-init="$el.value = edit.problem"
                            class="w-full resize-y rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"></textarea>
                    </div>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="edit = null" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</button>
                    <button type="submit" x-bind:disabled="busy" class="min-h-11 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">Simpan perubahan</button>
                </div>
            </form>
        </template>
    </div>
</div>
