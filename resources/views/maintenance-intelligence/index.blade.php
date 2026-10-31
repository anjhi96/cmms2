@extends('layouts.app')

@php
    // Standalone page: opt out of the global sidebar/topbar via the layout's
    // existing per-page flags (same mechanism as oil-audits.history). The
    // topbar is hidden too because its hamburger only toggles the sidebar.
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';
@endphp

@section('content')
@php
    // Status labels are honest: nothing here is implemented yet. Add a real
    // status (e.g. "In Development", "Active") only when the backend exists.
    $agents = [
        ['name' => 'AI Sparepart Analyst', 'status' => 'In Development', 'priority' => true,
         'points' => [
             'Forecast kebutuhan mandatory sparepart',
             'Forecast sparepart tambahan berdasarkan histori aktual',
             'Analisis pola pemakaian sparepart',
             'Rekomendasi persiapan sparepart',
         ]],
        ['name' => 'AI Machine Problem Predictor', 'status' => 'Planned', 'points' => []],
        ['name' => 'AI Oil Condition Analyst', 'status' => 'Planned', 'points' => []],
        ['name' => 'AI PM Planner', 'status' => 'Planned', 'points' => []],
        ['name' => 'AI Maintenance Performance Analyst', 'status' => 'Planned', 'points' => []],
        ['name' => 'AI Maintenance Knowledge Assistant', 'status' => 'Planned', 'points' => []],
    ];

    $vme = [
        'Virtual Office 2D', 'Production Area', 'Workshop', 'Human Characters',
        'AI Characters', 'Live Activity Synchronization', 'AI Agent Visualization',
    ];

    $roadmaps = [
        'AI Maintenance Intelligence' => [
            'Data Readiness Audit', 'Forecast Calculation Design', 'Database & API Design',
            'AI Backend Development', 'Gemini Integration', 'Dashboard Integration', 'Validation & Testing',
        ],
        'Virtual Maintenance Environment' => [
            'Concept & Architecture Design', 'Map & Environment Design', 'Dynamic Character Configuration',
            'Human Character Development', 'Today Activity Integration', 'AI Character Integration', 'Monitoring & Optimization',
        ],
    ];
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 transition">
            &larr; Kembali ke Landing Page
        </a>
        <h1 class="mt-3 text-2xl font-semibold text-slate-800">Maintenance Intelligence Center</h1>
        <p class="mt-1 text-sm text-slate-500">
            Central management for AI-powered maintenance analysis and virtual maintenance monitoring.
            Halaman ini hanya dapat diakses ADMIN dan bukan bagian dari menu utama.
        </p>
        <div class="mt-4 flex flex-wrap gap-2 text-xs">
            <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600">AI Maintenance Intelligence: Planned</span>
            <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600">Virtual Maintenance Environment: Planned</span>
        </div>
    </div>

    {{-- AI Maintenance Intelligence --}}
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">AI Maintenance Intelligence</h2>
        <p class="mt-1 text-sm text-slate-500">
            AI Agent adalah fitur tambahan; CMMS tetap berfungsi penuh tanpanya. Baru AI Sparepart Analyst yang sedang dikembangkan.
        </p>

        @php $first = $agents[0]; @endphp
        <div class="mt-5 rounded-2xl border-2 border-blue-200 bg-blue-50/50 p-5">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-base font-semibold text-slate-800">{{ $first['name'] }}</h3>
                <span class="rounded-full bg-blue-600 px-2.5 py-0.5 text-xs font-medium text-white">Prioritas pertama</span>
                <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $first['status'] }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-600">Engine forecast statistik dan dashboard sudah tersedia; integrasi Gemini dan backend AI terpisah belum.</p>
            <a href="{{ route('maintenance-intelligence.sparepart-analyst') }}" class="mt-3 inline-flex rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Buka AI Sparepart Analyst</a>
            <ul class="mt-3 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                @foreach ($first['points'] as $point)
                    <li class="flex gap-2"><span class="text-blue-500">&bull;</span>{{ $point }}</li>
                @endforeach
            </ul>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (array_slice($agents, 1) as $agent)
                <div class="rounded-2xl border border-slate-200 p-4">
                    <h3 class="text-sm font-semibold text-slate-800">{{ $agent['name'] }}</h3>
                    <span class="mt-2 inline-block rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ $agent['status'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Virtual Maintenance Environment --}}
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Virtual Maintenance Environment</h2>
        <p class="mt-1 text-sm text-slate-500">
            Visualisasi 2D aktivitas maintenance FreeDOMS. Belum ada komponen yang dikembangkan.
        </p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($vme as $item)
                <div class="rounded-2xl border border-slate-200 p-4">
                    <h3 class="text-sm font-semibold text-slate-800">{{ $item }}</h3>
                    <span class="mt-2 inline-block rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Planned</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Development Overview --}}
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Development Overview</h2>
        <p class="mt-1 text-sm text-slate-500">Urutan milestone pengembangan. Belum ada status progres yang dilacak.</p>
        <div class="mt-5 grid gap-6 lg:grid-cols-2">
            @foreach ($roadmaps as $title => $steps)
                <div>
                    <h3 class="text-sm font-semibold text-slate-700">{{ $title }}</h3>
                    <ol class="mt-3 space-y-2">
                        @foreach ($steps as $i => $step)
                            <li class="flex items-center gap-3 text-sm text-slate-600">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-medium text-slate-500">{{ $i + 1 }}</span>
                                {{ $step }}
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
