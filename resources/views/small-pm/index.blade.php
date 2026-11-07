@extends('layouts.app')

@section('title', 'Small PM')

@section('content')
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Small PM</h1>
            <p class="text-sm text-slate-500">Jadwal PM per row mesin. Row diatur di Master Data, Row Small PM.</p>
        </div>

        @if ($canManage && $available)
        <div class="flex flex-col gap-3 sm:flex-row">
            <form action="{{ route('small-pm.import') }}" method="POST" enctype="multipart/form-data"
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm sm:flex-row sm:items-center">
                @csrf
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="flex items-center gap-2">
                    <input type="file" id="smallPmFileInput" name="file" accept=".csv,.xlsx,.xls" class="sr-only"
                        onchange="document.getElementById('smallPmFileName').textContent = this.files.length ? this.files[0].name : 'Belum ada file'">
                    <label for="smallPmFileInput" class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Pilih File</label>
                    <span id="smallPmFileName" class="max-w-45 truncate text-sm text-slate-500">Belum ada file</span>
                </div>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">Import</button>
            </form>

            <a href="{{ route('small-pm.create', ['tab' => $tab]) }}" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                Add Schedule
            </a>
        </div>
        @endif
    </div>

    <nav class="mb-5 flex overflow-x-auto overflow-y-hidden border-b border-slate-200 pb-px sm:gap-1" aria-label="Jenis Small PM">
        @foreach ($tabs as $key => $t)
            @php($active = $tab === $key)
            <a href="{{ route('small-pm.index', ['tab' => $key]) }}" @if ($active) aria-current="page" @endif
                class="-mb-px inline-flex min-h-11 flex-1 items-center justify-center gap-2 whitespace-nowrap border-b-2 px-2 py-2 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 sm:flex-none sm:px-3 {{ $active ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                {{ $t['label'] }}
                @unless ($t['available'])
                    <span class="text-xs font-medium text-slate-500">(belum tersedia)</span>
                @endunless
            </a>
        @endforeach
    </nav>

    @foreach ([
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'error' => 'border-red-200 bg-red-50 text-red-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
    ] as $flash => $classes)
        @if (session($flash))
            <div class="mb-4 rounded-xl border px-4 py-3 text-sm {{ $classes }}" role="{{ $flash === 'error' ? 'alert' : 'status' }}">{{ session($flash) }}</div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
    @endif

    @if (session('small_pm_import_result'))
        @php($importResult = session('small_pm_import_result'))
        <div class="mb-4 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" role="status">
            <span>Hasil import:</span>
            <span class="font-semibold text-emerald-700">{{ $importResult['imported'] }} masuk</span>
            <span class="font-semibold text-amber-700">{{ $importResult['duplicate'] }} duplikat</span>
            <span class="font-semibold text-rose-700">{{ $importResult['skipped'] }} dilewati/tidak valid</span>
        </div>
    @endif

    @if (! $available)
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center">
            <p class="text-sm font-semibold text-slate-700">{{ $tabs[$tab]['label'] }} belum tersedia.</p>
            <p class="mt-1 text-sm text-slate-500">Jadwalnya akan diatur di tab ini setelah Cleaning Kisi-Kisi Motor berjalan.</p>
        </div>
    @else
        @if ($canManage && $unscheduledCount > 0)
            <form method="POST" action="{{ route('small-pm.generate') }}" class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4"
                x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <input type="hidden" name="tab" value="{{ $tab }}">
                <p class="text-sm font-semibold text-amber-900">{{ $unscheduledCount }} row belum punya jadwal OPEN di tab ini.</p>
                <p class="mt-1 text-sm text-amber-900">
                    Generate menjadwalkan {{ $rowsPerWeek }} row per minggu sesuai urutan row ({{ (int) ceil($unscheduledCount / $rowsPerWeek) }} minggu).
                    Plan date diisi hari Senin.
                </p>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div>
                        <label for="start-week" class="mb-1 block text-xs font-medium text-amber-900">Mulai minggu</label>
                        <input id="start-week" type="date" name="start_week" required value="{{ old('start_week', $thisMonday->toDateString()) }}"
                            class="min-h-11 w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none sm:w-44">
                    </div>
                    <div>
                        <label for="gen-cycle" class="mb-1 block text-xs font-medium text-amber-900">Cycle</label>
                        <input id="gen-cycle" type="text" name="cycle" required value="{{ old('cycle', $defaultCycle) }}" pattern="\d+[Ww]"
                            class="min-h-11 w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none sm:w-24">
                    </div>
                    <button type="submit" x-bind:disabled="busy"
                        class="min-h-11 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50">
                        Generate {{ $unscheduledCount }} row
                    </button>
                </div>
            </form>
        @endif

        <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari cycle, PIC, atau order number..." aria-label="Cari jadwal"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-64">

            <select name="row_id" aria-label="Row" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <option value="">Semua Row</option>
                @foreach ($rows as $r)
                    <option value="{{ $r->id }}" @selected(request('row_id') == $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>

            <x-checkbox-dropdown name="status" label="Status"
                :options="['OPEN' => 'OPEN', 'FINISH' => 'FINISH', 'FINISH ON TIME' => 'FINISH ON TIME']"
                :selected="(array) request('status', [])" />

            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">Filter</button>
            <a href="{{ route('small-pm.index', ['tab' => $tab]) }}" class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">Reset</a>
        </form>

        {{-- ============ DESKTOP: Table (md and up) ============ --}}
        <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach (['Order Number', 'Row', 'Mesin', 'Cycle', 'Plan Date', 'Due Date', 'PIC', 'Status', 'Action'] as $h)
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white" data-infinite-list="small-pm">
                    @forelse ($schedules as $s)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $s->order_number ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $s->row->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                @if ($s->row)
                                    <a href="{{ route('small-pm-rows.show', $s->row) }}" class="text-blue-700 underline-offset-2 hover:underline">Lihat mesin</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $s->cycle }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $s->plan_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $s->due_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">@include('small-pm._pic', ['s' => $s, 'suffix' => 'd'])</td>
                            <td class="px-4 py-3">@include('small-pm._status', ['status' => $s->status])</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">@include('small-pm._actions', ['s' => $s, 'mobile' => false])</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada jadwal Small PM</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        {{-- ============ MOBILE: Card List (below md) ============ --}}
        <div class="space-y-3 md:hidden" data-infinite-list="small-pm">
            @forelse ($schedules as $s)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-800">{{ $s->row->name ?? '-' }}</div>
                            <div class="text-xs text-slate-500">{{ $s->cycle }} · {{ $s->order_number ?? '-' }}</div>
                        </div>
                        @include('small-pm._status', ['status' => $s->status])
                    </div>
                    <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                        <div>
                            <div class="text-slate-500">Plan Date</div>
                            <div class="font-medium text-slate-700">{{ $s->plan_date->format('d M Y') }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500">Due Date</div>
                            <div class="font-medium text-slate-700">{{ $s->due_date->format('d M Y') }}</div>
                        </div>
                        <div class="col-span-2">
                            <div class="text-slate-500">PIC</div>
                            <div class="font-medium text-slate-700">@include('small-pm._pic', ['s' => $s, 'suffix' => 'm'])</div>
                        </div>
                        @if ($s->row)
                            <div class="col-span-2">
                                <a href="{{ route('small-pm-rows.show', $s->row) }}" class="inline-flex min-h-11 items-center text-blue-700 underline-offset-2 hover:underline">Lihat mesin di row ini</a>
                            </div>
                        @endif
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">@include('small-pm._actions', ['s' => $s, 'mobile' => true])</div>
                </div>
            @empty
                <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">Belum ada jadwal Small PM</p>
            @endforelse
        </div>

        <div class="mt-4">
            <x-infinite-scroll :paginator="$schedules" key="small-pm" />
        </div>

        {{-- ============ START MODAL (same flow as Greasing) ============ --}}
        <div id="small-pm-start-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-labelledby="small-pm-start-title">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 id="small-pm-start-title" class="text-lg font-semibold text-slate-800">Start Small PM</h3>
                <p id="small-pm-start-row" class="mt-1 text-sm text-slate-500"></p>
                <form id="small-pm-start-form" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="small-pm-start-input" class="mb-1 block text-sm font-medium text-slate-700">Tanggal &amp; jam mulai</label>
                        <input type="datetime-local" name="started_at" id="small-pm-start-input" required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="small-pm-start-cancel" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">BATAL</button>
                        <button type="submit" class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">START</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            (function () {
                const modal = document.getElementById('small-pm-start-modal');
                const form = document.getElementById('small-pm-start-form');
                const input = document.getElementById('small-pm-start-input');
                const rowLabel = document.getElementById('small-pm-start-row');

                function nowLocal() {
                    const d = new Date();
                    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                    return d.toISOString().slice(0, 16);
                }

                function closeModal() {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }

                // delegated: rows are appended later by infinite scroll
                document.addEventListener('click', function (e) {
                    const btn = e.target.closest('.small-pm-start-btn');
                    if (!btn) return;
                    form.action = btn.dataset.url;
                    input.value = nowLocal();
                    rowLabel.textContent = btn.dataset.row ? 'Row: ' + btn.dataset.row : '';
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    input.focus();
                });

                document.getElementById('small-pm-start-cancel').addEventListener('click', closeModal);
                modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

                document.addEventListener('change', function (e) {
                    const select = e.target.closest('.assign-small-pm-pic');
                    if (!select) return;
                    select.disabled = true;
                    fetch(select.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ pic_user_id: select.value }),
                    }).then(function (res) {
                        if (!res.ok) throw new Error();
                    }).catch(function () {
                        alert('PIC gagal disimpan. Muat ulang halaman lalu coba lagi.');
                    }).finally(function () {
                        select.disabled = false;
                    });
                });
            })();
        </script>
    @endif

    @include('partials.activity-conflict-modal')
@endsection
