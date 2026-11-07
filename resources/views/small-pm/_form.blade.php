{{-- Schedule fields shared by create and edit. $schedule is null on create. --}}
@php($input = 'w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none')
<div class="grid gap-5 md:grid-cols-2" x-data="{ rowArea: @js((string) optional($rows->firstWhere('id', (int) old('small_pm_row_id', $schedule?->small_pm_row_id)))->area_id) }">
    <div>
        <label for="f-row" class="mb-2 block text-sm font-medium text-slate-700">Row</label>
        <select id="f-row" name="small_pm_row_id" required class="{{ $input }}"
            @change="rowArea = $event.target.selectedOptions[0]?.dataset.area || ''">
            <option value="">-- Pilih Row --</option>
            @foreach ($rows as $r)
                <option value="{{ $r->id }}" data-area="{{ $r->area_id }}" @selected((string) old('small_pm_row_id', $schedule?->small_pm_row_id) === (string) $r->id)>
                    {{ $r->name }}{{ $r->area ? ' ('.$r->area->name.')' : '' }}
                </option>
            @endforeach
        </select>
        @error('small_pm_row_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="f-order" class="mb-2 block text-sm font-medium text-slate-700">Order Number <span class="font-normal text-slate-500">(opsional)</span></label>
        <input id="f-order" type="text" name="order_number" value="{{ old('order_number', $schedule?->order_number) }}" class="{{ $input }}">
        @error('order_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="f-cycle" class="mb-2 block text-sm font-medium text-slate-700">Cycle</label>
        <input id="f-cycle" type="text" name="cycle" required value="{{ old('cycle', $schedule?->cycle ?? $defaultCycle ?? '26W') }}" placeholder="Misalnya 26W" class="{{ $input }}">
        <p class="mt-1 text-xs text-slate-500">Dalam minggu. Setelah Execute, jadwal berikutnya dibuat cycle ini setelah action date.</p>
        @error('cycle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="f-plan" class="mb-2 block text-sm font-medium text-slate-700">Plan Date</label>
        <input id="f-plan" type="date" name="plan_date" required value="{{ old('plan_date', $schedule?->plan_date?->format('Y-m-d')) }}" class="{{ $input }}">
        <p class="mt-1 text-xs text-slate-500">Due date dihitung otomatis: plan date + 14 hari.</p>
        @error('plan_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="f-pic" class="mb-2 block text-sm font-medium text-slate-700">PIC <span class="font-normal text-slate-500">(opsional)</span></label>
        <select id="f-pic" name="pic_user_id" class="{{ $input }}">
            <option value="">Belum ada (siapa saja di area row)</option>
            @foreach ($picsByArea as $areaId => $pics)
                @foreach ($pics as $p)
                    <option value="{{ $p->id }}" data-area="{{ $areaId }}" x-show="rowArea === '' || rowArea === '{{ $areaId }}'"
                        @selected((string) old('pic_user_id', $schedule?->pic_user_id) === (string) $p->id)>{{ $p->name }}</option>
                @endforeach
            @endforeach
        </select>
        @error('pic_user_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label for="f-remarks" class="mb-2 block text-sm font-medium text-slate-700">Remarks</label>
        <textarea id="f-remarks" name="remarks" class="min-h-24 {{ $input }}">{{ old('remarks', $schedule?->remarks) }}</textarea>
        @error('remarks') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
