@extends('layouts.app')

@php $contentMaxWidth = 'max-w-full'; @endphp
@section('title', 'Action Predictive')

@section('content')
    @php
        $isDashboard = $tab === 'dashboard';
        $reopenedAction = null;
        if (old('_action_wo')) {
            $reopenedWo = \App\Models\PredictiveWorkOrder::find((int) old('_action_wo'));
            if ($reopenedWo && $reopenedWo->canBeActionedBy(auth()->user())) {
                $reopenedAction = [
                    'id' => $reopenedWo->id, 'order' => $reopenedWo->order_number, 'machine' => $reopenedWo->machine_number,
                    'url' => route('predictive.action', $reopenedWo), 'next' => $reopenedWo->actions()->count() + 1,
                ];
            }
        }
        $reopenedEdit = null;
        if (old('_edit_wo')) {
            $editWo = \App\Models\PredictiveWorkOrder::withCount(['actions', 'reopens'])->find((int) old('_edit_wo'));
            if ($editWo && $editWo->isManageableBy(auth()->user())) {
                $reopenedEdit = [
                    'id' => $editWo->id, 'url' => route('predictive.update', $editWo), 'order' => $editWo->order_number,
                    'order_input' => old('order_number', $editWo->order_number), 'machine_number' => old('machine_number', $editWo->machine_number),
                    'wo_date' => old('wo_date', $editWo->wo_date->format('Y-m-d')), 'due_date' => old('due_date', $editWo->due_date->format('Y-m-d')),
                    'problem' => old('problem_description', $editWo->problem_description),
                    'locked' => $editWo->pic_user_id !== null || $editWo->actions_count + $editWo->reopens_count > 0,
                ];
            }
        }
        $inputClass = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100';
    @endphp

    <div x-data="{ promptOpen: @json($promptStart), startOpen: false, startAt: '', openStart() { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); this.startAt = d.toISOString().slice(0, 16); this.promptOpen = false; this.startOpen = true; }, addOpen: {{ old('_form') === 'add' ? 'true' : 'false' }}, importOpen: {{ $errors->has('file') ? 'true' : 'false' }}, action: @js($reopenedAction), edit: @js($reopenedEdit) }">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Action Predictive</h1>
                <p class="mt-1 text-sm text-slate-500">Work order hasil pemeriksaan Vibration dan Air Leaked: claim, closing, dan reopen.</p>
            </div>
            @if ($canManage && ! $isDashboard)
                <div class="flex gap-2">
                    <button type="button" @click="importOpen = true" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:flex-none">Import WO</button>
                    <button type="button" @click="addOpen = true" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 sm:flex-none">Add WO</button>
                </div>
            @endif
        </div>

        <nav class="mb-5 flex overflow-x-auto overflow-y-hidden border-b border-slate-200 pb-px sm:gap-1" aria-label="Tab Action Predictive">
            @foreach ($tabs as $key => $t)
                @php $active = $tab === $key; @endphp
                <a href="{{ route('predictive.index', ['tab' => $key]) }}" @if ($active) aria-current="page" @endif
                    class="-mb-px inline-flex min-h-11 flex-1 items-center justify-center gap-2 whitespace-nowrap border-b-2 px-1.5 py-2 text-sm font-semibold focus:outline-none sm:flex-none sm:px-3 focus-visible:ring-2 focus-visible:ring-sky-400 {{ $active ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                    @if ($key === 'dashboard')
                        Dashboard
                    @else
                        <span><span class="hidden sm:inline">Action Predictive </span>{{ \Illuminate\Support\Str::after($t['label'], 'Action Predictive ') }}</span>
                    @endif
                    @if ($t['open'] !== null)
                        <span class="hidden rounded-full bg-amber-100 px-2 py-0.5 sm:inline text-xs font-semibold text-amber-800" title="{{ $t['open'] }} WO open">{{ $t['open'] }}<span class="hidden sm:inline"> open</span></span>
                    @endif
                </a>
            @endforeach
        </nav>

        @foreach ([
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'error' => 'border-rose-200 bg-rose-50 text-rose-800',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        ] as $flash => $classes)
            @if (session($flash))
                <div class="mb-4 rounded-xl border px-4 py-3 text-sm font-medium {{ $classes }}" role="{{ $flash === 'error' ? 'alert' : 'status' }}">{{ session($flash) }}</div>
            @endif
        @endforeach

        @if (auth()->user()->isPic() && ! $canWork)
            <div class="mb-4 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between" role="status">
                <p>Aktivitas Action Predictive belum dimulai. Claim dan Action aktif setelah Anda memulainya.</p>
                <button type="button" @click="openStart()" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Start Predictive</button>
            </div>
        @endif

        @if (session('predictive_import_result'))
            @include('predictive.partials.import-result')
        @endif

        @if ($isDashboard)
            @include('predictive.partials.dashboard')
        @else
            <form method="GET" action="{{ route('predictive.index') }}" class="mb-5 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input name="search" value="{{ request('search') }}" placeholder="Cari order, mesin, tipe, atau problem" aria-label="Cari WO" class="{{ $inputClass }} sm:w-72">
                <select name="status" aria-label="Status" class="{{ $inputClass }} sm:w-40">
                    <option value="">Semua status</option>
                    <option value="OPEN" @selected(request('status') === 'OPEN')>OPEN</option>
                    <option value="CLOSED" @selected(request('status') === 'CLOSED')>CLOSED</option>
                </select>
                <select name="claim" aria-label="Claim" class="{{ $inputClass }} sm:w-44">
                    <option value="">Semua claim</option>
                    <option value="unclaimed" @selected(request('claim') === 'unclaimed')>Belum di-claim</option>
                    @if (auth()->user()->isPic())
                        <option value="mine" @selected(request('claim') === 'mine')>Claim saya</option>
                    @endif
                </select>
                @if ($areaOptions->isNotEmpty())
                    <select name="area" aria-label="Area" class="{{ $inputClass }} sm:w-36">
                        <option value="">Semua area</option>
                        @foreach ($areaOptions as $a)
                            <option value="{{ $a }}" @selected(request('area') === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                @endif
                <div class="flex w-full items-center gap-2 text-sm text-slate-600 sm:w-auto">
                    <span class="shrink-0">Tanggal WO</span>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Dari tanggal WO" class="{{ $inputClass }} min-w-0 flex-1 sm:w-40 sm:flex-none">
                    <span class="shrink-0">s/d</span>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Sampai tanggal WO" class="{{ $inputClass }} min-w-0 flex-1 sm:w-40 sm:flex-none">
                </div>
                <div class="flex w-full gap-2 sm:w-auto">
                    <button class="min-h-11 flex-1 rounded-lg bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 sm:flex-none">Filter</button>
                    <a href="{{ route('predictive.index', ['tab' => $tab]) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-900 sm:flex-none">Reset</a>
                </div>
            </form>

            @if ($workOrders->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
                    @if (request()->hasAny(['search', 'status', 'claim', 'area', 'date_from', 'date_to']))
                        Tidak ada WO {{ $categoryLabel }} yang sesuai filter.
                    @else
                        Belum ada WO {{ $categoryLabel }}.@if ($canManage) Gunakan Add WO atau Import WO untuk menambahkan.@endif
                    @endif
                </div>
            @else
                <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    @foreach (['Order Number', 'Area', 'Machine', 'Date / Due Date', 'Problem Description', 'PIC', 'Status', 'Action'] as $h)
                                        <th scope="col" class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white" data-infinite-list="wos">
                                @foreach ($workOrders as $wo)
                                    @include('predictive.partials.wo-row', ['wo' => $wo])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-3 md:hidden" data-infinite-list="wos">
                    @foreach ($workOrders as $wo)
                        @include('predictive.partials.wo-card', ['wo' => $wo])
                    @endforeach
                </div>

                {{-- Table and card list share one key: the loader appends to both, only one is visible. --}}
                <div class="mt-5"><x-infinite-scroll :paginator="$workOrders" key="wos" /></div>
            @endif

            @if ($canManage)
                @include('predictive.partials.machine-picker-script')
                @include('predictive.partials.add-modal')
                @include('predictive.partials.import-modal')
                @include('predictive.partials.edit-modal')
            @endif
        @endif

        @include('predictive.partials.action-modal')

        @if (auth()->user()->isPic())
            {{-- Daily Start prompt, same flow as Oil Audit Action: NO only closes it (the banner above stays), YES asks for the start time. --}}
            @if ($promptStart)
            <div x-show="promptOpen" x-cloak x-transition.opacity.duration.150ms @keydown.escape.window="promptOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-labelledby="predictive-prompt-title">
                <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
                    <h3 id="predictive-prompt-title" class="text-lg font-semibold text-slate-800">APAKAH ANDA INGIN MEMULAI ACTION PREDICTIVE?</h3>
                    <p class="mt-2 text-sm text-slate-500">Aktivitas ini tercatat di Today's Activity. Tanpa memulainya, Anda belum bisa Claim maupun mengisi Action.</p>
                    <div class="mt-5 flex justify-center gap-3">
                        <button type="button" @click="promptOpen = false" class="min-h-11 rounded-lg border border-slate-300 px-6 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">NO</button>
                        <button type="button" @click="openStart()" class="min-h-11 rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white hover:bg-blue-700">YES</button>
                    </div>
                </div>
            </div>
            @endif

            <div x-show="startOpen" x-cloak x-transition.opacity.duration.150ms @keydown.escape.window="startOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-labelledby="predictive-start-title">
                <div @click.outside="startOpen = false" class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                    <h3 id="predictive-start-title" class="text-lg font-semibold text-slate-800">Start Action Predictive Activity</h3>
                    <form method="POST" action="{{ route('predictive.start') }}" class="mt-4 space-y-4" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <div>
                            <label for="predictive-start-at" class="mb-1 block text-sm font-medium text-slate-700">Start Date &amp; Time</label>
                            <input id="predictive-start-at" type="datetime-local" name="started_at" x-model="startAt" required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="startOpen = false" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Cancel</button>
                            <button type="submit" x-bind:disabled="busy" class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">START</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    @include('partials.activity-conflict-modal')
@endsection
