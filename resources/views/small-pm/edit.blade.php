@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Small PM Schedule</h1>
            <p class="mt-1 text-sm text-slate-500">Action date dan status diisi dari halaman Execute.</p>
        </div>

        <form action="{{ route('small-pm.update', $schedule) }}" method="POST" class="space-y-5 p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            @include('small-pm._form', ['schedule' => $schedule])

            <div class="flex flex-wrap gap-3 pt-2">
                <button class="min-h-11 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Simpan Perubahan</button>
                <a href="{{ route('small-pm.index', ['tab' => $tab]) }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
