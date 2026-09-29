@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Add Area</h1>
            <p class="mt-1 text-sm text-slate-500">
                Create a new operational area (e.g. GRIPPER, PACKING). Once
                saved, it appears everywhere areas are selected — no further
                setup needed.
            </p>
        </div>

        <form action="{{ route('areas.store') }}" method="POST" class="space-y-5 p-6">
            @csrf

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Area Name</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. GRIPPER"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                <p class="mt-1 text-xs text-slate-500">
                    The name cannot be changed after creation — machines, PM
                    schedules, and users already assigned to an area are
                    matched against this exact value.
                </p>
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Save</button>
                <a href="{{ route('areas.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
