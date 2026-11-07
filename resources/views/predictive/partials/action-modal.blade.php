{{--
    Action / Closing form, shared by the list and the detail page. The page
    root must own `action` (null or {id, order, machine, url, next}).
    Same shape as the Oil Audit follow-up: one or more Problems, each with one
    or more Findings, plus optional Remarks. Problem and Finding are picked
    from the master (never typed): $actionOptions is the Problem > Finding tree
    for the WO's category and area, $actionContext names that category and area.
--}}
@php
    $actionOptions = $actionOptions ?? [];
    $actionContext = $actionContext ?? ['category' => '', 'area' => ''];
    $oldForm = old('_action_wo')
        ? [
            'wo' => (int) old('_action_wo'),
            'remarks' => old('remarks', ''),
            'problems' => collect((array) old('problems', []))->map(fn ($p) => [
                'problem_id' => (string) ($p['problem_id'] ?? ''),
                'findings' => collect((array) ($p['findings'] ?? []))->map(fn ($f) => (string) ($f['finding_id'] ?? ''))->values()->all(),
            ])->values()->all(),
        ]
        : null;
@endphp

<div x-show="action" x-cloak x-transition.opacity.duration.150ms
    @keydown.escape.window="action = null"
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="predictive-action-title">
    <div @click.outside="action = null" class="max-h-dvh w-full max-w-xl overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6">
        <template x-if="action">
            <form method="POST" :action="action.url" x-data="actionForm(@js($oldForm), action.id, @js($actionOptions))" @submit="busy = true">
                @csrf
                <input type="hidden" name="_action_wo" :value="action.id">
                <h3 id="predictive-action-title" class="text-lg font-semibold text-slate-900">
                    Action #<span x-text="action.next"></span> · <span x-text="action.order"></span>
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Mesin <span class="font-medium text-slate-700" x-text="action.machine"></span>.
                    Setelah disimpan, WO berstatus CLOSED dan claim Anda dilepas.
                </p>

                @if ($errors->any() && old('_action_wo'))
                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                        <ul class="list-disc space-y-0.5 pl-4">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <template x-if="tree.length === 0">
                    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
                        Master Problem {{ $actionContext['category'] }} area {{ $actionContext['area'] }} belum diisi.
                        Minta Admin atau Koordinator mengisinya di menu Predictive Problem, lalu coba lagi.
                    </p>
                </template>
                <p x-show="tree.length > 0" class="mt-4 text-xs text-slate-500">
                    Satu Action bisa punya beberapa Problem, dan tiap Problem bisa punya beberapa Finding.
                    Problem tidak boleh sama, begitu juga Finding dalam satu Problem.
                </p>

                <div class="mt-3 space-y-3">
                    <template x-for="(p, pi) in problems" :key="p.key">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-end gap-2">
                                <div class="min-w-0 flex-1">
                                    <label :for="'pa-problem-' + p.key" class="mb-1 block text-sm font-medium text-slate-700">Problem #<span x-text="pi + 1"></span></label>
                                    <select :id="'pa-problem-' + p.key" :name="`problems[${pi}][problem_id]`" x-model="p.problemId" @change="problemChanged(p)" required
                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                                        <option value="">Pilih problem</option>
                                        <template x-for="o in tree" :key="o.id">
                                            <option :value="String(o.id)" x-text="o.name" :disabled="taken(p, o.id)"></option>
                                        </template>
                                    </select>
                                </div>
                                <button type="button" x-show="problems.length > 1" @click="removeProblem(pi)" aria-label="Hapus Problem"
                                    class="min-h-11 rounded-lg border border-red-200 bg-white px-3 text-xs font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                            </div>

                            <div class="mt-3 space-y-2 border-l-2 border-slate-200 pl-3">
                                <template x-for="(f, fi) in p.findings" :key="f.key">
                                    <div class="flex items-end gap-2">
                                        <div class="min-w-0 flex-1">
                                            <label :for="'pa-finding-' + f.key" class="mb-1 block text-xs font-medium text-slate-600">Finding #<span x-text="fi + 1"></span></label>
                                            <select :id="'pa-finding-' + f.key" :name="`problems[${pi}][findings][${fi}][finding_id]`" x-model="f.value" required :disabled="!p.problemId"
                                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-50 disabled:text-slate-400">
                                                <option value="" x-text="p.problemId ? 'Pilih finding' : 'Pilih problem dulu'"></option>
                                                <template x-for="o in findingsOf(p)" :key="o.id">
                                                    <option :value="String(o.id)" x-text="o.name" :disabled="p.findings.some(x => x !== f && x.value === String(o.id))"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <button type="button" x-show="p.findings.length > 1" @click="removeFinding(pi, fi)" aria-label="Hapus Finding"
                                            class="min-h-11 rounded-lg border border-red-200 bg-white px-3 text-xs font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                                    </div>
                                </template>
                                <button type="button" x-show="p.findings.length < 20" @click="addFinding(pi)"
                                    class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">+ Add Finding</button>
                            </div>
                        </div>
                    </template>
                    <button type="button" x-show="problems.length < 20" @click="addProblem()"
                        class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">+ Add Problem</button>
                </div>

                <div class="mt-4">
                    <label for="pa-remarks" class="mb-1.5 block text-sm font-medium text-slate-700">Remarks <span class="font-normal text-slate-400">(opsional)</span></label>
                    <textarea id="pa-remarks" name="remarks" rows="3" maxlength="2000" x-model="remarks"
                        class="w-full resize-y rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"></textarea>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="action = null" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</button>
                    <button type="submit" class="min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-50" x-bind:disabled="busy || tree.length === 0">Simpan &amp; Close WO</button>
                </div>
            </form>
        </template>
    </div>
</div>

<script>
    // Dependent Problem > Finding dropdowns. `tree` is [{id, name, findings:[{id, name}]}]
    // for the WO's category and area. At least one Problem and one Finding per
    // Problem always remain, and names are re-indexed by x-for on every change
    // so the server always receives a dense problems[i][findings][j] tree.
    window.actionForm = function (old, woId, tree) {
        let n = 0;
        const finding = (value = '') => ({ key: ++n, value });
        const problem = (problemId = '', findings = ['']) => ({ key: ++n, problemId, findings: findings.map(finding) });
        const restore = old && old.wo === woId && old.problems.length;
        return {
            tree,
            busy: false,
            remarks: restore ? old.remarks : '',
            problems: restore ? old.problems.map(p => problem(p.problem_id, p.findings.length ? p.findings : [''])) : [problem()],
            findingsOf(p) { const o = this.tree.find(x => String(x.id) === String(p.problemId)); return o ? o.findings : []; },
            taken(p, id) { return this.problems.some(x => x !== p && String(x.problemId) === String(id)); },
            problemChanged(p) { p.findings = [finding()]; },
            addProblem() { this.problems.push(problem()); },
            removeProblem(i) { if (this.problems.length > 1) this.problems.splice(i, 1); },
            addFinding(i) { this.problems[i].findings.push(finding()); },
            removeFinding(i, j) { if (this.problems[i].findings.length > 1) this.problems[i].findings.splice(j, 1); },
        };
    };
</script>
