<style>[x-cloak]{display:none !important}</style>
<div x-data="lastPmOverride({
        storeUrl: @js(route('machine-qr.overrides.store')),
        lookupUrl: @js(route('machine-qr.machines')),
        historyBase: @js(url('machine-qr/overrides')),
        resetBase: @js(url('machine-qr/overrides')),
        csrf: @js(csrf_token()),
        keep: @js(request()->only('search', 'area', 'status')),
        old: @js(['machine_id' => old('machine_id'), 'override_date' => old('override_date'), 'reason' => old('reason')]),
    })" x-init="init()">

    <div class="mb-4 flex justify-end">
        <button type="button" @click="openForm(null)" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">+ New Override</button>
    </div>

    @php
        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d-m-Y') : '-';
    @endphp

    {{-- Desktop table --}}
    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach (['Machine Number', 'Machine Name', 'Area', 'System Last PM', 'Override Date', 'Status', 'Action'] as $h)
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" data-infinite-list="machines">
                    @forelse ($machines as $machine)
                        @php
                            $ov = $machine->lastPmOverride;
                            $row = [
                                'id' => $machine->id, 'machine_number' => $machine->machine_number, 'machine_name' => $machine->machine_type,
                                'area' => $machine->area, 'system_last_pm' => $machine->system_last_pm,
                                'override_date' => $ov?->override_date?->toDateString(), 'reason' => $ov?->reason,
                            ];
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $machine->machine_number }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $machine->machine_type ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $machine->area }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $fmt($machine->system_last_pm) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $ov ? $ov->override_date->format('d-m-Y') : '-' }}</td>
                            <td class="px-4 py-3">
                                @if ($ov)
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Overridden</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Normal</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @if ($machine->system_last_pm)
                                        <button type="button" @click='openForm(@json($row))' class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">{{ $ov ? 'Edit' : 'Override' }}</button>
                                    @else
                                        <span class="text-xs text-slate-400" title="No System Last PM">No PM yet</span>
                                    @endif
                                    @if ($ov)
                                        <button type="button" @click='askReset(@json($row))' class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Reset</button>
                                    @endif
                                    <button type="button" @click="showHistory({{ $machine->id }}, @js($machine->machine_number))" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100">History</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No machines found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile cards --}}
    <div class="space-y-3 md:hidden" data-infinite-list="machines">
        @foreach ($machines as $machine)
            @php
                $ov = $machine->lastPmOverride;
                $row = [
                    'id' => $machine->id, 'machine_number' => $machine->machine_number, 'machine_name' => $machine->machine_type,
                    'area' => $machine->area, 'system_last_pm' => $machine->system_last_pm,
                    'override_date' => $ov?->override_date?->toDateString(), 'reason' => $ov?->reason,
                ];
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-slate-800">{{ $machine->machine_number }}</div>
                        <div class="truncate text-xs text-slate-500">{{ $machine->machine_type ?? '-' }} · {{ $machine->area }}</div>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $ov ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">{{ $ov ? 'Overridden' : 'Normal' }}</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div><div class="text-slate-400">System Last PM</div><div class="font-medium text-slate-700">{{ $fmt($machine->system_last_pm) }}</div></div>
                    <div><div class="text-slate-400">Override Date</div><div class="font-medium text-slate-700">{{ $ov ? $ov->override_date->format('d-m-Y') : '-' }}</div></div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($machine->system_last_pm)
                        <button type="button" @click='openForm(@json($row))' class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white">{{ $ov ? 'Edit' : 'Override' }}</button>
                    @endif
                    @if ($ov)
                        <button type="button" @click='askReset(@json($row))' class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-600">Reset</button>
                    @endif
                    <button type="button" @click="showHistory({{ $machine->id }}, @js($machine->machine_number))" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700">History</button>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4"><x-infinite-scroll :paginator="$machines" key="machines" /></div>

    {{-- Override form modal --}}
    <div x-show="form.open" x-cloak @keydown.escape.window="form.open = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <form method="POST" :action="storeUrl" @submit="confirmSave($event)" class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
            @csrf
            <template x-for="(v, k) in keep" :key="k"><input type="hidden" :name="k" :value="v"></template>
            <input type="hidden" name="machine_id" :value="form.machine?.id ?? ''">
            <h3 class="mb-4 text-base font-semibold text-slate-800" x-text="form.editing ? 'Edit Last PM Override' : 'New Last PM Override'"></h3>

            <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Machine Number</label>
            <template x-if="form.machine">
                <div class="mb-3 flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <span><span class="font-semibold" x-text="form.machine.machine_number"></span> <span class="text-slate-500" x-text="'· ' + (form.machine.machine_name ?? '') + ' · ' + form.machine.area"></span></span>
                    <button type="button" x-show="!form.editing" @click="form.machine = null" class="text-xs text-blue-600">Change</button>
                </div>
            </template>
            <div x-show="!form.machine" class="mb-3">
                <input type="search" x-model="lookup.q" @input.debounce.300ms="search()" placeholder="Type machine number / name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <ul class="mt-1 max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-white text-sm" x-show="lookup.results.length">
                    <template x-for="m in lookup.results" :key="m.id">
                        <li><button type="button" @click="pick(m)" class="flex w-full justify-between px-3 py-2 text-left hover:bg-slate-50">
                            <span x-text="m.machine_number + ' · ' + (m.machine_name ?? '')"></span>
                            <span class="text-xs" :class="m.system_last_pm ? 'text-slate-400' : 'text-red-500'" x-text="m.system_last_pm ? m.area : 'no PM yet'"></span>
                        </button></li>
                    </template>
                </ul>
            </div>

            <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">System Last PM</label>
            <input type="text" readonly :value="form.machine?.system_last_pm ? fmt(form.machine.system_last_pm) : '-'" class="mb-1 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-600">
            <p x-show="form.machine && !form.machine.system_last_pm" class="mb-2 text-xs text-red-600">This machine has no System Last PM yet, so it cannot be overridden.</p>

            <label class="mb-1 mt-2 block text-xs font-semibold uppercase text-slate-500">Override Date</label>
            <input type="date" name="override_date" x-model="form.override_date" required class="mb-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">

            <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Reason</label>
            <textarea name="reason" x-model="form.reason" required maxlength="500" rows="3" class="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>

            <p class="mb-4 text-xs text-slate-500">Only the Last PM shown in Machine History changes. PM transactions, Next PM and reports are not affected.</p>

            <div class="flex justify-end gap-2">
                <button type="button" @click="form.open = false" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Cancel</button>
                <button :disabled="!form.machine || !form.machine.system_last_pm" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">Save</button>
            </div>
        </form>
    </div>

    {{-- Reset confirm --}}
    <div x-show="resetting" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <form method="POST" :action="resetBase + '/' + (resetting?.id ?? '')" class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
            @csrf @method('DELETE')
            <template x-for="(v, k) in keep" :key="k"><input type="hidden" :name="k" :value="v"></template>
            <h3 class="mb-2 text-base font-semibold text-slate-800">Reset override?</h3>
            <p class="mb-4 text-sm text-slate-600">Machine History for <span class="font-semibold" x-text="resetting?.machine_number"></span> will show the System Last PM again. The change is kept in the history log.</p>
            <div class="flex justify-end gap-2">
                <button type="button" @click="resetting = null" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Cancel</button>
                <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Reset</button>
            </div>
        </form>
    </div>

    {{-- History modal --}}
    <div x-show="history.open" x-cloak @keydown.escape.window="history.open = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-800">Override History · <span x-text="history.machine"></span></h3>
                <button type="button" @click="history.open = false" class="text-slate-400 hover:text-slate-600" aria-label="Close">✕</button>
            </div>
            <p x-show="history.loading" class="text-sm text-slate-500">Loading…</p>
            <p x-show="!history.loading && history.logs.length === 0" class="text-sm text-slate-500">No changes recorded.</p>
            <ul class="space-y-2">
                <template x-for="l in history.logs" :key="l.at + l.action">
                    <li class="rounded-lg border border-slate-200 p-3 text-sm">
                        <div class="flex justify-between">
                            <span class="font-semibold" :class="l.action === 'RESET' ? 'text-red-600' : 'text-blue-700'" x-text="l.action"></span>
                            <span class="text-xs text-slate-500" x-text="l.at + ' · ' + l.by"></span>
                        </div>
                        <div class="mt-1 text-slate-700" x-text="(l.previous_date ?? '-') + '  →  ' + (l.new_date ?? '-')"></div>
                        <div class="mt-1 text-xs text-slate-500" x-show="l.new_reason" x-text="'Reason: ' + l.new_reason"></div>
                        <div class="mt-1 text-xs text-slate-400" x-show="l.previous_reason" x-text="'Previous reason: ' + l.previous_reason"></div>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>

