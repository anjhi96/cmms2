{{-- Name, area, order. $row is null on create. --}}
<div>
    <label for="row-name" class="mb-2 block text-sm font-medium text-slate-700">Nama Row</label>
    <input id="row-name" type="text" name="name" value="{{ old('name', $row?->name) }}" placeholder="Misalnya Row 01" required
        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
    @error('name')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="row-area" class="mb-2 block text-sm font-medium text-slate-700">Area</label>
        <select id="row-area" name="area_id" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
            @foreach ($areas as $area)
                <option value="{{ $area->id }}" @selected((string) old('area_id', $row?->area_id ?? ($areas->count() === 1 ? $area->id : '')) === (string) $area->id)>{{ $area->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Menentukan mesin yang bisa dipilih dan PIC yang boleh mengerjakan.</p>
        @error('area_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="row-order" class="mb-2 block text-sm font-medium text-slate-700">Urutan</label>
        <input id="row-order" type="number" name="sort_order" min="0" max="9999" required value="{{ old('sort_order', $row?->sort_order ?? $nextOrder ?? 0) }}"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
        <p class="mt-1 text-xs text-slate-500">Generate jadwal mengisi row dari urutan terkecil, 4 row per minggu.</p>
        @error('sort_order')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
