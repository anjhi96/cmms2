@extends('layouts.app')

@section('title', 'Planning PM Mingguan')

@section('content')
    <div id="weekly-plan"
        data-save-url="{{ route('weekly-plan.items.save', $plan) }}"
        data-day-limits-url="{{ route('weekly-plan.day-limits.save', $plan) }}"
        data-autofill-url="{{ route('weekly-plan.autofill', $plan) }}"
        data-day-defaults-url="{{ route('weekly-plan.day-defaults.restore', $plan) }}"
        data-pic-sync-url="{{ route('weekly-plan.pic-sync.apply', $plan) }}"
        data-mark-sent-url="{{ route('weekly-plan.mark-sent', $plan) }}"
        data-pm-schedules-url="{{ route('pm-schedules.index') }}"
        class="space-y-4">

        {{-- Area + week navigation --}}
        <section class="rounded-2xl border border-border bg-surface p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-text sm:text-2xl">Planning PM {{ $areaModel->name }}</h1>
                    <p class="text-sm text-text-muted">Minggu {{ $weekLabel }}. Jadwal ini terpisah dari PM Schedule: plan date dan PIC di sana tidak berubah.</p>
                </div>

                <form method="GET" action="{{ route('weekly-plan.index') }}" class="flex flex-wrap items-end gap-2">
                    <label class="grid gap-1 text-xs font-medium text-text-muted">
                        Area
                        <select name="area" class="select min-h-11 sm:min-h-0">
                            @foreach ($areas as $a)
                                <option value="{{ $a->slug }}" @selected($a->id === $areaModel->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-xs font-medium text-text-muted">
                        Minggu (pilih tanggal mana saja)
                        <input type="date" name="week" value="{{ $plan->week_start->toDateString() }}" class="input min-h-11 sm:min-h-0">
                    </label>
                    <button type="submit" class="btn btn-outline min-h-11 sm:min-h-0">Buka</button>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('weekly-plan.show', ['area' => $areaModel->slug, 'week' => $prevWeek]) }}" class="btn btn-ghost min-h-11 sm:min-h-0">Minggu sebelumnya</a>
                        <a href="{{ route('weekly-plan.show', ['area' => $areaModel->slug, 'week' => $nextWeek]) }}" class="btn btn-ghost min-h-11 sm:min-h-0">Minggu berikutnya</a>
                    </div>
                </form>
            </div>
        </section>

        {{-- Last week's realization (only when last week had a plan) --}}
        <section data-realization hidden class="rounded-2xl border border-border bg-surface p-4 shadow-sm sm:p-5"></section>

        {{-- Board --}}
        <section class="rounded-2xl border border-border bg-surface p-4 shadow-sm sm:p-5">
            {{-- Buttons keep a fixed order and label; changing text (save result,
                 PIC status) lives on its own line below so the buttons never move. --}}
            <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0 xl:max-w-xl">
                    <h2 class="text-base font-semibold text-text">Susun jadwal</h2>
                    <p class="text-sm text-text-muted">Seret kartu mesin ke hari, atau pilih hari langsung di kartunya. Isi otomatis mengikuti batas mesin per hari di judul tiap kolom.</p>
                </div>
                <div class="grid gap-2 sm:flex sm:shrink-0 sm:flex-wrap sm:items-center sm:gap-3 xl:justify-end">
                    <button type="button" class="btn btn-outline min-h-11 whitespace-normal sm:min-h-0 sm:whitespace-nowrap" data-pic-sync>Terapkan PIC ke PM Schedule</button>
                    <button type="button" class="btn btn-outline min-h-11 whitespace-normal sm:min-h-0 sm:whitespace-nowrap" data-reset>Kosongkan semua hari</button>
                    <button type="button" class="btn btn-primary min-h-11 whitespace-normal sm:min-h-0 sm:whitespace-nowrap" data-autofill>Isi otomatis mesin yang due minggu ini</button>
                </div>
            </div>

            <div class="mt-3 mb-4 grid min-h-10 gap-1 border-t border-border pt-3 text-sm sm:grid-cols-2 sm:gap-4">
                <p class="text-text-muted" data-save-status role="status" aria-live="polite"></p>
                <p data-pic-sync-status class="text-text-muted sm:text-right" aria-live="polite"></p>
            </div>
            <div data-board-error hidden class="alert alert-danger mb-4"></div>
            <div data-board class="grid gap-4"></div>
        </section>

        {{-- Email output --}}
        <section class="rounded-2xl border border-border bg-surface p-4 shadow-sm sm:p-5" data-email-panel></section>
    </div>

    <script type="application/json" id="weekly-plan-data">@json($payload)</script>
@endsection
