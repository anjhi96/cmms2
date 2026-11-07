{{--
    Per-WO actions. The same permission methods the controller enforces
    (PredictiveWorkOrder::canBe*By) decide what is shown, so a hidden button
    is never the only protection.

    Expects: $wo, $size ('row' | 'card'), optional $showDetail (default true)
--}}
@php
    $u = auth()->user();
    $btn = ($size ?? 'row') === 'card'
        ? 'inline-flex min-h-11 flex-1 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold'
        : 'inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold';
    $focus = 'focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-1';
@endphp
<div class="flex flex-wrap items-center gap-2">
    @if ($showDetail ?? true)
        <a href="{{ route('predictive.show', $wo) }}" class="{{ $btn }} {{ $focus }} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Detail</a>
    @endif

    @if ($wo->canBeClaimedBy($u))
        <form method="POST" action="{{ route('predictive.claim', $wo) }}" class="{{ ($size ?? 'row') === 'card' ? 'flex-1' : '' }}">
            @csrf
            <button type="submit" class="{{ $btn }} {{ $focus }} w-full bg-sky-600 text-white hover:bg-sky-700">Claim WO</button>
        </form>
    @endif

    @if ($wo->canBeActionedBy($u))
        <button type="button"
            @click="action = @js(['id' => $wo->id, 'order' => $wo->order_number, 'machine' => $wo->machine_number, 'url' => route('predictive.action', $wo), 'next' => ($wo->actions_count ?? $wo->actions()->count()) + 1])"
            class="{{ $btn }} {{ $focus }} bg-slate-900 text-white hover:bg-slate-700">Action / Closing</button>
    @endif

    @if ($wo->canBeReopenedBy($u))
        <form method="POST" action="{{ route('predictive.reopen', $wo) }}" class="{{ ($size ?? 'row') === 'card' ? 'flex-1' : '' }}"
            onsubmit="return confirm('Reopen WO {{ $wo->order_number }}? Status kembali OPEN dan WO bisa di-claim PIC lagi. Riwayat Action tetap tersimpan.')">
            @csrf
            <button type="submit" class="{{ $btn }} {{ $focus }} w-full border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100">Reopen</button>
        </form>
    @endif

    @if ($wo->isManageableBy($u))
        @php
            $historyCount = ($wo->actions_count ?? $wo->actions()->count()) + ($wo->reopens_count ?? $wo->reopens()->count());
            $editPayload = [
                'id' => $wo->id,
                'url' => route('predictive.update', $wo),
                'order' => $wo->order_number,
                'order_input' => $wo->order_number,
                'machine_number' => $wo->machine_number,
                'wo_date' => $wo->wo_date->format('Y-m-d'),
                'due_date' => $wo->due_date->format('Y-m-d'),
                'problem' => $wo->problem_description,
                'locked' => $wo->pic_user_id !== null || $historyCount > 0,
            ];
        @endphp
        <button type="button" @click="edit = @js($editPayload)"
            class="{{ $btn }} {{ $focus }} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Edit</button>
        <form method="POST" action="{{ route('predictive.destroy', $wo) }}" class="{{ ($size ?? 'row') === 'card' ? 'flex-1' : '' }}"
            onsubmit="return confirm(@js('Hapus WO '.$wo->order_number.'?'.($historyCount > 0 ? ' Seluruh riwayat Action dan Reopen WO ini ikut terhapus.' : '').' Tindakan ini tidak bisa dibatalkan.'))">
            @csrf
            @method('DELETE')
            <button type="submit" class="{{ $btn }} {{ $focus }} w-full border border-rose-200 bg-white text-rose-700 hover:bg-rose-50">Hapus</button>
        </form>
    @endif
</div>
