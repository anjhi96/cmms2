@extends('layouts.app')

@php
    // Standalone full-screen page (same flags as the Maintenance Intelligence
    // Center). VME is not part of the main sidebar.
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';
@endphp

@section('content')
<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
        <div>
            <a href="{{ route('home') }}" class="text-sm text-slate-500 hover:text-blue-600">&larr; Landing Page</a>
            <h1 class="text-lg font-semibold text-slate-800">Virtual Maintenance Environment</h1>
        </div>
        <div id="vme-hud" class="flex flex-wrap items-center gap-2 text-xs">
            <span id="vme-render" class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600" title="Status rendering peta">Peta: memuat…</span>
            <span id="vme-sync" class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600">Memuat…</span>
            <span id="vme-updated" class="text-slate-500"></span>
        </div>
    </div>

    <div id="vme-banner" class="hidden rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-800"></div>

    {{-- minmax(0,1fr): without it the wide canvas stretches the column past the viewport on mobile. --}}
    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_16rem]">
        <div class="relative min-w-0 self-start overflow-hidden rounded-2xl border border-slate-300 bg-slate-900 shadow-sm">
            <div id="vme-root"
                 data-state="loading"
                 data-snapshot-url="{{ $snapshotUrl }}"
                 data-config-url="{{ $configUrl }}"
                 class="w-full"
                 style="height: clamp(22rem, 72vh, 46rem); touch-action: none;"></div>

            <div class="absolute right-2 top-2 flex flex-col overflow-hidden rounded-lg border border-slate-600 bg-slate-800/90 text-white shadow">
                <button type="button" id="vme-zoom-in" class="px-3 py-1 text-lg leading-none hover:bg-slate-700" aria-label="Perbesar">+</button>
                <button type="button" id="vme-zoom-out" class="border-t border-slate-600 px-3 py-1 text-lg leading-none hover:bg-slate-700" aria-label="Perkecil">&minus;</button>
                <button type="button" id="vme-zoom-fit" class="border-t border-slate-600 px-2 py-1 text-[10px] hover:bg-slate-700" aria-label="Tampilkan seluruh peta">FIT</button>
            </div>

            <div id="vme-overlay" class="absolute inset-0 hidden flex-col items-center justify-center gap-2 bg-slate-900/90 p-6 text-center text-sm text-slate-100"></div>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-800">Aktivitas Aktual</h2>
            <p class="mt-1 text-xs text-slate-500">Hanya PIC yang benar-benar memulai aktivitas di FreeDOMS.</p>
            <ul id="vme-list" class="mt-3 space-y-2 text-sm"></ul>
            <p id="vme-empty" class="mt-3 hidden text-xs text-slate-400">Tidak ada aktivitas aktif.</p>
        </aside>
    </div>
</div>

@vite('resources/js/vme/main.js')
@endsection
