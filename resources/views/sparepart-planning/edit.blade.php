@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Mandatory Sparepart</h1>
            <p class="mt-1 text-sm text-slate-500">
                Changes only affect future Fill PM forms. PM records that are already saved are not modified.
            </p>
        </div>

        <form action="{{ route('sparepart-planning.update', $mandatory) }}" method="POST" class="space-y-5 p-6">
            @csrf
            @method('PUT')

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Machine Type</label>
                <select name="machine_type" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected(old('machine_type', $mandatory->machine_type) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                @error('machine_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Sparepart</label>
                <select name="sparepart_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                    @foreach ($spareparts as $sp)
                        <option value="{{ $sp->id }}" @selected((int) old('sparepart_id', $mandatory->sparepart_id) === $sp->id)>{{ $sp->material_number }} - {{ $sp->description }}</option>
                    @endforeach
                </select>
                @error('sparepart_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Qty per PM</label>
                <input type="number" name="qty" min="1" step="1" value="{{ old('qty', $mandatory->qty) }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                @error('qty') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Save</button>
                <a href="{{ route('sparepart-planning.index', ['tab' => 'mandatory']) }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
