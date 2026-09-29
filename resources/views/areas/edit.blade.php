@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Area</h1>
            <p class="mt-1 text-sm text-slate-500">
                The name cannot be changed here — only whether the area is
                active. Deactivating an area hides it from new selections
                (User Management, Machines, dashboards) without touching any
                existing data that already references it.
            </p>
        </div>

        <form action="{{ route('areas.update', $area) }}" method="POST" class="space-y-5 p-6">
            @csrf
            @method('PUT')

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Area Name</label>
                <input type="text" value="{{ $area->name }}" disabled
                    class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm font-medium text-slate-500">
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
                <select name="is_active" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="1" @selected(old('is_active', $area->is_active) == 1)>Active</option>
                    <option value="0" @selected(old('is_active', $area->is_active) == 0)>Inactive</option>
                </select>
                @error('is_active')
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