<script>
    function lastPmOverride(cfg) {
        return {
            storeUrl: cfg.storeUrl, resetBase: cfg.resetBase, keep: cfg.keep,
            form: { open: false, editing: false, machine: null, override_date: '', reason: '' },
            lookup: { q: '', results: [] }, resetting: null,
            history: { open: false, loading: false, machine: '', logs: [] },
            init() {
                // re-open the form with the user's input when the server rejected it
                if (cfg.old.machine_id) {
                    this.form = { open: true, editing: false, machine: null, override_date: cfg.old.override_date || '', reason: cfg.old.reason || '' };
                }
            },
            fmt(d) { const [y, m, day] = d.split('-'); return `${day}-${m}-${y}`; },
            openForm(row) {
                this.lookup = { q: '', results: [] };
                this.form = row
                    ? { open: true, editing: !!row.override_date, machine: row, override_date: row.override_date || '', reason: row.reason || '' }
                    : { open: true, editing: false, machine: null, override_date: '', reason: '' };
            },
            async search() {
                if (this.lookup.q.trim().length < 1) { this.lookup.results = []; return; }
                const r = await fetch(cfg.lookupUrl + '?search=' + encodeURIComponent(this.lookup.q), { headers: { Accept: 'application/json' } });
                this.lookup.results = await r.json();
            },
            pick(m) { this.form.machine = m; this.form.editing = false; this.lookup.results = []; },
            confirmSave(e) {
                const m = this.form.machine;
                if (!m || !confirm(`Save Last PM override for ${m.machine_number} → ${this.fmt(this.form.override_date)}?`)) e.preventDefault();
            },
            askReset(row) { this.resetting = row; },
            async showHistory(id, number) {
                this.history = { open: true, loading: true, machine: number, logs: [] };
                try {
                    const r = await fetch(cfg.historyBase + '/' + id + '/history', { headers: { Accept: 'application/json' } });
                    this.history.logs = (await r.json()).logs;
                } catch (e) { this.history.logs = []; }
                this.history.loading = false;
            },
        };
    }
</script>
