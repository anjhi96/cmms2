@extends('layouts.app')

@section('title', 'Detail WO '.$workOrder->order_number)

@section('content')
    @php
        $overdue = $workOrder->isOpen() && $workOrder->due_date->isPast() && ! $workOrder->due_date->isToday();
        $reopenedEdit = null;
        if (old('_edit_wo') && $workOrder->isManageableBy(auth()->user())) {
            $hist = $workOrder->actions->count() + $workOrder->reopens->count();
            $reopenedEdit = [
                'id' => $workOrder->id, 'url' => route('predictive.update', $workOrder), 'order' => $workOrder->order_number,
                'order_input' => old('order_number', $workOrder->order_number), 'machine_number' => old('machine_number', $workOrder->machine_number),
                'wo_date' => old('wo_date', $workOrder->wo_date->format('Y-m-d')), 'due_date' => old('due_date', $workOrder->due_date->format('Y-m-d')),
                'problem' => old('problem_description', $workOrder->problem_description),
                'locked' => $workOrder->pic_user_id !== null || $hist > 0,
            ];
        }
        $reopenedAction = null;
        if (old('_action_wo') && $workOrder->canBeActionedBy(auth()->user())) {
            $reopenedAction = [
                'id' => $workOrder->id, 'order' => $workOrder->order_number, 'machine' => $workOrder->machine_number,
                'url' => route('predictive.action', $workOrder), 'next' => $workOrder->actions->count() + 1,
            ];
        }
    @endphp

    <div x-data="{ action: @js($reopenedAction), edit: @js($reopenedEdit) }">
        <a href="{{ route('predictive.index', ['tab' => $tab]) }}" class="mb-4 inline-flex min-h-11 items-center text-sm font-semibold text-sky-700 hover:underline">&larr; Kembali ke daftar WO</a>

        @if (auth()->user()->isPic() && ! $canWork)
            <div class="mb-4 flex flex-col gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between" role="status">
                <p>Aktivitas Action Predictive belum dimulai, jadi Claim dan Action nonaktif.</p>
                <a href="{{ route('predictive.index', ['tab' => $tab]) }}" class="inline-flex min-h-11 shrink-0 items-center font-semibold text-sky-700 hover:underline">Buka daftar WO untuk memulai</a>
            </div>
        @endif

        @foreach ([
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'error' => 'border-rose-200 bg-rose-50 text-rose-800',
        ] as $flash => $classes)
            @if (session($flash))
                <div class="mb-4 rounded-xl border px-4 py-3 text-sm font-medium {{ $classes }}" role="{{ $flash === 'error' ? 'alert' : 'status' }}">{{ session($flash) }}</div>
            @endif
        @endforeach

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-slate-500">{{ $workOrder->categoryLabel() }}</p>
                    <h1 class="break-words text-2xl font-bold text-slate-900">{{ $workOrder->order_number }}</h1>
                </div>
                <div class="flex items-center gap-2">@include('predictive.partials.status-badge', ['wo' => $workOrder])</div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm md:grid-cols-4">
                <div><dt class="text-xs text-slate-500">Machine Number</dt><dd class="font-semibold text-slate-900">{{ $workOrder->machine_number }}</dd></div>
                <div><dt class="text-xs text-slate-500">Machine Type</dt><dd class="font-medium text-slate-800">{{ $workOrder->machine_type ?: '-' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Area</dt><dd class="font-medium text-slate-800">{{ $workOrder->area }}</dd></div>
                <div><dt class="text-xs text-slate-500">PIC</dt><dd class="font-medium text-slate-800">{{ $workOrder->pic?->name ?? 'Belum di-claim' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Tanggal WO</dt><dd class="font-medium text-slate-800">{{ $workOrder->wo_date->format('d-m-Y') }}</dd></div>
                <div><dt class="text-xs text-slate-500">Due Date</dt><dd class="font-medium {{ $overdue ? 'text-rose-700' : 'text-slate-800' }}">{{ $workOrder->due_date->format('d-m-Y') }}{{ $overdue ? ' (terlambat)' : '' }}</dd></div>
                <div class="col-span-2"><dt class="text-xs text-slate-500">Dibuka</dt><dd class="font-medium text-slate-800">{{ $workOrder->created_at->format('d-m-Y H:i') }}</dd></div>
            </dl>

            <div class="mt-4">
                <h2 class="text-xs text-slate-500">Problem Description</h2>
                <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-800">{{ $workOrder->problem_description }}</p>
            </div>

            <div class="mt-5">@include('predictive.partials.wo-buttons', ['wo' => $workOrder, 'size' => 'card', 'showDetail' => false])</div>
        </section>

        <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="Riwayat">
            <h2 class="font-bold text-slate-900">Riwayat Action dan Reopen</h2>
            @if ($history->isEmpty())
                <p class="mt-3 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">Belum ada Action. WO ini menunggu PIC melakukan claim.</p>
            @else
                <ol class="mt-4 space-y-4 border-l border-slate-200 pl-4">
                    @foreach ($history as $event)
                        @if ($event['type'] === 'action')
                            @php $a = $event['model']; @endphp
                            <li class="relative">
                                <span class="absolute -left-[21px] top-1.5 size-2.5 rounded-full bg-slate-900" aria-hidden="true"></span>
                                <p class="text-sm font-semibold text-slate-900">Action #{{ $a->action_number }} <span class="font-normal text-slate-500">· {{ $a->actioned_at->format('d-m-Y H:i') }} · {{ $a->pic_name }}</span></p>
                                <ul class="mt-1.5 space-y-2 text-sm">
                                    @foreach ($a->problems as $problem)
                                        <li>
                                            <p class="break-words"><span class="text-slate-500">Problem: </span><span class="font-medium text-slate-800">{{ $problem->problem }}</span></p>
                                            <ul class="mt-0.5 list-disc space-y-0.5 pl-5 text-slate-700">
                                                @foreach ($problem->findings as $finding)
                                                    <li class="break-words"><span class="text-slate-500">Finding: </span>{{ $finding->finding }}</li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    @endforeach
                                    @if ($a->remarks)<li><span class="text-slate-500">Remarks: </span><span class="whitespace-pre-line break-words text-slate-800">{{ $a->remarks }}</span></li>@endif
                                </ul>
                            </li>
                        @else
                            @php $r = $event['model']; @endphp
                            <li class="relative">
                                <span class="absolute -left-[21px] top-1.5 size-2.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                <p class="text-sm font-semibold text-amber-800">Reopen <span class="font-normal text-slate-500">· {{ $r->reopened_at->format('d-m-Y H:i') }} · {{ $r->user_name }}</span></p>
                            </li>
                        @endif
                    @endforeach
                </ol>
            @endif
        </section>

        @if ($workOrder->isManageableBy(auth()->user()))
            @include('predictive.partials.machine-picker-script')
            @include('predictive.partials.edit-modal')
        @endif
        @include('predictive.partials.action-modal')
    </div>
@endsection
