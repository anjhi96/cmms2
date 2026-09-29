@props([
    'sidebar' => false,
])

@if($sidebar)
<<<<<<< HEAD
    <flux:sidebar.brand name="FreeDOMS" {{ $attributes }}>
=======
    <flux:sidebar.brand name="{{ config('app.name', 'FreeDOMS') }}" {{ $attributes }}>
>>>>>>> 5dda833a70f4e6d016c5b572a7097833c4248005
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:sidebar.brand>
@else
<<<<<<< HEAD
    <flux:brand name="FreeDOMS" {{ $attributes }}>
=======
    <flux:brand name="{{ config('app.name', 'FreeDOMS') }}" {{ $attributes }}>
>>>>>>> 5dda833a70f4e6d016c5b572a7097833c4248005
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:brand>
@endif
