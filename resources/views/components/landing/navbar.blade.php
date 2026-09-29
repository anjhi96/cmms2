<nav class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-slate-200">

    <div class="max-w-7xl mx-auto px-6">

        <div class="flex items-center justify-between h-18">

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex items-center gap-3">

                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-600 to-cyan-500 flex items-center justify-center shadow-md shadow-blue-500/20 text-white">
                    <x-app-logo-icon class="h-6 w-6 text-white" />
                </div>

                <div>

                    <h1 class="font-bold text-xl text-slate-800">

                        FreeDOMS

                    </h1>

                    <p class="text-xs text-slate-500">

                        Preventive Maintenance

                    </p>

                </div>

            </a>

            {{-- Menu Desktop --}}
            <div class="hidden lg:flex items-center gap-8">

                <a href="#features" class="text-slate-600 hover:text-blue-600 transition">

                    Features

                </a>

                <a href="#how-it-works" class="text-slate-600 hover:text-blue-600 transition">

                    How It Works

                </a>

                <a href="#testimonial" class="text-slate-600 hover:text-blue-600 transition">

                    Testimonial

                </a>

                <a href="{{ route('dashboard-guest') }}" class="text-slate-600 hover:text-blue-600 transition">

                    Dashboard

                </a>

                <a href="{{ route('monitor') }}" class="text-slate-600 hover:text-blue-600 transition">

                    Monitor

                </a>

                {{-- PM Status is split per area (no single "all area" page),
                     so this is a small dropdown to the two area URLs rather
                     than one link — picking a default area here would be
                     arbitrary. --}}
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-1 text-slate-600 hover:text-blue-600 transition">

                        PM Status

                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                            <path d="m6 9 6 6 6-6" />
                        </svg>

                    </button>

                    <div x-show="open" x-cloak x-transition
                        class="absolute left-0 z-40 mt-2 w-32 rounded-xl border border-slate-200 bg-white py-2 shadow-lg">

                        <a href="{{ route('pm-status.show', 'wwd') }}"
                            class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-blue-600">
                            WWD
                        </a>

                        <a href="{{ route('pm-status.show', 'bul') }}"
                            class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-blue-600">
                            BUL
                        </a>

                    </div>
                </div>

            </div>

            {{-- Action --}}
            <div class="flex items-center gap-3">

                @guest
                    <a href="{{ route('login') }}"
                        class="hidden md:inline-flex px-3 py-2 rounded-xl border border-slate-300 hover:bg-slate-100 transition">

                        Login

                    </a>
                @else
                    <flux:dropdown position="bottom end" align="end">
                        <button type="button" class="hidden md:inline-flex">
                            <flux:avatar
                                size="sm"
                                :src="auth()->user()->photo_url"
                                :name="auth()->user()->name"
                                :initials="auth()->user()->initials()"
                            />
                        </button>

                        <flux:menu>
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :src="auth()->user()->photo_url"
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                            <flux:menu.separator />
                            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                                {{ __('Settings') }}
                            </flux:menu.item>
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item
                                    as="button"
                                    type="submit"
                                    icon="arrow-right-start-on-rectangle"
                                    class="w-full cursor-pointer"
                                >
                                    {{ __('Log out') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                @endguest

                <a href="{{ route('qr.scan') }}"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition shadow">

                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">

                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 7V4h3M20 7V4h-3M4 17v3h3M20 17v3h-3M8 8h2v2H8zM14 8h2v2h-2zM8 14h2v2H8zM14 14h2v2h-2z" />

                    </svg>

                    Scan Mesin

                </a>

            </div>

        </div>

    </div>

</nav>