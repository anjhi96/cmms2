@extends('layouts.app')

@section('content')

    @php
        $selectedType = old('machine_type', request('machine_type'));
        $selectedSection = old('section', request('section'));
        $selectedMaintenance = old('maintenance_type', request('maintenance_type', 'check'));
    @endphp

    <div class="container mx-auto px-4 py-6 sm:px-6 max-w-3xl">

        <div class="bg-white rounded-lg shadow">

            <div class="border-b px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-800">
                    Add Machine Checklist
                </h1>

                <p class="text-gray-500 text-sm">
                    Add one checklist item without importing a file
                </p>
            </div>

            <div class="p-6">

                @if (session('success'))
                    <div class="bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded mb-4">
                        <ul class="list-disc ml-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('machine-checklists.store') }}" method="POST">

                    @csrf

                    <!-- Machine Type -->
                    <div class="mb-5">
                        <label for="machine_type" class="block font-medium mb-2">
                            Machine Type
                        </label>

                        <select id="machine_type" name="machine_type" class="border rounded w-full px-3 py-2" required>
                            <option value="">Select machine type</option>
                            @foreach ($machines as $machine)
                                <option value="{{ $machine->machine_type }}"
                                    {{ $selectedType == $machine->machine_type ? 'selected' : '' }}>
                                    {{ $machine->machine_type }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Section -->
                    <div class="mb-5">
                        <label for="section" class="block font-medium mb-2">
                            Section
                        </label>

                        <input type="text" id="section" name="section" list="sectionOptions"
                            value="{{ $selectedSection }}" autocomplete="off"
                            class="border rounded w-full px-3 py-2" required>
                        <datalist id="sectionOptions"></datalist>

                        <p class="text-gray-500 text-xs mt-1">
                            Pick an existing section or type a new one.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">

                        <!-- Section Order -->
                        <div>
                            <label for="section_order" class="block font-medium mb-2">
                                Section Order
                            </label>

                            <input type="number" id="section_order" name="section_order"
                                value="{{ old('section_order', 1) }}"
                                class="border rounded w-full px-3 py-2" min="1" required>
                        </div>

                        <!-- Item Order -->
                        <div>
                            <label for="item_order" class="block font-medium mb-2">
                                Item Order
                            </label>

                            <input type="number" id="item_order" name="item_order"
                                value="{{ old('item_order', 1) }}"
                                class="border rounded w-full px-3 py-2" min="1" required>
                        </div>

                    </div>

                    <!-- Checklist Item -->
                    <div class="mb-5">
                        <label for="checklist_item" class="block font-medium mb-2">
                            Checklist Item
                        </label>

                        <input type="text" id="checklist_item" name="checklist_item"
                            value="{{ old('checklist_item') }}"
                            class="border rounded w-full px-3 py-2" required
                            {{ $selectedType && $selectedSection ? 'autofocus' : '' }}>
                    </div>

                    <!-- Maintenance Type -->
                    <div class="mb-6">
                        <label for="maintenance_type" class="block font-medium mb-2">
                            Maintenance Type
                        </label>

                        <select id="maintenance_type" name="maintenance_type" class="border rounded w-full px-3 py-2" required>
                            @foreach ($maintenanceTypes as $type)
                                <option value="{{ $type }}" {{ $selectedMaintenance == $type ? 'selected' : '' }}>
                                    {{ \Illuminate\Support\Str::title($type) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">

                        <a href="{{ route('machine-checklists.index') }}"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded text-center">
                            Cancel
                        </a>

                        <button type="submit" name="add_another" value="1"
                            class="border border-blue-600 text-blue-700 hover:bg-blue-50 px-5 py-2 rounded">
                            Save &amp; Add Another
                        </button>

                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">
                            Save
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

    <script>
        // Section suggestions follow the machine type; picking a section
        // prefills its section order and the next free item order.
        (function() {
            const sectionsByType = @json($sectionsByType);
            const typeSelect = document.getElementById('machine_type');
            const sectionInput = document.getElementById('section');
            const datalist = document.getElementById('sectionOptions');
            const sectionOrder = document.getElementById('section_order');
            const itemOrder = document.getElementById('item_order');
            const hasOldInput = @json(session()->hasOldInput());

            function sectionsForType() {
                return sectionsByType[typeSelect.value] || [];
            }

            function fillDatalist() {
                datalist.innerHTML = '';
                sectionsForType().forEach(function(row) {
                    datalist.appendChild(new Option(row.section));
                });
            }

            function fillOrders() {
                const name = sectionInput.value.trim().toLowerCase();
                const sections = sectionsForType();
                const match = sections.find(function(row) {
                    return row.section.toLowerCase() === name;
                });

                if (match) {
                    sectionOrder.value = match.section_order;
                    itemOrder.value = match.next_item_order;
                } else {
                    const maxOrder = sections.reduce(function(max, row) {
                        return Math.max(max, row.section_order);
                    }, 0);
                    sectionOrder.value = maxOrder + 1;
                    itemOrder.value = 1;
                }
            }

            typeSelect.addEventListener('change', function() {
                fillDatalist();
                fillOrders();
            });
            sectionInput.addEventListener('change', fillOrders);

            fillDatalist();
            // Keep the user's numbers after a validation error.
            if (!hasOldInput && typeSelect.value) {
                fillOrders();
            }
        })();
    </script>
@endsection
