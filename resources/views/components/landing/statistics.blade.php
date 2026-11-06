@php
    // Live counts, cached for 10 minutes so a public page does not hit three large tables on every view.
    $overview = null;
    $overviewFailed = false;

    try {
        $overview = \Illuminate\Support\Facades\Cache::remember('landing.overview', 600, fn () => [
            'machines' => \App\Models\Machine::count(),
            'pm' => \App\Models\PMSchedule::whereNotNull('actual_date')->count(),
            'spareparts' => \App\Models\Sparepart::count(),
        ]);
    } catch (\Throwable $e) {
        report($e);
        $overviewFailed = true;
    }

    $cells = [
        ['key' => 'machines', 'label' => 'Machines', 'note' => 'Asset yang terkelola'],
        ['key' => 'pm', 'label' => 'PM Records', 'note' => 'Riwayat maintenance'],
        ['key' => 'spareparts', 'label' => 'Spareparts', 'note' => 'Master sparepart'],
    ];
@endphp

<section class="border-y-2 border-blue-950 bg-slate-100 py-12 lg:py-16">

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="grid items-center gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">

            <div>
                <p class="font-signage text-lg font-semibold text-blue-700">Platform Overview</p>
                <h2 class="mt-2 font-signage text-3xl font-bold leading-tight text-blue-950 sm:text-4xl">
                    Maintenance Data Dalam Satu Dashboard
                </h2>
                <p class="mt-4 max-w-xl text-slate-700">
                    Semua aktivitas Preventive Maintenance terdokumentasi secara digital,
                    mudah dicari, dan siap dianalisis kapan saja.
                </p>
                <a href="{{ route('dashboard-guest') }}"
                    class="mt-6 inline-flex min-h-12 items-center rounded-md border-2 border-blue-950 px-5 font-semibold text-blue-950 hover:bg-blue-950 hover:text-white">
                    Lihat Dashboard
                </a>
            </div>

            {{-- Sample dashboard moved here from the old hero: illustrative data, labelled as such. --}}
            <div class="min-w-0">
                <div class="plate-lift overflow-hidden rounded-lg border-2 border-blue-950 bg-white">

                    <div class="flex items-center justify-between gap-3 bg-blue-950 px-4 py-3 text-white sm:px-5">
                        <h3 class="font-signage text-lg font-semibold">Dashboard Preview</h3>
                        <span class="text-sm text-blue-100">Data contoh</span>
                    </div>

                    <div class="grid grid-cols-3 gap-px bg-slate-200">

                        <div class="col-span-3 bg-white p-4 sm:p-5">
                            <p class="text-sm font-medium text-slate-600">Today's PM</p>
                            <p class="tabular mt-1 font-signage text-6xl font-bold leading-none text-blue-950">12</p>
                        </div>

                        <div class="bg-white p-3 sm:p-4">
                            <p class="flex items-center gap-2 text-sm font-medium text-slate-600">
                                <span class="h-2.5 w-2.5 shrink-0 bg-green-600" aria-hidden="true"></span>
                                Completed
                            </p>
                            <p class="tabular mt-2 font-signage text-3xl font-bold text-blue-950">9</p>
                        </div>

                        <div class="bg-white p-3 sm:p-4">
                            <p class="flex items-center gap-2 text-sm font-medium text-slate-600">
                                <span class="h-2.5 w-2.5 shrink-0 bg-red-600" aria-hidden="true"></span>
                                Overdue
                            </p>
                            <p class="tabular mt-2 font-signage text-3xl font-bold text-red-700">3</p>
                        </div>

                        <div class="bg-white p-3 sm:p-4">
                            <p class="text-sm font-medium text-slate-600">Machine History</p>
                            <p class="tabular mt-2 font-signage text-3xl font-bold text-blue-950">1,284</p>
                        </div>

                    </div>

                    <div class="border-t-2 border-blue-950 p-4 sm:p-5">

                        <h4 class="font-signage text-lg font-semibold text-blue-950">Recent Activities</h4>

                        <ul class="mt-3 divide-y divide-slate-200 text-[15px]">

                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 bg-green-600" aria-hidden="true"></span>
                                    <span class="truncate"><span class="font-mono text-sm">PM-30001</span> Completed</span>
                                </span>
                                <span class="shrink-0 text-sm text-slate-600">10 min ago</span>
                            </li>

                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 bg-blue-600" aria-hidden="true"></span>
                                    <span class="truncate"><span class="font-mono text-sm">PM-30015</span> In Progress</span>
                                </span>
                                <span class="shrink-0 text-sm text-slate-600">25 min ago</span>
                            </li>

                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 bg-slate-400" aria-hidden="true"></span>
                                    <span class="truncate"><span class="font-mono text-sm">PM-30021</span> Scheduled</span>
                                </span>
                                <span class="shrink-0 text-sm text-slate-600">Today</span>
                            </li>

                        </ul>

                    </div>

                </div>
            </div>

        </div>

        @if ($overviewFailed)

            <p class="mt-10 border-2 border-dashed border-slate-400 p-5 text-slate-800" role="status">
                Ringkasan data belum bisa dimuat. Muat ulang halaman beberapa saat lagi.
            </p>

        @else

            <dl class="mt-10 grid grid-cols-2 border-t-2 border-blue-950 lg:grid-cols-4 lg:divide-x lg:divide-slate-300">

                @foreach ($cells as $i => $cell)
                    @php $value = $overview[$cell['key']] ?? 0; @endphp

                    <div class="border-b border-slate-300 py-6 pr-4 lg:border-b-0 lg:px-6 lg:first:pl-0 {{ $i % 2 === 1 ? 'pl-4 max-lg:border-l max-lg:border-slate-300' : '' }}">
                        <dt class="font-semibold text-slate-800">{{ $cell['label'] }}</dt>
                        <dd class="mt-1">
                            @if ($value > 0)
                                <span class="tabular font-signage text-4xl font-bold text-blue-950 sm:text-5xl">{{ number_format($value) }}</span>
                            @else
                                <span class="font-signage text-xl font-semibold text-slate-700">Belum ada data</span>
                            @endif
                            <span class="mt-1 block text-sm text-slate-700">{{ $cell['note'] }}</span>
                        </dd>
                    </div>
                @endforeach

                <div class="border-b border-slate-300 py-6 pl-4 max-lg:border-l max-lg:border-slate-300 lg:border-b-0 lg:px-6 lg:pr-0">
                    <dt class="font-semibold text-slate-800">Paperless</dt>
                    <dd class="mt-1">
                        <span class="font-signage text-4xl font-bold text-blue-950 sm:text-5xl">Digital</span>
                        <span class="mt-1 block text-sm text-slate-700">Digital Checklist</span>
                    </dd>
                </div>

            </dl>

        @endif

    </div>

</section>
