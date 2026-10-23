@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Create PM Schedule</h1>
            <p class="mt-1 text-sm text-slate-500">Plan a new preventive maintenance schedule for a machine.</p>
            <p class="mt-1 text-xs text-slate-400">PIC is assigned afterwards from the schedule list — the same as an imported schedule.</p>
        </div>

        <form method="POST" action="{{ route('pm-schedules.store') }}" class="space-y-5 p-6">
            @csrf

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Machine</label>
                <select name="machine_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="">-- Select Machine --</option>
                    @foreach($machines as $machine)
                        <option value="{{ $machine->id }}" {{ (string) old('machine_id') === (string) $machine->id ? 'selected' : '' }}>{{ $machine->machine_number }} - {{ $machine->machine_type }}</option>
                    @endforeach
                </select>
                @error('machine_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Order Number</label>
                    <input type="text" name="order_number" value="{{ old('order_number') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    @error('order_number')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Plan Month</label>
                    <select name="plan_month" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        <option value="">-- Select Month --</option>
                        @foreach (['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $monthName)
                            <option value="{{ $monthName }}" {{ old('plan_month') === $monthName ? 'selected' : '' }}>{{ $monthName }}</option>
                        @endforeach
                    </select>
                    @error('plan_month')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Plan Year</label>
                    <input type="number" name="plan_year" value="{{ old('plan_year', date('Y')) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    @error('plan_year')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Plan Date</label>
                    <input type="date" name="plan_date" value="{{ old('plan_date') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-400">Due date is auto-calculated as plan date + 14 days.</p>
                    @error('plan_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Save</button>
                <a href="{{ route('pm-schedules.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection