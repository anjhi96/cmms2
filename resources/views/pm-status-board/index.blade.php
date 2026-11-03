@extends('layouts.app')

@section('title', 'PM Status — '.$currentArea)

@section('content')

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">PM Status — {{ $currentArea }}</h1>
            <p class="text-slate-500">
                Informasi status pelaksanaan Preventive Maintenance.
            </p>
        </div>

        {{-- AREA SWITCHER — navigation to each active area's own URL, not a
             filter. Sourced from the live Area master list (passed in by
             the controller), not a hardcoded map, so a newly added area
             appears here automatically. --}}
        <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1" role="group" aria-label="Area switcher">
            @foreach ($areas as $areaOption)
                <a href="{{ route('pm-status.show', $areaOption->slug) }}"
                    class="rounded-md px-4 py-2 text-sm font-semibold transition
                    {{ $areaSlug === $areaOption->slug ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-200' }}">
                    {{ $areaOption->name }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- LEGEND --}}
    <div class="mb-6 rounded-lg border border-slate-200 bg-blue-50 p-4 text-sm text-slate-600">
        <p class="font-medium text-slate-800 mb-2">ℹ️ Informasi</p>
        <p><strong>GAP DAY</strong> = Today − Plan Date</p>
        <p class="mt-1">Batas normal: <strong>-14 sampai +14 hari</strong></p>
        <p class="mt-1 text-slate-500">GAP DAY merah menunjukkan sudah di luar batas ±14 hari, sebaiknya tidak dipilih untuk mesin pengganti.</p>
    </div>

    {{-- DESKTOP TABLE --}}
    <div class="mb-4 hidden rounded-xl border border-slate-200 bg-white overflow-hidden md:block">
        <table class="w-full">
            <thead class="bg-slate-100 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Status</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Bulan</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Machine</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Plan Date</th>
                    <th class="px-4 py-3 text-center text-sm font-semibold text-slate-700">Gap Day</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Actual PM Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100" data-pm-rows-desktop @if ($canOverrideStatus) data-pm-can-override @endif>
                @include('pm-status-board._rows-desktop')
                @if ($schedules->isEmpty())
                    <tr data-pm-empty>
                        <td colspan="6" class="py-8 text-center text-slate-500">
                            Tidak ada mesin PM yang masih open.
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- MOBILE CARDS --}}
    <div class="space-y-3 md:hidden" data-pm-rows-mobile @if ($canOverrideStatus) data-pm-can-override @endif>
        @include('pm-status-board._cards-mobile')
        @if ($schedules->isEmpty())
            <div data-pm-empty class="rounded-lg border border-slate-200 bg-white p-8 text-center">
                <p class="text-slate-500">Tidak ada mesin PM yang masih open.</p>
            </div>
        @endif
    </div>

    {{-- LOAD NEXT MONTH — fetches one future month at a time (up to
         $maxMonthOffset months ahead) and appends it without a reload. --}}
    <div class="mt-6 text-center">
        <button type="button"
            data-pm-month-next
            data-url="{{ route('pm-status.month', $areaSlug) }}"
            data-offset="1"
            class="text-sm text-slate-400 underline-offset-2 transition hover:text-blue-600 hover:underline">
            Klik disini untuk memuat mesin bulan {{ $nextMonthLabel }}
        </button>
        <p data-pm-month-error class="mt-2 hidden text-xs text-red-600">
            Gagal memuat data, coba lagi.
        </p>
    </div>

@endsection
