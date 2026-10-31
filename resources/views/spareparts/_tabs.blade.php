@php
    $tabUser = auth()->user();
    $tabClass = fn ($active) => 'rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition '
        . ($active ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700');
@endphp
{{-- PIC only has the master list, so no tab bar is shown to them. --}}
@if ($tabUser->isAdmin() || $tabUser->isKoordinator() || $tabUser->isSupervisor())
    <div class="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200">
        <a href="{{ route('spareparts.index') }}" class="{{ $tabClass(request()->routeIs('spareparts.*')) }}">Sparepart Master</a>
        <a href="{{ route('sparepart-injection.index') }}" class="{{ $tabClass(request()->routeIs('sparepart-injection.*')) }}">Sparepart Injection</a>
    </div>
@endif
