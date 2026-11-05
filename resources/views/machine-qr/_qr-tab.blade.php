{{--
    Selection lives in Alpine (persisted in sessionStorage) as a set of machine
    ids, independent of the current page / search / area filter, so it is never
    lost when the list is re-filtered. "Select all" asks the server for every
    id matching the current filter (area-scoped), not just the visible page.
--}}
@php($input = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500')
<div x-data="machineQr(@js([
        'idsUrl' => route('machine-qr.ids', request()->only('search', 'area')),
        'countUrl' => route('machine-qr.count'),
        'total' => $machines->total(),
    ]))" x-init="init()">

    <div class="mb-4 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:flex-row lg:items-end lg:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="selectAllFiltered()" :disabled="busy"
                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50">
                Select all filtered ({{ $machines->total() }})
            </button>
            <button type="button" @click="clear()" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Clear</button>
            <span class="text-sm text-slate-600"><span class="font-semibold" x-text="selected.length"></span> selected</span>
            <span x-show="busy" class="text-xs text-slate-500">Loading…</span>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <form method="POST" action="{{ route('machine-qr.export') }}" @submit="confirmSelected($event)">
                @csrf
                <input type="hidden" name="mode" value="selected">
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button :disabled="selected.length === 0" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Export Selected to Word
                </button>
            </form>

            <form method="POST" action="{{ route('machine-qr.export') }}" class="flex gap-2" @submit="confirmArea($event)">
                @csrf
                <input type="hidden" name="mode" value="area">
                <select name="area" x-model="exportArea" @change="loadAreaCount()" required class="{{ $input }}">
                    <option value="">Export by Area…</option>
                    @foreach ($areas as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>
                <button :disabled="!exportArea || areaCount === 0" class="whitespace-nowrap rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Export Area<span x-show="exportArea && areaCount !== null" x-text="' (' + areaCount + ')'"></span>
                </button>
            </form>
        </div>
    </div>

    {{-- Desktop table --}}
    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="w-10 px-4 py-3"><input type="checkbox" :checked="allLoadedSelected" @change="togglePage($event.target.checked)" class="rounded border-slate-300" aria-label="Select all loaded"></th>
                    @foreach (['Machine Number', 'Machine Name', 'Area', 'QR Code', 'Action'] as $h)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100" data-infinite-list="machines">
                @forelse ($machines as $machine)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3"><input type="checkbox" value="{{ $machine->id }}" :checked="has({{ $machine->id }})" @change="toggle({{ $machine->id }}, $event.target.checked)" class="rounded border-slate-300" aria-label="Select {{ $machine->machine_number }}"></td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $machine->machine_number }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $machine->machine_type ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $machine->area }}</td>
                        <td class="px-4 py-3">
                            <button type="button" @click="preview = {{ $machine->id }}; previewName = @js($machine->machine_number)" class="block">
                                <img src="{{ route('machine-qr.preview', $machine) }}" loading="lazy" alt="QR {{ $machine->machine_number }}" class="h-16 w-16 rounded border border-slate-200 bg-white">
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <button type="button" @click="preview = {{ $machine->id }}; previewName = @js($machine->machine_number)" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100">Preview</button>
                                <a href="{{ route('machine-qr.download', $machine) }}" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">Download</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No machines found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile cards --}}
    <div class="space-y-3 md:hidden" data-infinite-list="machines">
        @forelse ($machines as $machine)
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <input type="checkbox" :checked="has({{ $machine->id }})" @change="toggle({{ $machine->id }}, $event.target.checked)" class="h-5 w-5 rounded border-slate-300" aria-label="Select {{ $machine->machine_number }}">
                <button type="button" @click="preview = {{ $machine->id }}; previewName = @js($machine->machine_number)">
                    <img src="{{ route('machine-qr.preview', $machine) }}" loading="lazy" alt="QR {{ $machine->machine_number }}" class="h-16 w-16 rounded border border-slate-200 bg-white">
                </button>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold text-slate-800">{{ $machine->machine_number }}</div>
                    <div class="truncate text-xs text-slate-500">{{ $machine->machine_type ?? '-' }} · {{ $machine->area }}</div>
                    <a href="{{ route('machine-qr.download', $machine) }}" class="mt-1 inline-block text-xs font-medium text-blue-600">Download PNG</a>
                </div>
            </div>
        @empty
            <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">No machines found.</p>
        @endforelse
    </div>

    <div class="mt-4"><x-infinite-scroll :paginator="$machines" key="machines" /></div>

    {{-- Preview modal --}}
    <div x-show="preview" x-cloak @keydown.escape.window="preview = null" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div @click.outside="preview = null" class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-800" x-text="previewName"></h3>
                <button type="button" @click="preview = null" class="text-slate-400 hover:text-slate-600" aria-label="Close">✕</button>
            </div>
            <img :src="preview ? '{{ url('machine-qr') }}/' + preview + '/preview' : ''" alt="QR preview" class="mx-auto h-64 w-64 border border-slate-200 bg-white p-4">
            <p class="mt-2 text-center text-xs text-slate-500">Prints at exactly 2 × 2 cm. Keep a white margin of at least 2 mm around it.</p>
            <a :href="preview ? '{{ url('machine-qr') }}/' + preview + '/download' : '#'" class="mt-3 block rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-blue-700">Download PNG (2 × 2 cm)</a>
        </div>
    </div>
</div>

<script>
    function machineQr(cfg) {
        const KEY = 'machine-qr-selected';
        return {
            selected: [], preview: null, previewName: '', busy: false,
            exportArea: '', areaCount: null,
            init() {
                try { this.selected = JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) { this.selected = []; }
            },
            save() { try { sessionStorage.setItem(KEY, JSON.stringify(this.selected)); } catch (e) {} },
            has(id) { return this.selected.includes(id); },
            toggle(id, on) {
                this.selected = on ? [...new Set([...this.selected, id])] : this.selected.filter(i => i !== id);
                this.save();
            },
            // rows are loaded progressively (infinite scroll), so "page" = every row loaded so far
            allLoadedSelected: false,
            loadedIds() { return Array.from(document.querySelectorAll('table input[type=checkbox][value]')).map(c => Number(c.value)); },
            togglePage(on) {
                const ids = this.loadedIds();
                this.allLoadedSelected = on;
                this.selected = on
                    ? [...new Set([...this.selected, ...ids])]
                    : this.selected.filter(i => !ids.includes(i));
                this.save();
                this.$nextTick(() => document.querySelectorAll('input[type=checkbox][value]').forEach(c => c.checked = this.has(Number(c.value))));
            },
            clear() { this.selected = []; this.allLoadedSelected = false; this.save(); document.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false); },
            async selectAllFiltered() {
                this.busy = true;
                try {
                    const r = await fetch(cfg.idsUrl, { headers: { Accept: 'application/json' } });
                    const d = await r.json();
                    this.selected = [...new Set([...this.selected, ...d.ids])];
                    this.save();
                    document.querySelectorAll('input[type=checkbox][value]').forEach(c => c.checked = this.has(Number(c.value)));
                } catch (e) { alert('Could not load the filtered machines. Please try again.'); }
                this.busy = false;
            },
            async loadAreaCount() {
                this.areaCount = null;
                if (!this.exportArea) return;
                const r = await fetch(cfg.countUrl + '?mode=area&area=' + encodeURIComponent(this.exportArea), { headers: { Accept: 'application/json' } });
                this.areaCount = (await r.json()).count;
            },
            confirmSelected(e) {
                if (!confirm('Export ' + this.selected.length + ' machine QR label(s) to Word?')) e.preventDefault();
            },
            confirmArea(e) {
                if (!confirm('Export ' + this.areaCount + ' machine QR label(s) from area ' + this.exportArea + ' to Word?')) e.preventDefault();
            },
        };
    }
</script>
