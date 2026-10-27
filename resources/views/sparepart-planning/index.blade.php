@extends('layouts.app')

@section('content')
    @php
        $monthNames = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(null, $m, 1)->translatedFormat('F')]);
        $tabClass = fn ($active) => 'rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition '
            . ($active ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700');
    @endphp

    <div class="mb-4">
        <h1 class="text-2xl font-semibold text-slate-800">Sparepart Planning</h1>
        <p class="text-sm text-slate-500">
            Mandatory sparepart per machine type and the order estimation for upcoming PM.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200">
        <a href="{{ route('sparepart-planning.index', ['tab' => 'mandatory']) }}" class="{{ $tabClass($tab === 'mandatory') }}">Mandatory Sparepart</a>
        <a href="{{ route('sparepart-planning.index', ['tab' => 'estimate']) }}" class="{{ $tabClass($tab === 'estimate') }}">Sparepart Order Estimation</a>
    </div>

    @if ($tab === 'mandatory')
        @if ($canManage)
            <form action="{{ route('sparepart-planning.store') }}" method="POST"
                class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-12">
                @csrf
                <div class="md:col-span-3">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Machine Type</label>
                    <select name="machine_type" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                        <option value="">-- Select --</option>
                        @foreach ($machineTypes as $type)
                            <option value="{{ $type }}" @selected(old('machine_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('machine_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-6">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Sparepart</label>
                    <select name="sparepart_id" id="mandatory-sparepart" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                        <option value="">-- Select Sparepart --</option>
                        @foreach ($spareparts as $sp)
                            <option value="{{ $sp->id }}" @selected((int) old('sparepart_id') === $sp->id)>{{ $sp->material_number }} - {{ $sp->description }}</option>
                        @endforeach
                    </select>
                    @error('sparepart_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-1">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Qty</label>
                    <input type="number" name="qty" min="1" step="1" required value="{{ old('qty', 1) }}"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                    @error('qty') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end md:col-span-2">
                    <button class="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Add</button>
                </div>
            </form>
        @else
            <p class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Read-only view.</p>
        @endif

        @forelse ($mandatories as $type => $items)
            <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">Machine Type: {{ $type }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Material Number</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Qty</th>
                                @if ($canManage)
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($items as $item)
                                <tr class="hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-800">{{ $item->sparepart->material_number }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $item->sparepart->description }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-slate-800">{{ $item->qty }} {{ $item->sparepart->unit }}</td>
                                    @if ($canManage)
                                        <td class="px-4 py-3">
                                            <div class="flex gap-2">
                                                <a href="{{ route('sparepart-planning.edit', $item) }}" class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-amber-600">Edit</a>
                                                <form action="{{ route('sparepart-planning.destroy', $item) }}" method="POST"
                                                    onsubmit="return confirm('Remove this mandatory sparepart?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-red-700">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500 shadow-sm">
                No mandatory sparepart configured yet.
            </div>
        @endforelse

        @if ($canManage)
            <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
            <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
            <script>
                window.addEventListener('load', function () {
                    if (window.TomSelect) { new TomSelect('#mandatory-sparepart', { maxOptions: 100 }); }
                });
            </script>
        @endif
    @else
        @php
            $inp = 'w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm';
            $th = 'px-3 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500';
        @endphp

        <form method="GET" action="{{ route('sparepart-planning.index') }}" id="estimate-form" class="mb-6 space-y-4">
            <input type="hidden" name="tab" value="estimate">
            @if ($estimate)
                <input type="hidden" name="prev_scope" value="{{ $scope }}">
            @endif
            {{-- Default (Enter key) action = recalculate, never a remove/add button. --}}
            <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Recalculate</button>

            <div class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Machine Type</label>
                    <select name="machine_type" class="{{ $inp }}">
                        <option value="">-- Select machine type --</option>
                        @foreach ($machineTypes as $type)
                            <option value="{{ $type }}" @selected($machineType === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Month</label>
                    <select name="month" class="{{ $inp }}">
                        <option value="all" @selected(! $month)>All months</option>
                        @foreach ($monthNames as $num => $name)
                            <option value="{{ $num }}" @selected($month === $num)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Year</label>
                    <select name="year" class="{{ $inp }}">
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($areas->isNotEmpty())
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Area</label>
                        <select name="area" class="{{ $inp }}">
                            <option value="">All areas</option>
                            @foreach ($areas as $a)
                                <option value="{{ $a }}" @selected($area === $a)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="flex items-end">
                    <button class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">Calculate</button>
                </div>
            </div>

            @unless ($estimate)
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500 shadow-sm">
                    Select a machine type and PM period to build the order estimate.
                </div>
            @else
                @foreach ($messages as $message)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $message }}</div>
                @endforeach

                <div class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
                    <div>
                        <div class="text-xs font-medium text-slate-500">Machines with PM in period (system)</div>
                        <div class="text-2xl font-semibold text-slate-800">{{ $estimate['machines_system'] }}</div>
                        <div class="text-xs text-slate-500">Distinct machines with an unfinished PM schedule.</div>
                    </div>
                    <div>
                        <label for="machines_final" class="text-xs font-medium text-slate-500">Final Machine Count (editable)</label>
                        <input id="machines_final" type="number" name="machines_final" min="0" step="1" value="{{ $estimate['machines_final'] }}"
                            class="mt-1 w-full rounded-xl border border-blue-300 bg-white px-3 py-2 text-lg font-semibold">
                        <div class="text-xs text-slate-500">Used for all mandatory quantities. Does not change PM schedules.</div>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">Recalculate</button>
                        <button name="reset" value="qty" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                            onclick="return confirm('Reset all quantity corrections to the calculated values? Manual items are kept.');">Reset quantities</button>
                        <button formaction="{{ route('sparepart-planning.export') }}"
                            class="rounded-xl border border-emerald-600 px-4 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50">Export Excel</button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">Order list — {{ $machineType }}</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead>
                                <tr>
                                    <th class="{{ $th }} text-left">Source</th>
                                    <th class="{{ $th }} text-left">Material Number</th>
                                    <th class="{{ $th }} text-left">Description</th>
                                    <th class="{{ $th }} text-left">Unit</th>
                                    <th class="{{ $th }} text-right">Qty / Machine</th>
                                    <th class="{{ $th }} text-right">System Estimate</th>
                                    <th class="{{ $th }} text-right">Order Qty (final)</th>
                                    <th class="{{ $th }}"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($estimate['rows'] as $row)
                                    @php $id = $row['sparepart_id']; @endphp
                                    <tr class="align-middle hover:bg-slate-50">
                                        <td class="px-3 py-3 text-sm">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $row['source'] === 'Mandatory' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">{{ $row['source'] }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-sm font-semibold text-slate-800">{{ $row['material_number'] }}</td>
                                        <td class="px-3 py-3 text-sm text-slate-700">{{ $row['description'] }}</td>
                                        <td class="px-3 py-3 text-sm text-slate-700">{{ $row['unit'] ?? '-' }}</td>
                                        <td class="px-3 py-3 text-right text-sm">{{ $row['per_machine'] ?? '-' }}</td>
                                        <td class="px-3 py-3 text-right text-sm text-slate-600">{{ $row['system_qty'] ?? '-' }}</td>
                                        <td class="px-3 py-3 text-right">
                                            @if ($row['source'] === 'Mandatory')
                                                <input type="hidden" name="orig[{{ $id }}]" value="{{ $row['auto_qty'] }}">
                                                <input type="number" min="0" step="1" name="qty[{{ $id }}]" value="{{ $row['order_qty'] }}"
                                                    class="w-24 rounded-lg border px-2 py-1.5 text-right text-sm {{ $row['overridden'] ? 'border-amber-400 bg-amber-50' : 'border-slate-300' }}">
                                                @if ($row['overridden'])
                                                    <div class="mt-1 text-xs text-amber-700">Edited (calculated: {{ $row['auto_qty'] }})</div>
                                                @endif
                                            @else
                                                <input type="number" min="0" step="1" name="manual[{{ $id }}]" value="{{ $row['order_qty'] }}"
                                                    class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm">
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            @if ($row['source'] === 'Manual')
                                                <button name="remove" value="{{ $id }}" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-700">Remove</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">No mandatory sparepart is configured for {{ $machineType }}. Add items manually below.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="px-4 py-3 text-xs text-slate-500">System Estimate = system machine count × qty per machine. Order Qty starts as final machine count × qty per machine and can be corrected; 0 excludes the item. Corrections are kept when the machine count changes.</p>
                </div>

                <div class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-12">
                    <div class="md:col-span-8">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Add extra sparepart (not multiplied by machine count)</label>
                        <select name="add_sparepart_id" id="add-sparepart" class="{{ $inp }}">
                            <option value="">-- Select Sparepart --</option>
                            @foreach ($spareparts as $sp)
                                <option value="{{ $sp->id }}">{{ $sp->material_number }} - {{ $sp->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-slate-600">Qty</label>
                        <input type="number" name="add_qty" min="0" step="1" value="1" class="{{ $inp }}">
                    </div>
                    <div class="flex items-end md:col-span-2">
                        <button class="w-full rounded-xl bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700">Add</button>
                    </div>
                </div>

                @php $existing = $estimate['rows']->pluck('sparepart_id')->all(); @endphp
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-sm font-semibold text-slate-800">Actual usage history (reference only)</div>
                        <div class="text-xs text-slate-500">
                            Finished {{ $machineType }} PMs, {{ $usageReference['from']->format('d M Y') }} – {{ $usageReference['to']->format('d M Y') }}.
                            Not included in the order list unless you add it.
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead>
                                <tr>
                                    <th class="{{ $th }} text-left">Material Number</th>
                                    <th class="{{ $th }} text-left">Description</th>
                                    <th class="{{ $th }} text-left">Unit</th>
                                    <th class="{{ $th }} text-right">Total Used</th>
                                    <th class="{{ $th }} text-right">PM Count</th>
                                    <th class="{{ $th }}"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($usageReference['rows'] as $ref)
                                    <tr class="hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-3 py-3 text-sm font-semibold text-slate-800">{{ $ref['material_number'] }}</td>
                                        <td class="px-3 py-3 text-sm text-slate-700">{{ $ref['description'] }}</td>
                                        <td class="px-3 py-3 text-sm text-slate-700">{{ $ref['unit'] ?? '-' }}</td>
                                        <td class="px-3 py-3 text-right text-sm">{{ $ref['total'] }}</td>
                                        <td class="px-3 py-3 text-right text-sm">{{ $ref['pm_count'] }}</td>
                                        <td class="px-3 py-3 text-right">
                                            @if (in_array($ref['sparepart_id'], $existing, true))
                                                <span class="text-xs text-slate-500">In list</span>
                                            @else
                                                <button name="add_sparepart_id" value="{{ $ref['sparepart_id'] }}" class="rounded-lg border border-green-600 px-3 py-1.5 text-xs font-medium text-green-700 transition hover:bg-green-50">Add</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No finished PM usage recorded in the reference period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endunless
        </form>

        <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
        <script>
            window.addEventListener('load', function () {
                if (window.TomSelect && document.getElementById('add-sparepart')) { new TomSelect('#add-sparepart', { maxOptions: 100 }); }
            });
        </script>
    @endif
@endsection
