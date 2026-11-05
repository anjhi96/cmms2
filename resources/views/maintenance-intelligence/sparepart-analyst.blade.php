@extends('layouts.app')

@php
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';

    $card = 'rounded-3xl border border-slate-200 bg-white p-6 shadow-sm';
    $th = 'px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500';
    $td = 'px-3 py-2 text-sm text-slate-700';
    $input = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-sm';
    $n = fn ($v, $d = 0) => number_format((float) $v, $d, ',', '.');
    $levelClass = ['LIKELY' => 'bg-emerald-100 text-emerald-700', 'POSSIBLE' => 'bg-amber-100 text-amber-700', 'RARE' => 'bg-slate-100 text-slate-600'];
    $trendLabel = ['INCREASING' => '↑ Naik', 'DECREASING' => '↓ Turun', 'STABLE' => '→ Stabil', 'UNKNOWN' => '—'];
    $q = $run?->summary['quality'] ?? null;
    $sections = ['overview' => 'Overview', 'machine' => 'Machine Forecast', 'demand' => 'Sparepart Demand', 'type' => 'Machine Type', 'history' => 'Historical Usage', 'evaluation' => 'Evaluation', 'quality' => 'Data Quality', 'recs' => 'Recommendations', 'runs' => 'History'];
@endphp

