@props([
    'title',
    'description',
])

<div class="flex w-full flex-col">
    <h1 class="font-signage text-3xl font-bold leading-tight text-blue-950 sm:text-4xl dark:text-white">{{ $title }}</h1>
    <p class="mt-2 text-slate-700 dark:text-slate-300">{{ $description }}</p>
</div>
