{{-- Footer sits on the same dark ground as the closing call to action; a thin rule separates them. --}}
<footer class="on-dark border-t border-blue-800 bg-blue-950 text-blue-100">

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">

        <div class="grid gap-10 lg:grid-cols-[1.4fr_1fr_1fr] lg:gap-16">

            <div>

                <p class="font-signage text-3xl font-bold text-white">FreeDOMS</p>

                <p class="mt-4 max-w-md leading-7">
                    Maintenance-Free Digital Optimization Management System
                    untuk membantu tim maintenance melakukan
                    Preventive Maintenance secara digital,
                    cepat, dan terdokumentasi.
                </p>

            </div>

            <nav aria-label="Features">

                <h2 class="font-signage text-lg font-semibold text-white">Features</h2>

                <ul class="mt-3">
                    <li><a href="#features" class="flex min-h-11 items-center hover:text-white hover:underline">PM Schedule</a></li>
                    <li><a href="#features" class="flex min-h-11 items-center hover:text-white hover:underline">Digital Checklist</a></li>
                    <li><a href="#features" class="flex min-h-11 items-center hover:text-white hover:underline">Machine History</a></li>
                    <li><a href="#features" class="flex min-h-11 items-center hover:text-white hover:underline">QR Scanner</a></li>
                </ul>

            </nav>

            <nav aria-label="Quick Access">

                <h2 class="font-signage text-lg font-semibold text-white">Quick Access</h2>

                <ul class="mt-3">
                    <li><a href="{{ route('login') }}" class="flex min-h-11 items-center hover:text-white hover:underline">Login</a></li>
                    <li><a href="{{ route('qr.scan') }}" class="flex min-h-11 items-center hover:text-white hover:underline">Scan Mesin</a></li>
                </ul>

            </nav>

        </div>

        <div class="mt-10 flex flex-col gap-2 border-t border-blue-800 pt-6 text-sm md:flex-row md:justify-between">

            <p>&copy; {{ date('Y') }} FreeDOMS. All Rights Reserved.</p>

            <p>Built with Laravel</p>

        </div>

    </div>

</footer>