@section('content')
<div class="space-y-6">

    <div class="{{ $card }}">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <a href="{{ route('maintenance-intelligence.index') }}" class="text-slate-500 hover:text-blue-600">&larr; Maintenance Intelligence Center</a>
            <a href="{{ route('home') }}" class="text-slate-500 hover:text-blue-600">Landing Page</a>
        </div>
        <h1 class="mt-3 text-2xl font-semibold text-slate-800">AI Sparepart Analyst</h1>
        <p class="mt-1 text-sm text-slate-500">
            Forecast kebutuhan sparepart per mesin dari histori PM Sparepart Usage. Perhitungan dilakukan oleh engine statistik
            (versi {{ \App\Services\SparepartForecast\ForecastEngine::VERSION }}); penjelasan AI (Gemini) belum terintegrasi.
            Mandatory Sparepart dan Sparepart Injection tidak dipakai sebagai sumber.
        </p>

        @if (session('success'))<div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('maintenance-intelligence.sparepart-analyst.run') }}" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <div>
                <label class="text-xs font-medium text-slate-500">Periode</label>
                <select name="period_type" class="{{ $input }}">
                    <option value="MONTH">Bulanan</option>
                    <option value="WEEK">Mingguan</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-500">Tanggal dalam periode</label>
                <input type="date" name="period_date" value="{{ now()->toDateString() }}" class="{{ $input }}" required>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-500">Area (scope analisis)</label>
                <select name="area" class="{{ $input }}"><option value="">Semua</option>@foreach ($areas as $a)<option>{{ $a }}</option>@endforeach</select>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-500">Machine Type (scope analisis)</label>
                <select name="machine_type" class="{{ $input }}"><option value="">Semua</option>@foreach ($machineTypes as $t)<option>{{ $t }}</option>@endforeach</select>
            </div>
            <div class="flex items-end">
                <button class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Jalankan Analisis</button>
            </div>
        </form>
    </div>

    @if (! $run)
        <div class="{{ $card }} text-sm text-slate-600">Belum ada analisis. Jalankan analisis pertama dari form di atas.</div>
    @elseif ($run->status !== 'COMPLETED')
        <div class="{{ $card }} text-sm text-red-700">Run #{{ $run->id }} berstatus {{ $run->status }}. {{ $run->error }}</div>
    @else
        <nav class="flex gap-2 overflow-x-auto text-sm">
            @foreach ($sections as $id => $label)
                <a href="#{{ $id }}" class="shrink-0 rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600 hover:text-blue-600">{{ $label }}</a>
            @endforeach
        </nav>

        {{-- Filters on the displayed run --}}
        <form method="GET" class="{{ $card }} grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <input type="hidden" name="run" value="{{ $run->id }}">
            <div><label class="text-xs font-medium text-slate-500">Area</label>
                <select name="area" class="{{ $input }}"><option value="">Semua</option>@foreach ($areas as $a)<option @selected(($filters['area'] ?? '') === $a)>{{ $a }}</option>@endforeach</select></div>
            <div><label class="text-xs font-medium text-slate-500">Machine Type</label>
                <select name="machine_type" class="{{ $input }}"><option value="">Semua</option>@foreach ($machineTypes as $t)<option @selected(($filters['machine_type'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></div>
            <div><label class="text-xs font-medium text-slate-500">Machine</label>
                <input name="machine" value="{{ $filters['machine'] ?? '' }}" placeholder="No. mesin" class="{{ $input }}"></div>
            <div class="lg:col-span-2"><label class="text-xs font-medium text-slate-500">Sparepart</label>
                <input name="sparepart" value="{{ $filters['sparepart'] ?? '' }}" placeholder="SAP number / deskripsi" class="{{ $input }}"></div>
            <div class="flex items-end gap-2">
                <button class="flex-1 rounded-xl bg-slate-800 px-4 py-2 text-sm text-white">Filter</button>
                <a href="{{ route('maintenance-intelligence.sparepart-analyst.export', $run) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Excel</a>
            </div>
        </form>

        {{-- 1. Overview --}}
        <section id="overview" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-slate-800">1. Forecast Overview</h2>
            <p class="mt-1 text-sm text-slate-500">
                Run #{{ $run->id }} · {{ $run->period_type === 'WEEK' ? 'Mingguan' : 'Bulanan' }}
                {{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}
                · dianalisis {{ $run->finished_at?->format('d M Y H:i') }}
                · scope: {{ $run->scope_area ?? 'semua area' }} / {{ $run->scope_machine_type ?? 'semua tipe' }}
            </p>
            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
                @foreach ([
                    ['Mesin dalam scope', $q['machines_in_scope']],
                    ['Mesin dengan prediksi', $q['machines_sufficient']],
                    ['Insufficient Historical Data', $q['machines_insufficient']],
                    ['Item LIKELY (terfilter)', $totals['likely_items']],
                    ['Total qty rekomendasi (terfilter)', $totals['recommended']],
                ] as [$label, $value])
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <p class="text-xs text-slate-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-800">{{ $n($value) }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-slate-500">
                Rekomendasi hanya untuk sparepart berstatus LIKELY (probabilitas pakai ≥ {{ $run->parameters['likely_probability'] * 100 }}%).
                Jumlah PM adalah PM terjadwal (OPEN / IN_PROGRESS / MISSED) pada periode; PM yang belum dijadwalkan tidak diperkirakan.
            </p>
        </section>

        {{-- 2. Machine-based --}}
        <section id="machine" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-slate-800">2. Machine-Based Forecast</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead><tr>
                        @foreach (['Mesin', 'Tipe', 'Sparepart', 'Dipakai', 'Avg qty', 'Prob.', 'PM', 'Estimasi', 'Rekomendasi', 'Level', 'Conf.', 'Dasar'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100" data-infinite-list="forecast">
                    @forelse ($forecast as $r)
                        <tr>
                            <td class="{{ $td }} whitespace-nowrap font-medium">{{ $r->machine_number }}</td>
                            <td class="{{ $td }}">{{ $r->machine_type }}</td>
                            <td class="{{ $td }}"><span class="font-medium">{{ $r->material_number }}</span><br><span class="text-xs text-slate-500">{{ $r->description }}</span></td>
                            <td class="{{ $td }} whitespace-nowrap">{{ $r->usage_pm_count }}/{{ $r->pm_considered }} PM</td>
                            <td class="{{ $td }}">{{ $n($r->avg_qty, 2) }} {{ $r->unit }}</td>
                            <td class="{{ $td }}">{{ $n($r->probability * 100) }}%</td>
                            <td class="{{ $td }}">{{ $r->upcoming_pm_count }}</td>
                            <td class="{{ $td }}">{{ $n($r->expected_qty, 2) }}</td>
                            <td class="{{ $td }} font-semibold">{{ $r->recommended_qty }}</td>
                            <td class="{{ $td }}"><span class="rounded-full px-2 py-0.5 text-xs {{ $levelClass[$r->level] }}">{{ $r->level }}</span></td>
                            <td class="{{ $td }}">{{ $r->confidence }}</td>
                            <td class="{{ $td }}"><details><summary class="cursor-pointer text-xs text-blue-600">Lihat</summary>
                                <ul class="mt-1 w-64 list-disc pl-4 text-xs text-slate-600">@foreach ($r->basis['lines'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul></details></td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="{{ $td }} text-slate-500">Tidak ada hasil prediksi untuk filter ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3"><x-infinite-scroll :paginator="$forecast" key="forecast" /></div>

            <h3 class="mt-6 text-sm font-semibold text-slate-700">Mesin dengan Insufficient Historical Data</h3>
            <p class="text-xs text-slate-500">Tidak diberi angka prediksi dan tidak digantikan oleh Mandatory Sparepart maupun Injection.</p>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead><tr>@foreach (['Mesin', 'Tipe', 'Area', 'PM selesai', 'PM ada pencatatan', 'PM dikecualikan', 'Keterangan'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100" data-infinite-list="insufficient">
                    @forelse ($insufficient as $m)
                        <tr>
                            <td class="{{ $td }} font-medium">{{ $m->machine_number }}</td><td class="{{ $td }}">{{ $m->machine_type }}</td><td class="{{ $td }}">{{ $m->area }}</td>
                            <td class="{{ $td }}">{{ $m->finished_pm_count }}</td><td class="{{ $td }}">{{ $m->pm_with_usage_count }}</td><td class="{{ $td }}">{{ $m->excluded_pm_count }}</td>
                            <td class="{{ $td }}">{{ $m->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="{{ $td }} text-slate-500">Tidak ada.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3"><x-infinite-scroll :paginator="$insufficient" key="insufficient" /></div>
        </section>

        {{-- 3. Sparepart demand --}}
        <section id="demand" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-slate-800">3. Sparepart Demand Summary</h2>
            <p class="mt-1 text-sm text-slate-500">Agregasi seluruh mesin pada filter ini, per sparepart (maks. 100 baris; lengkapnya di Excel).</p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead><tr>@foreach (['SAP Number', 'Deskripsi', 'Unit', 'Mesin', 'Estimasi total', 'Rekomendasi total'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse ($bySparepart as $r)
                        <tr><td class="{{ $td }} font-medium">{{ $r->material_number }}</td><td class="{{ $td }}">{{ $r->description }}</td><td class="{{ $td }}">{{ $r->unit }}</td>
                            <td class="{{ $td }}">{{ $r->machines }}</td><td class="{{ $td }}">{{ $n($r->expected, 2) }}</td><td class="{{ $td }} font-semibold">{{ $n($r->recommended) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="{{ $td }} text-slate-500">Belum ada data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 4. Machine type / area --}}
        <section id="type" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-slate-800">4. Machine Type Analysis</h2>
            <div class="mt-3 grid gap-6 lg:grid-cols-2">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead><tr>@foreach (['Machine Type', 'Mesin (prediksi / insufficient)', 'PM terjadwal', 'Jenis sparepart', 'Estimasi', 'Rekomendasi'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach ($machineStatusByType as $type => $s)
                            @php $r = $byMachineType->firstWhere('machine_type', $type); @endphp
                            <tr><td class="{{ $td }} font-medium">{{ $type }}</td><td class="{{ $td }}">{{ $s->sufficient }} / {{ $s->insufficient }}</td><td class="{{ $td }}">{{ $s->upcoming }}</td>
                                <td class="{{ $td }}">{{ $r->parts ?? 0 }}</td><td class="{{ $td }}">{{ $n($r->expected ?? 0, 2) }}</td><td class="{{ $td }} font-semibold">{{ $n($r->recommended ?? 0) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead><tr>@foreach (['Area', 'Mesin', 'Estimasi', 'Rekomendasi'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse ($byArea as $r)
                            <tr><td class="{{ $td }} font-medium">{{ $r->area }}</td><td class="{{ $td }}">{{ $r->machines }}</td><td class="{{ $td }}">{{ $n($r->expected, 2) }}</td><td class="{{ $td }} font-semibold">{{ $n($r->recommended) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="{{ $td }} text-slate-500">Belum ada data.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- 5. Historical usage --}}
        <section id="history" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-slate-800">5. Historical Usage Analysis</h2>
            <p class="mt-1 text-sm text-slate-500">
                Histori {{ $run->history_from?->format('d M Y') ?? '-' }} – {{ $run->history_to?->format('d M Y') ?? '-' }}
                (window {{ $run->parameters['window'] }} PM terakhir per mesin). Frekuensi, total qty, dan tren per sparepart.
            </p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead><tr>@foreach (['SAP Number', 'Deskripsi', 'Mesin yang pernah pakai', 'Total kejadian pakai', 'Total qty histori', 'Mesin tren naik', 'Mesin tren turun'] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse ($bySparepart->sortByDesc('hist_qty')->take(30) as $r)
                        <tr><td class="{{ $td }} font-medium">{{ $r->material_number }}</td><td class="{{ $td }}">{{ $r->description }}</td><td class="{{ $td }}">{{ $r->machines }}</td>
                            <td class="{{ $td }}">{{ $r->usage_pm }}</td><td class="{{ $td }}">{{ $n($r->hist_qty) }} {{ $r->unit }}</td><td class="{{ $td }}">{{ $r->increasing }}</td><td class="{{ $td }}">{{ $r->decreasing }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="{{ $td }} text-slate-500">Belum ada data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-500">Interval antar-pemakaian dan tren per mesin tersedia pada kolom "Dasar" (section 2) dan di export Excel.</p>
        </section>

        {{-- 6. Evaluation --}}
        <section id="evaluation" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-slate-800">6. Forecast Evaluation &amp; Accuracy</h2>
            @if ($evaluation)
                <p class="mt-1 text-sm text-slate-500">{{ $evaluation->split_method }}.</p>
                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 p-4 text-sm text-slate-700">
                        <h3 class="font-semibold text-slate-800">Training data</h3>
                        <p>Baris penggunaan: {{ $n($evaluation->train_usage_rows) }}</p>
                        <p>PM: {{ $n($evaluation->train_pm_count) }} · Mesin: {{ $n($evaluation->train_machine_count) }} · Sparepart: {{ $n($evaluation->train_sparepart_count) }}</p>
                        <p>Rentang: {{ $evaluation->train_from?->format('d M Y') ?? '-' }} – {{ $evaluation->train_to?->format('d M Y') ?? '-' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4 text-sm text-slate-700">
                        <h3 class="font-semibold text-slate-800">Testing data</h3>
                        <p>Baris penggunaan: {{ $n($evaluation->test_usage_rows) }}</p>
                        <p>PM: {{ $n($evaluation->test_pm_count) }} · Mesin: {{ $n($evaluation->test_machine_count) }} · Item dibandingkan: {{ $n($evaluation->compared_items) }}</p>
                        <p>Rentang: {{ $evaluation->test_from?->format('d M Y') ?? '-' }} – {{ $evaluation->test_to?->format('d M Y') ?? '-' }}</p>
                        <p class="mt-1">Cukup untuk evaluasi: <strong>{{ $evaluation->status === 'AVAILABLE' ? 'Ya' : 'Tidak' }}</strong></p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4 text-sm text-slate-700">
                        <h3 class="font-semibold text-slate-800">Accuracy</h3>
                        @if ($evaluation->status === 'AVAILABLE')
                            <p>MAE: <strong>{{ $n($evaluation->mae, 2) }}</strong> unit / mesin-sparepart</p>
                            <p>WAPE: <strong>{{ $n($evaluation->wape * 100, 1) }}%</strong></p>
                            <p>Bias: <strong>{{ $n($evaluation->bias * 100, 1) }}%</strong></p>
                        @else
                            <p class="font-semibold text-amber-700">Accuracy Not Available — Insufficient Testing Data</p>
                            @foreach ($evaluation->notes ?? [] as $note)<p class="text-xs text-slate-500">{{ $note }}</p>@endforeach
                        @endif
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">
                    Syarat evaluasi: minimal {{ $run->parameters['min_test_pm'] }} PM testing dari minimal {{ $run->parameters['min_test_machines'] }} mesin.
                    Angka di atas dihitung dari data aktual saat run ini dijalankan, bukan diisi manual.
                </p>
            @else
                <p class="mt-2 text-sm text-slate-500">Evaluasi tidak tersedia untuk run ini.</p>
            @endif
        </section>

        {{-- 7. Data quality --}}
        <section id="quality" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-slate-800">7. Data Quality &amp; Model Notes</h2>
            <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'PM selesai (mesin dalam scope)' => $q['finished_pm_total'],
                    'PM dipakai sebagai histori' => $q['finished_pm_used'],
                    'PM dipakai tanpa catatan sparepart' => $q['finished_pm_without_usage'],
                    'PM dikecualikan (memuat Injection)' => $q['finished_pm_excluded_warehouse'],
                    'PM selesai tanpa tanggal' => $q['finished_pm_without_date'],
                    'Baris penggunaan dipakai' => $q['usage_rows'],
                    'Jenis sparepart tercatat' => $q['distinct_spareparts'],
                    'Mesin tanpa histori PM selesai' => $q['machines_without_history'],
                    'Mesin cukup data tanpa PM terjadwal' => $q['machines_without_upcoming_pm'],
                ] as $label => $value)
                    <div class="flex justify-between border-b border-slate-100 py-1"><dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium text-slate-800">{{ $n($value) }}</dd></div>
                @endforeach
            </dl>
            <ul class="mt-4 list-disc space-y-1 pl-5 text-xs text-slate-600">
                <li>Algoritma: {{ $run->algorithm_version }}. Analisis: {{ $run->finished_at?->format('d M Y H:i') }}. Fingerprint data: <code>{{ \Illuminate\Support\Str::limit($run->data_fingerprint, 16, '…') }}</code>.</li>
                <li>Mesin butuh minimal {{ $run->parameters['min_pm'] }} PM selesai dengan minimal {{ $run->parameters['min_pm_with_usage'] }} PM yang punya catatan sparepart.</li>
                <li>PM selesai tanpa catatan sparepart dihitung sebagai "tidak memakai sparepart". Belum ada penanda bahwa pencatatan PM sudah lengkap, jadi pencatatan yang terlewat menurunkan probabilitas.</li>
                <li>Belum ada penanda pembeda sparepart yang benar-benar diganti dengan nilai default yang ikut tersimpan dari form.</li>
            </ul>
        </section>

        {{-- 8. Recommendations --}}
        <section id="recs" class="{{ $card }}">
            <h2 class="text-lg font-semibold text-slate-800">8. Recommendations</h2>
            <p class="mt-1 text-sm text-slate-500">Dihasilkan otomatis dari angka engine (source: ENGINE). Penjelasan Gemini belum aktif.</p>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($recommendations as $rec)
                    <li class="rounded-xl border px-3 py-2 {{ $rec->severity === 'WARNING' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-slate-200 text-slate-700' }}">{{ $rec->message }}</li>
                @empty
                    <li class="text-slate-500">Tidak ada rekomendasi.</li>
                @endforelse
            </ul>
        </section>
    @endif

    {{-- 9. History --}}
    <section id="runs" class="{{ $card }}">
        <h2 class="text-lg font-semibold text-slate-800">9. Analysis History</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead><tr>@foreach (['Run', 'Waktu', 'Periode', 'Scope', 'Versi', 'Status', 'Oleh', ''] as $h)<th class="{{ $th }}">{{ $h }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($runs as $r)
                    <tr class="{{ $run && $run->id === $r->id ? 'bg-blue-50/50' : '' }}">
                        <td class="{{ $td }}">#{{ $r->id }}</td><td class="{{ $td }}">{{ $r->created_at->format('d M Y H:i') }}</td>
                        <td class="{{ $td }}">{{ $r->period_type }} {{ $r->period_start->format('d M') }}–{{ $r->period_end->format('d M Y') }}</td>
                        <td class="{{ $td }}">{{ $r->scope_area ?? 'Semua' }} / {{ $r->scope_machine_type ?? 'Semua' }}</td>
                        <td class="{{ $td }}">{{ $r->algorithm_version }}</td><td class="{{ $td }}">{{ $r->status }}</td><td class="{{ $td }}">{{ $r->user?->name ?? '-' }}</td>
                        <td class="{{ $td }}"><a class="text-blue-600" href="{{ route('maintenance-intelligence.sparepart-analyst', ['run' => $r->id]) }}">Buka</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="{{ $td }} text-slate-500">Belum ada analisis.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-500">Hasil lama tetap tersimpan; menjalankan analisis baru tidak menimpanya.</p>
    </section>
</div>
@endsection
