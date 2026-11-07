@php
    $inputClass = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100';
    $rate = $overview['closing_rate'];
@endphp

<form method="GET" action="{{ route('predictive.index') }}" class="mb-5 grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-6">
    <input type="hidden" name="tab" value="dashboard">
    <label class="block text-xs font-medium text-slate-600">Dari tanggal WO
        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="{{ $inputClass }} mt-1">
    </label>
    <label class="block text-xs font-medium text-slate-600">Sampai tanggal WO
        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="{{ $inputClass }} mt-1">
    </label>
    <label class="block text-xs font-medium text-slate-600">Kategori
        <select name="category" class="{{ $inputClass }} mt-1">
            <option value="">Semua</option>
            @foreach (\App\Models\PredictiveWorkOrder::CATEGORIES as $key => $label)
                <option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    @if ($areaOptions->isNotEmpty())
        <label class="block text-xs font-medium text-slate-600">Area
            <select name="area" class="{{ $inputClass }} mt-1">
                <option value="">Semua area</option>
                @foreach ($areaOptions as $a)
                    <option value="{{ $a }}" @selected(($filters['area'] ?? '') === $a)>{{ $a }}</option>
                @endforeach
            </select>
        </label>
    @endif
    <label class="block text-xs font-medium text-slate-600">Machine Type
        <select name="machine_type" class="{{ $inputClass }} mt-1">
            <option value="">Semua tipe</option>
            @foreach ($machineTypes as $t)
                <option value="{{ $t }}" @selected(($filters['machine_type'] ?? '') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-xs font-medium text-slate-600">PIC
        <select name="pic" class="{{ $inputClass }} mt-1">
            <option value="">Semua PIC</option>
            @foreach ($pics as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['pic'] ?? '') === (string) $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </label>
    <div class="flex gap-2 sm:col-span-2 lg:col-span-6 lg:justify-end">
        <button class="min-h-11 flex-1 rounded-lg bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 lg:flex-none">Terapkan filter</button>
        <a href="{{ route('predictive.index', ['tab' => 'dashboard']) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-900 lg:flex-none">Reset</a>
    </div>
</form>

<p class="mb-3 text-xs text-slate-500">
    Periode memakai tanggal WO. Filter PIC mencakup WO yang sedang di-claim atau pernah diberi Action oleh PIC tersebut.
</p>

{{-- A. WO Overview. Closing Rate is the focal figure; the rest are supporting counts. --}}
<section class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-5" aria-label="WO Overview">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
        <h2 class="text-sm font-medium text-slate-500">Closing Rate</h2>
        <p class="mt-1 text-4xl font-bold text-slate-900">{{ rtrim(rtrim(number_format($rate, 1, ',', '.'), '0'), ',') }}%</p>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $rate }}" aria-valuemin="0" aria-valuemax="100" aria-label="Closing Rate">
            <div class="h-full rounded-full bg-emerald-600" style="width: {{ $rate }}%"></div>
        </div>
        <p class="mt-2 text-xs text-slate-500">{{ $overview['closed'] }} dari {{ $overview['total'] }} WO berstatus CLOSED. WO yang di-reopen dihitung OPEN.</p>
    </div>
    <dl class="grid grid-cols-3 divide-x divide-slate-200 rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-3">
        <div class="flex flex-col justify-center px-4 py-5"><dt class="text-sm text-slate-500">Total WO</dt><dd class="mt-1 text-3xl font-bold text-slate-900">{{ $overview['total'] }}</dd></div>
        <div class="flex flex-col justify-center px-4 py-5"><dt class="text-sm text-amber-800">Total OPEN</dt><dd class="mt-1 text-3xl font-bold text-amber-700">{{ $overview['open'] }}</dd></div>
        <div class="flex flex-col justify-center px-4 py-5"><dt class="text-sm text-emerald-800">Total CLOSED</dt><dd class="mt-1 text-3xl font-bold text-emerald-700">{{ $overview['closed'] }}</dd></div>
    </dl>
</section>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    @foreach ([['Finding Summary', 'Finding yang paling sering muncul', $findings, 'bg-sky-600'], ['Problem Summary', 'Problem yang paling sering muncul', $problems, 'bg-amber-500']] as [$title, $hint, $rows, $bar])
        @php $max = max(1, (int) ($rows->max('total') ?? 0)); @endphp
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="{{ $title }}">
            <h2 class="font-bold text-slate-900">{{ $title }}</h2>
            <p class="text-xs text-slate-500">{{ $hint }}, dari seluruh histori Action termasuk setelah Reopen.</p>
            @if ($rows->isEmpty())
                <p class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">Belum ada Action pada WO yang sesuai filter.</p>
            @else
                <ol class="mt-4 space-y-3">
                    @foreach ($rows as $row)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="min-w-0 break-words text-slate-800">{{ $row->label }}</span>
                                <span class="shrink-0 font-semibold text-slate-900">{{ $row->total }}x</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $bar }}" style="width: {{ round($row->total / $max * 100) }}%"></div></div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endforeach
</div>
