{{-- Tab "Temuan Blower Mati": every dead-blower finding from Ganti Filter Panel, OPEN first. --}}
@php($canReopen = auth()->user()->isAdmin() || auth()->user()->isKoordinator())

<form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor mesin..." aria-label="Cari nomor mesin"
        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-56">

    <select name="row_id" aria-label="Row" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
        <option value="">Semua Row</option>
        @foreach ($rows as $r)
            <option value="{{ $r->id }}" @selected(request('row_id') == $r->id)>{{ $r->name }}</option>
        @endforeach
    </select>

    <x-checkbox-dropdown name="status" label="Status"
        :options="['OPEN' => 'OPEN', 'COMPLETED' => 'COMPLETED']"
        :selected="(array) request('status', [])" />

    <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">Filter</button>
    <a href="{{ route('small-pm.index', ['tab' => $tab]) }}" class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">Reset</a>
</form>

<p class="mb-3 text-sm text-slate-500">Temuan dicatat dengan mencentang mesin di halaman Execute jadwal Ganti Filter Panel. Isi tindakan di sini setelah blower ditangani.</p>

<div class="space-y-3" data-infinite-list="small-pm-findings">
    @forelse ($findings as $finding)
        @php($schedule = $finding->schedule)
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <dl class="grid min-w-0 flex-1 grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Mesin</dt>
                        <dd class="font-semibold text-slate-800">{{ $finding->machine->machine_number ?? '-' }}</dd>
                        <dd class="text-xs text-slate-500">{{ $finding->machine->machine_type ?? '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Row</dt>
                        <dd class="text-slate-800">{{ $schedule->row->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Ditemukan</dt>
                        <dd class="text-slate-800">{{ $schedule->action_date?->format('d M Y') ?? $finding->created_at->format('d M Y') }}</dd>
                        @unless (auth()->user()->isSupervisor())
                            <dd><a href="{{ route('small-pm.execute', $schedule) }}" class="text-xs text-blue-700 underline-offset-2 hover:underline">Buka jadwal</a></dd>
                        @endunless
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Tindakan</dt>
                        <dd class="break-words text-slate-800">{{ $finding->action ?? '-' }}</dd>
                        @if ($finding->action_date)
                            <dd class="text-xs text-slate-500">{{ $finding->action_date->format('d M Y') }}</dd>
                        @endif
                    </div>
                </dl>
                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $finding->status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $finding->status }}</span>
            </div>

            @if ($finding->status === 'OPEN' && ! auth()->user()->isSupervisor())
                <form action="{{ route('small-pm.findings.update', [$schedule, $finding]) }}" method="POST" class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3 sm:flex-row sm:items-end">
                    @csrf
                    @method('PATCH')
                    <div class="flex-1">
                        <label for="fa-{{ $finding->id }}" class="mb-1 block text-xs font-medium text-slate-600">Tindakan</label>
                        <input id="fa-{{ $finding->id }}" type="text" name="action" required maxlength="2000" placeholder="Misalnya blower diganti baru"
                            class="min-h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="fd-{{ $finding->id }}" class="mb-1 block text-xs font-medium text-slate-600">Tanggal</label>
                        <input id="fd-{{ $finding->id }}" type="date" name="action_date" value="{{ now()->toDateString() }}"
                            class="min-h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none sm:w-44">
                    </div>
                    <button class="min-h-11 rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">Tutup temuan</button>
                </form>
            @elseif ($finding->status === 'COMPLETED' && $canReopen)
                <form action="{{ route('small-pm.findings.update', [$schedule, $finding]) }}" method="POST" class="mt-3 border-t border-slate-100 pt-3">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="OPEN">
                    <button class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Buka lagi</button>
                </form>
            @endif
        </div>
    @empty
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
            @if (request()->hasAny(['search', 'row_id', 'status']))
                Tidak ada temuan yang sesuai filter.
            @else
                Belum ada temuan blower filter mati.
            @endif
        </p>
    @endforelse
</div>

<div class="mt-4">
    <x-infinite-scroll :paginator="$findings" key="small-pm-findings" />
</div>
