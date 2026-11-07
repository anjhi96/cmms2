@if ($wo->isOpen())
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800"><span class="size-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>OPEN</span>
@else
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800"><span class="size-1.5 rounded-full bg-emerald-600" aria-hidden="true"></span>CLOSED</span>
@endif
