@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl space-y-6" x-data="{ recipients: @js(array_values($recipients)) }">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Notifikasi Email</h1>
            <p class="mt-1 text-sm text-slate-500">
                Kirim email otomatis ke daftar penerima saat Oil Audit tersimpan dengan kondisi KRITIS
                (input online maupun hasil sinkronisasi offline).
            </p>
        </div>

        @if (session('success'))
            <div class="mx-6 mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mx-6 mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <form action="{{ route('email-notifications.update') }}" method="POST" class="space-y-5 p-6">
            @csrf
            @method('PUT')

            <label class="flex items-center gap-3 text-sm font-medium text-slate-700">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $setting->enabled)) class="h-4 w-4 rounded border-slate-300">
                Aktifkan notifikasi email Oil Audit KRITIS
            </label>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Alamat Gmail pengirim</label>
                <input type="email" name="sender_email" value="{{ old('sender_email', $setting->sender_email) }}" placeholder="nama@gmail.com" autocomplete="off"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                @error('sender_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Gmail App Password</label>
                <input type="password" name="app_password" autocomplete="new-password"
                    placeholder="{{ $hasPassword ? '•••••••••••••••• (tersimpan — kosongkan jika tidak diubah)' : '16 huruf dari Google' }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                <p class="mt-1 text-xs text-slate-500">
                    Bukan password Gmail biasa. Buat di akun Google (verifikasi 2 langkah wajib aktif); kebijakan Google Workspace dapat membatasinya.
                    Disimpan terenkripsi dan tidak pernah ditampilkan kembali.
                </p>
                @error('app_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Email penerima</label>
                <div class="space-y-2">
                    <template x-for="(r, i) in recipients" :key="i">
                        <div class="flex gap-2">
                            <input type="email" name="recipients[]" x-model="recipients[i]" placeholder="penerima@perusahaan.com"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                            <button type="button" @click="recipients.splice(i, 1); if (!recipients.length) recipients.push('')"
                                class="rounded-xl border border-slate-300 px-3 text-sm text-slate-600 hover:bg-slate-100" aria-label="Hapus penerima">Hapus</button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="recipients.push('')" class="mt-2 text-sm font-medium text-blue-600 hover:underline">+ Tambah penerima</button>
                @error('recipients') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @foreach ($errors->get('recipients.*') as $messages)
                    <p class="mt-1 text-sm text-red-600">{{ $messages[0] }}</p>
                @endforeach
            </div>

            <div class="pt-2">
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Simpan</button>
            </div>
        </form>

        <form action="{{ route('email-notifications.test') }}" method="POST" class="border-t border-slate-200 px-6 py-5">
            @csrf
            <button class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Kirim Email Tes</button>
            <span class="ml-2 text-xs text-slate-500">Mengirim ke penerima yang sudah tersimpan; hasilnya adalah hasil SMTP sebenarnya.</span>
        </form>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">Status pengiriman</h2>
        <div class="mt-2 rounded-xl border px-4 py-3 text-sm {{ $worker['active'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' }}">
            <strong>Queue worker: {{ $worker['active'] ? 'AKTIF' : 'TIDAK AKTIF' }}</strong>
            — {{ $worker['message'] }}
            @if ($worker['pending'] > 0) Antrean menunggu: {{ $worker['pending'] }} job. @endif
        </div>
        @if ($setting->last_test_at)
            <p class="mt-2 text-sm {{ $setting->last_test_ok ? 'text-emerald-600' : 'text-red-600' }}">
                Tes terakhir ({{ $setting->last_test_at->timezone(config('app.timezone'))->format('d-m-Y H:i') }}): {{ $setting->last_test_message }}
            </p>
        @else
            <p class="mt-2 text-sm text-slate-500">Belum ada email tes untuk konfigurasi ini.</p>
        @endif

        <div class="mt-3 space-y-1 text-sm">
            @forelse ($recent as $n)
                <div>
                    <span class="font-medium">{{ $n->oilAudit?->machine_number ?? '—' }}</span>
                    — {{ match ($n->status) { 'sent' => 'Terkirim', 'failed' => 'Gagal (akan dicoba ulang)', 'skipped' => 'Dilewati', 'sending' => 'Mengirim', default => 'Antre' } }}
                    @if ($n->last_error) <span class="text-red-600">({{ $n->last_error }})</span> @endif
                </div>
            @empty
                <p class="text-slate-500">Belum ada notifikasi KRITIS.</p>
            @endforelse
        </div>
        <p class="mt-3 text-xs text-slate-500">Pengiriman otomatis berjalan lewat queue; jalankan <code>php artisan queue:work</code>. Status worker dihitung dari sinyal terakhir worker (maks. 2 menit lalu).</p>
    </div>
</div>
@endsection
