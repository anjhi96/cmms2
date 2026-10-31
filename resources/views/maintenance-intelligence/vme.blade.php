@extends('layouts.app')

@php
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';

    $labels = [
        'display' => ['VME Display', 'Menampilkan halaman peta VME (/vme).'],
        'activity_sync' => ['Today Activity Synchronization', 'Snapshot membaca aktivitas aktual PIC dari FreeDOMS. OFF = karakter selalu idle.'],
        'human_movement' => ['Human Character Movement', 'Karakter berjalan ke lokasi aktivitas. OFF = karakter tetap di basis.'],
    ];
@endphp

@section('content')
<div class="space-y-6">

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <a href="{{ route('maintenance-intelligence.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 transition">&larr; Maintenance Intelligence Center</a>
        <h1 class="mt-3 text-2xl font-semibold text-slate-800">Virtual Maintenance Environment</h1>
        <p class="mt-1 text-sm text-slate-500">
            Visualisasi 2D aktivitas aktual FreeDOMS. VME hanya membaca data; mematikannya tidak memengaruhi PM, Greasing, Oil Audit, atau modul lain.
        </p>
        @if ($effective['display'])
            <a href="{{ route('vme.index') }}" class="mt-3 inline-flex rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Buka VME</a>
        @endif
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    {{-- Feature switches --}}
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Feature Switch</h2>
        @if ($killed)
            <div class="mt-3 rounded-xl border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800">
                Kill switch lingkungan aktif (<code>VME_KILL_SWITCH=true</code>): seluruh fitur VME dinonaktifkan, apa pun isi pengaturan di bawah.
            </div>
        @endif
        <form method="POST" action="{{ route('maintenance-intelligence.vme.settings') }}" class="mt-4 space-y-3">
            @csrf
            @method('PUT')
            @foreach ($labels as $key => [$title, $hint])
                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4">
                    <input type="checkbox" name="{{ $key }}" value="1" class="mt-1" @checked($features[$key])>
                    <span>
                        <span class="block text-sm font-semibold text-slate-800">{{ $title }}
                            <span class="ml-2 rounded-full px-2 py-0.5 text-xs {{ $effective[$key] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $effective[$key] ? 'ON' : 'OFF' }}</span>
                        </span>
                        <span class="block text-xs text-slate-500">{{ $hint }}</span>
                    </span>
                </label>
            @endforeach
            <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
        </form>
    </section>

    {{-- Avatars --}}
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Avatar Karakter PIC</h2>
        <p class="mt-1 text-sm text-slate-500">Satu karakter per PIC aktif. PIC tanpa pilihan mendapat avatar otomatis.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($pics as $pic)
                <form method="POST" action="{{ route('maintenance-intelligence.vme.avatar', $pic) }}" class="flex items-center gap-2 rounded-2xl border border-slate-200 p-3">
                    @csrf
                    @method('PUT')
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-slate-800">{{ $pic->name }}</div>
                        <div class="text-xs text-slate-500">{{ $pic->area?->name ?? 'Tanpa area' }}</div>
                    </div>
                    <select name="avatar_key" class="rounded-lg border border-slate-300 px-2 py-1 text-sm">
                        @foreach ($avatars as $key => $a)
                            <option value="{{ $key }}" @selected(($profiles[$pic->id] ?? null) === $key)>{{ $a['label'] }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-slate-800 px-3 py-1 text-xs font-medium text-white">Simpan</button>
                </form>
            @empty
                <p class="text-sm text-slate-500">Belum ada PIC aktif.</p>
            @endforelse
        </div>
    </section>

    {{-- Unmapped --}}
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Aktivitas yang Belum Dapat Dipetakan</h2>
        <p class="mt-1 text-sm text-slate-500">
            PM dan Greasing menyimpan PIC sebagai nama. Bila nama dipakai lebih dari satu akun PIC, atau tidak cocok dengan PIC mana pun, VME tidak menebak dan tidak menampilkan aktivitasnya.
        </p>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-700">Nama PIC ganda</h3>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    @forelse ($diagnostics['ambiguous'] as $row)
                        <li>{{ $row['name'] }} <span class="text-xs text-slate-400">(user id {{ implode(', ', $row['user_ids']) }})</span></li>
                    @empty
                        <li class="text-slate-400">Tidak ada.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-700">Aktivitas hari ini tanpa PIC yang cocok</h3>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    @forelse ($diagnostics['orphans'] as $row)
                        <li>{{ $row['source'] }} #{{ $row['record_id'] }} &mdash; "{{ $row['pic'] }}"</li>
                    @empty
                        <li class="text-slate-400">Tidak ada.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </section>
</div>

@endsection
