@extends('layouts.app')

@php
    $tabClass = fn ($active) => 'whitespace-nowrap rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition '
        . ($active ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700');
    $input = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-800">Machine QR Management</h1>
        <p class="text-sm text-slate-500">Generate printable machine QR labels and correct the Last PM shown in Machine History.</p>
    </div>

    <div class="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200">
        <a href="{{ route('machine-qr.index', ['tab' => 'qr']) }}" class="{{ $tabClass($tab === 'qr') }}">QR Code Management</a>
        <a href="{{ route('machine-qr.index', ['tab' => 'override']) }}" class="{{ $tabClass($tab === 'override') }}">Last PM Override</a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    {{-- Shared search / filter bar (GET, so it survives reload + pagination) --}}
    <form method="GET" action="{{ route('machine-qr.index') }}" class="mb-4 grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="search" name="search" value="{{ $search }}" placeholder="Search machine number / name" class="{{ $input }}">
        <select name="area" class="{{ $input }}">
            <option value="">All Areas</option>
            @foreach ($areas as $a)
                <option value="{{ $a }}" @selected($area === $a)>{{ $a }}</option>
            @endforeach
        </select>
        @if ($tab === 'override')
            <select name="status" class="{{ $input }}">
                <option value="">All Status</option>
                <option value="normal" @selected($status === 'normal')>Normal</option>
                <option value="overridden" @selected($status === 'overridden')>Overridden</option>
            </select>
        @endif
        <div class="flex gap-2">
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Filter</button>
            <a href="{{ route('machine-qr.index', ['tab' => $tab]) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Reset</a>
        </div>
    </form>

    @if ($tab === 'qr')
        @include('machine-qr._qr-tab')
    @else
        @include('machine-qr._override-tab')
    @endif
@endsection
